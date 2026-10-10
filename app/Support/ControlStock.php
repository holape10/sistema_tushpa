<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Illuminate\Support\Facades\DB;

/**
 * Venta con o sin stock, según la sucursal (empresa_negocios.control_stock):
 *  - libre:     vende aunque no haya stock (el stock puede quedar en negativo).
 *  - productos: no vende productos (ni los de un combo) sin stock.
 *  - todo:      además no vende platos si faltan los insumos de su receta (o de su entrada).
 *
 * Los platos (Preparados) no tienen stock propio: lo que se controla es el stock de sus insumos.
 * Lo enviado a cocina y aún no cobrado queda RESERVADO, así dos mozos no pueden mandar el último plato a la vez.
 */
class ControlStock
{
    public const NIVELES = [
        'libre' => ['Vender sin stock', 'Se vende aunque no haya stock (puede quedar en negativo). Útil mientras ordenas tu almacén.'],
        'productos' => ['Solo con stock (productos)', 'No deja vender productos sin stock. Los platos se venden aunque falten insumos.'],
        'todo' => ['Solo con stock (productos e insumos)', 'Tampoco deja vender un plato si faltan los insumos de su receta. El más ordenado.'],
    ];

    public static function nivel(int $sucursal): string
    {
        $nivel = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('control_stock');

        return array_key_exists($nivel, self::NIVELES) ? $nivel : 'libre';
    }

    /**
     * Banderas para el kardex de una venta.
     *
     * @return array{exigir_stock?: bool, exigir_insumos?: bool}
     */
    public static function banderas(int $sucursal): array
    {
        return match (self::nivel($sucursal)) {
            'productos' => ['exigir_stock' => true],
            'todo' => ['exigir_stock' => true, 'exigir_insumos' => true],
            default => [],
        };
    }

    public static function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Qué necesita del almacén vender esta cantidad, según el nivel: [IdProducto => cantidad].
     *
     * @param  array<int, float>  $opciones  entradas elegidas: IdProducto => cantidad por plato
     * @return array<int, float>
     */
    public static function necesidades(Producto $producto, float $cantidad, string $nivel, float $factor = 1, array $opciones = []): array
    {
        $necesita = [];
        $sumar = function (Producto $p, float $cant) use (&$necesita, &$sumar, $nivel) {
            $tipo = (int) $p->promocion;
            if (in_array($tipo, [0, 4], true)) {
                $necesita[$p->IdProducto] = ($necesita[$p->IdProducto] ?? 0) + $cant;
            } elseif (in_array($tipo, [2, Producto::OPCION], true) && $nivel === 'todo') {
                foreach (Recetas::de($p->IdProducto) as $r) {
                    $necesita[$r->IdInsumo] = ($necesita[$r->IdInsumo] ?? 0) + $cant * $r->cantidad_base;
                }
            } elseif ($tipo === 6) {
                foreach ($p->itemsCombo()->with('itemProducto')->get() as $c) {
                    if ($c->itemProducto) {
                        $sumar($c->itemProducto, $cant * (float) $c->prod_comb_cant);
                    }
                }
            }
        };
        $sumar($producto, $cantidad * ($factor ?: 1));
        foreach ($opciones as $id => $porPlato) {
            if ($op = Producto::find($id)) {
                $sumar($op, $cantidad * (float) $porPlato);
            }
        }

        return $necesita;
    }

    /**
     * Lo reservado por las comandas enviadas a cocina y aún no cobradas: [IdProducto => cantidad].
     * Con $bloquear se lee lo último guardado (lectura con bloqueo), para usarlo dentro del envío.
     *
     * @param  array<int>  $ids  solo interesan estos productos o insumos
     * @return array<int, float>
     */
    public static function reservado(int $sucursal, string $nivel, array $ids, bool $bloquear = false): array
    {
        $lineas = DB::table('pedidos_detalle as d')->join('pedidos as p', 'p.ped_id', '=', 'd.ped_id')
            ->where('p.id_empresa_negocio', $sucursal)->where('p.ped_est', 'Aperturado')->where('d.estadoitem', '!=', 'Eliminado')
            ->whereRaw('d.ped_det_can - IFNULL(d.item_facturado, 0) > 0')
            ->when($bloquear, fn ($q) => $q->sharedLock())
            ->get(['d.IdProducto', 'd.id_presentacion', 'd.opciones', DB::raw('d.ped_det_can - IFNULL(d.item_facturado, 0) as pendiente')]);
        $productos = Producto::whereIn('IdProducto', $lineas->pluck('IdProducto')->filter()->unique())->get()->keyBy('IdProducto');
        $factores = ProductoPresentacion::whereIn('id_presentacion', $lineas->pluck('id_presentacion')->filter()->unique())->pluck('factor', 'id_presentacion');

        $reservado = [];
        $buscados = array_flip($ids);
        foreach ($lineas as $l) {
            if (! $p = $productos[$l->IdProducto] ?? null) {
                continue;
            }
            $factor = (float) ($factores[$l->id_presentacion] ?? 1);
            foreach (self::necesidades($p, (float) $l->pendiente, $nivel, $factor, OpcionesPlato::kardexDe($l->opciones)) as $id => $cant) {
                if (isset($buscados[$id])) {
                    $reservado[$id] = ($reservado[$id] ?? 0) + $cant;
                }
            }
        }

        return $reservado;
    }

    /**
     * Antes de agregar a la comanda: qué falta (null si alcanza o si la sucursal vende sin stock).
     * Descuenta lo reservado por otras comandas ya enviadas.
     *
     * @param  array<int, float>  $opciones
     */
    public static function faltante(Producto $producto, float $cantidad, int $sucursal, float $factor = 1, array $opciones = []): ?string
    {
        $nivel = self::nivel($sucursal);
        if ($nivel === 'libre') {
            return null;
        }

        return self::comparar($sucursal, $nivel, self::necesidades($producto, $cantidad, $nivel, $factor, $opciones), false);
    }

    /**
     * Al enviar la comanda (dentro de su transacción): bloquea el stock de lo que se manda y verifica que alcance,
     * contando lo que otros ya reservaron. Si otro mozo está enviando lo mismo, este espera a que termine.
     *
     * @param  array<int, array{producto: Producto, cantidad: float, factor?: float, opciones?: array<int, float>}>  $envios
     *
     * @throws \RuntimeException si ya no alcanza
     */
    public static function asegurar(int $sucursal, array $envios): void
    {
        $nivel = self::nivel($sucursal);
        if ($nivel === 'libre' || ! $envios) {
            return;
        }
        $necesita = [];
        foreach ($envios as $e) {
            foreach (self::necesidades($e['producto'], $e['cantidad'], $nivel, $e['factor'] ?? 1, $e['opciones'] ?? []) as $id => $cant) {
                $necesita[$id] = ($necesita[$id] ?? 0) + $cant;
            }
        }
        if ($error = self::comparar($sucursal, $nivel, $necesita, true)) {
            throw new \RuntimeException($error.' Otro pedido ya lo reservó o se acabó: cambia el pedido.');
        }
    }

    /** Stock − reservado contra lo que se necesita */
    private static function comparar(int $sucursal, string $nivel, array $necesita, bool $bloquear): ?string
    {
        $almacen = Kardex::almacenPredeterminado($sucursal);
        if (! $almacen || ! $necesita) {
            return null;
        }
        $ids = array_keys($necesita);
        sort($ids); // mismo orden de bloqueo en todos los envíos: evita bloqueos cruzados
        $stock = DB::table('producto_stock')->where('id_almacen', $almacen->id_almacen)->whereIn('IdProducto', $ids)
            ->when($bloquear, fn ($q) => $q->orderBy('IdProducto')->lockForUpdate())->pluck('stock', 'IdProducto');
        $reservado = self::reservado($sucursal, $nivel, $ids, $bloquear);
        $nombres = DB::table('productos')->whereIn('IdProducto', $ids)->pluck('pronom', 'IdProducto');

        foreach ($necesita as $id => $cant) {
            $libre = (float) ($stock[$id] ?? 0) - (float) ($reservado[$id] ?? 0);
            if ($libre + 0.0001 < $cant) {
                return 'No hay stock suficiente de '.$nombres[$id].': quedan '.self::numero(max(0, $libre))
                    .(($reservado[$id] ?? 0) > 0 ? ' libres ('.self::numero($reservado[$id]).' ya están en otras comandas)' : '')
                    .' y se necesitan '.self::numero($cant).'.';
            }
        }

        return null;
    }
}
