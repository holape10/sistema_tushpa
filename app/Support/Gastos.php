<?php

namespace App\Support;

use App\Models\Turno;
use App\Models\User;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireCuadre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gastos del negocio que no son mercadería (servicios, alquiler, honorarios, tributos municipales…).
 * La mercadería se registra en Compras porque mueve stock; aquí solo importes.
 *   total del comprobante = base + IGV + no gravado
 *   neto a pagar          = total − retención de 4ta categoría (recibos por honorarios)
 */
class Gastos
{
    public const DOCUMENTOS = ['01' => 'Factura', '03' => 'Boleta', '02' => 'Recibo por honorarios', '14' => 'Recibo de servicios públicos',
        '12' => 'Ticket', '07' => 'Nota de crédito', '00' => 'Otro / sin comprobante'];

    public const TASA_IGV = 0.18;

    public const TASA_RETENCION_4TA = 0.08;

    public static function categorias(string $ruc)
    {
        if (! DB::table('gasto_categorias')->where('IdEmpresa', $ruc)->exists()) {
            DB::table('gasto_categorias')->insert(['IdEmpresa' => $ruc, 'nombre' => 'OTROS GASTOS', 'cuenta_contable' => '659', 'color' => '#9ca3af',
                'created_at' => now(), 'updated_at' => now()]);
        }

        return DB::table('gasto_categorias')->where('IdEmpresa', $ruc)->orderBy('nombre')->get();
    }

    /** Guarda un gasto nuevo o editado. $d: fecha, tdocod, serie, numero, prov_doc, prov_nombre, categoria_id, descripcion, moneda, tipo_cambio, base, igv, no_gravado, retencion, credito_fiscal, forma_pago, id_med_pag */
    public static function guardar(User $user, array $d, ?int $id = null, string $origen = 'MANUAL'): int
    {
        $ruc = $user->IdEmpresa;
        MesesCerrados::validar((string) $ruc, [$d['fecha'] ?? null, $id ? DB::table('gastos')->where('id', $id)->value('fecha') : null], 'El gasto');
        $base = round((float) ($d['base'] ?? 0), 2);
        $igv = round((float) ($d['igv'] ?? 0), 2);
        $ng = round((float) ($d['no_gravado'] ?? 0), 2);
        $ret = round((float) ($d['retencion'] ?? 0), 2);
        $total = round($base + $igv + $ng, 2);
        if ($total <= 0) {
            throw new \RuntimeException('El gasto debe tener un importe mayor a 0.');
        }
        if ($ret > $total) {
            throw new \RuntimeException('La retención no puede ser mayor al total.');
        }
        if (($d['moneda'] ?? 'PEN') === 'USD' && ! ((float) ($d['tipo_cambio'] ?? 0) > 0)) {
            throw new \RuntimeException('Ingresa el tipo de cambio para un gasto en dólares.');
        }

        $serie = mb_strtoupper(trim((string) ($d['serie'] ?? ''))) ?: null;
        $numero = ltrim(trim((string) ($d['numero'] ?? '')), '0') ?: null;
        $provDoc = trim((string) ($d['prov_doc'] ?? '')) ?: null;
        if ($serie && $numero && $provDoc) {
            $repetido = DB::table('gastos')->where('IdEmpresa', $ruc)->where('estado', 'Registrado')->where('prov_doc', $provDoc)
                ->where('tdocod', $d['tdocod'])->where('serie', $serie)->where('numero', $numero)->when($id, fn ($w) => $w->where('id', '!=', $id))->exists()
                || DB::table('compras_cabecera as c')->join('proveedor as p', 'p.prov_id', '=', 'c.prov_id')->where('c.IdEmpresa', $ruc)
                    ->where('c.est_compra', 'Registrado')->where('p.prov_ruc', $provDoc)->where('c.tdocod', $d['tdocod'])
                    ->where('c.com_doc_ser', $serie)->where('c.com_doc_num', $numero)->exists();
            if ($repetido) {
                throw new \RuntimeException("El comprobante {$serie}-{$numero} de {$provDoc} ya está registrado (en gastos o en compras).");
            }
        }

        $datos = [
            'fecha' => $d['fecha'], 'tdocod' => $d['tdocod'], 'serie' => $serie, 'numero' => $numero,
            'prov_doc' => $provDoc, 'prov_nombre' => mb_strtoupper(trim((string) ($d['prov_nombre'] ?? ''))) ?: null,
            'categoria_id' => $d['categoria_id'] ?? null, 'descripcion' => mb_strtoupper(trim((string) ($d['descripcion'] ?? ''))) ?: null,
            'moneda' => $d['moneda'] ?? 'PEN', 'tipo_cambio' => ($d['moneda'] ?? 'PEN') === 'USD' ? round((float) $d['tipo_cambio'], 3) : null,
            'base' => $base, 'igv' => $igv, 'no_gravado' => $ng, 'retencion' => $ret, 'total' => $total,
            'credito_fiscal' => (bool) ($d['credito_fiscal'] ?? ($d['tdocod'] === '01' && $igv > 0)),
            'forma_pago' => ($d['forma_pago'] ?? 'CONTADO') === 'CREDITO' ? 'CREDITO' : 'CONTADO',
            'id_med_pag' => $d['id_med_pag'] ?? null, 'updated_at' => now(),
            // Centro de costo: el elegido (si es de la empresa) o el del local donde se registra
            'centro_costo_id' => ! empty($d['centro_costo_id']) && CentrosCosto::valido((string) $ruc, (int) $d['centro_costo_id'])
                ? (int) $d['centro_costo_id'] : CentrosCosto::deSucursal((string) $ruc, (int) $user->id_empresa_negocio),
        ];
        if ($id) {
            DB::table('gastos')->where('id', $id)->where('IdEmpresa', $ruc)->update($datos);

            return $id;
        }

        return DB::table('gastos')->insertGetId($datos + ['IdEmpresa' => $ruc, 'id_empresa_negocio' => $user->id_empresa_negocio,
            'estado' => 'Registrado', 'origen' => $origen, 'IdUsuario' => $user->IdUsuario, 'created_at' => now()]);
    }

    // ------------------------------------------------------------------ salida de caja

    /** Tipo de movimiento de caja con que sale un gasto pagado en efectivo (GASTOS VARIOS, como en el sistema antiguo) */
    public const TIPO_CAJA = '003';

    /** Al contado, en soles y pagado en EFECTIVO: ese dinero sale de la caja */
    public static function saleDeCaja(object $g): bool
    {
        if ($g->estado !== 'Registrado' || $g->forma_pago !== 'CONTADO' || $g->moneda !== 'PEN' || $g->tdocod === '07' || ! $g->id_med_pag) {
            return false;
        }
        $medio = DB::table('medios_pagos')->where('id_med_pag', $g->id_med_pag)->value('nom_med_pag');

        return mb_strtoupper(trim((string) $medio)) === 'EFECTIVO';
    }

    /** Lo que dice la salida de caja: qué se pagó y a quién */
    private static function datosCaja(object $g): array
    {
        $categoria = DB::table('gasto_categorias')->where('id', $g->categoria_id)->value('nombre');
        $detalle = trim(($g->descripcion ?: $categoria ?: 'GASTO').($g->prov_nombre ? ' - '.$g->prov_nombre : ''));

        return ['tip_caj_id' => self::TIPO_CAJA, 'mov_com' => mb_substr($detalle, 0, 255), 'importe' => round((float) $g->total - (float) $g->retencion, 2),
            'mov_num_doc' => trim(($g->serie ? $g->serie.'-' : '').$g->numero, '-') ?: null];
    }

    /** Registra la salida de caja del gasto en ese turno y la enlaza al gasto */
    public static function sacarDeCaja(object $g, object $turno, ?string $registro = null): int
    {
        $registro ??= now()->format('Y-m-d H:i:s');
        $id = DB::table('movimientoscaja')->insertGetId(self::datosCaja($g) + [
            'estado' => 'ACTIVO', 'mov_fecha' => substr($registro, 0, 10), 'registro' => $registro,
            'IdEmpresa' => $g->IdEmpresa, 'id_empresa_negocio' => $turno->id_empresa_negocio, 'id_turno' => $turno->id_turno, 'IdUsuario' => $turno->IdUsuario,
        ]);
        DB::table('gastos')->where('id', $g->id)->update(['mov_caj_id' => $id]);

        return $id;
    }

    /**
     * Deja la caja de acuerdo con el gasto: en efectivo sale de la caja del turno abierto (así lo cuenta el arqueo); al
     * editarlo se corrige esa salida y al anularlo o pagarlo con otro medio se anula. Lo de un turno ya cerrado no se
     * toca: ese efectivo ya se contó. Los gastos traídos del sistema antiguo ya trajeron su salida de caja.
     *
     * @return string|null aviso cuando debía salir de caja y no se pudo
     */
    public static function sincronizarCaja(User $user, int $id): ?string
    {
        $g = DB::table('gastos')->where('id', $id)->first();
        if (! $g || $g->origen === 'ANTIGUO') {
            return null;
        }
        $mov = $g->mov_caj_id ? DB::table('movimientoscaja')->where('mov_caj_id', $g->mov_caj_id)->first() : null;
        if ($mov && DB::table('turnos')->where('id_turno', $mov->id_turno)->value('estado') !== 'ABIERTO') {
            return null;
        }
        if (! self::saleDeCaja($g)) {
            if ($mov && $mov->estado === 'ACTIVO') {
                DB::table('movimientoscaja')->where('mov_caj_id', $mov->mov_caj_id)->update(['estado' => 'ANULADO']);
            }

            return null;
        }
        if ($mov) {
            DB::table('movimientoscaja')->where('mov_caj_id', $mov->mov_caj_id)->update(self::datosCaja($g) + ['estado' => 'ACTIVO']);

            return null;
        }
        $turno = Turno::abiertoDe($user);
        if (! $turno) {
            return 'El gasto se guardó, pero NO salió de la caja: no tienes un turno abierto. Si lo pagaste con efectivo de la caja, abre tu turno y regístralo en Caja › Movimientos.';
        }
        if ($g->fecha < $turno->apertura->toDateString()) {
            return 'El gasto se guardó, pero NO salió de la caja: es de una fecha anterior a tu turno abierto.';
        }
        self::sacarDeCaja($g, $turno);

        return null;
    }

    /** Importe en soles con signo (las notas de crédito restan) */
    public static function soles(object $g, string $campo = 'total'): float
    {
        $v = (float) $g->$campo * ($g->moneda === 'USD' ? (float) $g->tipo_cambio : 1);

        return round($g->tdocod === '07' ? -$v : $v, 2);
    }

    // ------------------------------------------------------------------ SIRE (RCE)

    /**
     * Comprobantes de la propuesta RCE de SUNAT que aún no están en el sistema (ni en compras ni en gastos).
     * Cada fila trae base, IGV, no gravado y total ubicando las columnas por su título.
     */
    public static function pendientesSire(string $ruc, int $solicitudId): array
    {
        $s = DB::table('sire_solicitudes')->where('id', $solicitudId)->where('IdEmpresa', $ruc)->where('libro', Sire::COMPRAS)->first();
        if (! $s || ! $s->archivo || ! Storage::exists($s->archivo)) {
            throw new \RuntimeException('La propuesta RCE no está descargada. Descárgala primero en SIRE Compras.');
        }
        $propuesta = Sire::leerZip(Storage::path($s->archivo));
        $cuadre = SireCuadre::comparar(Sire::COMPRAS, $s->periodo, $propuesta, $ruc);

        $cab = array_map(fn ($h) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ',
            strtr(mb_strtolower((string) $h), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'])))), $propuesta['cabecera']);
        $cols = fn (callable $f) => array_keys(array_filter($cab, $f));
        $col = SireCuadre::columnas($propuesta['cabecera']);
        $cBase = $cols(fn ($h) => str_starts_with($h, 'bi gravad') || str_starts_with($h, 'bi '));
        $cIgv = $cols(fn ($h) => str_starts_with($h, 'igv'));
        $cNg = $cols(fn ($h) => str_contains($h, 'valor adq ng') || str_contains($h, 'no gravad') || str_contains($h, 'otros trib') || str_starts_with($h, 'isc') || str_starts_with($h, 'icbper'));
        $cMon = $cols(fn ($h) => $h === 'moneda' || str_starts_with($h, 'cod moneda') || str_starts_with($h, 'moneda'))[0] ?? null;
        $cTc = $cols(fn ($h) => str_contains($h, 'tipo de cambio') || str_contains($h, 'tipo cambio'))[0] ?? null;

        $num = fn ($v) => round((float) str_replace(',', '', (string) $v), 2);
        $filas = [];
        foreach ($propuesta['filas'] as $f) {
            $tipo = str_pad(trim($f[$col['tipo']] ?? ''), 2, '0', STR_PAD_LEFT);
            $serie = strtoupper(trim($f[$col['serie']] ?? ''));
            $numero = ltrim(trim($f[$col['numero']] ?? ''), '0') ?: '0';
            $filas["{$tipo}|{$serie}|{$numero}"] = $f;
        }

        $res = [];
        foreach ($cuadre['soloSunat'] as $c) {
            $f = $filas["{$c['tipo']}|{$c['serie']}|{$c['numero']}"] ?? null;
            if (! $f) {
                continue;
            }
            $sum = fn (array $idx) => round(array_sum(array_map(fn ($i) => abs($num($f[$i] ?? 0)), $idx)), 2);
            $base = $sum($cBase);
            $igv = $sum($cIgv);
            $total = abs((float) $c['total']);
            $ng = max(0, round($total - $base - $igv, 2));
            $fecha = \DateTime::createFromFormat('d/m/Y', $c['fecha']) ?: \DateTime::createFromFormat('Y-m-d', $c['fecha']);
            $res[] = [
                'clave' => "{$c['tipo']}|{$c['serie']}|{$c['numero']}|{$c['doc']}", 'tipo' => $c['tipo'], 'serie' => $c['serie'], 'numero' => $c['numero'],
                'fecha' => $fecha ? $fecha->format('Y-m-d') : null, 'prov_doc' => $c['doc'], 'prov_nombre' => $c['nombre'],
                'base' => $base, 'igv' => $igv, 'no_gravado' => $ng, 'total' => $total,
                'moneda' => $cMon !== null && strtoupper(trim($f[$cMon] ?? '')) === 'USD' ? 'USD' : 'PEN',
                'tipo_cambio' => $cTc !== null ? $num($f[$cTc] ?? 0) : null,
            ];
        }

        return ['periodo' => $s->periodo, 'filas' => $res, 'cuadre' => ['sunat' => $cuadre['cantSunat'], 'sistema' => $cuadre['cantSistema']]];
    }
}
