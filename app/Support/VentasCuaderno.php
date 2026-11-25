<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pasar al sistema las ventas anotadas en un cuaderno (una venta por hoja), con la fecha del cuaderno, de una sola vez.
 * Cada línea busca el producto por su nombre exacto en la sucursal. Sin $grabar todo se registra y se deshace al final:
 * sirve para ver antes si falta algún producto, stock, turno abierto o serie.
 */
class VentasCuaderno
{
    /**
     * @param  array{fecha: string, hojas: list<array{nombre: string, total: float, lineas: list<array{0: string, 1: float, 2: float}>}>}  $cuaderno
     * @return array{ok: bool, mensajes: list<string>, ventas: list<int>}
     */
    public static function cargar(User $user, array $cuaderno, string $tdocod, bool $grabar): array
    {
        Auth::setUser($user);
        $sucursal = (int) $user->id_empresa_negocio;
        $mensajes = [];

        // Productos por nombre (sin espacios de más ni diferencia de mayúsculas)
        $clave = fn (string $n) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($n)));
        $productos = DB::table('productos')->where('id_empresa_negocio', $sucursal)->where('proest', 'Activo')
            ->get(['IdProducto', 'pronom'])->keyBy(fn ($p) => $clave($p->pronom));
        $faltan = collect($cuaderno['hojas'])->flatMap(fn ($h) => array_column($h['lineas'], 0))
            ->unique()->reject(fn ($n) => isset($productos[$clave($n)]))->values();
        if ($faltan->isNotEmpty()) {
            return ['ok' => false, 'ventas' => [], 'mensajes' => array_merge(
                ['No encontré estos productos (créalos o corrige el nombre para que sea igual):'], $faltan->map(fn ($n) => '  - '.$n)->all())];
        }

        $ventas = [];
        DB::beginTransaction();
        try {
            foreach ($cuaderno['hojas'] as $hoja) {
                $items = array_map(fn ($l) => ['id' => (int) $productos[$clave($l[0])]->IdProducto, 'cantidad' => (float) $l[1], 'precio' => (float) $l[2]], $hoja['lineas']);
                $total = round(array_sum(array_map(fn ($i) => $i['cantidad'] * $i['precio'], $items)), 2);
                if (abs($total - (float) $hoja['total']) > 0.001) {
                    throw new \RuntimeException("{$hoja['nombre']}: las líneas suman S/ {$total} y el cuaderno dice S/ {$hoja['total']}.");
                }
                $id = self::registrarHoja($user, $cuaderno['fecha'], $hoja['nombre'], $items, $tdocod);
                $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();
                $ventas[] = $id;
                $mensajes[] = sprintf('%s: %s-%s · %d líneas · S/ %.2f · fecha %s', $hoja['nombre'], $cab->serdoc, $cab->numdoc, count($items), $total,
                    substr((string) $cab->ccafem, 0, 10));
            }
            $grabar ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'ventas' => [], 'mensajes' => array_merge($mensajes, ['Error: '.$e->getMessage(), 'No se grabó nada.'])];
        }

        return ['ok' => true, 'ventas' => $grabar ? $ventas : [], 'mensajes' => $mensajes];
    }

    /**
     * Registra una hoja del cuaderno como una venta al contado en efectivo a CLIENTES VARIOS, con la fecha del cuaderno.
     *
     * @param  list<array{id: int, cantidad: float, precio: float}>  $items
     * @return int IdCpe_cabecera
     */
    public static function registrarHoja(User $user, string $fecha, string $nombre, array $items, string $tdocod): int
    {
        $sucursal = (int) $user->id_empresa_negocio;
        $contado = (int) DB::table('credito_dias')->where('id_empresa_negocio', $sucursal)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
        $efectivo = (int) (DB::table('medios_pagos')->where('id_empresa_negocio', $sucursal)->where('nom_med_pag', 'like', '%EFECTIVO%')->value('id_med_pag')
            ?: DB::table('medios_pagos')->where('id_empresa_negocio', $sucursal)->orderBy('id_med_pag')->value('id_med_pag'));
        $total = round(array_sum(array_map(fn ($i) => $i['cantidad'] * $i['precio'], $items)), 2);

        return VentaDirecta::registrar($user, [
            'tdocod' => $tdocod, 'estadopago' => $contado, 'fecEmi' => $fecha,
            'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'CLIENTES VARIOS',
            'observaciones' => mb_substr('Cuaderno '.$nombre, 0, 100),
            'items' => $items, 'id_med_pag' => [$efectivo], 'mon_med_pag' => [$total], 'paga' => $total,
        ], 'PV');
    }
}
