<?php

namespace App\Http\Controllers;

use App\Support\PreciosCompra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Productos › Precios por costo: los productos que subieron de costo en las compras y ahora ganan menos, con el precio
 * que mantiene su ganancia. El permiso lo da la opción Productos del menú (rutas productos.*).
 */
class PreciosCostoController extends Controller
{
    public function index(Request $request): View
    {
        $compra = $request->integer('compra') ?: null;

        return view('empresas.productos.precios_costo', [
            'compra' => $compra,
            'sugerencias' => PreciosCompra::sugerencias((int) Auth::user()->id_empresa_negocio, $compra),
            'dias' => PreciosCompra::DIAS,
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'precios' => 'required|array|min:1|max:500',
            'precios.*' => 'nullable|numeric|min:0.01|max:999999',
            'elegidos' => 'required|array|min:1',
            'elegidos.*' => 'integer',
        ], ['elegidos.required' => 'Marca los productos que quieres subir.']);
        $precios = array_intersect_key($d['precios'], array_flip($d['elegidos']));
        $n = PreciosCompra::aplicar(Auth::user(), array_filter($precios, fn ($p) => $p !== null));

        return redirect()->route('productos.precios_costo')->with('success', $n ? "Listo: {$n} precio(s) actualizado(s)." : 'No cambió ningún precio.');
    }
}
