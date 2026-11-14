<?php

namespace App\Support;

use App\Support\Contabilidad\SireManuales;
use App\Support\Sunat\Sire;
use App\Support\Sunat\SireCuadre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Detalle de las compras del SIRE: guarda lo que se compró en cada factura (de su XML), propone la cuenta contable de
 * cada línea y aprende de lo que el contador elige (este proveedor + esta descripción → esta cuenta).
 */
class ComprasDetalle
{
    /** La descripción sin números ni signos: "INTERNET JULIO 2026 PLAN 100MB" y "... AGOSTO ..." se reconocen igual */
    public static function clave(string $descripcion): string
    {
        $d = mb_strtoupper(trim($descripcion));
        $d = strtr($d, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
        $d = preg_replace('/\b(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SETIEMBRE|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\b/', ' ', $d);
        $d = preg_replace('/[^A-Z ]+/', ' ', $d);

        return mb_substr(trim(preg_replace('/\s+/', ' ', $d)), 0, 120) ?: 'SIN DESCRIPCION';
    }

    /**
     * La cuenta que ya se usó para esto: el mismo proveedor y descripción, la misma descripción con otro proveedor, o la
     * única cuenta que se le pone a ese proveedor.
     */
    public static function sugerir(string $idEmpresa, string $rucEmisor, string $descripcion): ?string
    {
        $reglas = DB::table('compras_cuentas_reglas')->where('IdEmpresa', $idEmpresa);
        $clave = self::clave($descripcion);

        return (clone $reglas)->where('ruc_emisor', $rucEmisor)->where('clave', $clave)->value('cuenta')
            ?? (clone $reglas)->where('clave', $clave)->orderByDesc('updated_at')->value('cuenta')
            ?? (($delProveedor = (clone $reglas)->where('ruc_emisor', $rucEmisor)->distinct()->pluck('cuenta'))->count() === 1 ? $delProveedor->first() : null);
    }

    /**
     * Guarda los comprobantes leídos de los XML. Los que no son compras de esta empresa (otro comprador) no se guardan;
     * uno que ya estaba no se toca (conserva las cuentas que se le pusieron).
     *
     * @param  array<int, array>  $comprobantes  de CompraXml::leer
     * @return array{nuevos: int, repetidos: int, ajenos: int}
     */
    public static function guardar(string $idEmpresa, array $comprobantes): array
    {
        $r = ['nuevos' => 0, 'repetidos' => 0, 'ajenos' => 0];
        foreach ($comprobantes as $c) {
            if ($c['ruc_comprador'] && $c['ruc_comprador'] !== $idEmpresa) {
                $r['ajenos']++;

                continue;
            }
            $llave = ['IdEmpresa' => $idEmpresa, 'ruc_emisor' => mb_substr($c['ruc_emisor'], 0, 11), 'tipo' => $c['tipo'], 'serie' => $c['serie'], 'numero' => $c['numero']];
            if (DB::table('compras_xml')->where($llave)->exists()) {
                $r['repetidos']++;

                continue;
            }
            DB::transaction(function () use ($llave, $c, $idEmpresa) {
                $id = DB::table('compras_xml')->insertGetId($llave + [
                    'razon_emisor' => $c['razon_emisor'] ? mb_substr($c['razon_emisor'], 0, 200) : null, 'ruc_comprador' => $c['ruc_comprador'],
                    'fecha' => $c['fecha'], 'moneda' => mb_substr($c['moneda'], 0, 3), 'igv' => $c['igv'], 'total' => $c['total'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($c['lineas'] as $l) {
                    DB::table('compras_xml_lineas')->insert(['compra_xml_id' => $id, 'cuenta' => self::sugerir($idEmpresa, $llave['ruc_emisor'], $l['descripcion']),
                        'codigo' => $l['codigo'] ? mb_substr($l['codigo'], 0, 60) : null] + array_intersect_key($l, array_flip(['orden', 'descripcion', 'unidad', 'cantidad', 'precio', 'base', 'igv'])));
                }
            });
            $r['nuevos']++;
        }

        return $r;
    }

    /**
     * Pone la cuenta a unas líneas, la aprende y se la pone también a las líneas iguales (mismo proveedor y descripción)
     * que aún no tienen cuenta.
     *
     * @param  array<int, int>  $lineas
     * @return int líneas con cuenta nueva
     */
    public static function asignar(string $idEmpresa, array $lineas, string $cuenta): int
    {
        $filas = DB::table('compras_xml_lineas as l')->join('compras_xml as c', 'c.id', '=', 'l.compra_xml_id')
            ->where('c.IdEmpresa', $idEmpresa)->whereIn('l.id', $lineas)->get(['l.id', 'l.descripcion', 'c.ruc_emisor']);
        $cambiadas = 0;
        foreach ($filas as $f) {
            $clave = self::clave($f->descripcion);
            DB::table('compras_cuentas_reglas')->updateOrInsert(['IdEmpresa' => $idEmpresa, 'ruc_emisor' => $f->ruc_emisor, 'clave' => $clave],
                ['cuenta' => $cuenta, 'updated_at' => now(), 'created_at' => now()]);
            $cambiadas += DB::table('compras_xml_lineas')->where('id', $f->id)->update(['cuenta' => $cuenta]);
            // Las iguales sin cuenta aprenden al momento
            $iguales = DB::table('compras_xml_lineas as l')->join('compras_xml as c', 'c.id', '=', 'l.compra_xml_id')
                ->where('c.IdEmpresa', $idEmpresa)->where('c.ruc_emisor', $f->ruc_emisor)->whereNull('l.cuenta')
                ->get(['l.id', 'l.descripcion'])->filter(fn ($o) => self::clave($o->descripcion) === $clave)->pluck('id');
            if ($iguales->isNotEmpty()) {
                $cambiadas += DB::table('compras_xml_lineas')->whereIn('id', $iguales)->update(['cuenta' => $cuenta]);
            }
        }

        return $cambiadas;
    }

    /**
     * Los comprobantes de compras del periodo, cada uno con su XML y líneas si se subió. Con propuesta del SIRE se
     * listan los de SUNAT (y aparte los XML que SUNAT no tiene en ese mes); sin propuesta, los XML por su fecha.
     *
     * @param  array<string, array>|null  $propuesta  de SireCuadre::comprasPropuesta
     * @return array{comprobantes: array<int, array>, sinPropuesta: array<int, array>, conDetalle: int, lineasSinCuenta: int}
     */
    /** La propuesta del SIRE de compras ya descargada del periodo (null si aún no se descargó) */
    public static function propuestaSire(string $idEmpresa, string $periodo): ?array
    {
        $s = DB::table('sire_solicitudes')->where('IdEmpresa', $idEmpresa)->where('libro', Sire::COMPRAS)->where('periodo', $periodo)
            ->whereNotNull('archivo')->orderByDesc('id')->first();
        if (! $s || ! Storage::exists($s->archivo)) {
            return null;
        }
        try {
            return SireCuadre::comprasPropuesta(Sire::leerZip(Storage::path($s->archivo)));
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** Los comprobantes de compra del periodo (de la propuesta del SIRE, si ya está) con su XML si ya se trajo */
    public static function conPropuesta(string $idEmpresa, string $periodo): array
    {
        $propuesta = self::propuestaSire($idEmpresa, $periodo);
        // Los agregados a mano a este mes van como si estuvieran en la propuesta (también se les trae el XML)
        if ($propuesta !== null) {
            $propuesta += array_diff_key(SireManuales::comprasDelPeriodo($idEmpresa, $periodo), $propuesta);
        }

        return ['propuesta' => $propuesta !== null] + self::delPeriodo($idEmpresa, $periodo, $propuesta);
    }

    public static function delPeriodo(string $idEmpresa, string $periodo, ?array $propuesta): array
    {
        $ini = substr($periodo, 0, 4).'-'.substr($periodo, 4, 2).'-01';
        $fin = date('Y-m-t', strtotime($ini));
        $xmls = DB::table('compras_xml')->where('IdEmpresa', $idEmpresa)
            ->when($propuesta, fn ($q) => $q->where(function ($q) use ($propuesta, $ini, $fin) {
                $q->whereBetween('fecha', [$ini, $fin])
                    ->orWhereIn(DB::raw("CONCAT(ruc_emisor, '|', tipo, '|', serie, '|', numero)"), array_keys($propuesta) ?: ['-']);
            }), fn ($q) => $q->whereBetween('fecha', [$ini, $fin]))
            ->get()->keyBy(fn ($x) => "{$x->ruc_emisor}|{$x->tipo}|{$x->serie}|{$x->numero}");
        $lineas = DB::table('compras_xml_lineas')->whereIn('compra_xml_id', $xmls->pluck('id')->all() ?: [0])->orderBy('orden')->get()->groupBy('compra_xml_id');

        $armar = fn (array $c, ?object $x) => $c + ['xml' => $x, 'lineas' => $x ? ($lineas[$x->id] ?? collect())->values()->all() : []];
        $comprobantes = [];
        foreach ($propuesta ?? [] as $clave => $c) {
            $comprobantes[] = $armar($c, $xmls[$clave] ?? null);
        }
        $sinPropuesta = [];
        foreach ($xmls as $clave => $x) {
            if (! isset($propuesta[$clave])) {
                $c = ['ruc' => $x->ruc_emisor, 'nombre' => (string) $x->razon_emisor, 'tipo' => $x->tipo, 'serie' => $x->serie, 'numero' => $x->numero,
                    'fecha' => $x->fecha, 'vence' => $x->fecha, 'igv' => (float) $x->igv, 'total' => (float) $x->total, 'moneda' => $x->moneda, 'tc' => null, 'ref' => null];
                $propuesta ? $sinPropuesta[] = $armar($c, $x) : $comprobantes[] = $armar($c, $x);
            }
        }
        $todas = collect($comprobantes)->pluck('lineas')->flatten(1);

        return [
            'comprobantes' => $comprobantes, 'sinPropuesta' => $sinPropuesta,
            'conDetalle' => collect($comprobantes)->filter(fn ($c) => $c['xml'])->count(),
            'lineasSinCuenta' => $todas->filter(fn ($l) => ! $l->cuenta)->count(),
        ];
    }

    /**
     * Las líneas del XML sin cuenta propia toman la Cta. cargo de su comprobante (la que se puso en el .dbf o la que
     * aprendió de ese proveedor) y su centro de costo; si el comprobante tampoco tiene, siguen sin cuenta.
     *
     * @param  array{comprobantes: array<int, array>, lineasSinCuenta: int}  $datos  de delPeriodo
     * @param  array<int, array>  $dbf  de SireDbf::delPeriodo (con clave, ctacargo y ccosto)
     */
    public static function heredarDelComprobante(array $datos, array $dbf, string $ctaDefecto): array
    {
        $porClave = collect($dbf)->keyBy('clave');
        foreach ($datos['comprobantes'] as $i => $c) {
            $d = $porClave["{$c['ruc']}|{$c['tipo']}|{$c['serie']}|{$c['numero']}"] ?? null;
            if (! $d) {
                continue;
            }
            $cuenta = ($d['ctacargo'] ?? null) !== $ctaDefecto ? ($d['ctacargo'] ?? null) : null;
            $datos['comprobantes'][$i]['lineas'] = array_map(function ($l) use ($cuenta, $d) {
                $l = clone $l;
                $l->cuenta = $l->cuenta ?: $cuenta;
                $l->centro_costo = ($l->centro_costo ?? null) ?: ($d['ccosto'] ?? null) ?: null;

                return $l;
            }, $c['lineas']);
        }
        $datos['lineasSinCuenta'] = collect($datos['comprobantes'])->pluck('lineas')->flatten(1)->filter(fn ($l) => ! $l->cuenta)->count();

        return $datos;
    }
}
