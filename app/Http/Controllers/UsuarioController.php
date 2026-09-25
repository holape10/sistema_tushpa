<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Empleado;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $roles = DB::table('roles')->get();
        $modulos = Modulo::all()->groupBy('mod_gen');
        return view('empresas.usuarios.create', compact('roles', 'modulos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'apeusu'   => 'required|string|max:100',
            'email'    => 'required|string|max:50|unique:users,email',
            'password' => 'required|string|min:4',
            'role_id'  => 'required|exists:roles,id',
        ], [], [
            'name' => 'Nombre de usuario', 'apeusu' => 'Nombres y apellidos',
            'email' => 'Usuario de acceso', 'password' => 'Contraseña', 'role_id' => 'Rol',
        ]);

        DB::transaction(function () use ($request) {
            $empleado = Empleado::create([
                'emp_nom'            => $request->apeusu,
                'emp_ape_pat'        => '.',
                'est_cod'            => '1',
                'rol_id'             => $request->role_id,
                'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
            ]);

            $usuario = User::create([
                'name'               => $request->name,
                'apeusu'             => $request->apeusu,
                'email'              => $request->email,
                'password'           => bcrypt($request->password),
                'estusu'             => 1,
                'IdEmpresa'          => Auth::user()->IdEmpresa,
                'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
                'emp_id'             => $empleado->emp_id,
            ]);

            DB::table('role_user')->insert([
                'role_id'            => $request->role_id,
                'user_IdUsuario'     => $usuario->IdUsuario,
                'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
            ]);

            // el admin marca a mano qué opciones del menú tendrá este usuario
            $usuario->modulos()->sync($request->modulos ?? []);
        });

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $usuario)
    {
        $roles = DB::table('roles')->get();
        $modulos = Modulo::all()->groupBy('mod_gen');
        $modulosAsignados = $usuario->modulos()->pluck('mod_id')->toArray();
        return view('empresas.usuarios.edit', compact('usuario', 'roles', 'modulos', 'modulosAsignados'));
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'apeusu' => 'required|string|max:100',
        ], [], ['apeusu' => 'Nombres y apellidos']);

        $usuario->update(['apeusu' => $request->apeusu, 'estusu' => $request->estusu ?? 1]);

        if ($request->filled('password')) {
            $usuario->update(['password' => bcrypt($request->password)]);
        }

        $usuario->modulos()->sync($request->modulos ?? []);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $usuario)
    {
        $usuario->delete();
        return back()->with('success', 'Usuario eliminado.');
    }
}