<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Requerimientos del día (pedido de mercado): quien cocina o lleva el almacén ve el stock de su almacén, lo que ya falta
 * (llegó a su mínimo), lo que se acabaría pronto según lo que se gasta por día, y pide lo que hay que comprar. El
 * administrador lo ve en su inicio, lo imprime o lo abre en el celular en el mercado, marca lo que compró y a qué
 * precio, y lo registra como compra con un clic (el stock sube con esa compra, no con el requerimiento).
 * Cada sede pide para sus propios almacenes. También se le puede pedir a otra sede (la central): esa sede lo despacha
 * desde su almacén (sale de su stock), viaja en tránsito y la sede que pidió lo recibe (entra a su stock). Como cada
 * sede tiene su catálogo, el producto se empareja por código y, si no, por nombre.
 */
class Requerimientos
{
    public const ESTADOS = [
        'PENDIENTE' => ['Por atender', 'amber'],
        'EN CAMINO' => ['En camino', 'sky'],
        'RECIBIDO' => ['Recibido', 'emerald'],
        'COMPRADO' => ['Comprado', 'emerald'],
        'ANULADO' => ['Anulado', 'slate'],
    ];

    /** Días de consumo para calcular el gasto diario y para cuántos días se sugiere comprar */
    public const DIAS_CONSUMO = 7;

    public const DIAS_PREVISION = 2;

    /** "RQ-000012" */
    public static function codigo(object $requerimiento): string
    {
        return 'RQ-'.str_pad((string) $requerimiento->numero, 6, '0', STR_PAD_LEFT);
    }

    /** ¿Atiende requerimientos (compra lo pedido)? El administrador y quien registra compras */
    public static function atiende(User $user): bool
    {
        return $user->esAdmin() || $user->tieneModulo('/compras');
    }

    /**
     * Productos e insumos del almacén con su stock, mínimo y gasto por día, y cuánto conviene comprar.
     * Estado: agotado (ya no hay), falta (llegó a su mínimo), se_acaba (no alcanza para los próximos días) u ok.
     *
     * @return Collection<int, object{id: int, nombre: string, unidad: string, categoria: ?string, tipo: int, costo: float, stock: float, minimo: float, consumo_dia: float, dias: ?float, estado: string, sugerido: float}>
     */
    public static function sugerencias(int $sucursal, int $almacen, ?CarbonImmutable $hoy = null): Collection
    {
        $hoy ??= CarbonImmutable::now();
        $consumo = DB::table('movimientos_productos')->where('id_almacen', $almacen)->where('mov_tip', 'E')
            ->where('fecha_mov', '>=', $hoy->subDays(self::DIAS_CONSUMO)->startOfDay())->where('fecha_mov', '<', $hoy->startOfDay())
            ->groupBy('IdProducto')->selectRaw('IdProducto, SUM(cantidad) as total')->pluck('total', 'IdProducto');

        return DB::table('productos as p')
            ->leftJoin('producto_stock as s', fn ($j) => $j->on('s.IdProducto', '=', 'p.IdProducto')->where('s.id_almacen', $almacen))
            ->leftJoin('categorias as c', 'c.cat_id', '=', 'p.cat_id')
            ->where('p.id_empresa_negocio', $sucursal)->where('p.proest', 'Activo')->whereIn('p.promocion', Compras::TIPOS_CON_STOCK)
            ->orderBy('p.pronom')
            ->get(['p.IdProducto', 'p.pronom', 'p.umecod', 'p.promocion', 'p.costo', 'p.stock_min', 'c.cat_nom', DB::raw('COALESCE(s.stock, 0) as stock')])
            ->map(function (object $p) use ($consumo) {
                $stock = (float) $p->stock;
                $minimo = max((float) $p->stock_min, 0);
                $porDia = round((float) ($consumo[$p->IdProducto] ?? 0) / self::DIAS_CONSUMO, 3);
                $necesario = max($minimo, $porDia * self::DIAS_PREVISION);
                $estado = match (true) {
                    $stock <= 0 && ($minimo > 0 || $porDia > 0) => 'agotado',
                    $minimo > 0 && $stock <= $minimo => 'falta',
                    $porDia > 0 && $stock < $porDia * self::DIAS_PREVISION => 'se_acaba',
                    default => 'ok',
                };

                return (object) [
                    'id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'unidad' => $p->umecod ?: 'NIU', 'categoria' => $p->cat_nom,
                    'tipo' => (int) $p->promocion, 'costo' => (float) $p->costo, 'stock' => $stock, 'minimo' => $minimo, 'consumo_dia' => $porDia,
                    'dias' => $porDia > 0 ? round(max($stock, 0) / $porDia, 1) : null, 'estado' => $estado,
                    // Lo que falta para tener el mínimo y lo que se gasta en los próximos días (redondeado hacia arriba)
                    'sugerido' => $estado === 'ok' ? 0.0 : ceil(max($necesario - max($stock, 0), 0) * 100) / 100,
                ];
            })
            ->sortBy(fn ($p) => [['agotado' => 0, 'falta' => 1, 'se_acaba' => 2, 'ok' => 3][$p->estado], $p->nombre])->values();
    }

    /**
     * Guarda un requerimiento nuevo (lo que pidió quien cocina o lleva el almacén). Devuelve su id.
     *
     * @param  array{id_almacen: int, fecha_para: string, observaciones?: ?string, urgente?: bool, sucursal_origen?: ?int, items: array<int, array{id: int, cantidad: float, nota?: ?string}>}  $d
     */
    public static function guardar(User $user, array $d): int
    {
        $sucursal = (int) $user->id_empresa_negocio;
        $sede = ! empty($d['sucursal_origen']) ? (int) $d['sucursal_origen'] : null;
        if ($sede && ! self::sedes($user)->contains('id_empresa_negocio', $sede)) {
            throw new \RuntimeException('Esa sede no es de tu empresa.');
        }
        $items = array_values(array_filter($d['items'], fn ($i) => (float) ($i['cantidad'] ?? 0) > 0));
        if (! $items) {
            throw new \RuntimeException('Pon la cantidad de al menos un producto.');
        }
        if (! DB::table('almacenes')->where('id_almacen', $d['id_almacen'])->where('id_empresa_negocio', $sucursal)->exists()) {
            throw new \RuntimeException('Almacén no válido.');
        }
        $situacion = self::sugerencias($sucursal, (int) $d['id_almacen'])->keyBy('id');
        $validos = Producto::where('id_empresa_negocio', $sucursal)->whereIn('promocion', Compras::TIPOS_CON_STOCK)
            ->whereIn('IdProducto', array_column($items, 'id'))->pluck('IdProducto')->map(fn ($id) => (int) $id)->all();
        if (count(array_unique(array_column($items, 'id'))) !== count($validos)) {
            throw new \RuntimeException('Hay productos que no son de esta sucursal o no manejan stock.');
        }

        $id = DB::transaction(function () use ($user, $d, $items, $sucursal, $situacion, $sede) {
            $numero = (int) DB::table('requerimientos')->where('id_empresa_negocio', $sucursal)->lockForUpdate()->max('numero') + 1;
            $id = DB::table('requerimientos')->insertGetId([
                'numero' => $numero, 'id_empresa_negocio' => $sucursal, 'id_almacen' => (int) $d['id_almacen'], 'fecha_para' => $d['fecha_para'],
                'sucursal_origen' => $sede, 'urgente' => ! empty($d['urgente']),
                'IdUsuario' => $user->IdUsuario, 'estado' => 'PENDIENTE', 'observaciones' => ($d['observaciones'] ?? null) ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($items as $i) {
                $s = $situacion->get((int) $i['id']);
                DB::table('requerimientos_detalle')->insert([
                    'requerimiento_id' => $id, 'IdProducto' => (int) $i['id'], 'cantidad' => round((float) $i['cantidad'], 3),
                    'stock' => $s->stock ?? 0, 'minimo' => $s->minimo ?? 0, 'consumo_dia' => $s->consumo_dia ?? 0,
                    'nota' => mb_substr(trim((string) ($i['nota'] ?? '')), 0, 150) ?: null,
                ]);
            }

            return $id;
        });
        self::avisar($id);

        return $id;
    }

    /**
     * Aviso al celular del administrador de la sede que lo atiende (la que pidió, o la central si se le pidió a ella).
     * Siempre sale; el urgente se marca como tal.
     */
    private static function avisar(int $id): void
    {
        $r = DB::table('requerimientos')->find($id);
        $quien = DB::table('users')->where('IdUsuario', $r->IdUsuario)->value('apeusu');
        $deSede = $r->sucursal_origen ? ' de '.DB::table('empresa_negocios')->where('id_empresa_negocio', $r->id_empresa_negocio)->value('nombre_comercial') : '';
        $lineas = DB::table('requerimientos_detalle')->where('requerimiento_id', $id)->count();
        AvisosCelular::alDueno((int) ($r->sucursal_origen ?: $r->id_empresa_negocio),
            ($r->urgente ? '🚨 URGENTE: ' : '📝 ').'Requerimiento '.self::codigo($r).$deSede,
            "{$quien} pide {$lineas} producto(s) para el ".date('d/m', strtotime($r->fecha_para)).($r->observaciones ? ": {$r->observaciones}" : '.'),
            route('requerimientos.show', $id));
    }

    /** Las otras sedes de la empresa (a las que se les puede pedir) */
    public static function sedes(User $user): Collection
    {
        return DB::table('empresa_negocios')->where('IdEmpresa', $user->IdEmpresa)->where('id_empresa_negocio', '!=', $user->id_empresa_negocio)
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', 'Activo'))->orderBy('nombre_comercial')->get(['id_empresa_negocio', 'nombre_comercial']);
    }

    /** El requerimiento de la sucursal del usuario (bloqueado para cambiarlo) */
    public static function deSucursal(User $user, int $id, bool $bloquear = false): object
    {
        $r = DB::table('requerimientos')->where('id', $id)
            ->where(fn ($q) => $q->where('id_empresa_negocio', $user->id_empresa_negocio)->orWhere('sucursal_origen', $user->id_empresa_negocio))
            ->when($bloquear, fn ($q) => $q->lockForUpdate())->first();
        if (! $r) {
            throw new \RuntimeException('El requerimiento no existe.');
        }

        return $r;
    }

    /** @return Collection<int, object> las líneas con el nombre y la unidad del producto */
    public static function detalle(int $id): Collection
    {
        return DB::table('requerimientos_detalle as d')->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->leftJoin('categorias as c', 'c.cat_id', '=', 'p.cat_id')->where('d.requerimiento_id', $id)
            ->orderBy('c.cat_nom')->orderBy('p.pronom')
            ->get(['d.*', 'p.pronom', 'p.procod', 'p.umecod', 'p.costo as costo_actual', 'c.cat_nom']);
    }

    /**
     * En el mercado: cuánto se compró de cada línea y a qué precio (con IGV, por unidad). Se puede guardar varias veces.
     *
     * @param  array<int|string, array{comprado?: mixed, precio?: mixed}>  $lineas  por id de la línea
     */
    public static function marcar(User $user, int $id, array $lineas): void
    {
        DB::transaction(function () use ($user, $id, $lineas) {
            $r = self::deSucursal($user, $id, true);
            if ($r->estado !== 'PENDIENTE' || $r->sucursal_origen || (int) $r->id_empresa_negocio !== (int) $user->id_empresa_negocio) {
                throw new \RuntimeException('El requerimiento '.self::codigo($r).' ya no está por comprar.');
            }
            foreach (DB::table('requerimientos_detalle')->where('requerimiento_id', $id)->get() as $linea) {
                $l = $lineas[$linea->id] ?? null;
                if ($l === null) {
                    continue;
                }
                $comprado = ($l['comprado'] ?? '') === '' ? null : max(round((float) $l['comprado'], 3), 0);
                $precio = ($l['precio'] ?? '') === '' ? null : max(round((float) $l['precio'], 4), 0);
                DB::table('requerimientos_detalle')->where('id', $linea->id)->update(['comprado' => $comprado, 'precio' => $precio]);
            }
            DB::table('requerimientos')->where('id', $id)->update(['updated_at' => now()]);
        });
    }

    public static function anular(User $user, int $id, string $motivo): object
    {
        return DB::transaction(function () use ($user, $id, $motivo) {
            $r = self::deSucursal($user, $id, true);
            if ($r->estado !== 'PENDIENTE') {
                throw new \RuntimeException('Solo se anula un requerimiento que aún no se compra.');
            }
            if (! self::atiende($user) && (int) $r->IdUsuario !== (int) $user->IdUsuario) {
                throw new \RuntimeException('Solo quien lo pidió o el administrador lo anula.');
            }
            DB::table('requerimientos')->where('id', $id)->update(['estado' => 'ANULADO', 'motivo_anulacion' => mb_substr($motivo, 0, 255),
                'atendido_por' => $user->IdUsuario, 'atendido_at' => now(), 'updated_at' => now()]);

            return $r;
        });
    }

    /** Para registrar la compra: el requerimiento tiene que seguir por comprar */
    public static function paraComprar(User $user, int $id): object
    {
        $r = self::deSucursal($user, $id, true);
        if ($r->estado !== 'PENDIENTE' || $r->sucursal_origen || (int) $r->id_empresa_negocio !== (int) $user->id_empresa_negocio) {
            throw new \RuntimeException('El requerimiento '.self::codigo($r).' ya se compró o se anuló.');
        }

        return $r;
    }

    public static function marcarComprado(object $requerimiento, int $comId, User $user): void
    {
        DB::table('requerimientos')->where('id', $requerimiento->id)->update([
            'estado' => 'COMPRADO', 'com_cab_id' => $comId, 'atendido_por' => $user->IdUsuario, 'atendido_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Lo comprado, en el formato del formulario de compra: lo marcado en el mercado (o lo pedido, si no se marcó) y su
     * precio pagado (o el costo actual). Lo que se marcó con 0 no se compró y no va.
     */
    public static function paraFormulario(object $r, string $comprobante = '12'): array
    {
        $conRuc = in_array($comprobante, ['01', '03'], true);
        $items = self::detalle($r->id)->reject(fn ($l) => $l->comprado !== null && (float) $l->comprado <= 0);
        $controles = Producto::whereIn('IdProducto', $items->pluck('IdProducto'))->get(['IdProducto', 'procod', 'control_lote', 'control_serie'])->keyBy('IdProducto');
        $igvPorDefecto = (string) (DB::table('empresa_negocios')->where('id_empresa_negocio', $r->id_empresa_negocio)->value('tip_igv_pred') ?: '10');

        return [
            'tipo' => 'requerimiento', 'id' => $r->id, 'codigo' => self::codigo($r), 'moneda' => 'PEN', 'tip_cam' => null, 'tdocod' => $comprobante,
            'id_almacen' => $r->id_almacen, 'observaciones' => trim(self::codigo($r).' '.($r->observaciones ?? '')),
            // Con factura o boleta se escribe el RUC del vendedor (se completa solo); sin comprobante va como "varios - mercado"
            'proveedor' => $conRuc ? ['tdicod' => '6', 'num' => '', 'nom' => '', 'dir' => ''] : ['tdicod' => '0', 'num' => '00000000', 'nom' => 'VARIOS - MERCADO', 'dir' => ''],
            'items' => $items->map(fn ($l) => [
                'id' => (int) $l->IdProducto, 'codigo' => $controles[$l->IdProducto]->procod ?? '', 'nombre' => $l->pronom, 'tip_igv' => $igvPorDefecto,
                'presentacion' => null, 'factor' => 1, 'presentacion_nombre' => null,
                'cantidad' => (float) ($l->comprado ?? $l->cantidad), 'costo' => (float) ($l->precio ?? $l->costo_actual), 'flete' => 0,
                'lote' => null, 'vencimiento' => null, 'control_lote' => (bool) ($controles[$l->IdProducto]->control_lote ?? false),
                'control_serie' => (bool) ($controles[$l->IdProducto]->control_serie ?? false), 'series' => '',
            ])->values()->all(),
        ];
    }

    /**
     * Pedido a otra sede: el producto equivalente en el catálogo de la sede que envía (por código; si no, por nombre).
     *
     * @return array<int, ?object> por IdProducto de la sede que pidió
     */
    public static function emparejar(int $sucursalOrigen, Collection $lineas): array
    {
        $productos = DB::table('productos')->where('id_empresa_negocio', $sucursalOrigen)->where('proest', 'Activo')
            ->whereIn('promocion', Compras::TIPOS_CON_STOCK)->get(['IdProducto', 'procod', 'pronom', 'umecod', 'costo']);
        $porCodigo = $productos->filter(fn ($p) => trim((string) $p->procod) !== '')->keyBy(fn ($p) => mb_strtoupper(trim($p->procod)));
        $porNombre = $productos->keyBy(fn ($p) => mb_strtoupper(trim($p->pronom)));
        $pares = [];
        foreach ($lineas as $l) {
            $codigo = mb_strtoupper(trim((string) ($l->procod ?? '')));
            $pares[$l->IdProducto] = ($codigo !== '' ? $porCodigo->get($codigo) : null) ?? $porNombre->get(mb_strtoupper(trim($l->pronom)));
        }

        return $pares;
    }

    /**
     * La sede central despacha lo pedido desde uno de sus almacenes: sale de su stock ahora y queda en camino.
     *
     * @param  array<int|string, mixed>  $enviados  cantidad por id de línea (0 = no hay)
     */
    public static function despachar(User $user, int $id, int $almacenOrigen, array $enviados): object
    {
        return DB::transaction(function () use ($user, $id, $almacenOrigen, $enviados) {
            $r = self::deSucursal($user, $id, true);
            if ((int) $r->sucursal_origen !== (int) $user->id_empresa_negocio || $r->estado !== 'PENDIENTE') {
                throw new \RuntimeException('Este requerimiento no está por despachar desde tu sede.');
            }
            if (! DB::table('almacenes')->where('id_almacen', $almacenOrigen)->where('id_empresa_negocio', $user->id_empresa_negocio)->exists()) {
                throw new \RuntimeException('Almacén no válido.');
            }
            $lineas = self::detalle($r->id);
            $pares = self::emparejar((int) $r->sucursal_origen, $lineas);
            $destino = DB::table('empresa_negocios')->where('id_empresa_negocio', $r->id_empresa_negocio)->value('nombre_comercial');
            $total = 0;
            foreach ($lineas as $l) {
                $cantidad = round(max((float) ($enviados[$l->id] ?? 0), 0), 3);
                $par = $pares[$l->IdProducto] ?? null;
                if ($cantidad > 0 && ! $par) {
                    throw new \RuntimeException("{$l->pronom} no existe en tu catálogo (ni con el mismo código ni con el mismo nombre): créalo o envíalo en 0.");
                }
                if ($cantidad > 0) {
                    Kardex::registrar((int) $par->IdProducto, $almacenOrigen, $cantidad, 'E', [
                        'exigir_stock' => true, 'cod_tip_ope' => '11', 'costo' => (float) $par->costo, 'numero' => self::codigo($r),
                        'descripcion' => 'ENVÍO A SEDE '.mb_strtoupper((string) $destino).' '.self::codigo($r), 'fecha_mov' => now()->toDateString(),
                        'id_empresa_negocio' => $user->id_empresa_negocio, 'id_almacen_origen' => $almacenOrigen, 'id_almacen_destino' => $r->id_almacen,
                    ]);
                    $total++;
                }
                DB::table('requerimientos_detalle')->where('id', $l->id)->update(['IdProducto_origen' => $par->IdProducto ?? null, 'enviado' => $cantidad]);
            }
            if (! $total) {
                throw new \RuntimeException('Pon la cantidad que envías de al menos un producto.');
            }
            DB::table('requerimientos')->where('id', $r->id)->update(['estado' => 'EN CAMINO', 'id_almacen_origen' => $almacenOrigen,
                'despachado_por' => $user->IdUsuario, 'despachado_at' => now(), 'updated_at' => now()]);

            return $r;
        });
    }

    /**
     * Llegó lo despachado: entra al almacén de la sede que pidió lo que realmente llegó (lo que falte queda anotado).
     *
     * @param  array<int|string, mixed>  $recibidos  cantidad por id de línea; si no viene, lo enviado
     */
    public static function recibir(User $user, int $id, array $recibidos): object
    {
        return DB::transaction(function () use ($user, $id, $recibidos) {
            $r = self::deSucursal($user, $id, true);
            if ((int) $r->id_empresa_negocio !== (int) $user->id_empresa_negocio || $r->estado !== 'EN CAMINO') {
                throw new \RuntimeException('Este requerimiento no está en camino a tu sede.');
            }
            $origen = DB::table('empresa_negocios')->where('id_empresa_negocio', $r->sucursal_origen)->value('nombre_comercial');
            foreach (self::detalle($r->id)->where('enviado', '>', 0) as $l) {
                $cantidad = round(max(min((float) ($recibidos[$l->id] ?? $l->enviado), (float) $l->enviado), 0), 3);
                $costo = (float) (DB::table('productos')->where('IdProducto', $l->IdProducto_origen)->value('costo') ?? $l->costo_actual);
                Kardex::registrar((int) $l->IdProducto, (int) $r->id_almacen, $cantidad, 'I', [
                    'cod_tip_ope' => '21', 'costo' => $costo, 'numero' => self::codigo($r), 'fecha_mov' => now()->toDateString(),
                    'descripcion' => 'LLEGÓ DE SEDE '.mb_strtoupper((string) $origen).' '.self::codigo($r),
                    'id_empresa_negocio' => $r->id_empresa_negocio, 'id_almacen_origen' => $r->id_almacen_origen, 'id_almacen_destino' => $r->id_almacen,
                ]);
                DB::table('requerimientos_detalle')->where('id', $l->id)->update(['recibido' => $cantidad]);
            }
            DB::table('requerimientos')->where('id', $r->id)->update(['estado' => 'RECIBIDO', 'recibido_por' => $user->IdUsuario,
                'recibido_at' => now(), 'updated_at' => now()]);

            return $r;
        });
    }

    /**
     * Producción del día: los insumos que necesitan los platos planificados (por sus recetas) contra lo que hay en el
     * almacén. Lo que falta, con lo que se sugiere pedir.
     *
     * @param  array<int|string, mixed>  $platos  cantidad por IdProducto del plato
     * @return Collection<int, object{id: int, nombre: string, unidad: string, necesario: float, stock: float, falta: float, platos: string}>
     */
    public static function produccion(int $sucursal, int $almacen, array $platos): Collection
    {
        $platos = collect($platos)->map(fn ($n) => max((float) $n, 0))->filter();
        $nombres = DB::table('productos')->where('id_empresa_negocio', $sucursal)->whereIn('IdProducto', $platos->keys())->pluck('pronom', 'IdProducto');
        $necesario = [];
        foreach ($platos as $plato => $cantidad) {
            if (! isset($nombres[$plato])) {
                continue;
            }
            foreach (Recetas::de((int) $plato) as $r) {
                $necesario[$r->IdInsumo]['cantidad'] = ($necesario[$r->IdInsumo]['cantidad'] ?? 0) + $cantidad * $r->cantidad_base;
                $necesario[$r->IdInsumo]['platos'][] = $nombres[$plato];
            }
        }
        if (! $necesario) {
            return collect();
        }
        $insumos = DB::table('productos as p')->leftJoin('producto_stock as s', fn ($j) => $j->on('s.IdProducto', '=', 'p.IdProducto')->where('s.id_almacen', $almacen))
            ->where('p.id_empresa_negocio', $sucursal)->whereIn('p.IdProducto', array_keys($necesario))
            ->get(['p.IdProducto', 'p.pronom', 'p.umecod', DB::raw('COALESCE(s.stock, 0) as stock')]);

        return $insumos->map(function ($i) use ($necesario) {
            $falta = max($necesario[$i->IdProducto]['cantidad'] - max((float) $i->stock, 0), 0);

            return (object) ['id' => (int) $i->IdProducto, 'nombre' => $i->pronom, 'unidad' => $i->umecod ?: 'NIU',
                'necesario' => round($necesario[$i->IdProducto]['cantidad'], 3), 'stock' => (float) $i->stock,
                'falta' => ceil($falta * 100) / 100, 'platos' => implode(', ', array_unique($necesario[$i->IdProducto]['platos']))];
        })->sortBy([fn ($a, $b) => ($b->falta > 0) <=> ($a->falta > 0), fn ($a, $b) => strcmp($a->nombre, $b->nombre)])->values();
    }
}
