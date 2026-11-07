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
 * Todo es de una sola sucursal: cada sede pide para sus propios almacenes.
 */
class Requerimientos
{
    public const ESTADOS = [
        'PENDIENTE' => ['Por comprar', 'amber'],
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
     * @param  array{id_almacen: int, fecha_para: string, observaciones?: ?string, items: array<int, array{id: int, cantidad: float, nota?: ?string}>}  $d
     */
    public static function guardar(User $user, array $d): int
    {
        $sucursal = (int) $user->id_empresa_negocio;
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

        return DB::transaction(function () use ($user, $d, $items, $sucursal, $situacion) {
            $numero = (int) DB::table('requerimientos')->where('id_empresa_negocio', $sucursal)->lockForUpdate()->max('numero') + 1;
            $id = DB::table('requerimientos')->insertGetId([
                'numero' => $numero, 'id_empresa_negocio' => $sucursal, 'id_almacen' => (int) $d['id_almacen'], 'fecha_para' => $d['fecha_para'],
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
    }

    /** El requerimiento de la sucursal del usuario (bloqueado para cambiarlo) */
    public static function deSucursal(User $user, int $id, bool $bloquear = false): object
    {
        $r = DB::table('requerimientos')->where('id', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)
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
            ->get(['d.*', 'p.pronom', 'p.umecod', 'p.costo as costo_actual', 'c.cat_nom']);
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
            if ($r->estado !== 'PENDIENTE') {
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
        if ($r->estado !== 'PENDIENTE') {
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
    public static function paraFormulario(object $r): array
    {
        $items = self::detalle($r->id)->reject(fn ($l) => $l->comprado !== null && (float) $l->comprado <= 0);
        $controles = Producto::whereIn('IdProducto', $items->pluck('IdProducto'))->get(['IdProducto', 'procod', 'control_lote', 'control_serie'])->keyBy('IdProducto');
        $igvPorDefecto = (string) (DB::table('empresa_negocios')->where('id_empresa_negocio', $r->id_empresa_negocio)->value('tip_igv_pred') ?: '10');

        return [
            'tipo' => 'requerimiento', 'id' => $r->id, 'codigo' => self::codigo($r), 'moneda' => 'PEN', 'tip_cam' => null, 'tdocod' => '12',
            'id_almacen' => $r->id_almacen, 'observaciones' => trim(self::codigo($r).' '.($r->observaciones ?? '')),
            // Lo del mercado suele ser sin factura: el administrador cambia el proveedor si compró con RUC
            'proveedor' => ['tdicod' => '0', 'num' => '00000000', 'nom' => 'VARIOS - MERCADO', 'dir' => ''],
            'items' => $items->map(fn ($l) => [
                'id' => (int) $l->IdProducto, 'codigo' => $controles[$l->IdProducto]->procod ?? '', 'nombre' => $l->pronom, 'tip_igv' => $igvPorDefecto,
                'presentacion' => null, 'factor' => 1, 'presentacion_nombre' => null,
                'cantidad' => (float) ($l->comprado ?? $l->cantidad), 'costo' => (float) ($l->precio ?? $l->costo_actual), 'flete' => 0,
                'lote' => null, 'vencimiento' => null, 'control_lote' => (bool) ($controles[$l->IdProducto]->control_lote ?? false),
                'control_serie' => (bool) ($controles[$l->IdProducto]->control_serie ?? false), 'series' => '',
            ])->values()->all(),
        ];
    }
}
