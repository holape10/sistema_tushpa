<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle de las ventas del SIRE: guarda lo que se vendió en cada comprobante emitido por la empresa (de su XML).
 * Como los emitió la empresa, cada uno se reconoce por su tipo, serie y número.
 */
class VentasDetalle
{
    /**
     * Guarda los comprobantes leídos de los XML. Los que no emitió esta empresa (una compra, otra empresa) no se guardan;
     * uno que ya estaba no se toca.
     *
     * @param  array<int, array>  $comprobantes  de CompraXml::leer
     * @return array{nuevos: int, repetidos: int, ajenos: int}
     */
    public static function guardar(string $idEmpresa, array $comprobantes): array
    {
        $r = ['nuevos' => 0, 'repetidos' => 0, 'ajenos' => 0];
        foreach ($comprobantes as $c) {
            if ($c['ruc_emisor'] !== $idEmpresa) {
                $r['ajenos']++;

                continue;
            }
            $llave = ['IdEmpresa' => $idEmpresa, 'tipo' => $c['tipo'], 'serie' => $c['serie'], 'numero' => $c['numero']];
            if (DB::table('ventas_xml')->where($llave)->exists()) {
                $r['repetidos']++;

                continue;
            }
            DB::transaction(function () use ($llave, $c) {
                $id = DB::table('ventas_xml')->insertGetId($llave + [
                    'doc_cliente' => $c['ruc_comprador'] ? mb_substr($c['ruc_comprador'], 0, 15) : null,
                    'razon_cliente' => ! empty($c['razon_comprador']) ? mb_substr($c['razon_comprador'], 0, 200) : null,
                    'fecha' => $c['fecha'], 'moneda' => mb_substr($c['moneda'], 0, 3), 'igv' => $c['igv'], 'total' => $c['total'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($c['lineas'] as $l) {
                    DB::table('ventas_xml_lineas')->insert(['venta_xml_id' => $id, 'codigo' => $l['codigo'] ? mb_substr($l['codigo'], 0, 60) : null]
                        + array_intersect_key($l, array_flip(['orden', 'descripcion', 'unidad', 'cantidad', 'precio', 'base', 'igv'])));
                }
            });
            $r['nuevos']++;
        }

        return $r;
    }

    /**
     * Las líneas de los XML de esas ventas, por su "tipo|serie|número" (null en la lista = falta su XML).
     *
     * @param  array<int, string>  $llaves  "01|F001|8354"
     * @return array<string, array{xml_id: int, lineas: array<int, object>}>
     */
    public static function lineas(string $idEmpresa, array $llaves): array
    {
        if (! $llaves || ! Schema::hasTable('ventas_xml')) {
            return [];
        }
        $xmls = DB::table('ventas_xml')->where('IdEmpresa', $idEmpresa)
            ->whereIn(DB::raw("CONCAT(tipo, '|', serie, '|', numero)"), array_values(array_unique($llaves)))->get();
        $lineas = DB::table('ventas_xml_lineas')->whereIn('venta_xml_id', $xmls->pluck('id')->all() ?: [0])->orderBy('orden')->get()->groupBy('venta_xml_id');
        $res = [];
        foreach ($xmls as $x) {
            $res["{$x->tipo}|{$x->serie}|{$x->numero}"] = ['xml_id' => (int) $x->id, 'lineas' => ($lineas[$x->id] ?? collect())->values()->all()];
        }

        return $res;
    }
}
