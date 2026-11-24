<?php

namespace App\Http\Controllers;

use App\Support\Bitacora;
use App\Support\Seguridad\Dispositivos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Seguridad › Equipos y sesiones (solo el administrador): desde qué equipos entra cada usuario, cuáles tienen la sesión
 * abierta ahora, y los nuevos por revisar. Confirmar ("sí, fue él"), bloquear ("no fui yo"), cerrar la sesión o ponerle
 * nombre al equipo. Confirmar y bloquear solo se puede desde un equipo de confianza (así quien entró con una clave
 * robada no puede aprobarse a sí mismo).
 */
class SeguridadController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador ve la seguridad de los equipos.');
        abort_unless(Dispositivos::disponible(), 404);
    }

    public function index(): View
    {
        $this->autorizar();
        $empresa = Auth::user()->IdEmpresa;
        $vigencia = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;
        $abiertas = DB::table('dispositivo_sesiones as ds')->join('sessions as s', 's.id', '=', 'ds.session_id')
            ->where('s.last_activity', '>=', $vigencia)->groupBy('ds.dispositivo_id')->selectRaw('ds.dispositivo_id, COUNT(*) AS n')->pluck('n', 'dispositivo_id');
        $equipos = DB::table('dispositivos as d')->join('users as u', 'u.IdUsuario', '=', 'd.IdUsuario')->where('u.IdEmpresa', $empresa)
            ->orderByDesc('d.por_revisar')->orderByDesc('d.ultima_vez')
            ->get(['d.*', 'u.apeusu', 'u.name as usuario_login'])
            ->each(fn ($e) => $e->abiertas = (int) ($abiertas[$e->id] ?? 0));

        return view('empresas.seguridad.equipos', [
            'porUsuario' => $equipos->groupBy('IdUsuario'), 'actual' => Dispositivos::actual(), 'deConfianza' => Dispositivos::deConfianza(),
        ]);
    }

    public function accion(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['accion' => 'required|in:confiar,bloquear,cerrar,nombre', 'nombre' => 'nullable|string|max:100']);
        $equipo = DB::table('dispositivos as d')->join('users as u', 'u.IdUsuario', '=', 'd.IdUsuario')
            ->where('u.IdEmpresa', Auth::user()->IdEmpresa)->where('d.id', $id)->first(['d.*', 'u.apeusu']);
        abort_unless($equipo, 404);
        $yo = Auth::user()->apeusu ?: Auth::user()->name;
        $actual = Dispositivos::actual();

        // Confirmar o bloquear: solo desde un equipo de confianza (y nunca el mismo equipo desde el que se está)
        if (in_array($d['accion'], ['confiar', 'bloquear'], true)) {
            if (! Dispositivos::deConfianza()) {
                return back()->with('error', 'Solo se puede confirmar o bloquear desde un equipo de confianza. Hazlo desde tu PC o celular de siempre.');
            }
            if ($actual && (int) $actual->id === (int) $equipo->id) {
                return back()->with('error', 'No puedes bloquear el equipo desde el que estás ahora.');
            }
        }

        switch ($d['accion']) {
            case 'confiar':
                Dispositivos::confiar($equipo, $yo);
                Bitacora::registrar('sesion.equipo_confiable', "Confirmó el equipo {$equipo->nombre} de {$equipo->apeusu}");
                $mensaje = "Listo: {$equipo->nombre} de {$equipo->apeusu} es de confianza.";
                break;
            case 'bloquear':
                Dispositivos::bloquear($equipo, $yo);
                Bitacora::registrar('sesion.no_fui_yo', "Bloqueó el equipo {$equipo->nombre} de {$equipo->apeusu} (IP {$equipo->ip})", null, ['alerta' => true]);
                $mensaje = "Bloqueado: {$equipo->nombre} ya no puede entrar y se cerró su sesión. Cambia la clave de {$equipo->apeusu} (Usuarios) por si la conocen.";
                break;
            case 'cerrar':
                $n = Dispositivos::cerrarSesiones($equipo);
                Bitacora::registrar('sesion.cerrar', "Cerró la sesión de {$equipo->apeusu} en {$equipo->nombre}");
                $mensaje = $n ? "Se cerró la sesión de {$equipo->apeusu} en {$equipo->nombre}." : 'Ese equipo no tenía la sesión abierta.';
                break;
            default:
                $nombre = mb_strtoupper(trim((string) ($d['nombre'] ?? '')));
                DB::table('dispositivos')->where('id', $equipo->id)->update(['nombre' => $nombre !== '' ? $nombre : $equipo->navegador.' en '.$equipo->sistema]);
                $mensaje = 'Nombre del equipo guardado.';
        }

        return back()->with('success', $mensaje);
    }
}
