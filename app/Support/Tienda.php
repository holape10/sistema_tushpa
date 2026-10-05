<?php
namespace App\Support;

use App\Models\{Almacen, EmpresaNegocio, User};
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Tienda virtual de la empresa: catálogo público con precio vigente (precio dinámico), stock e imagen;
 * acceso del cliente con su DNI/RUC y pedidos que llegan como proforma (origen WEB).
 */
class Tienda
{
    /** Sucursal que publica la tienda (la primera con la tienda activada) */
    public static function sucursal(): ?EmpresaNegocio
    {
        return EmpresaNegocio::where('tienda_activa', 1)->where('estado', 'Activo')->orderBy('id_empresa_negocio')->first();
    }

    /** ¿El plan contratado incluye tienda virtual? (sin plan = sí) */
    public static function permitidaPorPlan(): bool
    {
        $plan = Tenancy::plan();
        return !$plan || $plan->tienda_virtual;
    }

    /** Cliente con sesión en la tienda (fila de la tabla cliente) */
    public static function cliente(EmpresaNegocio $negocio): ?object
    {
        $id = session('tienda_cliente');
        return $id ? DB::table('cliente')->where('clicod', $id)->where('rucemp', $negocio->IdEmpresa)->first() : null;
    }

    public static function catalogo(EmpresaNegocio $negocio): array
    {
        $almacen = Almacen::where('id_empresa_negocio', $negocio->id_empresa_negocio)->where('predeterminado', 1)->value('id_almacen');
        $filas = DB::table('productos as p')
            ->leftJoin('producto_stock as s', fn($j) => $j->on('s.IdProducto', '=', 'p.IdProducto')->where('s.id_almacen', $almacen))
            ->where('p.id_empresa_negocio', $negocio->id_empresa_negocio)->where('p.proest', 'Activo')->where('p.promocion', '!=', 4)
            ->orderBy('p.pronom')
            ->get(['p.IdProducto', 'p.pronom', 'p.propun', 'p.cat_id', 'p.promocion', 'p.umecod', 'p.imagenproducto', 's.stock']);
        $precios = Precios::vigentes($filas);

        $productos = $filas->map(fn($p) => [
            'id' => $p->IdProducto, 'nombre' => $p->pronom, 'precio' => $precios[$p->IdProducto], 'normal' => (float) $p->propun,
            'cat' => $p->cat_id, 'img' => $p->imagenproducto ? asset($p->imagenproducto) : null,
            // Preparados y combos no llevan stock propio: siempre disponibles
            'stock' => (int) $p->promocion === 0 ? max(0, (float) ($p->stock ?? 0)) : null,
        ])->filter(fn($p) => $p['precio'] > 0)
          ->filter(fn($p) => $negocio->tienda_mostrar_agotados || $p['stock'] === null || $p['stock'] > 0)
          ->values()->all();

        $usadas = array_unique(array_column($productos, 'cat'));
        $categorias = DB::table('categorias')->where('id_empresa_negocio', $negocio->id_empresa_negocio)->where('visible', 1)
            ->whereIn('cat_id', $usadas ?: [0])->orderBy('cat_nom')->get(['cat_id', 'cat_nom', 'color']);

        return compact('productos', 'categorias');
    }

    /**
     * Crea el pedido como proforma. Los precios y nombres salen de la base (nunca del navegador).
     * @param array $items [['id' => int, 'cantidad' => number], ...]
     */
    public static function pedido(EmpresaNegocio $negocio, object $cliente, array $items, array $datos): array
    {
        $catalogo = collect(self::catalogo($negocio)['productos'])->keyBy('id');
        $lineas = [];
        foreach ($items as $i) {
            $p = $catalogo[(int) ($i['id'] ?? 0)] ?? null;
            $cant = round((float) ($i['cantidad'] ?? 0), 2);
            if (!$p || $cant <= 0) {
                throw new \RuntimeException('Un producto del carrito ya no está disponible. Revisa tu carrito.');
            }
            if ($p['stock'] !== null && $cant > $p['stock']) {
                throw new \RuntimeException("Solo quedan " . rtrim(rtrim(number_format($p['stock'], 2), '0'), '.') . " de {$p['nombre']}.");
            }
            $lineas[] = ['id' => $p['id'], 'cantidad' => $cant, 'precio' => $p['precio']];
        }
        if (!$lineas) {
            throw new \RuntimeException('Tu carrito está vacío.');
        }

        // Usuario "del sistema" para la proforma: la tienda no tiene cajero
        $usuario = (new User())->forceFill(['IdUsuario' => null, 'IdEmpresa' => $negocio->IdEmpresa, 'id_empresa_negocio' => $negocio->id_empresa_negocio]);
        $obs = trim('WEB · ' . $datos['entrega'] . ' · ' . $datos['pago'] . ($datos['telefono'] ? ' · TEL ' . $datos['telefono'] : ''));

        $id = Proformas::guardar($usuario, [
            'origen' => 'WEB', 'items' => $lineas,
            'tdicod' => $cliente->tdicod ?: (strlen($cliente->clinum) === 11 ? '6' : '1'),
            'clinum' => $cliente->clinum, 'clinom' => $cliente->clinom,
            'clidir' => $datos['direccion'] ?: ($cliente->clidir !== '--' ? $cliente->clidir : null),
            'observaciones' => mb_substr($obs . ($datos['nota'] ? ' · ' . $datos['nota'] : ''), 0, 100),
        ]);
        $p = DB::table('proformas')->where('id_proforma', $id)->first();
        return ['id' => $id, 'numero' => Proformas::numero($p), 'total' => (float) $p->total];
    }
}
