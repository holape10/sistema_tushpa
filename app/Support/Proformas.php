<?php
namespace App\Support;

use App\Models\{EmpresaNegocio, User};
use Illuminate\Support\Facades\DB;

/**
 * Proformas (cotizaciones) de los puntos de venta: no emiten comprobante, no mueven stock ni caja.
 * Llevan serie y correlativo propios de la sucursal (empresa_negocios.SerProforma / NumProforma).
 * Al cobrarla en una caja se emite el comprobante y la proforma queda FACTURADA con su enlace.
 */
class Proformas
{
    public const ORIGENES = ['PV' => 'Punto Venta', 'FARMACIA' => 'PV Farmacia', 'POS' => 'PV Móvil', 'TACTIL' => 'PV Táctil', 'GRIFO' => 'PV Grifo', 'WEB' => 'Tienda virtual'];

    public const REGLAS = [
        'id'                  => 'nullable|integer',
        'origen'              => 'required|in:PV,FARMACIA,POS,TACTIL,GRIFO,WEB',
        'tdicod'              => 'required|string|size:1',
        'clinum'              => 'required|string|max:15',
        'clinom'              => 'required|string|max:120',
        'clidir'              => 'nullable|string|max:150',
        'observaciones'       => 'nullable|string|max:100',
        'items'               => 'required|array|min:1|max:200',
        'items.*.id'          => 'nullable|integer',
        'items.*.presentacion' => 'nullable|integer',
        'items.*.descripcion' => 'nullable|string|max:150',
        'items.*.cantidad'    => 'required|numeric|min:0.001|max:99999',
        'items.*.importe'     => 'nullable|numeric|min:0.01|max:999999',
        'items.*.precio'      => 'required|numeric|min:0.01|max:999999',
        'items.*.lote'        => 'nullable|string|max:50',
    ];

    public static function numero(object $p): string
    {
        return $p->serie . '-' . str_pad($p->numero, 8, '0', STR_PAD_LEFT);
    }

    /** Crea (o actualiza si trae id) una proforma; devuelve su id */
    public static function guardar(User $user, array $datos): int
    {
        return DB::transaction(function () use ($user, $datos) {
            // Mismas reglas de líneas que una venta: productos activos de la sucursal y presentaciones vigentes
            $lineas = VentaDirecta::lineas($user, collect($datos['items']));
            // Línea por importe (combustible S/ 20): su total es el importe
            $totalDe = fn(array $l) => isset($l['importe']) ? round((float) $l['importe'], 2) : round($l['cantidad'] * $l['precio'], 2);
            $total = round(array_sum(array_map($totalDe, $lineas)), 2);

            $cabecera = [
                'tdicod'        => $datos['tdicod'],
                'clinum'        => trim($datos['clinum']),
                'clinom'        => mb_strtoupper(trim($datos['clinom'])),
                'clidir'        => trim((string) ($datos['clidir'] ?? '')) ?: null,
                'observaciones' => trim((string) ($datos['observaciones'] ?? '')) ?: null,
                'total'         => $total,
                'updated_at'    => now(),
            ];

            if (!empty($datos['id'])) {
                $proforma = self::bloquearPendiente($user, (int) $datos['id']);
                $id = $proforma->id_proforma;
                DB::table('proformas')->where('id_proforma', $id)->update($cabecera);
                DB::table('proforma_detalle')->where('id_proforma', $id)->delete();
            } else {
                // Correlativo bloqueado para que dos cajas no repitan número
                $sucursal = EmpresaNegocio::where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
                $sucursal->NumProforma = (int) $sucursal->NumProforma + 1;
                $sucursal->save();

                $id = DB::table('proformas')->insertGetId($cabecera + [
                    'serie' => $sucursal->SerProforma ?: 'PR01', 'numero' => $sucursal->NumProforma,
                    'fecha' => now()->toDateString(), 'estado' => 'PENDIENTE', 'origen' => $datos['origen'],
                    'IdUsuario' => $user->IdUsuario, 'IdEmpresa' => $user->IdEmpresa,
                    'id_empresa_negocio' => $user->id_empresa_negocio, 'created_at' => now(),
                ]);
            }

            $unidades = DB::table('productos')->whereIn('IdProducto', array_filter(array_column($lineas, 'IdProducto')))->pluck('umecod', 'IdProducto');
            DB::table('proforma_detalle')->insert(array_map(fn($l) => [
                'id_proforma'     => $id,
                'IdProducto'      => $l['IdProducto'],
                'id_presentacion' => $l['presentacion'] ?? null,
                'descripcion'     => $l['descripcion'],
                'umecod'          => ($l['umecod'] ?? null) ?: ($unidades[$l['IdProducto']] ?? 'NIU'),
                'factor'          => $l['factor'] ?? 1,
                'cantidad'        => $l['cantidad'],
                'precio'          => $l['precio'],
                'total'           => $totalDe($l),
                'lote'            => $l['lote'] ?? null,
            ], $lineas));

            return $id;
        });
    }

    /** Bloquea la proforma para editarla o cobrarla; falla si no es de la sucursal o ya se cobró. Dentro de una transacción. */
    public static function bloquearPendiente(User $user, int $id): object
    {
        $proforma = DB::table('proformas')->where('id_proforma', $id)
            ->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
        if (!$proforma) {
            throw new \RuntimeException('La proforma ya no existe.');
        }
        if ($proforma->estado !== 'PENDIENTE') {
            throw new \RuntimeException('La proforma ' . self::numero($proforma) . ' ya fue cobrada.');
        }
        return $proforma;
    }

    public static function marcarFacturada(int $id, int $cabId): void
    {
        DB::table('proformas')->where('id_proforma', $id)->update(['estado' => 'FACTURADA', 'IdCpe_cabecera' => $cabId, 'updated_at' => now()]);
    }

    /**
     * Proforma pendiente lista para cargarla en una caja: cada línea con los datos actuales del producto
     * (stock, presentaciones, lotes) pero con la cantidad y el precio cotizados. null si no existe o ya se cobró.
     */
    public static function paraCaja(User $user, int $id): ?array
    {
        $p = DB::table('proformas')->where('id_proforma', $id)
            ->where('id_empresa_negocio', $user->id_empresa_negocio)->where('estado', 'PENDIENTE')->first();
        if (!$p) {
            return null;
        }
        $detalle = DB::table('proforma_detalle')->where('id_proforma', $id)->orderBy('id_proforma_detalle')->get();
        $productos = CatalogoVenta::porIds($user, $detalle->pluck('IdProducto')->filter()->unique()->values()->all());

        $items = $detalle->map(function ($d) use ($productos) {
            $prod = $d->IdProducto ? ($productos[$d->IdProducto] ?? null) : null;
            $presentaciones = collect($prod['presentaciones'] ?? []);
            // Si la presentación ya no existe, la línea vuelve a la unidad base (el cajero revisa el precio)
            $pres = $d->id_presentacion ? $presentaciones->firstWhere('id', (int) $d->id_presentacion) : null;
            return [
                'producto' => $prod,
                'id' => $prod ? (int) $d->IdProducto : null,
                'presentacion' => $pres['id'] ?? null,
                'factor' => $pres['factor'] ?? 1,
                'descripcion' => $d->descripcion,
                'cantidad' => (float) $d->cantidad,
                'precio' => (float) $d->precio,
                // Vendida por importe (combustible): el total no es exactamente cantidad x precio
                'importe' => abs((float) $d->total - round($d->cantidad * $d->precio, 2)) > 0.001 ? (float) $d->total : null,
                'lote' => $d->lote,
                // El producto se desactivó o borró después de cotizar
                'no_disponible' => $d->IdProducto && !$prod,
            ];
        })->values();

        return [
            'id' => $p->id_proforma,
            'numero' => self::numero($p),
            'cliente' => ['tdicod' => $p->tdicod, 'num' => $p->clinum, 'nom' => $p->clinom, 'dir' => $p->clidir ?? ''],
            'observaciones' => $p->observaciones ?? '',
            'items' => $items,
        ];
    }
}
