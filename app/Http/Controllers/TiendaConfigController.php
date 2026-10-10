<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Models\Producto;
use App\Support\Tienda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Configuración de la tienda virtual (solo Administrador): activarla, WhatsApp y mensaje para los clientes */
class TiendaConfigController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador configura la tienda virtual.');
    }

    public function edit()
    {
        $this->autorizar();
        $negocio = EmpresaNegocio::find(Auth::user()->id_empresa_negocio);
        $pedidos = DB::table('proformas')->where('id_empresa_negocio', $negocio->id_empresa_negocio)->where('origen', 'WEB')->where('estado', 'PENDIENTE')->count();
        $productos = DB::table('productos')->where('id_empresa_negocio', $negocio->id_empresa_negocio)->where('proest', 'Activo')->whereNotIn('promocion', Producto::NO_VENDIBLES);

        return view('empresas.tienda.configuracion', [
            'negocio' => $negocio, 'pedidos' => $pedidos, 'permitida' => Tienda::permitidaPorPlan(),
            'conImagen' => (clone $productos)->whereNotNull('imagenproducto')->count(), 'totalProductos' => $productos->count(),
            'url' => url('/tiendavirtual'),
        ]);
    }

    public function update(Request $request)
    {
        $this->autorizar();
        abort_unless(Tienda::permitidaPorPlan(), 403, 'Tu plan no incluye tienda virtual.');
        $d = $request->validate(['tienda_whatsapp' => 'nullable|string|max:20', 'tienda_mensaje' => 'nullable|string|max:2000']);
        $activa = $request->boolean('tienda_activa');
        $suc = Auth::user()->id_empresa_negocio;
        // Una sola sucursal publica la tienda
        if ($activa) {
            EmpresaNegocio::where('IdEmpresa', Auth::user()->IdEmpresa)->where('id_empresa_negocio', '!=', $suc)->update(['tienda_activa' => false]);
        }
        EmpresaNegocio::where('id_empresa_negocio', $suc)->update($d + [
            'tienda_activa' => $activa, 'tienda_mostrar_agotados' => $request->boolean('tienda_mostrar_agotados'),
        ]);

        return back()->with('success', $activa ? 'Tienda virtual publicada.' : 'Tienda virtual desactivada.');
    }
}
