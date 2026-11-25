<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Bitacora;
use App\Support\Seguridad\Dispositivos;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Usuarios de una empresa desde el panel: cuando el cliente olvida su contraseña, se le pone una nueva
 * (la escribe quien atiende o se genera sola) y se muestra una sola vez para entregársela.
 */
class AccesoController extends Controller
{
    private function autorizar(Cliente $cliente): void
    {
        $yo = Auth::guard('superadmin')->user();
        abort_unless($yo->es_dueno || (int) $cliente->creado_por === (int) $yo->id, 404);
        abort_if($cliente->estado === 'ELIMINADO', 404);
    }

    public function index(Cliente $cliente): View
    {
        $this->autorizar($cliente);
        $usuarios = Tenancy::en($cliente->base_datos, fn () => DB::table('users as u')
            ->leftJoin('role_user as ru', 'ru.user_IdUsuario', '=', 'u.IdUsuario')->leftJoin('roles as r', 'r.id', '=', 'ru.role_id')
            ->leftJoin('empresa_negocios as n', 'n.id_empresa_negocio', '=', 'u.id_empresa_negocio')
            ->orderByRaw('ru.role_id = 2 DESC')->orderByDesc('u.estusu')->orderBy('u.IdUsuario')
            ->get(['u.IdUsuario', 'u.name', 'u.apeusu', 'u.email', 'u.estusu', 'r.description as rol', 'ru.role_id', 'n.nombre_comercial as sucursal'])
            ->unique('IdUsuario')->values());

        // Equipos de sus usuarios (si la empresa ya tiene el control de equipos): los por revisar primero
        $equipos = Tenancy::en($cliente->base_datos, fn () => Schema::hasTable('dispositivos')
            ? DB::table('dispositivos')->orderByDesc('por_revisar')->orderByRaw('bloqueado_el IS NOT NULL')->orderByDesc('ultima_vez')->limit(200)->get()
            : collect());

        return view('admin.clientes.accesos', ['cliente' => $cliente, 'usuarios' => $usuarios, 'equipos' => $equipos->groupBy('IdUsuario')]);
    }

    /**
     * Autorizar o bloquear un equipo desde el panel (soporte): sirve cuando la empresa se quedó sin un equipo de confianza
     * desde donde aprobar (cambió de PC, borró el navegador) o cuando soporte entra desde su propia PC. "todos" autoriza
     * todos los equipos por revisar de la empresa.
     */
    public function equipo(Request $request, Cliente $cliente, string $id): RedirectResponse
    {
        $this->autorizar($cliente);
        $accion = $request->validate(['accion' => 'required|in:confiar,bloquear'])['accion'];
        $quien = 'Panel: '.mb_substr((string) Auth::guard('superadmin')->user()->nombre, 0, 100);

        $n = Tenancy::en($cliente->base_datos, function () use ($id, $accion, $quien) {
            $equipos = DB::table('dispositivos')->when($id === 'todos', fn ($q) => $q->where('por_revisar', true)->whereNull('bloqueado_el'),
                fn ($q) => $q->where('id', (int) $id))->get();
            abort_if($equipos->isEmpty() && $id !== 'todos', 404);
            foreach ($equipos as $e) {
                $accion === 'confiar' ? Dispositivos::confiar($e, $quien) : Dispositivos::bloquear($e, $quien);
                Bitacora::registrar($accion === 'confiar' ? 'sesion.equipo_confiable' : 'sesion.bloqueo_soporte',
                    ($accion === 'confiar' ? 'Soporte autorizó el equipo ' : 'Soporte bloqueó el equipo ').$e->nombre, null,
                    ['usuario' => null, 'nombre' => $quien, 'referencia' => ['dispositivo', $e->id]]);
            }

            return $equipos->count();
        });

        return back()->with('ok', $accion === 'confiar' ? "{$n} equipo(s) autorizado(s): ya pueden confirmar y hacer acciones delicadas." : 'Equipo bloqueado y su sesión cerrada.');
    }

    /** 4 letras y 4 números, sin las que se confunden (l, i, o) para dictarla por teléfono: ej. "kafm4827" */
    private static function generar(): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyz';
        $clave = '';
        for ($i = 0; $i < 4; $i++) {
            $clave .= $letras[random_int(0, strlen($letras) - 1)];
        }

        return $clave.random_int(1000, 9999);
    }

    public function clave(Request $request, Cliente $cliente, int $usuario): RedirectResponse
    {
        $this->autorizar($cliente);
        $d = $request->validate(['password' => 'nullable|string|min:6|max:60', 'activar' => 'nullable|boolean'], [], ['password' => 'contraseña']);
        $clave = ($d['password'] ?? null) ?: self::generar();

        $usuarioFila = Tenancy::en($cliente->base_datos, function () use ($usuario, $clave, $request) {
            $u = DB::table('users')->where('IdUsuario', $usuario)->first(['IdUsuario', 'name', 'email', 'estusu']);
            abort_unless($u, 404);
            DB::table('users')->where('IdUsuario', $usuario)->update(['password' => bcrypt($clave), 'remember_token' => null, 'updated_at' => now()]
                + ($request->boolean('activar') ? ['estusu' => 1] : []));

            return $u;
        });
        Log::info('Panel: contraseña cambiada', ['cliente' => $cliente->ruc, 'usuario' => $usuarioFila->email, 'por' => Auth::guard('superadmin')->user()->email]);

        return redirect()->route('admin.clientes.accesos', $cliente)->with('clave', ['usuario' => $usuarioFila->email, 'nombre' => $usuarioFila->name,
            'password' => $clave, 'url' => $cliente->url()]);
    }
}
