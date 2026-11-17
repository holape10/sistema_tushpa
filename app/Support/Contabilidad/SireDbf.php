<?php

namespace App\Support\Contabilidad;

use App\Support\Concar;
use App\Support\Estudio;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireCuadre;
use App\Support\VentasDetalle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * La propuesta del SIRE lista para el sistema contable en FoxPro: ventas → tienfact.dbf, compras → almaingr.dbf.
 * El contador revisa cada comprobante (cuentas, montos, cuáles no se llevan) y eso se guarda en sire_ajustes sin tocar
 * la propuesta de SUNAT. Los campos se llenan igual que el sistema contable actual llena lo que trae del SIRE.
 */
class SireDbf
{
    public const LIBROS = ['ventas' => Sire::VENTAS, 'compras' => Sire::COMPRAS];

    /** Medios de pago del sistema contable (Tabla 1 de SUNAT): van en almaingr.MPAGO */
    public const MEDIOS_PAGO = ['01' => 'DEPÓSITO EN CUENTA', '02' => 'GIRO', '03' => 'TRANSFERENCIA DE FONDOS', '04' => 'ORDEN DE PAGO',
        '05' => 'TARJETA DE DÉBITO', '06' => 'TARJETA DE CRÉDITO', '07' => 'CHEQUE NO NEGOCIABLE', '08' => 'EFECTIVO',
        '09' => 'EFECTIVO EN DEMÁS CASOS', '10' => 'COMERCIO EXTERIOR', '99' => 'CRÉDITO'];

    /** Montos de una compra (por destino del crédito fiscal, como el RCE) */
    public const MONTOS_COMPRA = ['base_dg', 'igv_dg', 'base_dgng', 'igv_dgng', 'base_dng', 'igv_dng', 'no_gravado', 'isc', 'otros', 'total'];

    /** Hasta cuánto se corrige solo una diferencia de redondeo del proveedor (su total y sus líneas no cuadran por céntimos) */
    public const REDONDEO_MAXIMO = 0.10;

    /** Nombre de cada tipo de comprobante, para el filtro «Documento» */
    public const NOMBRES_TIPO = ['01' => 'Factura', '02' => 'Recibo por honorarios', '03' => 'Boleta', '05' => 'Boleto aéreo', '07' => 'Nota de crédito',
        '08' => 'Nota de débito', '10' => 'Recibo de arrendamiento', '12' => 'Ticket', '13' => 'Doc. bancario / financiero', '14' => 'Servicio público',
        '46' => 'Formulario SUNAT', '50' => 'DUA (importación)', '87' => 'Nota de crédito especial', '88' => 'Nota de débito especial', '91' => 'Comprobante no domiciliado'];

    private const DESCRIPCION = ['01' => 'FACTURA', '02' => 'R/H', '03' => 'BOLETA', '07' => 'NOTA CRED', '08' => 'NOTA DEB', '12' => 'TICKET', '14' => 'RSP'];

    /** Columnas de importes de la propuesta, por su título (sin tildes ni signos) */
    private const COLUMNAS = [
        'compras' => ['base_dg' => 'bi gravado dg', 'igv_dg' => 'igv ipm dg', 'base_dgng' => 'bi gravado dgng', 'igv_dgng' => 'igv ipm dgng',
            'base_dng' => 'bi gravado dng', 'igv_dng' => 'igv ipm dng', 'no_gravado' => 'valor adq ng', 'isc' => 'isc', 'icbper' => 'icbper',
            'otros' => 'otros trib cargos', 'total' => 'total cp'],
        'ventas' => ['exporta' => 'valor facturado exportacion', 'gravada' => 'bi gravada', 'dscto_bi' => 'dscto bi', 'igv' => 'igv ipm',
            'dscto_igv' => 'dscto igv ipm', 'exonerada' => 'mto exonerado', 'inafecta' => 'mto inafecto', 'isc' => 'isc', 'icbper' => 'icbper',
            'base_ivap' => 'bi grav ivap', 'ivap' => 'ivap', 'otros' => 'otros tributos', 'total' => 'total cp'],
    ];

    private static function normalizar(string $h): string
    {
        $h = mb_strtolower(trim(preg_replace('/^\x{FEFF}/u', '', $h)));
        $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', $h)));
    }

    private static function numero(mixed $v): float
    {
        return round((float) str_replace(',', '', (string) $v), 2);
    }

    /** La propuesta ya traída de SUNAT para el periodo (null si aún no se trajo) */
    public static function propuesta(string $ruc, string $libro, string $periodo): ?array
    {
        $s = DB::table('sire_solicitudes')->where('IdEmpresa', $ruc)->where('libro', self::LIBROS[$libro])->where('periodo', $periodo)
            ->whereNotNull('archivo')->orderByDesc('id')->first();
        if (! $s || ! Storage::exists($s->archivo)) {
            return null;
        }
        try {
            return Sire::leerZip(Storage::path($s->archivo));
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Los comprobantes de la propuesta con sus importes como los da SUNAT (las notas de crédito en negativo).
     *
     * @return array<string, array{clave: string, tipo: string, serie: string, numero: string, fecha: ?string, vence: ?string,
     *     ruc: string, nombre: string, moneda: string, tc: float, ref: ?array, anulado: bool, montos: array<string, float>}>
     */
    public static function leer(string $libro, array $propuesta): array
    {
        $c = SireCuadre::columnas($propuesta['cabecera']);
        $norm = array_map(fn ($h) => self::normalizar($h), $propuesta['cabecera']);
        $donde = fn (string $titulo) => array_search($titulo, $norm, true) ?: null;
        $empieza = function (string $inicio) use ($norm) {
            foreach ($norm as $i => $h) {
                if (str_starts_with($h, $inicio)) {
                    return $i;
                }
            }

            return null;
        };
        $col = array_map($donde, self::COLUMNAS[$libro]);
        $otras = [
            'vence' => $empieza('fecha vcto'), 'moneda' => $donde('moneda'), 'tc' => $donde('tipo de cambio') ?? $donde('tipo cambio'),
            'docTipo' => $empieza('tipo doc identidad'),
            'refFecha' => $empieza('fecha emision doc modificado'), 'refTipo' => $empieza('tipo cp modificado'),
            'refSerie' => $empieza('serie cp modificado'), 'refNumero' => $empieza('nro cp modificado'), 'estado' => $empieza('est comp'),
        ];
        if ($c['tipo'] === null || $c['serie'] === null || $c['numero'] === null) {
            return [];
        }
        $valor = fn (array $f, ?int $i) => $i === null ? '' : trim((string) ($f[$i] ?? ''));
        $fecha = fn (string $v) => preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;

        $res = [];
        foreach ($propuesta['filas'] as $f) {
            $tipo = str_pad($valor($f, $c['tipo']), 2, '0', STR_PAD_LEFT);
            $serie = strtoupper($valor($f, $c['serie']));
            $numero = ltrim($valor($f, $c['numero']), '0') ?: '0';
            $ruc = $valor($f, $c['doc']);
            if ($serie === '') {
                continue;
            }
            $montos = array_map(fn ($i) => self::numero($i === null ? 0 : ($f[$i] ?? 0)), $col);
            if ($libro === 'ventas') {
                $montos['gravada'] = round($montos['gravada'] + $montos['dscto_bi'], 2);
                $montos['igv'] = round($montos['igv'] + $montos['dscto_igv'], 2);
                // El arroz pilado (IVAP) va con los otros tributos: el .dbf no tiene campo para él
                $montos['otros'] = round($montos['otros'] + $montos['base_ivap'] + $montos['ivap'], 2);
                unset($montos['dscto_bi'], $montos['dscto_igv'], $montos['base_ivap'], $montos['ivap']);
            } else {
                $montos['otros'] = round($montos['otros'] + $montos['icbper'], 2);
                unset($montos['icbper']);
            }
            $refSerie = $valor($f, $otras['refSerie']);
            $clave = "{$ruc}|{$tipo}|{$serie}|{$numero}";
            $res[$clave] = [
                'clave' => $clave, 'tipo' => $tipo, 'serie' => $serie, 'numero' => $numero,
                'fecha' => $fecha($valor($f, $c['fecha'])), 'vence' => $fecha($valor($f, $otras['vence'])),
                'ruc' => $ruc, 'doc_tipo' => $valor($f, $otras['docTipo']), 'nombre' => $valor($f, $c['nombre']),
                'moneda' => $valor($f, $otras['moneda']) ?: 'PEN', 'tc' => (float) ($valor($f, $otras['tc']) ?: 1) ?: 1.0,
                'ref' => $refSerie !== '' ? ['tipo' => str_pad($valor($f, $otras['refTipo']), 2, '0', STR_PAD_LEFT), 'serie' => strtoupper($refSerie),
                    'numero' => ltrim($valor($f, $otras['refNumero']), '0') ?: '0', 'fecha' => $fecha($valor($f, $otras['refFecha']))] : null,
                // RVIE: "2" = anulado
                'anulado' => $valor($f, $otras['estado']) === '2',
                'montos' => $montos,
            ];
        }

        return $res;
    }

    /**
     * Los comprobantes del periodo con lo que el contador revisó: cuentas (o las sugeridas), montos corregidos, si se
     * llevan al .dbf y si cuadran. Las compras traen además lo que se compró (de su XML).
     *
     * @return array{comprobantes: array<int, array>, propuesta: bool}
     */
    public static function delPeriodo(string $ruc, string $libro, string $periodo): array
    {
        $propuesta = self::propuesta($ruc, $libro, $periodo);
        if ($propuesta === null) {
            return ['comprobantes' => [], 'propuesta' => false];
        }
        $comprobantes = self::leer($libro, $propuesta);
        // Los que el contador agregó a mano a este mes (si SUNAT ya lo trae en la propuesta, vale el de la propuesta)
        $comprobantes += array_diff_key(SireManuales::delPeriodo($ruc, $libro, $periodo), $comprobantes);
        // Los de la propuesta que ya se agregaron a mano en otro mes: aquí no se llevan (no entran dos veces)
        $yaManual = SireManuales::enOtroPeriodo($ruc, $libro, $periodo, array_keys($comprobantes));
        $ajustes = DB::table('sire_ajustes')->where('IdEmpresa', $ruc)->where('libro', $libro)->where('periodo', $periodo)->get()->keyBy('clave');
        $cfg = Concar::config($ruc);
        // La última cuenta que se usó con cada proveedor o cliente (de cualquier mes)
        $ultimas = DB::table('sire_ajustes')->where('IdEmpresa', $ruc)->where('libro', $libro)
            ->whereIn('ruc', array_unique(array_column($comprobantes, 'ruc')) ?: ['-'])->where(fn ($q) => $q->whereNotNull('ctacargo')->orWhereNotNull('ctaabono')->when(self::conCancelacion(), fn ($w) => $w->orWhereNotNull('ctacance'))->when(self::conMedioPago(), fn ($w) => $w->orWhereNotNull('mpago')))
            ->orderBy('updated_at')->get()->keyBy('ruc');
        $lineas = $libro === 'compras' ? self::lineasXml($ruc, array_keys($comprobantes)) : [];
        // Total y moneda que dice el XML de cada compra (para ver si lo que se va a llevar es lo mismo)
        $cabecerasXml = $libro === 'compras' ? self::$cabecerasXml : [];
        // Ventas: los XML que subió el contador, por tipo|serie|número (la empresa los emitió)
        $ventasXml = $libro === 'ventas' ? VentasDetalle::lineas($ruc, array_map(fn ($c) => "{$c['tipo']}|{$c['serie']}|{$c['numero']}", $comprobantes)) : [];

        $lista = [];
        foreach ($comprobantes as $clave => $c) {
            $a = $ajustes[$clave] ?? null;
            $u = $ultimas[$c['ruc']] ?? null;
            $c['lineas'] = $lineas[$clave] ?? [];
            $c['xml_id'] = isset($c['lineas'][0]) ? (int) $c['lineas'][0]->compra_xml_id : null;
            if ($libro === 'ventas' && ($v = $ventasXml["{$c['tipo']}|{$c['serie']}|{$c['numero']}"] ?? null)) {
                [$c['lineas'], $c['xml_id']] = [$v['lineas'], $v['xml_id']];
            }
            $c['manual'] ??= null;
            $c['yaManual'] = $yaManual[$clave] ?? null;
            $c['excluido'] = (bool) ($a->excluido ?? isset($yaManual[$clave]));
            $c['editado'] = $a && $a->montos;
            // Lo que dice SUNAT (en la moneda del comprobante), para notar si al editar se escribió en otra moneda
            $c['total_sunat'] = (float) $c['montos']['total'];
            $c['montos_sunat'] = $c['montos'];
            $c['montos'] = $a && $a->montos ? array_map('floatval', json_decode($a->montos, true)) + array_fill_keys(array_keys($c['montos']), 0.0) : $c['montos'];
            if ($libro === 'compras') {
                $cuentaXml = collect($c['lineas'])->pluck('cuenta')->filter()->unique();
                $elegida = $a->ctacargo ?? $u->ctacargo ?? ($cuentaXml->count() === 1 ? $cuentaXml->first() : null);
                $c['ctacargo'] = $elegida ?? $cfg['cta_compras'];
                // Nadie le puso Cta. cargo (ni a ese proveedor): va a la de compras por defecto
                $c['sinCtaCargo'] = $elegida === null;
                $c['ctaabono'] = $a->ctaabono ?? $u->ctaabono ?? $cfg['cta_por_pagar'];
                // Centro de costo (94, 95, 97…): el del comprobante o el último de ese proveedor
                // Apagado no sale en ningún lado: el .dbf queda como antes (CODDESTINO vacío)
                $c['ccosto'] = CuentasCompras::activo() ? ($a->ccosto ?? $u->ccosto ?? null) : null;
            } else {
                $c['ctacargo'] = $a->ctacargo ?? $u->ctacargo ?? $cfg['cta_por_cobrar'];
                $c['ctaabono'] = $a->ctaabono ?? $u->ctaabono ?? ($c['montos']['gravada'] != 0 ? $cfg['cta_ventas'] : $cfg['cta_ventas_exo']);
            }
            // Cuenta de cancelación (con qué se pagó o cobró): la del comprobante o la última de ese proveedor o cliente
            $c['ctacance'] = $a->ctacance ?? $u->ctacance ?? null;
            // Medio de pago (el del comprobante o el último de ese proveedor) y fecha de pago (la del comprobante)
            $c['mpago'] = $a->mpago ?? $u->mpago ?? null;
            // En efectivo (el 08 por defecto o el 09) se paga el mismo día: sin otra fecha, la de emisión
            $c['fepago'] = $a->fepago ?? (in_array($c['mpago'] ?? '08', ['08', '09'], true) ? $c['fecha'] : null);
            $c['cuentaPropia'] = (bool) ($a?->ctacargo || $a?->ctaabono);
            $x = $cabecerasXml[$clave] ?? null;
            $c['xml_total'] = $x ? (float) $x->total : null;
            $c['xml_moneda'] = $x ? $x->moneda : null;
            // En dólares pero con los montos en soles: se pasan a dólares, como su XML (el .dbf lleva US$ y su TC)
            $c['de_soles'] = null;
            if (self::montosEnSoles($c)) {
                $c['de_soles'] = (float) $c['montos']['total'];
                $c['montos'] = self::aDolares($c['montos'], (float) $c['tc'], $c['xml_total']);
            }
            $c['redondeo'] = $c['editado'] || $c['de_soles'] !== null ? 0.0 : self::redondear($libro, $c['montos']);
            $c['diferencia'] = self::diferencia($libro, $c['montos']);
            $c['xml_dif'] = self::diferenciaXml($c);
            $c['alerta'] = self::revisarIgv($c['montos'], $libro) ?? self::revisarMoneda($c);
            // Cuentas de gasto 62–68: sin centro de costo no se descarga
            if (! $c['alerta'] && $libro === 'compras' && CuentasCompras::requiereCentro($c['ctacargo']) && ! ($c['ccosto'] ?? null)) {
                $c['alerta'] = "La Cta. cargo {$c['ctacargo']} es de gasto: elige su centro de costo.";
            }
            $lista[] = $c;
        }
        usort($lista, fn ($x, $y) => [$x['fecha'], $x['tipo'], $x['serie'], (int) $x['numero']] <=> [$y['fecha'], $y['tipo'], $y['serie'], (int) $y['numero']]);

        return ['comprobantes' => $lista, 'propuesta' => true];
    }

    /**
     * Si las partes no suman el total por unos céntimos (el redondeo del proveedor: SUNAT copia lo que dice su XML),
     * la diferencia se suma al monto más grande. El total, que es lo que se pagó, no se toca.
     *
     * @return float lo que se corrigió (0 si no hizo falta)
     */
    private static function redondear(string $libro, array &$montos): float
    {
        $dif = self::diferencia($libro, $montos);
        if (abs($dif) < 0.01 || abs($dif) > self::REDONDEO_MAXIMO) {
            return 0.0;
        }
        $partes = array_diff_key($montos, ['total' => 0]);
        uasort($partes, fn ($a, $b) => abs($b) <=> abs($a));
        $mayor = array_key_first($partes);
        if ($mayor === null || $partes[$mayor] == 0) {
            return 0.0;
        }
        $montos[$mayor] = round($montos[$mayor] + $dif, 2);

        return $dif;
    }

    /** Lo que falta (o sobra) para que las partes sumen el total; 0 = cuadra */
    public static function diferencia(string $libro, array $m): float
    {
        $partes = array_sum(array_diff_key($m, ['total' => 0]));

        return round(($m['total'] ?? 0) - $partes, 2);
    }

    /** Pares base → IGV de una compra (IGV 1, 2 y 3) y de una venta, con su nombre en pantalla */
    private const IGV_COMPRA = [['base_dg', 'igv_dg', 'IGV 1', 'B.I. gravadas'], ['base_dgng', 'igv_dgng', 'IGV 2', 'B.I. grav. y no grav.'],
        ['base_dng', 'igv_dng', 'IGV 3', 'B.I. no gravadas']];

    private const IGV_VENTA = [['gravada', 'igv', 'IGV', 'Gravada']];

    /**
     * ¿Cada IGV está junto a su base? El IGV va del 10 % (restaurantes y hoteles MYPE) al 18 % de su base; si no, los
     * montos están en otra casilla (por ejemplo, el IGV escrito en la base). Que las partes sumen el total no basta.
     *
     * @return string|null el aviso para el contador, o null si está bien
     */
    public static function revisarIgv(array $m, string $libro = 'compras'): ?string
    {
        foreach ($libro === 'ventas' ? self::IGV_VENTA : self::IGV_COMPRA as [$kBase, $kIgv, $nIgv, $nBase]) {
            $base = round((float) ($m[$kBase] ?? 0), 2);
            $igv = round((float) ($m[$kIgv] ?? 0), 2);
            if ($base == 0 && $igv == 0) {
                continue;
            }
            if ($base == 0) {
                return "{$nIgv} tiene monto pero su base ({$nBase}) está en 0: el IGV va al lado de su base.";
            }
            if ($igv == 0) {
                return "{$nBase} tiene monto pero su {$nIgv} está en 0. Si no lleva IGV, va en «No gravado».";
            }
            $tolerancia = max(0.10, abs($base) * 0.005);
            if (($igv > 0) !== ($base > 0) || abs($igv) < abs($base) * 0.10 - $tolerancia || abs($igv) > abs($base) * 0.18 + $tolerancia) {
                return "{$nIgv} (".number_format($igv, 2).") no corresponde a su base {$nBase} (".number_format($base, 2)
                    .'; al 18 % sería '.number_format($base * 0.18, 2).'). Revisa que cada monto esté en su casilla.';
            }
        }

        return null;
    }

    /** @return array<string, array<int, object>> líneas de los XML de compras por clave */
    /** @var array<string, object> cabecera del XML (total y moneda) de cada compra, de la última lectura de lineasXml */
    private static array $cabecerasXml = [];

    private static function lineasXml(string $ruc, array $claves): array
    {
        $xmls = DB::table('compras_xml')->where('IdEmpresa', $ruc)
            ->whereIn(DB::raw("CONCAT(ruc_emisor, '|', tipo, '|', serie, '|', numero)"), $claves ?: ['-'])->get();
        self::$cabecerasXml = $xmls->keyBy(fn ($x) => "{$x->ruc_emisor}|{$x->tipo}|{$x->serie}|{$x->numero}")->all();
        $lineas = DB::table('compras_xml_lineas')->whereIn('compra_xml_id', $xmls->pluck('id')->all() ?: [0])->orderBy('orden')->get()->groupBy('compra_xml_id');
        $res = [];
        foreach ($xmls as $x) {
            $res["{$x->ruc_emisor}|{$x->tipo}|{$x->serie}|{$x->numero}"] = ($lineas[$x->id] ?? collect())->values()->all();
        }

        return $res;
    }

    /**
     * Guarda lo que el contador revisó de un comprobante. Los montos solo se guardan si cambiaron; las cuentas quedan
     * como las últimas de ese proveedor o cliente para los próximos meses.
     *
     * @param  array{excluido?: bool, ctacargo?: ?string, ctaabono?: ?string, ctacance?: ?string, montos?: ?array<string, float>}  $datos
     */
    public static function guardar(string $ruc, string $libro, string $periodo, string $clave, string $rucTercero, array $datos): void
    {
        $llave = ['IdEmpresa' => $ruc, 'libro' => $libro, 'periodo' => $periodo, 'clave' => $clave];
        $actual = DB::table('sire_ajustes')->where($llave)->first();
        $fila = ['ruc' => $rucTercero, 'updated_at' => now()];
        foreach (array_merge(['excluido', 'ctacargo', 'ctaabono', 'ccosto'], self::conCancelacion() ? ['ctacance'] : [], self::conMedioPago() ? ['mpago', 'fepago'] : []) as $campo) {
            if (array_key_exists($campo, $datos)) {
                $fila[$campo] = $campo === 'excluido' ? (bool) $datos[$campo] : (trim((string) $datos[$campo]) ?: null);
            }
        }
        if (array_key_exists('montos', $datos)) {
            $fila['montos'] = $datos['montos'] ? json_encode(array_map(fn ($v) => round((float) $v, 2), $datos['montos'])) : null;
        }
        $actual
            ? DB::table('sire_ajustes')->where('id', $actual->id)->update($fila)
            : DB::table('sire_ajustes')->insert($llave + $fila + ['created_at' => now()]);
    }

    /** ¿Ya existen las columnas del medio y la fecha de pago? */
    private static function conMedioPago(): bool
    {
        static $hay = null;

        return $hay ??= Schema::hasColumn('sire_ajustes', 'mpago');
    }

    /**
     * Los medios de pago con la cuenta contable que la empresa le puso a cada uno.
     *
     * @return array<string, array{nombre: string, cuenta: ?string}>
     */
    public static function mediosPago(string $ruc): array
    {
        $cuentas = Schema::hasTable('sire_medios_pago') ? DB::table('sire_medios_pago')->where('IdEmpresa', $ruc)->pluck('cuenta', 'codigo')->all() : [];

        return array_map(fn ($codigo) => ['nombre' => self::MEDIOS_PAGO[$codigo], 'cuenta' => $cuentas[$codigo] ?? null],
            array_combine(array_keys(self::MEDIOS_PAGO), array_keys(self::MEDIOS_PAGO)));
    }

    public static function guardarCuentaMedio(string $ruc, string $codigo, ?string $cuenta): void
    {
        DB::table('sire_medios_pago')->updateOrInsert(['IdEmpresa' => $ruc, 'codigo' => $codigo],
            ['cuenta' => trim((string) $cuenta) ?: null, 'updated_at' => now(), 'created_at' => now()]);
    }

    /**
     * Ventas: los correlativos que faltan en cada serie del periodo (entre el primero y el último que trae SUNAT).
     * Puede ser un comprobante que no se envió, uno de otro mes o un salto de numeración: hay que revisarlo.
     *
     * @return list<array{serie: string, faltan: list<string>, cantidad: int}> «B001», ["7–8", "12"], 3
     */
    public static function correlativosFaltantes(array $comprobantes): array
    {
        $res = [];
        foreach (collect($comprobantes)->groupBy(fn ($c) => $c['tipo'].'|'.$c['serie']) as $clave => $grupo) {
            $numeros = $grupo->pluck('numero')->map(fn ($n) => (int) $n)->filter(fn ($n) => $n > 0)->unique()->sort()->values();
            $tramos = [];
            $cantidad = 0;
            for ($i = 1; $i < $numeros->count(); $i++) {
                [$desde, $hasta] = [$numeros[$i - 1] + 1, $numeros[$i] - 1];
                if ($hasta >= $desde) {
                    $tramos[] = $desde === $hasta ? (string) $desde : "{$desde}–{$hasta}";
                    $cantidad += $hasta - $desde + 1;
                }
            }
            if ($tramos) {
                $res[] = ['serie' => explode('|', $clave)[1], 'tipo' => explode('|', $clave)[0], 'faltan' => $tramos, 'cantidad' => $cantidad];
            }
        }

        return $res;
    }

    /** ¿Ya existe la columna de la cuenta de cancelación? (antes de migrar, el resto sigue funcionando) */
    private static function conCancelacion(): bool
    {
        static $hay = null;

        return $hay ??= Schema::hasColumn('sire_ajustes', 'ctacance');
    }

    /**
     * ¿Comprobante en dólares cuyos montos están en soles? (SUNAT o una edición los dejó ya convertidos). Se sabe con su
     * XML, que está en dólares: el total es el del XML por el tipo de cambio. Sin XML, con el total de SUNAT si se editó.
     */
    public static function montosEnSoles(array $c): bool
    {
        $tc = (float) ($c['tc'] ?? 0);
        if (($c['moneda'] ?? 'PEN') !== 'USD' || $tc <= 1) {
            return false;
        }
        $total = abs((float) $c['montos']['total']);
        $esPorTc = fn (float $dolares) => $dolares >= 0.01 && abs($total - $dolares) >= 0.01
            && abs($total - round($dolares * $tc, 2)) <= max(1.0, $dolares * $tc * 0.005);
        if (($c['xml_total'] ?? null) !== null && ($c['xml_moneda'] ?? 'USD') === 'USD') {
            return $esPorTc(abs((float) $c['xml_total']));
        }

        return ! empty($c['editado']) && $esPorTc(abs((float) ($c['total_sunat'] ?? 0)));
    }

    /**
     * Pasa a dólares los montos que vinieron en soles: cada uno entre el tipo de cambio y el total, el del XML (lo que
     * dice el proveedor); los céntimos del redondeo van a la parte más grande.
     *
     * @return array<string, float>
     */
    public static function aDolares(array $montos, float $tc, ?float $totalXml): array
    {
        $res = array_map(fn ($v) => round((float) $v / $tc, 2), $montos);
        if ($totalXml !== null) {
            $res['total'] = ($res['total'] < 0 ? -1 : 1) * round(abs($totalXml), 2);
        }
        $partes = array_diff_key($res, ['total' => 0]);
        $dif = round($res['total'] - array_sum($partes), 2);
        if ($partes && abs($dif) >= 0.01 && abs($dif) <= 0.10) {
            $mayor = array_search(max(array_map('abs', $partes)), array_map('abs', $partes), true);
            $res[$mayor] = round($res[$mayor] + $dif, 2);
        }

        return $res;
    }

    /** Diferencia entre el total que se va a llevar y el de su XML (en la moneda del comprobante). null si no hay XML. */
    public static function diferenciaXml(array $c): ?float
    {
        if (($c['xml_total'] ?? null) === null) {
            return null;
        }

        // Las notas de crédito: SUNAT las da en negativo y el XML en positivo
        return round(abs((float) $c['montos']['total']) - abs((float) $c['xml_total']), 2);
    }

    /** En dólares los montos van en dólares y el .dbf lleva su tipo de cambio: sin TC el sistema contable no puede convertirlos */
    public static function revisarMoneda(array $c): ?string
    {
        if (($c['moneda'] ?? 'PEN') === 'USD' && (float) ($c['tc'] ?? 0) <= 1) {
            return 'Comprobante en dólares sin tipo de cambio: el sistema contable no podrá pasarlo a soles.';
        }

        return null;
    }

    /** ¿Este comprobante impide descargar? (no cuadra o su IGV no está junto a su base) */
    public static function conError(array $c): bool
    {
        return abs($c['diferencia']) >= 0.01 || ! empty($c['alerta']);
    }

    /**
     * Suma de lo que se va a llevar al .dbf y, aparte, de lo que no se lleva (para ver la diferencia con SUNAT). En SOLES:
     * los comprobantes en dólares se pasan con su tipo de cambio (como en el resumen de SUNAT).
     */
    public static function totales(string $libro, array $comprobantes): array
    {
        $llevar = array_filter($comprobantes, fn ($c) => ! $c['excluido']);
        $fuera = array_filter($comprobantes, fn ($c) => $c['excluido']);
        $enSoles = fn (array $c) => ($c['moneda'] ?? 'PEN') === 'USD' && (float) ($c['tc'] ?? 0) > 0 ? (float) $c['tc'] : 1.0;
        $montos = function (array $lista) use ($libro, $enSoles): array {
            $suma = fn (string ...$k) => round(array_sum(array_map(fn ($c) => round(array_sum(array_intersect_key($c['montos'], array_flip($k))) * $enSoles($c), 2), $lista)), 2);

            return [
                'base' => $libro === 'compras' ? $suma('base_dg', 'base_dgng', 'base_dng') : $suma('gravada'),
                'igv' => $libro === 'compras' ? $suma('igv_dg', 'igv_dgng', 'igv_dng') : $suma('igv'),
                'no_gravado' => $libro === 'compras' ? $suma('no_gravado') : $suma('exonerada', 'inafecta', 'exporta'),
                'total' => $suma('total'),
            ];
        };

        return [
            'registros' => count($llevar), 'excluidos' => count($fuera),
            'descuadrados' => count(array_filter($llevar, fn ($c) => self::conError($c))),
            'sin_cta_cargo' => count(array_filter($llevar, fn ($c) => $c['sinCtaCargo'] ?? false)),
            'en_dolares' => count(array_filter($llevar, fn ($c) => ($c['moneda'] ?? 'PEN') === 'USD')),
            'distinto_xml' => count(array_filter($llevar, fn ($c) => abs((float) ($c['xml_dif'] ?? 0)) >= 0.01)),
            'no_llevan' => $montos($fuera),
        ] + $montos($llevar);
    }

    private static function plantilla(string $tabla): Dbf
    {
        return new Dbf(resource_path("dbf/{$tabla}.dbf"));
    }

    /**
     * Tipo de documento del proveedor o cliente (campo TIPO_2 del .dbf): 6 = RUC, 1 = DNI, 4 = carné de extranjería,
     * 7 = pasaporte, 0 = otros. El que dice SUNAT; si no lo trae (agregados a mano), por el largo del número.
     */
    public static function tipoDocumento(array $c): string
    {
        $tipo = strtoupper(trim((string) ($c['doc_tipo'] ?? '')));
        if (preg_match('/^[0-9A-Z]$/', $tipo)) {
            return $tipo;
        }
        $numero = trim((string) ($c['ruc'] ?? ''));

        return match (true) {
            preg_match('/^\d{11}$/', $numero) === 1 => '6',
            preg_match('/^\d{8}$/', $numero) === 1 => '1',
            default => '0',
        };
    }

    /** Campo del tienfact.dbf donde va el total de la venta, según el tipo de empresa del estudio */
    private const CAMPO_TIPO_EMPRESA = ['SERVICIO' => 'SERVI', 'COMERCIO' => 'MERCA', 'PRODUCCION' => 'PROTE', 'OTROS' => 'OTR'];

    /** Compras → almaingr.dbf, como el sistema contable registra lo que trae del SIRE */
    public static function almaingr(string $periodo, array $comprobantes): string
    {
        [$anio, $mes] = [(int) substr($periodo, 0, 4), substr($periodo, 4, 2)];
        $filas = [];
        $n = 0;
        foreach ($comprobantes as $c) {
            if ($c['excluido']) {
                continue;
            }
            $n++;
            $m = $c['montos'];
            // Las notas de crédito van en positivo: el sistema contable las resta por su tipo (07)
            if (in_array($c['tipo'], SireAuditoria::NOTAS_CREDITO, true)) {
                $m = array_map(fn ($v) => abs((float) $v), $m);
            }
            $correlativo = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $filas[] = [
                'ORIGEN' => 'SIRE', 'NUMDOC' => str_pad($c['numero'], 12, '0', STR_PAD_LEFT), 'SERIEDOC' => $c['serie'], 'RUC_DNI' => $c['ruc'], 'TIPO_2' => self::tipoDocumento($c),
                'ID' => 'G', 'NORDEN' => $mes.$correlativo, 'CODMOV' => str_pad((string) $n, 6, '0', STR_PAD_LEFT), 'ALMA' => '01', 'COD_OPERA' => '02',
                'CODDOC' => $c['tipo'], 'DESDOC' => self::DESCRIPCION[$c['tipo']] ?? $c['tipo'],
                'FECHADOC' => $c['fecha'], 'FECHAING' => $c['fecha'], 'FECVENCI' => $c['vence'] ?: $c['fecha'],
                'IMPOPAGO' => $m['total'], 'TOTAL' => $m['total'], 'TC' => $c['tc'], 'MONEDA' => $c['moneda'] === 'USD' ? 'US$' : 'S/',
                'MES' => (int) $mes, 'ANO' => $anio, 'NOMAREA' => $c['nombre'], 'PROVE' => $c['nombre'], 'FCON' => $mes.$anio,
                'REGCOMPRA' => 1, 'NUM1' => $correlativo, 'MPAGO' => $c['mpago'] ?? '08', 'FEPAGO' => $c['fepago'] ?? null, 'CTACARGO' => $c['ctacargo'], 'CTAABONO' => $c['ctaabono'], 'CTACANCE' => $c['ctacance'] ?? null,
                'CODDESTINO' => $c['ccosto'] ?? null,
                'OGYE' => $m['base_dg'] ?: null, 'IGV_1' => $m['igv_dg'] ?: null, 'OGYEYONG' => $m['base_dgng'] ?: null, 'IGV_2' => $m['igv_dgng'] ?: null,
                'ONG' => $m['base_dng'] ?: null, 'IGV_3' => $m['igv_dng'] ?: null, 'NG' => $m['no_gravado'] ?: null,
                'ISC' => $m['isc'] ?: null, 'OTROS' => $m['otros'] ?: null,
                'FCODDOC' => $c['ref']['tipo'] ?? null, 'FECHANC' => $c['ref']['fecha'] ?? null, 'FSERIE' => $c['ref']['serie'] ?? null,
                'FNUMERO' => isset($c['ref']) ? str_pad($c['ref']['numero'], 12, '0', STR_PAD_LEFT) : null,
            ];
        }

        return self::plantilla('almaingr')->generar($filas);
    }

    /** Ventas → tienfact.dbf, como el sistema contable registra lo que trae del SIRE */
    public static function tienfact(string $ruc, string $periodo, array $comprobantes): string
    {
        $mes = (int) substr($periodo, 4, 2);
        // Empresa de un estudio: el total de cada venta va también al campo de su tipo de empresa
        $campoTipo = self::CAMPO_TIPO_EMPRESA[Estudio::tipoEmpresa($ruc) ?? ''] ?? null;
        $filas = [];
        foreach ($comprobantes as $c) {
            if ($c['excluido']) {
                continue;
            }
            $m = $c['montos'];
            $numero = str_pad($c['numero'], 12, '0', STR_PAD_LEFT);
            $filas[] = [
                'CODMOV' => substr($ruc.$c['tipo'].$c['serie'].$numero, 0, 15), 'NUM_MES' => $mes, 'ID' => '1', 'TIPO_2' => self::tipoDocumento($c),
                'COD_DOC' => $c['tipo'], 'SERIEDOC' => $c['serie'], 'NUM_DOC' => $numero, 'RUC' => $c['ruc'], 'COD_OPERA' => '01',
                'DES_DOC' => self::DESCRIPCION[$c['tipo']] ?? $c['tipo'], 'FECHA_DOC' => $c['fecha'], 'FECHA_SAL' => $c['fecha'],
                'ALMA' => '01', 'FPAGO' => '01', 'FECVENCI' => $c['vence'] ?: $c['fecha'], 'NOTA' => $c['nombre'], 'NOMBRE' => $c['nombre'],
                'TOTAL' => $m['total'], 'TC' => $c['tc'], 'MONEDA' => $c['moneda'], 'ANULADA' => $c['anulado'] ? 1 : 0,
                'CTACARGO' => $c['ctacargo'], 'CTAABONO' => $c['ctaabono'], 'NUMERO' => $c['ctacance'] ?? null, 'TBRUTO' => 0, 'TNETO' => 0,
                'EXPORTA' => $m['exporta'], 'GRAVADA' => $m['gravada'], 'IGV' => $m['igv'],
                'EXONERADA' => $m['exonerada'], 'INAFECTA' => $m['inafecta'], 'ISC' => $m['isc'],
                'IBOLSAS' => $m['icbper'], 'OTROS' => $m['otros'] ?: null,
                'FECHA_C' => $c['ref']['fecha'] ?? null, 'TIP_DOC_C' => $c['ref']['tipo'] ?? null, 'SER_DOC_C' => $c['ref']['serie'] ?? null,
                'NUM_DOC_C' => isset($c['ref']) ? str_pad($c['ref']['numero'], 12, '0', STR_PAD_LEFT) : null,
            ] + ($campoTipo ? [$campoTipo => $m['total']] : []);
        }

        return self::plantilla('tienfact')->generar($filas);
    }
}
