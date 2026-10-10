<?php

namespace App\Support;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el mozo elige al pedir ciertos platos en la comanda:
 *  - "Lleva entrada": elige 1 entrada (SOPA, TEQUEÑOS, ENSALADA...) de las creadas en Restaurante > Entradas.
 *    La entrada sale del almacén con su receta.
 *  - "Arma tu trío" (trio_cantidad = 3): elige 3 platos distintos de los marcados "puede ir en un trío".
 *    El trío es libre: no maneja stock, solo le dice a la cocina qué preparar.
 * Lo elegido va a la cocina y a la precuenta, pero no al comprobante (ahí sale solo el plato).
 */
class OpcionesPlato
{
    /**
     * Qué se elige para este plato.
     *
     * @return array<int, array{grupo_id: string, nombre: string, cantidad: int, obligatorio: bool, repetir: bool, descuenta: bool, items: array<int, array{id: int, nombre: string, precio_extra: float}>}>
     */
    public static function de(int $idProducto): array
    {
        $p = DB::table('productos')->where('IdProducto', $idProducto)->first(['IdProducto', 'id_empresa_negocio', 'lleva_entrada', 'trio_cantidad']);
        if (! $p) {
            return [];
        }
        $lista = fn ($q) => $q->where('id_empresa_negocio', $p->id_empresa_negocio)->where('proest', 'Activo')
            ->where('IdProducto', '!=', $p->IdProducto)->orderBy('pronom')->get(['IdProducto', 'pronom'])
            ->map(fn ($i) => ['id' => (int) $i->IdProducto, 'nombre' => $i->pronom, 'precio_extra' => 0.0])->all();

        $grupos = [];
        if ($p->lleva_entrada) {
            $grupos[] = ['grupo_id' => 'entrada', 'nombre' => 'Entrada', 'cantidad' => 1, 'obligatorio' => true, 'repetir' => false, 'descuenta' => true,
                'items' => $lista(DB::table('productos')->where('promocion', Producto::OPCION))];
        }
        if ((int) $p->trio_cantidad > 0) {
            $grupos[] = ['grupo_id' => 'trio', 'nombre' => 'Arma tu trío', 'cantidad' => (int) $p->trio_cantidad, 'obligatorio' => true, 'repetir' => false, 'descuenta' => false,
                'items' => $lista(DB::table('productos')->where('en_trio', 1)->where('promocion', '!=', Producto::OPCION))];
        }

        return $grupos;
    }

    /**
     * Cuáles de estos productos piden elegir algo en la comanda.
     *
     * @param  iterable<int>  $ids
     * @return array<int, true>
     */
    public static function conOpciones(iterable $ids): array
    {
        return DB::table('productos')->whereIn('IdProducto', collect($ids)->all())
            ->where(fn ($q) => $q->where('lleva_entrada', 1)->orWhere('trio_cantidad', '>', 0))
            ->pluck('IdProducto')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /**
     * Valida lo que eligió el mozo y lo deja listo para el pedido.
     *
     * @param  array<string, array<int, int>>  $seleccion  'entrada' => [IdProducto], 'trio' => [IdProducto, IdProducto, IdProducto]
     * @return array{grupos: array<int, array{nombre: string, items: array<int, array{id: int, nombre: string, cantidad: int}>}>, extra: float, texto: string, kardex: array<int, float>, firma: string}
     *
     * @throws \RuntimeException con un mensaje para el mozo
     */
    public static function elegir(int $idProducto, array $seleccion): array
    {
        $resultado = ['grupos' => [], 'extra' => 0.0, 'kardex' => []];
        foreach (self::de($idProducto) as $g) {
            $elegidos = array_map('intval', (array) ($seleccion[$g['grupo_id']] ?? []));
            $permitidos = collect($g['items'])->keyBy('id');
            $n = count($elegidos);
            if (! $g['items']) {
                throw new \RuntimeException($g['grupo_id'] === 'entrada'
                    ? 'Aún no hay entradas creadas. Créalas en Restaurante > Entradas.'
                    : 'No hay platos marcados "puede ir en un trío".');
            }
            if ($n !== $g['cantidad']) {
                throw new \RuntimeException($g['nombre'].': elige '.$g['cantidad'].($g['cantidad'] === 1 ? '.' : ' platos distintos.'));
            }
            if (! $g['repetir'] && count(array_unique($elegidos)) !== $n) {
                throw new \RuntimeException($g['nombre'].': elige platos distintos.');
            }
            $items = [];
            foreach (array_count_values($elegidos) as $id => $veces) {
                $item = $permitidos[$id] ?? null;
                if (! $item) {
                    throw new \RuntimeException($g['nombre'].': una opción ya no está disponible. Vuelve a elegir.');
                }
                $items[] = ['id' => $id, 'nombre' => $item['nombre'], 'cantidad' => $veces];
                if ($g['descuenta']) {
                    $resultado['kardex'][$id] = ($resultado['kardex'][$id] ?? 0) + $veces;
                }
            }
            $resultado['grupos'][] = ['nombre' => $g['nombre'], 'items' => $items];
        }
        $resultado['texto'] = self::texto($resultado['grupos']);
        $resultado['firma'] = $resultado['grupos'] ? substr(md5(json_encode($resultado['grupos'])), 0, 8) : '';

        return $resultado;
    }

    /**
     * "ENTRADA: SOPA · ARMA TU TRÍO: CEVICHE, CAUSA, CHICHARRÓN" (para la cocina, el carrito y la precuenta).
     *
     * @param  array<int, array{nombre: string, items: array<int, array{nombre: string, cantidad: int}>}>|string|null  $grupos
     */
    public static function texto(array|string|null $grupos): string
    {
        if (is_string($grupos)) {
            $grupos = json_decode($grupos, true)['grupos'] ?? [];
        }

        return collect($grupos ?? [])->map(fn ($g) => mb_strtoupper($g['nombre']).': '
            .collect($g['items'])->map(fn ($i) => $i['nombre'].($i['cantidad'] > 1 ? ' x'.$i['cantidad'] : ''))->implode(', '))
            ->implode(' · ');
    }

    /**
     * Lo elegido en una línea del pedido que sale del almacén (las entradas) → [IdProducto => cantidad por plato].
     *
     * @return array<int, float>
     */
    public static function kardexDe(?string $json): array
    {
        return array_map('floatval', (array) (json_decode((string) $json, true)['kardex'] ?? []));
    }
}
