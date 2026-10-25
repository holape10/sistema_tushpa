<?php

namespace App\Support;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\EmpresaNegocio;
use App\Models\MedioPago;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Support\Impresion\Impresion;
use App\Support\Sunat\EnvioAutomatico;
use Carbon\Carbon;
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
     *                         'series' => ?array<int, string> (productos con número de serie: una por unidad),
     *                         'factor' => ?float (presentación: unidades base por unidad vendida), 'umecod' => ?string,
     *                         'opciones' => ?array<int, float> (opciones elegidas en la comanda: IdProducto => cantidad por plato)], ...]
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

        $cre = self::credito($user, $datos['estadopago']);
        $esContado = $cre->cre_dia_tip === 'CONTADO';
        $cliente = self::cliente($user, $datos, $esContado);

        // ---- Descuento a la cuenta y cortesías (revisa permisos y reparte el descuento entre las líneas) ----
        [$lineas, $descuento] = Descuentos::aplicar($user, $lineas, $datos);
        $extra = $descuento + $extra;

        // ---- Serie y correlativo (bloqueado para que dos cajas no repitan número) ----
        [$colSerie, $colNum] = self::SERIES[$tdocod];

        $sucursal = EmpresaNegocio::where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
        if (! empty($datos['serie_offline'])) {
            // Venta hecha sin internet: ya salió impresa con el número de la serie propia de esa caja
            [$serie, $numero] = CajaSinInternet::validarNumero($user, $tdocod, (string) $datos['serie_offline']['serie'], (int) $datos['serie_offline']['numero']);
        } else {
            $numero = $sucursal->$colNum + 1;
            $serie = $sucursal->$colSerie;
            $sucursal->$colNum = $numero;
            $sucursal->save();
        }

        // ---- Totales ----
        $gravado = $sucursal->tip_igv_pred === '10';
        $factorIgv = self::factorPara($extra['ped_tip'] ?? null);
        $totales = self::totales($lineas, $gravado, $factorIgv);
        $total = $totales['ccaitv'];

        $fecEmi = $datos['fecEmi'];
        $fecVen = self::vencimiento($datos, $fecEmi, $esContado);
        // Varias cajas/barras: sale del almacén de la PC donde se cobra, y queda anotado en qué caja se cobró
        $almacen = Kardex::almacenDeVenta((int) $user->id_empresa_negocio);
        $equipo = Impresion::equipo((int) $user->id_empresa_negocio)?->pc;

        $cabId = DB::table('cpe_cabecera')->insertGetId($extra + self::cabecera($datos, $cliente, $cre, $totales, $gravado, $factorIgv) + [
            'tdocod' => $tdocod, 'serdoc' => $serie, 'numdoc' => $numero,
            'ccafem' => $fecEmi, 'ccafve' => $fecVen,
            'IdUsuario' => $user->IdUsuario,
            'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
            'id_almacen' => $almacen?->id_almacen, 'id_turno' => $turno->id_turno, 'equipo' => $equipo,
            'est_sunat' => $tdocod === '13' ? null : 'PENDIENTE',
        ]);

        // ---- Medios de pago (contado) o cuenta por cobrar (crédito); todo en cortesía = nada que cobrar ----
        if ($total <= 0 && ! $esContado) {
            throw new \RuntimeException('Una cuenta toda en cortesía no se vende al crédito: elige CONTADO.');
        }
        if ($esContado && $total > 0) {
            self::registrarMedios($user, $turno, $cabId, $total,
                (array) ($datos['id_med_pag'] ?? []), (array) ($datos['mon_med_pag'] ?? []), (array) ($datos['op_med_pag'] ?? []),
                (array) ($datos['usd_med_pag'] ?? []));
        } elseif (! $esContado) {
            Cuentas::abrirPorCobrar($cabId);
        }
        // Propina del cliente: aparte del comprobante
        Propinas::registrar($user, $turno, $cabId, $datos);

        // ---- Detalle: una línea por producto, con su salida de stock ----
        self::registrarDetalle($user, $cabId, $lineas, $sucursal, $almacen, $gravado, $factorIgv, [
            'tdocod' => $tdocod, 'serie' => $serie, 'numero' => (string) $numero, 'cliente' => strtoupper(trim($datos['clinom'])), 'fecha' => $fecEmi,
        ], ! empty($extra['ped_id']));

        Fidelizacion::acumular($cabId);       // puntos del cliente (si la sucursal tiene fidelización)
        EnvioAutomatico::programar($cabId);   // si la empresa tiene envío automático

        return $cabId;
    }

    /**
     * Corrige una venta que aún no llegó a SUNAT (la edición la valida antes: ver EdicionVenta) sobre el mismo registro.
     * Mismo tipo: conserva serie y número. Otro tipo (boleta → factura, nota de venta → boleta…): toma el siguiente número
     * de la nueva serie y el suyo vuelve a quedar libre (por eso solo se permite con el último número de su serie).
     *
     * @param  array|null  $lineas  null = los productos no cambian (venta de mesa): solo cambian comprobante, cliente y pago
     */
    public static function reemitir(User $user, Turno $turno, object $cab, array $datos, ?array $lineas): void
    {
        $tdocod = $datos['tdocod'];
        if (! isset(self::SERIES[$tdocod])) {
            throw new \RuntimeException('Tipo de comprobante no válido.');
        }
        if ($lineas !== null && ! $lineas) {
            throw new \RuntimeException('La venta debe tener al menos un producto.');
        }
        $cabId = (int) $cab->IdCpe_cabecera;

        $cre = self::credito($user, $datos['estadopago']);
        $esContado = $cre->cre_dia_tip === 'CONTADO';
        $cliente = self::cliente($user, $datos, $esContado);

        // ---- Serie y correlativo ----
        $sucursal = EmpresaNegocio::where('id_empresa_negocio', $cab->id_empresa_negocio)->lockForUpdate()->first();
        $serie = $cab->serdoc;
        $numero = (int) $cab->numdoc;
        if ($tdocod !== $cab->tdocod) {
            [$colSerieAnt, $colNumAnt] = self::SERIES[$cab->tdocod];
            if ($cab->serdoc !== $sucursal->$colSerieAnt || (int) $sucursal->$colNumAnt !== (int) $cab->numdoc) {
                throw new \RuntimeException('Solo se puede cambiar el tipo del último comprobante de su serie (el último es '
                    .$sucursal->$colSerieAnt.'-'.$sucursal->$colNumAnt.'). Para este, corrige solo los productos o el cliente, o anúlalo.');
            }
            $sucursal->$colNumAnt = (int) $cab->numdoc - 1;   // su número queda libre para la próxima venta
            [$colSerie, $colNum] = self::SERIES[$tdocod];
            $numero = $sucursal->$colNum + 1;
            $serie = $sucursal->$colSerie;
            $sucursal->$colNum = $numero;
            $sucursal->save();
        }

        // ---- Lo que hizo la venta antes: cobros, puntos y, si cambian los productos, el stock ----
        DB::table('venta_medio_pago')->where('IdCpe_cabecera', $cabId)->delete();
        DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $cabId)->delete();
        Fidelizacion::quitarPuntosDeVenta($cabId);

        $gravado = $sucursal->tip_igv_pred === '10';
        $factorIgv = self::factorPara($cab->ped_tip);
        $almacen = $cab->id_almacen ? Almacen::find($cab->id_almacen) : null;
        $cliNom = strtoupper(trim($datos['clinom']));
        if ($lineas !== null) {
            Kardex::revertirVenta($cabId, ['cliente' => $cab->ccanom, 'descripcion' => 'EDICIÓN DE LA VENTA', 'fecha_mov' => now()->toDateString()]);
            Series::revertirVenta($cabId);
            DB::table('porciones_movimientos')->where('IdCpe_cabecera', $cabId)->delete();
            DB::table('cpe_detalle')->where('IdCpe_cabecera', $cabId)->delete();
            $totales = self::totales($lineas, $gravado, $factorIgv);
        } else {
            $totales = ['ccatvg' => (float) $cab->ccatvg, 'ccaigv' => (float) $cab->ccaigv, 'ccatexo' => (float) $cab->ccatexo, 'ccaitv' => (float) $cab->ccaitv,
                'ccagra' => (float) ($cab->ccagra ?? 0), 'ccaigvgra' => (float) ($cab->ccaigvgra ?? 0)];
            // Los movimientos de kardex llevan el comprobante: se actualizan al nuevo
            DB::table('movimientos_productos')->where('IdCpe_cabecera', $cabId)
                ->update(['tdocod' => $tdocod, 'serie' => $serie, 'numero' => (string) $numero, 'cliente' => $cliNom]);
        }
        $total = $totales['ccaitv'];

        $fecVen = self::vencimiento($datos, $cab->ccafem, $esContado);
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->update(self::cabecera($datos, $cliente, $cre, $totales, $gravado, $factorIgv) + [
            'tdocod' => $tdocod, 'serdoc' => $serie, 'numdoc' => $numero, 'ccafve' => $fecVen,
            // Vuelve a quedar por enviar (si SUNAT lo rechazó o falló el envío, se manda corregido)
            'est_sunat' => $tdocod === '13' ? null : 'PENDIENTE', 'ccasunrescod' => null, 'ccadessun' => null, 'ccaqr' => null, 'res_id' => null,
        ]);

        if ($esContado && $total > 0) {
            self::registrarMedios($user, $turno, $cabId, $total,
                (array) ($datos['id_med_pag'] ?? []), (array) ($datos['mon_med_pag'] ?? []), (array) ($datos['op_med_pag'] ?? []),
                (array) ($datos['usd_med_pag'] ?? []));
        } elseif (! $esContado) {
            Cuentas::abrirPorCobrar($cabId);
        }

        if ($lineas !== null) {
            self::registrarDetalle($user, $cabId, $lineas, $sucursal, $almacen, $gravado, $factorIgv, [
                'tdocod' => $tdocod, 'serie' => $serie, 'numero' => (string) $numero, 'cliente' => $cliNom, 'fecha' => $cab->ccafem,
            ], ! empty($cab->ped_id));
        }

        Fidelizacion::acumular($cabId);
        EnvioAutomatico::programar($cabId);
    }

    private static function credito(User $user, $creDiaId): object
    {
        $cre = DB::table('credito_dias')
            ->where('cre_dia_id', $creDiaId)
            ->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $cre) {
            throw new \RuntimeException('Estado de pago no válido.');
        }

        return $cre;
    }

    /** Valida el documento según el comprobante y guarda (o actualiza) al cliente */
    private static function cliente(User $user, array $datos, bool $esContado): Cliente
    {
        $clinum = trim($datos['clinum']);
        $clinom = strtoupper(trim($datos['clinom']));
        $tdicod = $datos['tdicod'];

        if ($datos['tdocod'] === '01') {
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
            return Cliente::firstOrCreate(
                ['clinum' => $clinum, 'rucemp' => $user->IdEmpresa],
                ['clinom' => 'VENTA AL PORTADOR', 'tdicod' => '1', 'clidir' => '--']
            );
        }

        $direccion = trim((string) ($datos['clidir'] ?? '')) ?: '--';
        $existente = Cliente::where('clinum', $clinum)->where('rucemp', $user->IdEmpresa)->first();
        // Cliente con varias sucursales: la dirección principal no se pisa; la otra queda en su lista de direcciones
        $principal = $existente && $existente->clidir && $existente->clidir !== '--' ? $existente->clidir : $direccion;
        $cliente = Cliente::updateOrCreate(
            ['clinum' => $clinum, 'rucemp' => $user->IdEmpresa],
            [
                'clinom' => $clinom,
                'clidir' => $principal,
                'clicor' => $datos['clicor'] ?? null,
                'tdicod' => $tdicod,
                'telefono' => $datos['telefono'] ?? null,
            ]
        );
        ClienteDirecciones::recordar($cliente->clicod, $direccion, $principal);

        return $cliente;
    }

    /**
     * Total de una línea: cantidad x precio, o el importe cobrado si se vendió por importe (S/ 20 de combustible)
     * o con descuento. Una cortesía no se cobra: 0.
     */
    private static function totalLinea(array $l): float
    {
        if (! empty($l['cortesia'])) {
            return 0.0;
        }

        return isset($l['importe']) ? round((float) $l['importe'], 2) : round($l['cantidad'] * $l['precio'], 2);
    }

    /**
     * Totales cobrados y, aparte, el valor de las cortesías (operaciones gratuitas: no suman al total ni al IGV cobrado).
     *
     * @return array{ccatvg: float, ccaigv: float, ccatexo: float, ccaitv: float, ccagra: float, ccaigvgra: float}
     */
    private static function totales(array $lineas, bool $gravado, float $factorIgv): array
    {
        $total = round(array_sum(array_map(fn ($l) => self::totalLinea($l), $lineas)), 2);
        $ccatvg = $gravado ? round($total / $factorIgv, 2) : 0;
        $gratuito = round(array_sum(array_map(fn ($l) => ! empty($l['cortesia']) ? round($l['cantidad'] * $l['precio'], 2) : 0, $lineas)), 2);
        $ccagra = $gravado ? round($gratuito / $factorIgv, 2) : $gratuito;

        return ['ccatvg' => $ccatvg, 'ccaigv' => $gravado ? round($total - $ccatvg, 2) : 0, 'ccatexo' => $gravado ? 0 : $total, 'ccaitv' => $total,
            'ccagra' => $ccagra, 'ccaigvgra' => $gravado ? round($gratuito - $ccagra, 2) : 0];
    }

    private static function vencimiento(array $datos, string $fecEmi, bool $esContado): ?string
    {
        $fecVen = $esContado ? $fecEmi : ($datos['fecVen'] ?? null);
        if (! $esContado && (! $fecVen || $fecVen <= $fecEmi)) {
            throw new \RuntimeException('La fecha de vencimiento debe ser posterior a la de emisión.');
        }

        return $fecVen;
    }

    /** Columnas de cliente, totales y pago de cpe_cabecera (iguales al emitir y al corregir) */
    private static function cabecera(array $datos, Cliente $cliente, object $cre, array $totales, bool $gravado, float $factorIgv): array
    {
        $esContado = $cre->cre_dia_tip === 'CONTADO';
        $total = $totales['ccaitv'];
        $paga = (float) ($datos['paga'] ?? 0);
        // Con lo que paga el cliente también se cubre la propina: el vuelto es lo que sobra de las dos
        $propina = round(max(0, (float) ($datos['propina'] ?? 0)), 2);

        return [
            'tdicod' => $datos['tdicod'], 'ccandi' => trim($datos['clinum']), 'ccanom' => strtoupper(trim($datos['clinom'])),
            'direccion' => ($datos['clidir'] ?? null) ?: '--', 'clicod' => $cliente->clicod,
            'clicorcli' => $datos['clicor'] ?? null, 'telefono_cliente' => $datos['telefono'] ?? null,
            'ccatvg' => $totales['ccatvg'], 'ccaigv' => $totales['ccaigv'], 'por_igv' => $gravado ? round(($factorIgv - 1) * 100, 2) : null,
            'ccatexo' => $totales['ccatexo'], 'ccaitv' => $total,
            'ccagra' => $totales['ccagra'] ?? 0, 'ccaigvgra' => $totales['ccaigvgra'] ?? 0,
            'totalcontado' => $esContado ? $total : 0, 'totalcredito' => $esContado ? 0 : $total,
            'paga' => $paga, 'vuelto' => $esContado ? max(0, round($paga - $total - $propina, 2)) : 0,
            'estadopago' => $esContado ? 'CONTADO' : 'CREDITO', 'cre_dia_id' => $cre->cre_dia_id,
            'ccaobs' => $datos['observaciones'] ?? null, 'consumo' => (int) ($datos['consumo'] ?? 0),
        ];
    }

    /**
     * Detalle del comprobante con su salida de stock, series, lotes y porciones.
     * Las líneas libres (sin producto, ej. un servicio escrito a mano o un recargo) no mueven stock.
     *
     * @param  array{tdocod: string, serie: string, numero: string, cliente: string, fecha: string}  $comprobante
     */
    private static function registrarDetalle(User $user, int $cabId, array $lineas, EmpresaNegocio $sucursal, ?Almacen $almacen,
        bool $gravado, float $factorIgv, array $comprobante, bool $desdeComanda): void
    {
        // Opciones elegidas en la comanda (entrada del menú, platos del trío): [IdProducto => cantidad por plato]
        $idsOpciones = collect($lineas)->flatMap(fn ($it) => array_keys($it['opciones'] ?? []))->all();
        $productos = Producto::whereIn('IdProducto', array_merge(array_filter(array_column($lineas, 'IdProducto')), $idsOpciones))->get()->keyBy('IdProducto');
        // Platos y opciones con receta: su costo es el de sus ingredientes al precio de hoy (para la rentabilidad)
        $costosReceta = Recetas::costos($productos->whereIn('promocion', [2, Producto::OPCION])->keys())
            + Recetas::costosCombos($productos->where('promocion', 6)->keys()); // combos: lo que lleva
        $costoDe = fn ($id) => (float) ($costosReceta[$id] ?? $productos[$id]->costo ?? 0);
        // Sucursal que vende solo con stock
        $banderas = ControlStock::banderas((int) $user->id_empresa_negocio);

        $porciones = []; // platos que controlan porciones del día: IdProducto => porciones vendidas
        foreach ($lineas as $it) {
            $prod = $it['IdProducto'] ? ($productos[$it['IdProducto']] ?? null) : null;
            $cant = (float) $it['cantidad'];
            // Presentación (SACO x 50): se vende por saco pero del stock salen cantidad x factor unidades base
            $factor = (float) ($it['factor'] ?? 1) > 0 ? (float) ($it['factor'] ?? 1) : 1.0;
            $precio = (float) $it['precio'];
            $totalLinea = self::totalLinea($it);

            $subtotal = $gravado ? round($totalLinea / $factorIgv, 2) : $totalLinea;
            $valorUni = $gravado ? round($precio / $factorIgv, 2) : $precio;

            if ($it['IdProducto']) {
                $porciones[$it['IdProducto']] = ($porciones[$it['IdProducto']] ?? 0) + $cant * $factor;
            }
            foreach ($it['opciones'] ?? [] as $idOpcion => $porPlato) {
                $porciones[$idOpcion] = ($porciones[$idOpcion] ?? 0) + $cant * (float) $porPlato;
            }

            // Cortesía: operación gratuita (no se cobra); el precio de lista queda como valor referencial
            $cortesia = ! empty($it['cortesia']);
            $detId = DB::table('cpe_detalle')->insertGetId([
                'IdCpe_cabecera' => $cabId, 'IdProducto' => $it['IdProducto'], 'IdProducto_rel' => $it['IdProducto'],
                'procod' => $prod->procod ?? '', 'umecod' => ($it['umecod'] ?? null) ?: ($prod->umecod ?? 'NIU'),
                'cdecan' => $cant, 'cdedes' => $it['descripcion'],
                'cdevun' => $cortesia ? 0 : $valorUni, 'cdepuni' => $cortesia ? 0 : $precio, 'cdepve' => $subtotal,
                'cdeigv' => round($totalLinea - $subtotal, 2), 'cdevve' => $totalLinea,
                'tigcod' => $cortesia ? Descuentos::afectacionGratuita((string) $sucursal->tip_igv_pred) : $sucursal->tip_igv_pred,
                'precio_lista' => $cortesia ? $precio : ($it['precio_lista'] ?? null), 'descuento' => (float) ($it['descuento'] ?? 0),
                'cortesia' => $cortesia, 'cortesia_motivo' => $cortesia ? ($it['motivo'] ?? Descuentos::MOTIVO_CORTESIA) : null,
                'costo' => round(($it['IdProducto'] ? $costoDe($it['IdProducto']) : 0) * $factor
                    + collect($it['opciones'] ?? [])->sum(fn ($porPlato, $id) => $costoDe($id) * $porPlato), 2),
                'cpe_det_factor' => $factor, 'id_almacen_pro' => $almacen?->id_almacen,
                // Cuentas contables del producto al momento de la venta (CONCAR)
                'debe' => $prod->debe ?? null, 'haber' => $prod->haber ?? null,
            ]);

            // Stock + kardex: productos simples descuentan directo y los combos descuentan sus componentes
            // Con lotes (farmacia) sale primero el que vence antes, o el que eligió el cajero; queda anotado para el ticket
            if ($prod && $almacen) {
                $doc = [
                    'cod_tip_ope' => '01', 'tdocod' => $comprobante['tdocod'], 'serie' => $comprobante['serie'], 'numero' => $comprobante['numero'],
                    'IdCpe_cabecera' => $cabId, 'cliente' => $comprobante['cliente'], 'fecha_mov' => $comprobante['fecha'],
                ] + $banderas;
                $partes = Kardex::salidaPorVenta($prod, $almacen->id_almacen, round($cant * $factor, 3), $doc + [
                    'precio' => $cortesia ? 0 : round($precio / $factor, 2), 'lote_preferido' => $it['lote'] ?? null,
                ]);
                if ($lotes = Lotes::texto($partes)) {
                    DB::table('cpe_detalle')->where('IdCpe_detalle', $detId)->update(['lotes' => $lotes]);
                }
                // Electrodomésticos, celulares: las series que se llevan quedan vendidas (y salen en el comprobante)
                if ($prod->control_serie ?? false) {
                    $series = Series::vender($prod, Series::lista($it['series'] ?? []), round($cant * $factor, 3), $cabId, $comprobante['cliente'], $comprobante['fecha']);
                    DB::table('cpe_detalle')->where('IdCpe_detalle', $detId)->update(['series' => $series]);
                }
                // Las opciones elegidas (la sopa del menú, los platos del trío) también salen del almacén
                foreach ($it['opciones'] ?? [] as $idOpcion => $porPlato) {
                    if ($opcion = $productos[$idOpcion] ?? null) {
                        Kardex::salidaPorVenta($opcion, $almacen->id_almacen, round($cant * (float) $porPlato, 3),
                            $doc + ['IdProducto_rel' => $prod->IdProducto, 'precio' => 0]);
                    }
                }
            }
        }

        // Porciones del día (juanes, sopa del día...): se restan; en venta directa también se revisa que alcancen
        Porciones::vender((int) $user->id_empresa_negocio, $cabId, $porciones, $desdeComanda);
    }

    /**
     * Medios de pago de una venta al contado; sin medios elegidos se cobra todo con el predeterminado.
     * $operaciones: número de operación de cada medio (tarjeta, Yape, transferencia...); obligatorio para los que no son
     * EFECTIVO cuando la sucursal lo exige (empresa_negocios.exigir_operacion).
     * $dolares: qué medios pagó el cliente en dólares (1/0 por medio). El monto sigue en soles; se anota cuántos dólares
     * son con el tipo de cambio de la sucursal, para que la caja sepa cuántos dólares debe tener.
     */
    private static function registrarMedios(User $user, Turno $turno, int $cabId, float $total, array $ids, array $montos, array $operaciones = [], array $dolares = []): void
    {
        $tipoCambio = Descuentos::config((int) $user->id_empresa_negocio)->tipoCambio;
        if (array_filter($dolares) && ! $tipoCambio) {
            throw new \RuntimeException('Para cobrar en dólares, el administrador debe poner el tipo de cambio en la sucursal.');
        }
        $fila = fn ($idMedio, $monto, $operacion = null, $enDolares = false) => [
            'IdCpe_cabecera' => $cabId, 'id_med_pag' => $idMedio, 'monto' => $monto,
            'id_turno' => $turno->id_turno, 'id_empresa_negocio' => $user->id_empresa_negocio,
            'num_operacion' => trim((string) $operacion) !== '' ? mb_substr(trim((string) $operacion), 0, 30) : null,
        ] + ($enDolares ? ['moneda' => 'USD', 'monto_usd' => round((float) $monto / $tipoCambio, 2), 'tipo_cambio' => $tipoCambio] : []);
        $exige = (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $user->id_empresa_negocio)->value('exigir_operacion');
        $nombres = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->pluck('nom_med_pag', 'id_med_pag');
        $revisar = function ($idMedio, $operacion) use ($exige, $nombres) {
            $nombre = mb_strtoupper(trim((string) ($nombres[$idMedio] ?? '')));
            if ($exige && $nombre !== 'EFECTIVO' && trim((string) $operacion) === '') {
                throw new \RuntimeException("Escribe el número de operación del pago con {$nombre}.");
            }
        };

        if (! count($ids)) {
            $medio = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->orderByDesc('predeterminado')->first();
            if (! $medio) {
                throw new \RuntimeException('No hay medios de pago configurados.');
            }
            $revisar($medio->id_med_pag, null);
            DB::table('venta_medio_pago')->insert($fila($medio->id_med_pag, $total, null, ! empty($dolares[0])));

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
            $revisar($idMedio, $operaciones[$k] ?? null);
        }
        // El N° de operación no se repite: ni en este cobro ni en otra venta vigente de la empresa
        $usados = [];
        foreach ($ids as $k => $idMedio) {
            $op = mb_strtoupper(trim((string) ($operaciones[$k] ?? '')));
            if ($op === '') {
                continue;
            }
            if (isset($usados[$op])) {
                throw new \RuntimeException("El N° de operación {$op} está repetido en este cobro.");
            }
            $usados[$op] = true;
            $previo = DB::table('venta_medio_pago as v')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
                ->where('c.IdEmpresa', $user->IdEmpresa)->whereNull('c.ccabaj')->where('v.num_operacion', $op)
                ->first(['c.serdoc', 'c.numdoc', 'c.fecha_hora']);
            if ($previo) {
                throw new \RuntimeException("El N° de operación {$op} ya se usó en {$previo->serdoc}-{$previo->numdoc} ("
                    .Carbon::parse($previo->fecha_hora)->format('d/m/Y H:i').'). Revisa el número.');
            }
            $operaciones[$k] = $op;
        }
        foreach ($ids as $k => $idMedio) {
            DB::table('venta_medio_pago')->insert($fila($idMedio, $montos[$k], $operaciones[$k] ?? null, ! empty($dolares[$k])));
        }
    }
}
