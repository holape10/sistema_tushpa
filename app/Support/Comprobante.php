<?php

namespace App\Support;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\EmpresaNegocio;
use App\Models\MedioPago;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Support\Sunat\EnvioAutomatico;
use Illuminate\Support\Facades\DB;

/**
 * Emisión de comprobantes (factura, boleta y nota de venta) compartida por el cobro de mesas y el PV Móvil:
 * cliente, serie y correlativo, totales, medios de pago, detalle y salida de kardex.
 * Debe llamarse dentro de una transacción; los errores de negocio se lanzan como RuntimeException.
 */
class Comprobante
{
    // IGV general (puntos de venta, compras)
    public const FACTOR_IGV = 1.18;

    // Restaurante y hotel: lo que se cobra desde Comandas (mesa, llevar, delivery y su punto de venta) y las habitaciones
    public const FACTOR_RESTAURANTE = 1.105;

    private const ORIGENES_RESTAURANTE = ['SALON', 'LLEVAR', 'DELIVERY', 'PVCOMANDA', 'HOTEL'];

    /** Factor de IGV según de dónde sale la venta (cpe_cabecera.ped_tip) */
    public static function factorPara(?string $origen): float
    {
        return in_array(mb_strtoupper((string) $origen), self::ORIGENES_RESTAURANTE, true) ? self::FACTOR_RESTAURANTE : self::FACTOR_IGV;
    }

    /** Factor con el que se emitió un comprobante (lo anterior a guardar la tasa se hizo con 10.5%) */
    public static function factorDe(object $cab): float
    {
        return $cab->por_igv !== null ? 1 + (float) $cab->por_igv / 100 : self::FACTOR_RESTAURANTE;
    }

    // Comprobante => columnas de serie y correlativo en empresa_negocios
    private const SERIES = [
        '01' => ['FseEmpresa', 'FnuEmpresa'],
        '03' => ['BseEmpresa', 'BnuEmpresa'],
        '13' => ['SerNota', 'NumNota'],
    ];

    /**
     * @param  array  $datos  tdocod, estadopago (cre_dia_id), fecEmi, fecVen, tdicod, clinum, clinom, clidir, clicor,
     *                        telefono, observaciones, consumo, paga, id_med_pag[], mon_med_pag[]
     * @param  array  $lineas  [['IdProducto' => ?int, 'descripcion' => string, 'cantidad' => float, 'precio' => float, 'lote' => ?string,
     *                         'factor' => ?float (presentación: unidades base por unidad vendida), 'umecod' => ?string], ...]
     * @param  array  $extra  columnas propias del origen para cpe_cabecera (ped_id, mes_id, mozo, IdUsuario_ven...)
     * @return int IdCpe_cabecera
     */
    public static function emitir(User $user, Turno $turno, array $datos, array $lineas, array $extra = []): int
    {
        $tdocod = $datos['tdocod'];
        if (! isset(self::SERIES[$tdocod])) {
            throw new \RuntimeException('Tipo de comprobante no válido.');
        }
        if (! $lineas) {
            throw new \RuntimeException('No hay productos para cobrar.');
        }

        $cre = DB::table('credito_dias')
            ->where('cre_dia_id', $datos['estadopago'])
            ->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $cre) {
            throw new \RuntimeException('Estado de pago no válido.');
        }
        $esContado = $cre->cre_dia_tip === 'CONTADO';

        // ---- Cliente ----
        $clinum = trim($datos['clinum']);
        $clinom = strtoupper(trim($datos['clinom']));
        $tdicod = $datos['tdicod'];

        if ($tdocod === '01') {
            $okRuc = strlen($clinum) === 11
                && in_array(substr($clinum, 0, 2), ['10', '20', '15', '17'])
                && $tdicod === '6';
            if (! $okRuc) {
                throw new \RuntimeException('TIPO DE DOCUMENTO NO PERMITIDO PARA EMITIR UNA FACTURA (requiere RUC válido).');
            }
        }
        if (! $esContado && $clinum === '00000000') {
            throw new \RuntimeException('Para vender a crédito debes identificar al cliente.');
        }

        if ($clinum === '00000000') {
            $cliente = Cliente::firstOrCreate(
                ['clinum' => $clinum, 'rucemp' => $user->IdEmpresa],
                ['clinom' => 'VENTA AL PORTADOR', 'tdicod' => '1', 'clidir' => '--']
            );
        } else {
            $cliente = Cliente::updateOrCreate(
                ['clinum' => $clinum, 'rucemp' => $user->IdEmpresa],
                [
                    'clinom' => $clinom,
                    'clidir' => ($datos['clidir'] ?? null) ?: '--',
                    'clicor' => $datos['clicor'] ?? null,
                    'tdicod' => $tdicod,
                    'telefono' => $datos['telefono'] ?? null,
                ]
            );
        }

        // ---- Serie y correlativo (bloqueado para que dos cajas no repitan número) ----
        [$colSerie, $colNum] = self::SERIES[$tdocod];

        $sucursal = EmpresaNegocio::where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
        $numero = $sucursal->$colNum + 1;
        $serie = $sucursal->$colSerie;
        $sucursal->$colNum = $numero;
        $sucursal->save();

        // ---- Totales ----
        $gravado = $sucursal->tip_igv_pred === '10';
        $factorIgv = self::factorPara($extra['ped_tip'] ?? null);
        // Total de una línea: cantidad x precio, o el importe cobrado si se vendió por importe (S/ 20 de combustible)
        $totalDe = fn (array $l) => isset($l['importe']) ? round((float) $l['importe'], 2) : round($l['cantidad'] * $l['precio'], 2);
        $total = round(array_sum(array_map($totalDe, $lineas)), 2);

        $ccatvg = $gravado ? round($total / $factorIgv, 2) : 0;
        $ccaigv = $gravado ? round($total - $ccatvg, 2) : 0;
        $ccatexo = $gravado ? 0 : $total;

        $fecEmi = $datos['fecEmi'];
        $fecVen = $esContado ? $fecEmi : ($datos['fecVen'] ?? null);
        if (! $esContado && (! $fecVen || $fecVen <= $fecEmi)) {
            throw new \RuntimeException('La fecha de vencimiento debe ser posterior a la de emisión.');
        }

        $paga = (float) ($datos['paga'] ?? 0);
        $almacen = Almacen::where('id_empresa_negocio', $user->id_empresa_negocio)->where('predeterminado', 1)->first();

        $cabId = DB::table('cpe_cabecera')->insertGetId($extra + [
            'tdocod' => $tdocod, 'serdoc' => $serie, 'numdoc' => $numero,
            'ccafem' => $fecEmi, 'ccafve' => $fecVen,
            'tdicod' => $tdicod, 'ccandi' => $clinum, 'ccanom' => $clinom,
            'direccion' => ($datos['clidir'] ?? null) ?: '--', 'clicod' => $cliente->clicod,
            'clicorcli' => $datos['clicor'] ?? null, 'telefono_cliente' => $datos['telefono'] ?? null,
            'ccatvg' => $ccatvg, 'ccaigv' => $ccaigv, 'por_igv' => $gravado ? round(($factorIgv - 1) * 100, 2) : null, 'ccatexo' => $ccatexo, 'ccaitv' => $total,
            'totalcontado' => $esContado ? $total : 0, 'totalcredito' => $esContado ? 0 : $total,
            'paga' => $paga, 'vuelto' => $esContado ? max(0, round($paga - $total, 2)) : 0,
            'estadopago' => $esContado ? 'CONTADO' : 'CREDITO', 'cre_dia_id' => $cre->cre_dia_id,
            'ccaobs' => $datos['observaciones'] ?? null, 'consumo' => (int) ($datos['consumo'] ?? 0),
            'IdUsuario' => $user->IdUsuario,
            'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
            'id_almacen' => $almacen?->id_almacen, 'id_turno' => $turno->id_turno,
            'est_sunat' => $tdocod === '13' ? null : 'PENDIENTE',
        ]);

        // ---- Medios de pago (contado) o cuenta por cobrar (crédito) ----
        if ($esContado) {
            self::registrarMedios($user, $turno, $cabId, $total,
                (array) ($datos['id_med_pag'] ?? []), (array) ($datos['mon_med_pag'] ?? []));
        } else {
            Cuentas::abrirPorCobrar($cabId);
        }

        // ---- Detalle: una línea por producto, con su salida de stock ----
        // Las líneas libres (sin producto, ej. un servicio escrito a mano o un recargo) no mueven stock
        $productos = Producto::whereIn('IdProducto', array_filter(array_column($lineas, 'IdProducto')))->get()->keyBy('IdProducto');

        foreach ($lineas as $it) {
            $prod = $it['IdProducto'] ? ($productos[$it['IdProducto']] ?? null) : null;
            $cant = (float) $it['cantidad'];
            // Presentación (SACO x 50): se vende por saco pero del stock salen cantidad x factor unidades base
            $factor = (float) ($it['factor'] ?? 1) > 0 ? (float) ($it['factor'] ?? 1) : 1.0;
            $precio = (float) $it['precio'];
            $totalLinea = $totalDe($it);

            $subtotal = $gravado ? round($totalLinea / $factorIgv, 2) : $totalLinea;
            $valorUni = $gravado ? round($precio / $factorIgv, 2) : $precio;

            $detId = DB::table('cpe_detalle')->insertGetId([
                'IdCpe_cabecera' => $cabId, 'IdProducto' => $it['IdProducto'], 'IdProducto_rel' => $it['IdProducto'],
                'procod' => $prod->procod ?? '', 'umecod' => ($it['umecod'] ?? null) ?: ($prod->umecod ?? 'NIU'),
                'cdecan' => $cant, 'cdedes' => $it['descripcion'],
                'cdevun' => $valorUni, 'cdepuni' => $precio, 'cdepve' => $subtotal,
                'cdeigv' => round($totalLinea - $subtotal, 2), 'cdevve' => $totalLinea,
                'tigcod' => $sucursal->tip_igv_pred, 'costo' => round(($prod->costo ?? 0) * $factor, 2),
                'cpe_det_factor' => $factor, 'id_almacen_pro' => $almacen?->id_almacen,
                // Cuentas contables del producto al momento de la venta (CONCAR)
                'debe' => $prod->debe ?? null, 'haber' => $prod->haber ?? null,
            ]);

            // Stock + kardex: productos simples descuentan directo y los combos descuentan sus componentes
            // Con lotes (farmacia) sale primero el que vence antes, o el que eligió el cajero; queda anotado para el ticket
            if ($prod && $almacen) {
                $partes = Kardex::salidaPorVenta($prod, $almacen->id_almacen, round($cant * $factor, 3), [
                    'cod_tip_ope' => '01', 'tdocod' => $tdocod, 'serie' => $serie, 'numero' => (string) $numero,
                    'IdCpe_cabecera' => $cabId, 'cliente' => $clinom,
                    'precio' => round($precio / $factor, 2), 'fecha_mov' => $fecEmi, 'lote_preferido' => $it['lote'] ?? null,
                ]);
                if ($lotes = Lotes::texto($partes)) {
                    DB::table('cpe_detalle')->where('IdCpe_detalle', $detId)->update(['lotes' => $lotes]);
                }
            }
        }

        Fidelizacion::acumular($cabId);       // puntos del cliente (si la sucursal tiene fidelización)
        EnvioAutomatico::programar($cabId);   // si la empresa tiene envío automático

        return $cabId;
    }

    /** Medios de pago de una venta al contado; sin medios elegidos se cobra todo con el predeterminado */
    private static function registrarMedios(User $user, Turno $turno, int $cabId, float $total, array $ids, array $montos): void
    {
        $fila = fn ($idMedio, $monto) => [
            'IdCpe_cabecera' => $cabId, 'id_med_pag' => $idMedio, 'monto' => $monto,
            'id_turno' => $turno->id_turno, 'id_empresa_negocio' => $user->id_empresa_negocio,
        ];

        if (! count($ids)) {
            $medio = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->orderByDesc('predeterminado')->first();
            if (! $medio) {
                throw new \RuntimeException('No hay medios de pago configurados.');
            }
            DB::table('venta_medio_pago')->insert($fila($medio->id_med_pag, $total));

            return;
        }

        $mediosValidos = array_map('intval', MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->pluck('id_med_pag')->all());
        foreach ($ids as $k => $idMedio) {
            if (! in_array((int) $idMedio, $mediosValidos, true) || (float) ($montos[$k] ?? 0) <= 0) {
                throw new \RuntimeException('Medio de pago o monto no válido.');
            }
        }
        $suma = round(array_sum(array_map('floatval', $montos)), 2);
        if (abs($suma - $total) > 0.01) {
            throw new \RuntimeException('Los medios de pago suman S/ '.number_format($suma, 2)
                .' y el total es S/ '.number_format($total, 2).'.');
        }
        foreach ($ids as $k => $idMedio) {
            DB::table('venta_medio_pago')->insert($fila($idMedio, $montos[$k]));
        }
    }
}
