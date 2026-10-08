<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpresaNegocio;
use App\Models\Modulo;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    private const ROL_MOZO = 8;

    // Módulos que se marcan solos al elegir el rol (el admin puede cambiarlos después)
    public const PRESETS = [
        8 => ['Comandas'],
        10 => ['Inicio', 'Historias Clínicas', 'Agenda de Citas'],
        4 => ['Inicio', 'Dashboard', 'Comandas', 'Caja', 'Listar Cajas', 'Envío de Comprobantes', 'Resumen Diario',
            'Kardex', 'Stock Productos', 'Clientes'],
    ];

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador gestiona usuarios.');
    }

    /** Usuario de la misma empresa (nunca de otra) */
    private function propio(User $usuario): void
    {
        abort_unless($usuario->IdEmpresa === Auth::user()->IdEmpresa, 404);
    }

    private function datosFormulario(): array
    {
        $modulos = Modulo::orderBy('mod_id')->get();
        $presets = [2 => $modulos->pluck('mod_id')->all()];
        foreach (self::PRESETS as $rol => $nombres) {
            $presets[$rol] = $modulos->whereIn('mod_nom', $nombres)->pluck('mod_id')->values()->all();
        }

        return [
            'roles' => DB::table('roles')->orderBy('id')->get(),
            'modulos' => $modulos->groupBy('mod_gen'),
            'presets' => $presets,
            'sucursales' => EmpresaNegocio::where('IdEmpresa', Auth::user()->IdEmpresa)->get(),
            'documentos' => DB::table('tipo_documento_identidad')->whereIn('tdicod', ['1', '4', '7'])->orderBy('orden')->get(),
        ];
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $q = trim((string) $request->get('q'));

        $usuarios = User::with('empleado')
            ->leftJoin('role_user as ru', 'ru.user_IdUsuario', '=', 'users.IdUsuario')
            ->leftJoin('roles as r', 'r.id', '=', 'ru.role_id')
            ->leftJoin('empleado as e', 'e.emp_id', '=', 'users.emp_id')
            ->where('users.IdEmpresa', Auth::user()->IdEmpresa)
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('users.apeusu', 'like', "%$q%")
                ->orWhere('users.email', 'like', "%$q%")->orWhere('e.emp_num_doc', 'like', "$q%")))
            ->orderBy('users.apeusu')
            ->select('users.*', 'r.id as role_id', 'r.name as rol', 'r.description as rol_nombre')
            ->paginate(30)->withQueryString();

        return view('empresas.usuarios.index', compact('usuarios', 'q'));
    }

    public function create()
    {
        $this->autorizar();

        return view('empresas.usuarios.form', $this->datosFormulario() + ['usuario' => null, 'empleado' => null, 'rolActual' => null, 'modulosAsignados' => []]);
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $sucursal = (int) $request->input('id_empresa_negocio');
        $nuevo = $usuario === null;

        return $request->validate([
            'id_empresa_negocio' => ['required', Rule::exists('empresa_negocios', 'id_empresa_negocio')->where('IdEmpresa', Auth::user()->IdEmpresa)],
            'tdicod' => 'required|in:1,4,7',
            'emp_num_doc' => 'nullable|string|max:15',
            'emp_nom' => 'required|string|max:100',
            'emp_ape_pat' => 'required|string|max:100',
            'emp_ape_mat' => 'nullable|string|max:100',
            'sex_cod' => 'nullable|in:M,F',
            'emp_fec_nac' => 'nullable|date|before:today',
            'estusu' => 'required|in:0,1',
            'asistencia' => 'required|in:0,1',
            'emp_tel' => 'nullable|string|max:20',
            'emp_cel' => 'nullable|string|max:20',
            'emp_cor' => 'nullable|email|max:100',   // el correo NO es obligatorio
            'emp_dir' => 'nullable|string|max:200',
            // Sin espacios para usuarios nuevos; los importados del sistema antiguo ("PEDRO BARBA") conservan el suyo si no lo cambian
            'email' => array_merge(['required', 'string', 'max:50'],
                $usuario && trim((string) $request->email) === $usuario->email ? [] : ['regex:/^\S+$/'],
                [Rule::unique('users', 'email')->ignore($usuario?->IdUsuario, 'IdUsuario')]),
            'role_id' => 'required|exists:roles,id',
            // El mozo entra desde la tablet/celular con este código
            'codigo_movil' => ['nullable', 'required_if:role_id,'.self::ROL_MOZO, 'digits_between:1,6',
                Rule::unique('users', 'codigo_movil')->where('id_empresa_negocio', $sucursal)->ignore($usuario?->IdUsuario, 'IdUsuario')],
            'password' => [$nuevo ? 'required' : 'nullable', 'string', 'min:4', 'confirmed'],
            'modulos' => 'nullable|array',
            'modulos.*' => 'integer|exists:modulos,mod_id',
            'foto' => 'nullable|image|max:5120',
        ], [
            'codigo_movil.required_if' => 'El código móvil es obligatorio para los mozos (lo usan para entrar desde la tablet o celular).',
            'codigo_movil.unique' => 'Ese código móvil ya lo tiene otro usuario de la sucursal.',
            'email.regex' => 'El usuario de acceso no puede tener espacios.',
        ], [
            'emp_nom' => 'Nombres', 'emp_ape_pat' => 'Apellido paterno', 'email' => 'Usuario de acceso',
            'emp_cor' => 'Correo', 'codigo_movil' => 'Código móvil', 'role_id' => 'Rol', 'password' => 'Contraseña',
            'id_empresa_negocio' => 'Sucursal', 'emp_fec_nac' => 'Fecha de nacimiento', 'foto' => 'Foto',
        ]);
    }

    private function datosEmpleado(array $d): array
    {
        return [
            'emp_nom' => mb_strtoupper(trim($d['emp_nom'])), 'emp_ape_pat' => mb_strtoupper(trim($d['emp_ape_pat'])),
            'emp_ape_mat' => mb_strtoupper(trim($d['emp_ape_mat'] ?? '')), 'tdicod' => $d['tdicod'],
            'emp_num_doc' => $d['emp_num_doc'] ?? null, 'sex_cod' => $d['sex_cod'] ?? null,
            'emp_fec_nac' => $d['emp_fec_nac'] ?? null, 'emp_tel' => $d['emp_tel'] ?? null, 'emp_cel' => $d['emp_cel'] ?? null,
            'emp_cor' => $d['emp_cor'] ?? null, 'emp_dir' => $d['emp_dir'] ?? null,
            'est_cod' => $d['estusu'], 'emp_est' => $d['estusu'] ? 'ACTIVO' : 'INACTIVO',
            'rol_id' => $d['role_id'], 'asistencia' => $d['asistencia'], 'id_empresa_negocio' => $d['id_empresa_negocio'],
        ];
    }

    private function nombreCompleto(array $d): string
    {
        return mb_strtoupper(trim($d['emp_nom'].' '.$d['emp_ape_pat'].' '.($d['emp_ape_mat'] ?? '')));
    }

    /** Foto del trabajador en public/imagenes/empleados/{RUC}/ (se ve en el kiosko de asistencia); borra la anterior */
    private function guardarFoto(Request $request, ?Empleado $empleado): void
    {
        if (! $empleado) {
            return;
        }
        $anterior = $empleado->emp_foto;
        if ($request->hasFile('foto')) {
            $archivo = $request->file('foto');
            $carpeta = 'imagenes/empleados/'.preg_replace('/\D/', '', (string) Auth::user()->IdEmpresa);
            $nombre = $empleado->emp_id.'_'.uniqid().'.'.strtolower($archivo->guessExtension() ?: 'jpg');
            $archivo->move(public_path($carpeta), $nombre);
            $empleado->update(['emp_foto' => $carpeta.'/'.$nombre]);
        } elseif ($request->boolean('quitar_foto')) {
            $empleado->update(['emp_foto' => null]);
        } else {
            return;
        }
        if ($anterior && str_starts_with($anterior, 'imagenes/empleados/') && is_file(public_path($anterior))) {
            @unlink(public_path($anterior));
        }
    }

    public function store(Request $request)
    {
        $this->autorizar();
        // Límite de usuarios del plan contratado (multi-empresa)
        $plan = Tenancy::plan();
        if ($plan && $plan->max_usuarios && User::where('IdEmpresa', Auth::user()->IdEmpresa)->count() >= $plan->max_usuarios) {
            return back()->withInput()->with('error', "Tu plan {$plan->nombre} permite hasta {$plan->max_usuarios} usuarios. Para agregar más, cámbiate a un plan mayor.")
                ->withErrors(['plan' => "Tu plan {$plan->nombre} permite hasta {$plan->max_usuarios} usuarios. Para agregar más, cámbiate a un plan mayor."]);
        }
        $d = $this->validar($request);

        $empleado = DB::transaction(function () use ($d) {
            $empleado = Empleado::create($this->datosEmpleado($d));

            $usuario = User::create([
                'name' => mb_strtoupper(trim($d['emp_nom'])),
                'apeusu' => $this->nombreCompleto($d),
                'email' => trim($d['email']),
                'codigo_movil' => $d['codigo_movil'] ?? null,
                'password' => bcrypt($d['password']),
                'estusu' => (int) $d['estusu'],
                'IdEmpresa' => Auth::user()->IdEmpresa,
                'id_empresa_negocio' => $d['id_empresa_negocio'],
                'emp_id' => $empleado->emp_id,
            ]);

            DB::table('role_user')->insert([
                'role_id' => $d['role_id'], 'user_IdUsuario' => $usuario->IdUsuario, 'id_empresa_negocio' => $d['id_empresa_negocio'],
            ]);
            $usuario->modulos()->sync($d['modulos'] ?? []);

            return $empleado;
        });
        $this->guardarFoto($request, $empleado);

        return redirect()->route('usuarios.index')->with('success', 'Usuario '.$this->nombreCompleto($d).' registrado.');
    }

    public function edit(User $usuario)
    {
        $this->autorizar();
        $this->propio($usuario);

        return view('empresas.usuarios.form', $this->datosFormulario() + [
            'usuario' => $usuario,
            'empleado' => $usuario->empleado,
            'rolActual' => DB::table('role_user')->where('user_IdUsuario', $usuario->IdUsuario)->value('role_id'),
            'modulosAsignados' => $usuario->modulos()->pluck('modulos.mod_id')->all(),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $this->autorizar();
        $this->propio($usuario);
        $d = $this->validar($request, $usuario);

        // El administrador no puede quitarse a sí mismo el rol ni desactivarse (se quedaría sin acceso)
        if ((int) $usuario->IdUsuario === (int) Auth::id() && ((int) $d['role_id'] !== 2 || (int) $d['estusu'] !== 1)) {
            return back()->withInput()->withErrors(['role_id' => 'No puedes quitarte el rol de Administrador ni desactivar tu propio usuario.']);
        }

        DB::transaction(function () use ($d, $usuario) {
            $datosEmp = $this->datosEmpleado($d);
            if ($usuario->empleado) {
                $usuario->empleado->update($datosEmp);
            } else {
                $usuario->emp_id = Empleado::create($datosEmp)->emp_id;
            }

            $usuario->fill([
                'name' => mb_strtoupper(trim($d['emp_nom'])), 'apeusu' => $this->nombreCompleto($d),
                'email' => trim($d['email']), 'codigo_movil' => $d['codigo_movil'] ?? null,
                'estusu' => (int) $d['estusu'], 'id_empresa_negocio' => $d['id_empresa_negocio'],
            ]);
            if (! empty($d['password'])) {
                $usuario->password = bcrypt($d['password']);
            }
            $usuario->save();

            DB::table('role_user')->where('user_IdUsuario', $usuario->IdUsuario)->delete();
            DB::table('role_user')->insert([
                'role_id' => $d['role_id'], 'user_IdUsuario' => $usuario->IdUsuario, 'id_empresa_negocio' => $d['id_empresa_negocio'],
            ]);
            $usuario->modulos()->sync($d['modulos'] ?? []);
        });
        $this->guardarFoto($request, Empleado::find($usuario->emp_id));

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $usuario)
    {
        $this->autorizar();
        $this->propio($usuario);
        if ((int) $usuario->IdUsuario === (int) Auth::id()) {
            return back()->withErrors(['usuario' => 'No puedes eliminar tu propio usuario.']);
        }

        // Si ya tiene ventas o pedidos se desactiva en vez de borrarse, para no perder el historial
        $tieneHistorial = DB::table('cpe_cabecera')->where('IdUsuario', $usuario->IdUsuario)->exists()
            || DB::table('pedidos')->where('IdUsuario', $usuario->IdUsuario)->exists()
            || DB::table('turnos')->where('IdUsuario', $usuario->IdUsuario)->exists();

        if ($tieneHistorial) {
            $usuario->update(['estusu' => 0]);
            $usuario->empleado?->update(['est_cod' => '0', 'emp_est' => 'INACTIVO']);

            return back()->with('success', 'El usuario tiene ventas o pedidos registrados: se DESACTIVÓ en vez de eliminarse.');
        }

        DB::transaction(function () use ($usuario) {
            DB::table('role_user')->where('user_IdUsuario', $usuario->IdUsuario)->delete();
            $usuario->modulos()->detach();
            $empleado = $usuario->empleado;
            $usuario->delete();
            $empleado?->delete();
        });

        return back()->with('success', 'Usuario eliminado.');
    }
}
