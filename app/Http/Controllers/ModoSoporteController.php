<?php

namespace App\Http\Controllers;

use App\Support\Bitacora;
use App\Support\Tenancy\ModoSoporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada y salida del modo soporte en el sistema de la empresa (el enlace lo genera el panel: ModoSoporte::enlace).
 */
class ModoSoporteController extends Controller
{
    public function entrar(Request $request, string $token): RedirectResponse
    {
        $canje = ModoSoporte::canjear($token);
        if (! $canje) {
            return redirect('/login')->withErrors(['email' => 'El enlace de soporte ya se usó o venció. Genera otro desde el panel.']);
        }
        ['ingreso' => $ingreso, 'usuario' => $usuario] = $canje;

        // Si había alguien con sesión en este navegador, sale primero (nunca se mezclan dos usuarios)
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(ModoSoporte::SESION, ['id' => (int) $ingreso->id, 'nombre' => (string) $ingreso->superadmin_nombre,
            'usuario' => (string) $ingreso->usuario_nombre, 'modo' => (string) $ingreso->modo, 'hasta' => now()->addMinutes(ModoSoporte::MINUTOS_VISITA)->timestamp]);
        Auth::guard('web')->login($usuario);

        Bitacora::registrar('sesion.soporte_entro', "Soporte ({$ingreso->superadmin_nombre}) entró a revisar el sistema como {$ingreso->usuario_nombre}"
            .($ingreso->modo === 'mirar' ? ' (solo para mirar)' : ' (con permiso para hacer cambios)'), null,
            ['usuario' => null, 'nombre' => 'Soporte: '.$ingreso->superadmin_nombre, 'sucursal' => $usuario->id_empresa_negocio, 'empresa' => $usuario->IdEmpresa,
                'referencia' => ['usuario', $usuario->IdUsuario]]);

        return redirect()->route($usuario->rutaInicio());
    }

    public function salir(Request $request): RedirectResponse
    {
        if ($visita = ModoSoporte::actual()) {
            $usuario = Auth::guard('web')->user();
            Bitacora::registrar('sesion.soporte_salio', "Soporte ({$visita['nombre']}) terminó de revisar el sistema", null,
                ['usuario' => null, 'nombre' => 'Soporte: '.$visita['nombre'], 'sucursal' => $usuario?->id_empresa_negocio, 'empresa' => $usuario?->IdEmpresa]);
            ModoSoporte::terminar((int) $visita['id']);
        }
        // Primero se cierra la sesión (con la visita aún anotada, así la bitácora no lo cuenta como salida del usuario)
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Saliste del modo soporte.');
    }
}
