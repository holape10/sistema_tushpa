<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Órdenes de compra: pedido al proveedor → el administrador la aprueba (o la rechaza) → se convierte en compra con un
 * clic (el formulario de compra ya viene lleno) → la compra da el ingreso al almacén (o queda en tránsito hasta que llegue).
 * Solo se edita mientras está PENDIENTE. Lo que crea el administrador ya nace aprobado.
 */
class OrdenesCompra
{
    public const ESTADOS = [
        'PENDIENTE' => ['Por aprobar', 'amber'],
        'APROBADA' => ['Aprobada', 'emerald'],
        'RECHAZADA' => ['Rechazada', 'rose'],
        'COMPRADA' => ['Comprada', 'indigo'],
        'ANULADA' => ['Anulada', 'slate'],
    ];

    /** "OC-000025" */
    public static function codigo(object $orden): string
    {
        return 'OC-'.str_pad((string) $orden->numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Crea o edita (solo PENDIENTE) una orden. Devuelve su id.
     *
     * @param  array{fecha: string, fecha_entrega?: ?string, prov_tdicod: string, prov_num: string, prov_nom: string, moneda: string, tip_cam?: ?float,
     *               id_almacen?: ?int, condiciones?: ?string, observaciones?: ?string, items: array<int, array{id: int, cantidad: float, costo: float, tip_igv?: string}>}  $d
     */
    public static function guardar(User $user, array $d, ?int $id = null): int
    {
        return DB::transaction(function () use ($user, $d, $id) {
            $sucursal = (int) $user->id_empresa_negocio;
            if ($id) {
                $orden = DB::table('ordenes_compra')->where('id', $id)->where('id_empresa_negocio', $sucursal)->lockForUpdate()->first();
                if (! $orden) {
                    throw new \RuntimeException('La orden no existe.');
                }
                if ($orden->estado !== 'PENDIENTE') {
                    throw new \RuntimeException('Solo se edita una orden que aún no se aprueba.');
                }
            }
            if (($d['moneda'] ?? 'PEN') === 'USD' && ! ((float) ($d['tip_cam'] ?? 0) > 0)) {
                throw new \RuntimeException('Ingresa el tipo de cambio para una orden en dólares.');
            }
            if (! empty($d['fecha_entrega']) && $d['fecha_entrega'] < $d['fecha']) {
                throw new \RuntimeException('La fecha de entrega no puede ser anterior a la de la orden.');
            }

            // Productos de esta sucursal que manejan stock (lo que se compra)
            $productos = Producto::where('id_empresa_negocio', $sucursal)->whereIn('promocion', Compras::TIPOS_CON_STOCK)
                ->whereIn('IdProducto', array_column($d['items'], 'id'))->get()->keyBy('IdProducto');
            $lineas = [];
            foreach ($d['items'] as $i) {
                if (! isset($productos[$i['id']])) {
                    throw new \RuntimeException('Un producto de la orden no existe o no maneja stock.');
                }
                $cantidad = round((float) $i['cantidad'], 3);
                $costo = round((float) $i['costo'], 4);
                $tipIgv = (string) ($i['tip_igv'] ?? '10');
                $lineas[] = ['IdProducto' => (int) $i['id'], 'tip_igv' => in_array($tipIgv, ['10', '20', '30'], true) ? $tipIgv : '10',
                    'cantidad' => $cantidad, 'costo' => $costo, 'total' => round($cantidad * $costo, 2)];
            }
            $proveedor = DB::table('proveedor')->where('prov_ruc', trim($d['prov_num']))->where('IdEmpresa', $user->IdEmpresa)->first();
            $datos = [
                'fecha' => $d['fecha'], 'fecha_entrega' => ($d['fecha_entrega'] ?? null) ?: null,
                'prov_id' => $proveedor?->prov_id, 'prov_tdicod' => $d['prov_tdicod'], 'prov_num' => trim($d['prov_num']),
                'prov_nom' => mb_strtoupper(trim($d['prov_nom'])), 'moneda' => $d['moneda'] ?? 'PEN',
                'tip_cam' => ($d['moneda'] ?? 'PEN') === 'USD' ? round((float) $d['tip_cam'], 3) : null,
                'id_almacen' => $d['id_almacen'] ?? null, 'condiciones' => ($d['condiciones'] ?? null) ?: null,
                'observaciones' => ($d['observaciones'] ?? null) ?: null, 'total' => round(array_sum(array_column($lineas, 'total')), 2), 'updated_at' => now(),
            ];

            if ($id) {
                DB::table('ordenes_compra')->where('id', $id)->update($datos);
                DB::table('ordenes_compra_detalle')->where('orden_id', $id)->delete();
            } else {
                // Correlativo por sucursal (bloqueado para que dos personas no tomen el mismo número)
                $numero = (int) DB::table('ordenes_compra')->where('id_empresa_negocio', $sucursal)->lockForUpdate()->max('numero') + 1;
                $admin = $user->esAdmin();
                $id = DB::table('ordenes_compra')->insertGetId($datos + [
                    'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $sucursal, 'numero' => $numero, 'IdUsuario' => $user->IdUsuario,
                    // Lo que pide el administrador no espera aprobación
                    'estado' => $admin ? 'APROBADA' : 'PENDIENTE', 'IdUsuario_aprueba' => $admin ? $user->IdUsuario : null,
                    'aprobada_at' => $admin ? now() : null, 'created_at' => now(),
                ]);
            }
            foreach ($lineas as $l) {
                DB::table('ordenes_compra_detalle')->insert($l + ['orden_id' => $id]);
            }

            return $id;
        });
    }

    /** El administrador aprueba o rechaza una orden PENDIENTE */
    public static function decidir(User $user, int $id, bool $aprobar, ?string $motivo = null): object
    {
        if (! $user->esAdmin()) {
            throw new \RuntimeException('Solo el administrador aprueba o rechaza órdenes de compra.');
        }

        return DB::transaction(function () use ($user, $id, $aprobar, $motivo) {
            $orden = self::bloquear($user, $id);
            if ($orden->estado !== 'PENDIENTE') {
                throw new \RuntimeException('Esta orden ya fue '.mb_strtolower(self::ESTADOS[$orden->estado][0]).'.');
            }
            DB::table('ordenes_compra')->where('id', $id)->update([
                'estado' => $aprobar ? 'APROBADA' : 'RECHAZADA', 'IdUsuario_aprueba' => $user->IdUsuario, 'aprobada_at' => now(),
                'motivo' => $motivo ? mb_substr(trim($motivo), 0, 255) : null, 'updated_at' => now(),
            ]);

            return DB::table('ordenes_compra')->where('id', $id)->first();
        });
    }

    /** Anula una orden que aún no es compra */
    public static function anular(User $user, int $id, string $motivo): object
    {
        return DB::transaction(function () use ($user, $id, $motivo) {
            $orden = self::bloquear($user, $id);
            if (in_array($orden->estado, ['COMPRADA', 'ANULADA'], true)) {
                throw new \RuntimeException($orden->estado === 'COMPRADA' ? 'La orden ya es una compra: anula la compra.' : 'La orden ya estaba anulada.');
            }
            if (! $user->esAdmin() && (int) $orden->IdUsuario !== (int) $user->IdUsuario) {
                throw new \RuntimeException('Solo quien la hizo o el administrador anulan la orden.');
            }
            DB::table('ordenes_compra')->where('id', $id)->update(['estado' => 'ANULADA', 'motivo' => mb_substr(trim($motivo), 0, 255), 'updated_at' => now()]);

            return $orden;
        });
    }

    /** La orden lista para volverse compra (dentro de la transacción de la compra) */
    public static function paraComprar(User $user, int $id): object
    {
        $orden = self::bloquear($user, $id);
        if ($orden->estado !== 'APROBADA') {
            throw new \RuntimeException('La orden '.self::codigo($orden).' no está aprobada (está '.mb_strtolower(self::ESTADOS[$orden->estado][0]).').');
        }

        return $orden;
    }

    public static function marcarComprada(object $orden, int $comId): void
    {
        DB::table('ordenes_compra')->where('id', $orden->id)->update(['estado' => 'COMPRADA', 'com_cab_id' => $comId, 'updated_at' => now()]);
    }

    /** Lo de la orden en el formato del formulario de compra (para convertirla con un clic) */
    public static function paraFormulario(object $orden): array
    {
        $items = DB::table('ordenes_compra_detalle as d')->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')->where('d.orden_id', $orden->id)
            ->orderBy('d.id')->get(['d.*', 'p.pronom', 'p.procod', 'p.control_lote', 'p.control_serie']);

        return [
            'id' => $orden->id, 'codigo' => self::codigo($orden), 'moneda' => $orden->moneda, 'tip_cam' => $orden->tip_cam,
            'id_almacen' => $orden->id_almacen, 'observaciones' => trim(self::codigo($orden).' '.($orden->observaciones ?? '')),
            'proveedor' => ['tdicod' => $orden->prov_tdicod, 'num' => $orden->prov_num, 'nom' => $orden->prov_nom, 'dir' => ''],
            'items' => $items->map(fn ($i) => [
                'id' => (int) $i->IdProducto, 'codigo' => $i->procod, 'nombre' => $i->pronom, 'tip_igv' => $i->tip_igv, 'presentacion' => null,
                'factor' => 1, 'presentacion_nombre' => null, 'cantidad' => (float) $i->cantidad, 'costo' => (float) $i->costo, 'flete' => 0,
                'lote' => null, 'vencimiento' => null, 'control_lote' => (bool) $i->control_lote, 'control_serie' => (bool) $i->control_serie, 'series' => '',
            ])->values()->all(),
        ];
    }

    private static function bloquear(User $user, int $id): object
    {
        $orden = DB::table('ordenes_compra')->where('id', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
        if (! $orden) {
            throw new \RuntimeException('La orden no existe.');
        }

        return $orden;
    }
}
