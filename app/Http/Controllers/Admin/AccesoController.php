<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        return view('admin.clientes.accesos', ['cliente' => $cliente, 'usuarios' => $usuarios]);
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
