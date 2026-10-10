<?php

namespace App\Support;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Compras: cabecera, detalle, proveedor e ingreso al kardex (operación 02 COMPRA NACIONAL).
 * Los totales e IGV se calculan aquí; del navegador solo se toman cantidades, costos, tipo de IGV y flete.
 * Editar o anular una compra devuelve primero lo que ingresó (salida en el kardex) para que el stock cuadre.
 */
class Compras
{
    public const DOCUMENTOS = ['01' => 'FACTURA', '03' => 'BOLETA', '12' => 'TICKET', '13' => 'NOTA DE VENTA', '00' => 'OTROS'];

    public const TIPOS_IGV = ['10' => 'Gravado', '20' => 'Exonerado', '30' => 'Inafecto'];

    // Solo productos simples (0) e insumos (4) tienen stock propio
    public const TIPOS_CON_STOCK = [0, 4];

    /** Registra una compra nueva ($id null) o reemplaza una existente; devuelve com_cab_id */
    public static function guardar(User $user, array $d, ?int $id = null): int
    {
        return DB::transaction(function () use ($user, $d, $id) {
            $sucursal = $user->id_empresa_negocio;
            $anterior = null;

            if ($id) {
                $anterior = DB::table('compras_cabecera')->where('com_cab_id', $id)
                    ->where('id_empresa_negocio', $sucursal)->lockForUpdate()->first();
                if (! $anterior) {
                    throw new \RuntimeException('La compra no existe.');
                }
                if ($anterior->est_compra !== 'Registrado') {
                    throw new \RuntimeException('Una compra anulada no se puede editar.');
                }
            }

            // ---- Forma de pago ----
            $cre = DB::table('credito_dias')->where('cre_dia_id', $d['estadopago'])->where('id_empresa_negocio', $sucursal)->first();
            if (! $cre) {
                throw new \RuntimeException('Forma de pago no válida.');
            }
            $contado = $cre->cre_dia_tip === 'CONTADO';
            $fecVen = $contado ? $d['fecEmi'] : ($d['fecVen'] ?? null);
            if (! $contado && (! $fecVen || $fecVen < $d['fecEmi'])) {
                throw new \RuntimeException('La fecha de vencimiento no puede ser anterior a la de emisión.');
            }

            // ---- Moneda: los costos del producto y el kardex siempre en soles ----
            $moneda = $d['moneda'] ?? 'PEN';
            $tc = $moneda === 'USD' ? round((float) ($d['tip_cam'] ?? 0), 3) : null;
            if ($moneda === 'USD' && $tc <= 0) {
                throw new \RuntimeException('Ingresa el tipo de cambio para una compra en dólares.');
            }

            $almacen = Almacen::where('id_almacen', $d['id_almacen'])->where('id_empresa_negocio', $sucursal)->first();
            if (! $almacen) {
                throw new \RuntimeException('Almacén no válido.');
            }

            $proveedor = self::proveedor($user, $d);

            // ---- Mismo documento del mismo proveedor ya registrado ----
            $serie = mb_strtoupper(trim($d['serie']));
            $numero = ltrim(trim($d['numero']), '0') ?: '0';
            $repetida = DB::table('compras_cabecera')
                ->where('id_empresa_negocio', $sucursal)->where('prov_id', $proveedor->prov_id)
                ->where('tdocod', $d['tdocod'])->where('com_doc_ser', $serie)->where('com_doc_num', $numero)
                ->where('est_compra', 'Registrado')
                ->when($id, fn ($q) => $q->where('com_cab_id', '!=', $id))
                ->exists();
            if ($repetida) {
                throw new \RuntimeException("La compra {$serie}-{$numero} de este proveedor ya está registrada.");
            }

            $lineas = self::lineas($user, $d['items'], $tc, $d['fecIng']);
            $tot = [
                'grav' => round($lineas->where('tip_igv', '10')->sum('com_det_subtot'), 2),
                'exo' => round($lineas->where('tip_igv', '20')->sum('com_det_subtot'), 2),
                'inaf' => round($lineas->where('tip_igv', '30')->sum('com_det_subtot'), 2),
                'igv' => round($lineas->sum('com_det_igv'), 2),
                'total' => round($lineas->sum('total'), 2),
            ];

            $cabecera = [
                'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $sucursal,
                'tdocod' => $d['tdocod'], 'com_doc_ser' => $serie, 'com_doc_num' => $numero,
                'prov_id' => $proveedor->prov_id, 'prov_num' => $proveedor->prov_ruc,
                'com_fec' => $d['fecEmi'], 'com_fec_ven' => $fecVen, 'com_fec_ing' => $d['fecIng'],
                'mon_id' => $moneda, 'tip_cam' => $tc,
                'com_grav' => $tot['grav'], 'com_exo' => $tot['exo'], 'com_inaf' => $tot['inaf'],
                'subtot_com' => round($tot['grav'] + $tot['exo'] + $tot['inaf'], 2), 'igv_com' => $tot['igv'],
                'total_com' => $tot['total'],
                'tot_con' => $contado ? $tot['total'] : 0, 'tot_cre' => $contado ? 0 : $tot['total'],
                'saldofactura' => $contado ? 0 : $tot['total'],
                'cre_dia_id' => $cre->cre_dia_id, 'id_almacen' => $almacen->id_almacen,
                'comp_obs' => ($d['observaciones'] ?? null) ?: null,
                'updated_at' => now(),
            ];

            if ($anterior) {
                self::revertirStock($anterior, 'EDICIÓN DE COMPRA');
                DB::table('compras_detalle')->where('com_cab_id', $id)->delete();
                DB::table('compras_cabecera')->where('com_cab_id', $id)->update($cabecera);
                $comId = $id;
            } else {
                $comId = DB::table('compras_cabecera')->insertGetId($cabecera + [
                    'IdUsuario' => $user->IdUsuario, 'id_turno' => Turno::abiertoDe($user)?->id_turno,
                    'est_compra' => 'Registrado', 'created_at' => now(),
                ]);
            }

            // ---- Detalle + ingreso al kardex + costo del producto ----
            foreach ($lineas as $l) {
                DB::table('compras_detalle')->insert(collect($l)->except('producto')->all() + [
                    'com_cab_id' => $comId, 'id_almacen_pro' => $almacen->id_almacen, 'IdEmpresa' => $user->IdEmpresa,
                ]);

                // Por presentación (1 SACO x 50) ingresan cantidad x factor unidades base, al costo por unidad base
                $factor = (float) $l['factor'];
                Kardex::registrar($l['pro_id'], $almacen->id_almacen, round((float) $l['cantidad'] * $factor, 3), 'I', [
                    'cod_tip_ope' => '02', 'tdocod' => $d['tdocod'], 'serie' => $serie, 'numero' => $numero,
                    'com_cab_id' => $comId, 'cliente' => $proveedor->prov_raz,
                    'costo' => round($l['precio_costo'] / $factor, 4), 'fecha_mov' => $d['fecIng'],
                    'lote' => $l['lote'], 'vencimiento' => $l['vencimiento'],
                ]);

                if (! empty($d['actualizar_costo'])) {
                    $antes = (float) Producto::where('IdProducto', $l['pro_id'])->value('costo');
                    $nuevo = round($l['precio_costo'] / $factor, 2);
                    Producto::where('IdProducto', $l['pro_id'])->update(['costo' => $nuevo]);
                    // Para avisar qué platos ganan menos si subió un insumo de sus recetas
                    Recetas::registrarCambioCosto((int) $l['pro_id'], $antes, $nuevo, 'COMPRA', $comId, (int) $user->id_empresa_negocio);
                }
            }

            // Cuenta por pagar si es al crédito (y respeta los pagos que ya tenga si se está editando)
            Cuentas::sincronizarPorPagar($comId);

            return $comId;
        });
    }

    /** Anula la compra y saca del stock lo que había ingresado */
    public static function anular(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $cab = DB::table('compras_cabecera')->where('com_cab_id', $id)
                ->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            if (! $cab) {
                throw new \RuntimeException('La compra no existe.');
            }
            if ($cab->est_compra !== 'Registrado') {
                throw new \RuntimeException('La compra ya estaba anulada.');
            }

            Cuentas::anularPorDocumento('pagar', $id);   // si ya tiene pagos, no se deja anular
            self::revertirStock($cab, 'ANULACIÓN DE COMPRA');
            DB::table('compras_cabecera')->where('com_cab_id', $id)->update([
                'est_compra' => 'Anulado', 'saldofactura' => 0, 'usu_elimino' => $user->IdUsuario, 'updated_at' => now(),
            ]);
        });
    }

    /** Salida en el kardex de todo lo que ingresó con la compra (queda la huella de la corrección) */
    private static function revertirStock(object $cab, string $motivo): void
    {
        $proveedor = DB::table('proveedor')->where('prov_id', $cab->prov_id)->value('prov_raz');
        $detalle = DB::table('compras_detalle')->where('com_cab_id', $cab->com_cab_id)->whereNotNull('pro_id')->get();

        foreach ($detalle as $l) {
            $factor = (float) ($l->factor ?? 1) ?: 1;
            Kardex::registrar((int) $l->pro_id, (int) ($l->id_almacen_pro ?: $cab->id_almacen), round((float) $l->cantidad * $factor, 3), 'E', [
                'cod_tip_ope' => '02', 'tdocod' => $cab->tdocod, 'serie' => $cab->com_doc_ser, 'numero' => $cab->com_doc_num,
                'com_cab_id' => $cab->com_cab_id, 'cliente' => $proveedor, 'descripcion' => $motivo,
                'costo' => round($l->precio_costo / $factor, 4), 'lote' => $l->lote, 'vencimiento' => $l->vencimiento,
                'fecha_mov' => now()->toDateString(),
            ]);
        }
    }

    /** Calcula cada línea: IGV según su tipo, flete prorrateado por unidad y costo final en soles */
    private static function lineas(User $user, array $items, ?float $tc, string $fecIng)
    {
        $productos = Producto::whereIn('IdProducto', array_column($items, 'id'))
            ->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->whereIn('promocion', self::TIPOS_CON_STOCK)
            ->get()->keyBy('IdProducto');
        $presentaciones = ProductoPresentacion::whereIn('id_presentacion', array_filter(array_column($items, 'presentacion')))
            ->where('estado', 1)->get()->keyBy('id_presentacion');
        $factor = Comprobante::FACTOR_IGV;

        return collect($items)->map(function ($i) use ($productos, $presentaciones, $factor, $tc, $fecIng) {
            $prod = $productos[$i['id']] ?? null;
            if (! $prod) {
                throw new \RuntimeException('Un producto del detalle no existe o no maneja stock (combos y preparados no se compran).');
            }

            $pres = null;
            if (! empty($i['presentacion'])) {
                $pres = $presentaciones[$i['presentacion']] ?? null;
                if (! $pres || (int) $pres->IdProducto !== (int) $prod->IdProducto) {
                    throw new \RuntimeException("La presentación elegida de {$prod->pronom} ya no existe. Quita la línea y agrégala de nuevo.");
                }
            }

            $tipIgv = (string) $i['tip_igv'];
            $cant = round((float) $i['cantidad'], 2);
            $preUni = round((float) $i['costo'], 4);            // costo unitario con IGV, en la moneda de la compra
            $total = round($cant * $preUni, 2);
            $subtot = $tipIgv === '10' ? round($total / $factor, 2) : $total;
            $flete = round((float) ($i['flete'] ?? 0), 2);
            $fleteUnd = $cant > 0 ? round($flete / $cant, 4) : 0;
            $costoFinal = round(($preUni + $fleteUnd) * ($tc ?: 1), 4);

            // Farmacia: los productos con control de lote exigen lote y vencimiento, y no se compra mercadería ya vencida
            $lote = trim((string) ($i['lote'] ?? '')) ?: null;
            $vence = ($i['vencimiento'] ?? null) ?: null;
            if ($prod->control_lote && (! $lote || ! $vence)) {
                throw new \RuntimeException("Ingresa el lote y la fecha de vencimiento de {$prod->pronom}.");
            }
            if ($vence && $vence < $fecIng) {
                throw new \RuntimeException("El lote {$lote} de {$prod->pronom} ya estaba vencido al ingresar ({$vence}).");
            }
            if ($vence && ! $lote) {
                throw new \RuntimeException("Ingresa el número de lote de {$prod->pronom} (tiene fecha de vencimiento).");
            }

            return [
                'pro_id' => $prod->IdProducto, 'ume_cod' => $pres->umecod ?? $prod->umecod, 'tip_igv' => $tipIgv,
                'id_presentacion' => $pres?->id_presentacion, 'factor' => $pres ? (float) $pres->factor : 1,
                'cantidad' => $cant, 'pre_uni' => $preUni,
                'val_uni' => $tipIgv === '10' ? round($preUni / $factor, 4) : $preUni,
                'com_det_subtot' => $subtot, 'com_det_igv' => round($total - $subtot, 2), 'total' => $total,
                'flete' => $flete, 'flete_und' => $fleteUnd, 'precio_costo' => $costoFinal,
                'lote' => $lote ? mb_strtoupper(mb_substr($lote, 0, 50)) : null,
                'vencimiento' => $vence,
            ];
        });
    }

    /** Crea o actualiza el proveedor por su número de documento */
    private static function proveedor(User $user, array $d): object
    {
        $num = trim($d['prov_num']);
        $datos = [
            'tdicod' => $d['prov_tdicod'],
            'prov_raz' => mb_strtoupper(trim($d['prov_nom'])),
            'prov_dir' => ($d['prov_dir'] ?? null) ?: '--',
            'id_empresa_negocio' => $user->id_empresa_negocio,
            'prov_est' => '1',
        ];

        $existe = DB::table('proveedor')->where('IdEmpresa', $user->IdEmpresa)->where('prov_ruc', $num)->first();
        if ($existe) {
            DB::table('proveedor')->where('prov_id', $existe->prov_id)->update($datos);

            return DB::table('proveedor')->where('prov_id', $existe->prov_id)->first();
        }

        $id = DB::table('proveedor')->insertGetId($datos + ['IdEmpresa' => $user->IdEmpresa, 'prov_ruc' => $num]);

        return DB::table('proveedor')->where('prov_id', $id)->first();
    }
}
