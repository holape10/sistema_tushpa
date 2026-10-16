<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Superadmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Usuarios del panel admin. Crea usuarios el dueño del sistema y a quien él le dé el permiso.
 * Solo el dueño da o quita ese permiso. Quien no es dueño ve y maneja solo los usuarios que él creó.
 */
class UsuarioController extends Controller
{
    private function yo(): Superadmin
    {
        return Auth::guard('superadmin')->user();
    }

    private function autorizar(): void
    {
        abort_unless($this->yo()->es_dueno || $this->yo()->puede_crear_usuarios, 403, 'No tienes permiso para manejar usuarios del panel.');
    }

    /** El dueño maneja a todos menos a otro dueño; los demás, solo a los que crearon */
    private function puedeManejar(Superadmin $usuario): bool
    {
        if ($usuario->id === $this->yo()->id || $usuario->es_dueno) {
            return false;
        }

        return (bool) $this->yo()->es_dueno || (int) $usuario->creado_por === (int) $this->yo()->id;
    }

    public function index(): View
    {
        $this->autorizar();
        $usuarios = Superadmin::query()
            ->when(! $this->yo()->es_dueno, fn ($q) => $q->where('creado_por', $this->yo()->id)->orWhere('id', $this->yo()->id))
            ->orderByDesc('es_dueno')->orderBy('nombre')->get();
        $empresas = DB::connection('central')->table('clientes')->whereNotNull('creado_por')
            ->groupBy('creado_por')->select('creado_por', DB::raw('COUNT(*) as total'))->pluck('total', 'creado_por');
        $nombres = Superadmin::pluck('nombre', 'id');

        return view('admin.usuarios.index', ['usuarios' => $usuarios, 'empresas' => $empresas, 'nombres' => $nombres,
            'esDueno' => (bool) $this->yo()->es_dueno, 'puedeManejar' => fn ($u) => $this->puedeManejar($u)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizar();
        $datos = $request->validate([
            'nombre' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:120', Rule::unique('central.superadmins', 'email')],
            'password' => 'required|string|min:10',
        ], [], ['password' => 'contraseña', 'email' => 'correo']);

        Superadmin::create($datos + [
            'activo' => 1, 'creado_por' => $this->yo()->id,
            'puede_crear_usuarios' => $this->yo()->es_dueno && $request->boolean('puede_crear_usuarios') ? 1 : 0,
        ]);

        return back()->with('ok', "Usuario {$datos['email']} creado. Ya puede entrar al panel.");
    }

    /** Dar o quitar el permiso de crear usuarios (solo el dueño) */
    public function permiso(Superadmin $usuario): RedirectResponse
    {
        abort_unless($this->yo()->es_dueno && $this->puedeManejar($usuario), 403);
        $usuario->update(['puede_crear_usuarios' => $usuario->puede_crear_usuarios ? 0 : 1]);

        return back()->with('ok', $usuario->puede_crear_usuarios
            ? "{$usuario->nombre} ahora puede crear usuarios." : "{$usuario->nombre} ya no puede crear usuarios.");
    }

    public function estado(Superadmin $usuario): RedirectResponse
    {
        $this->autorizar();
        abort_unless($this->puedeManejar($usuario), 403);
        $usuario->update(['activo' => $usuario->activo ? 0 : 1]);

        return back()->with('ok', $usuario->activo ? "{$usuario->nombre} puede volver a entrar." : "{$usuario->nombre} ya no puede entrar al panel.");
    }

    public function clave(Request $request, Superadmin $usuario): RedirectResponse
    {
        $this->autorizar();
        abort_unless($this->puedeManejar($usuario), 403);
        $request->validate(['password' => 'required|string|min:10'], [], ['password' => 'contraseña']);
        $usuario->update(['password' => $request->password, 'remember_token' => null]);

        return back()->with('ok', "Contraseña de {$usuario->nombre} cambiada.");
    }
}
