<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpresaNegocio;
use App\Models\Modulo;
use App\Models\User;
use App\Support\Acciones;
use App\Support\Bitacora;
use App\Support\ConsultaPeru;
use App\Support\Delivery;
use App\Support\Estudio;
use App\Support\Rubros;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    private const ROL_MOZO = 8;

    private const ROL_MOTORIZADO = 12;

    private const ROL_PROMOTOR = 13;

    private const ROL_COBRADOR = 14;

    /** Entran solo desde el celular con su código (no necesitan contraseña) */
    private const ROLES_CON_CODIGO = [self::ROL_MOZO, self::ROL_PROMOTOR, self::ROL_COBRADOR];

    // Módulos que se marcan solos al elegir el rol (el admin puede cambiarlos después)
    public const PRESETS = [
        8 => ['Inicio', 'Comandas'],
        11 => ['Inicio', 'Entrenador'],
        12 => ['Inicio'],
        13 => ['Inicio', 'Comandas', 'Ventas de Promotores'],
        10 => ['Inicio', 'Historias Clínicas', 'Agenda de Citas'],
        14 => ['Inicio', 'Cobranza del Día', 'Clientes de Préstamos'],
        // Cocinero y almacenero: ven el stock y piden lo que falta (solo en su sucursal)
        16 => ['Inicio', 'Pantalla Cocina', 'Requerimientos del día', 'Stock Productos', 'Mermas'],
        17 => ['Inicio', 'Requerimientos del día', 'Inventario ciego', 'Stock Productos', 'Kardex', 'Ingresos Productos', 'Salidas Productos', 'Órdenes de compra'],
        4 => ['Inicio', 'Dashboard', 'Comandas', 'Caja', 'Listar Cajas', 'Cajas cerradas (turnos)', 'Envío de Comprobantes', 'Resumen Diario',
            'Kardex', 'Stock Productos', 'Clientes'],
        // Contador: reporte de ventas SUNAT, envío a SUNAT y, si la empresa los tiene, contabilidad, planilla y SIRE
        15 => ['Inicio', 'Reporte: SUNAT', 'Envío de Comprobantes', 'Resumen Diario',
            'Plan Contable', 'Centralizar Ventas', 'Centralizar Compras', 'Libro Diario', 'Libro Mayor', 'Balance de Comprobación', 'Estados Financieros',
            'Registro de Ventas (contable)', 'Registro de Compras (contable)', 'CONCAR',
            'Trabajadores', 'Planillas y Boletas', 'Parámetros de Planilla',
            'SIRE Ventas (RVIE)', 'SIRE Compras (RCE)', 'Credenciales SIRE', 'Resumen Tributario'],
    ];

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador gestiona usuarios.');
    }

    /**
     * Empresa dueña de los usuarios: la del administrador o, en un estudio contable, el estudio (sus usuarios pasan de
     * una empresa cliente a otra, pero siguen siendo del estudio).
     */
    private function empresaUsuarios(): string
    {
        return Estudio::ruc() ?? (string) Auth::user()->IdEmpresa;
    }

    /** Usuario de la misma empresa (nunca de otra); en un estudio, todos los de su base son del estudio */
    private function propio(User $usuario): void
    {
        abort_unless(Estudio::activo() || $usuario->IdEmpresa === Auth::user()->IdEmpresa, 404);
    }

    /** @return array<int, int> mod_id que el administrador conectado puede dar a sus usuarios */
    private function modulosPermitidos(): array
    {
        return array_map('intval', Auth::user()->modulos()->pluck('modulos.mod_id')->all());
    }

    /** Módulos elegidos (solo los que el administrador puede repartir) y siempre "Inicio": la bienvenida del sistema */
    private function modulosConInicio(array $elegidos): array
    {
        $ids = array_intersect(array_map('intval', $elegidos), $this->modulosPermitidos());
        if ($inicio = Modulo::where('mod_url', '/inicio')->value('mod_id')) {
            $ids[] = (int) $inicio;
        }

        return array_values(array_unique($ids));
    }

    private function datosFormulario(): array
    {
        // Solo se reparte el menú que tiene la empresa (el de su administrador, que define el panel TUSHPA)
        $modulos = Modulo::whereIn('mod_id', $this->modulosPermitidos())->orderBy('mod_id')->get();
        $presets = [2 => $modulos->pluck('mod_id')->all()];
        foreach (self::PRESETS as $rol => $nombres) {
            $presets[$rol] = $modulos->whereIn('mod_nom', $nombres)->pluck('mod_id')->values()->all();
        }

        return [
            // Solo los roles del rubro del negocio (mozo y promotor en restaurante, doctor en clínica, entrenador en gimnasio)
            'roles' => DB::table('roles')->orderBy('id')->get()->filter(fn ($r) => Rubros::permiteRol((int) $r->id))->values(),
            'modulos' => $modulos->groupBy('mod_gen'),
            'presets' => $presets,
            'sucursales' => EmpresaNegocio::where('IdEmpresa', $this->empresaUsuarios())->get(),
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
            ->when(! Estudio::activo(), fn ($w) => $w->where('users.IdEmpresa', Auth::user()->IdEmpresa))
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

        $d = $request->validate([
            'id_empresa_negocio' => ['required', Rule::exists('empresa_negocios', 'id_empresa_negocio')->where('IdEmpresa', $this->empresaUsuarios())],
            'tdicod' => 'required|in:1,4,7',
            'emp_num_doc' => 'nullable|string|max:15',
            'nombre_completo' => 'required|string|max:200',
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
            'role_id' => ['required', 'exists:roles,id', function ($campo, $valor, $falla) use ($usuario) {
                // Un rol de otro rubro no se asigna (salvo que el usuario ya lo tenga)
                $actual = $usuario ? (int) DB::table('role_user')->where('user_IdUsuario', $usuario->IdUsuario)->value('role_id') : 0;
                if ((int) $valor !== $actual && ! Rubros::permiteRol((int) $valor)) {
                    $falla('Ese rol no corresponde al tipo de negocio de la empresa.');
                }
            }],
            // Mozo y promotor entran desde el celular con este código (de 1 a 8 dígitos; puede ser su DNI)
            'codigo_movil' => ['nullable', 'required_if:role_id,'.implode(',', self::ROLES_CON_CODIGO), 'regex:/^\d{1,8}$/',
                Rule::unique('users', 'codigo_movil')->where('id_empresa_negocio', $sucursal)->ignore($usuario?->IdUsuario, 'IdUsuario')],
            // Mozo y promotor no necesitan contraseña: entran con su código
            'password' => [$nuevo && ! in_array((int) $request->input('role_id'), self::ROLES_CON_CODIGO, true) ? 'required' : 'nullable', 'string', 'min:4', 'confirmed'],
            'acciones_enviadas' => 'nullable|in:1',
            'acciones' => 'nullable|array',
            'acciones.*' => 'string|in:'.implode(',', array_keys(Acciones::LISTA)),
            'modulos' => 'nullable|array',
            'modulos.*' => 'integer|exists:modulos,mod_id',
            'foto' => 'nullable|image|max:5120',
        ], [
            'codigo_movil.required_if' => 'El código móvil es obligatorio para mozos, promotores y cobradores (con él entran desde el celular).',
            'codigo_movil.regex' => 'El código móvil lleva de 1 a 8 números (puede ser su DNI).',
            'codigo_movil.unique' => 'Ese código móvil ya lo tiene otro usuario de la sucursal.',
            'email.regex' => 'El usuario de acceso no puede tener espacios.',
        ], [
            'nombre_completo' => 'Nombre completo', 'email' => 'Usuario de acceso',
            'emp_cor' => 'Correo', 'codigo_movil' => 'Código móvil', 'role_id' => 'Rol', 'password' => 'Contraseña',
            'id_empresa_negocio' => 'Sucursal', 'emp_fec_nac' => 'Fecha de nacimiento', 'foto' => 'Foto',
        ]);

        // El nombre completo va en una sola línea (nombres y apellidos juntos)
        $d['nombre_completo'] = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $d['nombre_completo'])));

        return $d;
    }

    /** Nombre del DNI (padrón de SUNAT propio y, de respaldo, apiperu.dev) */
    public function consultarDni(string $dni): JsonResponse
    {
        $this->autorizar();
        $r = ConsultaPeru::dni($dni);

        return $r ? response()->json(['ok' => true, 'nombre' => mb_strtoupper($r['nombre'])])
            : response()->json(['ok' => false, 'mensaje' => 'No se encontró el DNI. Escribe el nombre a mano.']);
    }

    private function datosEmpleado(array $d): array
    {
        return [
            'emp_nom' => $d['nombre_completo'], 'emp_ape_pat' => '', 'emp_ape_mat' => '', 'tdicod' => $d['tdicod'],
            'emp_num_doc' => $d['emp_num_doc'] ?? null, 'sex_cod' => $d['sex_cod'] ?? null,
            'emp_fec_nac' => $d['emp_fec_nac'] ?? null, 'emp_tel' => $d['emp_tel'] ?? null, 'emp_cel' => $d['emp_cel'] ?? null,
            'emp_cor' => $d['emp_cor'] ?? null, 'emp_dir' => $d['emp_dir'] ?? null,
            'est_cod' => $d['estusu'], 'emp_est' => $d['estusu'] ? 'ACTIVO' : 'INACTIVO',
            'rol_id' => $d['role_id'], 'asistencia' => $d['asistencia'], 'id_empresa_negocio' => $d['id_empresa_negocio'],
        ];
    }

    private function nombreCompleto(array $d): string
    {
        return $d['nombre_completo'];
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
        $delPlan = Estudio::activo() ? User::query() : User::where('IdEmpresa', Auth::user()->IdEmpresa);
        if ($plan && $plan->max_usuarios && $delPlan->count() >= $plan->max_usuarios) {
            return back()->withInput()->with('error', "Tu plan {$plan->nombre} permite hasta {$plan->max_usuarios} usuarios. Para agregar más, cámbiate a un plan mayor.")
                ->withErrors(['plan' => "Tu plan {$plan->nombre} permite hasta {$plan->max_usuarios} usuarios. Para agregar más, cámbiate a un plan mayor."]);
        }
        $d = $this->validar($request);

        try {
            $empleado = $this->crear($d);
        } catch (UniqueConstraintViolationException) {
            // Dos envíos casi a la vez (doble clic): el primero ya lo creó
            return back()->withInput()->withErrors(['email' => 'El usuario de acceso "'.trim($d['email']).'" o su código móvil ya existe. '
                .'Puede que se haya guardado con el primer clic: revisa la lista de usuarios.']);
        }
        $this->guardarFoto($request, $empleado);
        $nuevo = (int) User::where('emp_id', $empleado->emp_id)->value('IdUsuario');
        $this->sincronizarMotorizado($nuevo, (int) $d['role_id']);
        Bitacora::registrar('usuario.crear', 'Creó el usuario '.$this->nombreCompleto($d).' ('.trim($d['email']).')',
            Bitacora::diferencias(null, self::fotoUsuario($nuevo), self::ETIQUETAS_BITACORA),
            ['referencia' => ['usuario', $nuevo], 'alerta' => (int) $d['role_id'] === 2]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario '.$this->nombreCompleto($d).' registrado.');
    }

    /** Crea el empleado, su usuario, su rol y su menú (todo o nada) */
    private function crear(array $d): Empleado
    {
        return DB::transaction(function () use ($d) {
            $empleado = Empleado::create($this->datosEmpleado($d));

            $usuario = User::create([
                'name' => strtok($d['nombre_completo'], ' '),
                'apeusu' => $this->nombreCompleto($d),
                'email' => trim($d['email']),
                'codigo_movil' => $d['codigo_movil'] ?? null,
                // Mozo o promotor sin contraseña: una al azar (entra con su código móvil)
                'password' => bcrypt(($d['password'] ?? null) ?: Str::random(32)),
                'estusu' => (int) $d['estusu'],
                'IdEmpresa' => $this->empresaUsuarios(),
                'id_empresa_negocio' => $d['id_empresa_negocio'],
                'emp_id' => $empleado->emp_id,
                'acciones_bloqueadas' => $this->accionesBloqueadas($d),
            ]);

            DB::table('role_user')->insert([
                'role_id' => $d['role_id'], 'user_IdUsuario' => $usuario->IdUsuario, 'id_empresa_negocio' => $d['id_empresa_negocio'],
            ]);
            $usuario->modulos()->sync($this->modulosConInicio($d['modulos'] ?? []));

            return $empleado;
        });
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
        $antes = self::fotoUsuario((int) $usuario->IdUsuario);

        DB::transaction(function () use ($d, $usuario) {
            $datosEmp = $this->datosEmpleado($d);
            if ($usuario->empleado) {
                $usuario->empleado->update($datosEmp);
            } else {
                $usuario->emp_id = Empleado::create($datosEmp)->emp_id;
            }

            $usuario->fill([
                'name' => strtok($d['nombre_completo'], ' '), 'apeusu' => $this->nombreCompleto($d),
                'email' => trim($d['email']), 'codigo_movil' => $d['codigo_movil'] ?? null,
                'estusu' => (int) $d['estusu'], 'id_empresa_negocio' => $d['id_empresa_negocio'],
                // La sucursal elegida es del estudio: el usuario vuelve a él (nunca queda con RUC de una y sucursal de otra)
                'IdEmpresa' => $this->empresaUsuarios(),
            ]);
            if (! empty($d['password'])) {
                $usuario->password = bcrypt($d['password']);
            }
            if (array_key_exists('acciones_enviadas', $d)) {
                $usuario->acciones_bloqueadas = $this->accionesBloqueadas($d);
            }
            $usuario->save();

            DB::table('role_user')->where('user_IdUsuario', $usuario->IdUsuario)->delete();
            DB::table('role_user')->insert([
                'role_id' => $d['role_id'], 'user_IdUsuario' => $usuario->IdUsuario, 'id_empresa_negocio' => $d['id_empresa_negocio'],
            ]);
            $usuario->modulos()->sync($this->modulosConInicio($d['modulos'] ?? []));
        });
        $this->guardarFoto($request, Empleado::find($usuario->emp_id));
        $this->sincronizarMotorizado((int) $usuario->IdUsuario, (int) $d['role_id']);
        $cambios = Bitacora::diferencias($antes, self::fotoUsuario((int) $usuario->IdUsuario), self::ETIQUETAS_BITACORA);
        if ($cambios) {
            // Rol, permisos, estado y contraseña son lo que más importa revisar
            Bitacora::registrar('usuario.editar', 'Editó el usuario '.$usuario->apeusu, $cambios, ['referencia' => ['usuario', $usuario->IdUsuario],
                'alerta' => (bool) array_intersect(array_keys($cambios), ['rol', 'modulos', 'estado', 'password', 'pin'])]);
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    /** Lo que el formulario dejó sin marcar (sin la sección en el formulario, todo permitido) */
    private function accionesBloqueadas(array $d): ?string
    {
        if (! array_key_exists('acciones_enviadas', $d)) {
            return null;
        }

        return Acciones::guardables(array_values((array) ($d['acciones'] ?? [])), (int) $d['role_id']);
    }

    /** Lo que se compara en la bitácora */
    public const ETIQUETAS_BITACORA = ['nombre' => 'Nombre', 'email' => 'Correo', 'estado' => 'Estado', 'sucursal' => 'Sucursal', 'rol' => 'Rol',
        'modulos' => 'Opciones del menú', 'acciones' => 'Permisos especiales', 'pin' => 'Código de ingreso móvil', 'password' => 'Contraseña'];

    /**
     * El usuario como se ve en pantalla (nombres en vez de números) para la bitácora. El código móvil y la contraseña
     * van solo para saber si cambiaron: la bitácora no guarda su valor.
     *
     * @return array<string, mixed>
     */
    public static function fotoUsuario(int $id): array
    {
        $u = DB::table('users')->where('IdUsuario', $id)->first();
        if (! $u) {
            return [];
        }

        return [
            'nombre' => $u->apeusu ?: $u->name, 'email' => $u->email, 'estado' => (int) $u->estusu === 1 ? 'Activo' : 'Inactivo',
            'sucursal' => DB::table('empresa_negocios')->where('id_empresa_negocio', $u->id_empresa_negocio)->value('nombre_comercial') ?? $u->id_empresa_negocio,
            'rol' => DB::table('role_user as r')->join('roles as ro', 'ro.id', '=', 'r.role_id')->where('r.user_IdUsuario', $id)->value('ro.description'),
            'modulos' => DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')->where('mu.user_IdUsuario', $id)
                ->orderBy('m.mod_nom')->pluck('m.mod_nom')->all(),
            'acciones' => collect(Acciones::LISTA)->keys()->diff(json_decode((string) ($u->acciones_bloqueadas ?? ''), true) ?: [])
                ->map(fn ($k) => Acciones::LISTA[$k][0])->values()->all(),
            'pin' => $u->codigo_movil ?? null,
            'password' => $u->password,
        ];
    }

    /** Rol Motorizado: su ficha de motorizado (para asignarle pedidos) queda enlazada; si deja de serlo, se desactiva */
    private function sincronizarMotorizado(int $usuarioId, int $rol): void
    {
        if ($rol === self::ROL_MOTORIZADO) {
            Delivery::motorizadoDeUsuario($usuarioId);
        } else {
            DB::table('motorizados')->where('IdUsuario', $usuarioId)->update(['activo' => 0]);
        }
    }

    public function destroy(User $usuario)
    {
        $this->autorizar();
        $this->propio($usuario);
        if ((int) $usuario->IdUsuario === (int) Auth::id()) {
            return back()->withErrors(['usuario' => 'No puedes eliminar tu propio usuario.']);
        }

        // Si era motorizado, su ficha queda inactiva (conserva sus entregas)
        DB::table('motorizados')->where('IdUsuario', $usuario->IdUsuario)->update(['activo' => 0]);

        // Si ya tiene ventas o pedidos se desactiva en vez de borrarse, para no perder el historial
        $tieneHistorial = DB::table('cpe_cabecera')->where('IdUsuario', $usuario->IdUsuario)->exists()
            || DB::table('pedidos')->where('IdUsuario', $usuario->IdUsuario)->exists()
            || DB::table('turnos')->where('IdUsuario', $usuario->IdUsuario)->exists();

        if ($tieneHistorial) {
            if ((int) $usuario->estusu !== 0) {
                Bitacora::registrar('usuario.desactivar', "Desactivó al usuario {$usuario->apeusu} ({$usuario->email})", null,
                    ['referencia' => ['usuario', $usuario->IdUsuario], 'alerta' => true]);
            }
            $usuario->update(['estusu' => 0]);
            $usuario->empleado?->update(['est_cod' => '0', 'emp_est' => 'INACTIVO']);

            return back()->with('success', 'El usuario tiene ventas o pedidos registrados: se DESACTIVÓ en vez de eliminarse.');
        }

        $foto = self::fotoUsuario((int) $usuario->IdUsuario);
        DB::transaction(function () use ($usuario, $foto) {
            Bitacora::registrar('usuario.eliminar', "Eliminó al usuario {$usuario->apeusu} ({$usuario->email})",
                Bitacora::diferencias($foto, null, self::ETIQUETAS_BITACORA), ['referencia' => ['usuario', $usuario->IdUsuario], 'alerta' => true]);
            DB::table('role_user')->where('user_IdUsuario', $usuario->IdUsuario)->delete();
            $usuario->modulos()->detach();
            $empleado = $usuario->empleado;
            $usuario->delete();
            $empleado?->delete();
        });

        return back()->with('success', 'Usuario eliminado.');
    }
}
