<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Models\Central\Plan;
use App\Models\Central\Superadmin;
use App\Models\User;
use App\Support\Rubros;
use App\Support\Tenancy\Provisionador;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    private function esDueno(): bool
    {
        return (bool) Auth::guard('superadmin')->user()->es_dueno;
    }

    /** Empresas que ve este usuario del panel: el dueño todas; los demás, solo las que crearon */
    private function mios()
    {
        return Cliente::query()->when(! $this->esDueno(), fn ($q) => $q->where('creado_por', Auth::guard('superadmin')->id()));
    }

    private function autorizar(Cliente $cliente): void
    {
        abort_unless($this->esDueno() || (int) $cliente->creado_por === (int) Auth::guard('superadmin')->id(), 404);
    }

    /**
     * Comprobantes emitidos por tipo (01 factura, 03 boleta, 07/08 notas, 13 nota de venta…), total y del mes.
     * Se lee de la base de cada empresa y se guarda 10 minutos para no cargar el panel.
     *
     * @return array{tipos: array<string,int>, total: int, mes: int}
     */
    private function comprobantes(Cliente $cliente): array
    {
        return Cache::store('file')->remember("panel-cpe-{$cliente->id}", 600, function () use ($cliente) {
            try {
                return Tenancy::en($cliente->base_datos, function () {
                    if (! Schema::hasTable('cpe_cabecera')) {
                        return ['tipos' => [], 'total' => 0, 'mes' => 0];
                    }
                    $tipos = DB::table('cpe_cabecera')->whereNull('ccabaj')->groupBy('tdocod')->orderBy('tdocod')
                        ->selectRaw('tdocod, COUNT(*) n')->pluck('n', 'tdocod')->map(fn ($n) => (int) $n)->all();
                    $mes = DB::table('cpe_cabecera')->whereNull('ccabaj')->where('ccafem', '>=', now()->startOfMonth()->toDateString())->count();

                    return ['tipos' => $tipos, 'total' => array_sum($tipos), 'mes' => $mes];
                });
            } catch (\Throwable $e) {
                return ['tipos' => [], 'total' => 0, 'mes' => 0];
            }
        });
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $clientes = $this->mios()->with('planContratado')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('ruc', 'like', $q.'%')
                ->orWhere('subdominio', 'like', $q.'%')
                ->orWhere('razon_social', 'like', '%'.$q.'%')
                ->orWhere('nombre_comercial', 'like', '%'.$q.'%')))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $totales = [
            'activos' => $this->mios()->where('estado', 'ACTIVO')->count(),
            'suspendidos' => $this->mios()->where('estado', 'SUSPENDIDO')->count(),
            'por_vencer' => $this->mios()->where('estado', 'ACTIVO')->whereNotNull('vence_el')
                ->where('vence_el', '<=', now()->addDays(7)->toDateString())->count(),
            // Ingreso mensual de los clientes activos según su plan
            'ingreso' => (float) $this->mios()->where('clientes.estado', 'ACTIVO')->join('planes', 'planes.id', '=', 'clientes.plan_id')->sum('planes.precio'),
        ];
        $comprobantes = $clientes->getCollection()->mapWithKeys(fn ($cl) => [$cl->id => $this->comprobantes($cl)]);
        $usuarios = $this->esDueno() ? Superadmin::orderBy('nombre')->pluck('nombre', 'id') : collect();

        return view('admin.clientes.index', compact('clientes', 'totales', 'q', 'comprobantes', 'usuarios'));
    }

    public function create()
    {
        return view('admin.clientes.form', ['cliente' => new Cliente, 'planes' => $this->planes(),
            'rubros' => Rubros::RUBROS, 'catalogo' => Rubros::catalogo(), 'menuActual' => null]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->reglas() + [
            // 99999999999 = empresa de demostración (para que prueben el sistema)
            'ruc' => ['required', 'regex:/^((10|15|17|20)\d{9}|99999999999)$/', 'unique:central.clientes,ruc'],
            'direccion' => 'required|string|max:255',
            'ubigeo' => 'nullable|digits:6',
            'usuario' => 'nullable|string|max:60',
            'password' => 'nullable|string|min:8|max:60',
            'rubro' => ['required', Rule::in(array_keys(Rubros::RUBROS))],
            'modulos' => 'nullable|array', 'modulos.*' => 'string|max:100',
        ], [
            'ruc.regex' => 'El RUC debe tener 11 dígitos y empezar con 10, 15, 17 o 20 (o 99999999999 para una demo).',
            'ruc.unique' => 'Ya existe un cliente con ese RUC.',
        ] + $this->mensajes(), $this->nombres());
        if ($error = $this->errorSubdominio($d['subdominio'] ?? null)) {
            return back()->withInput()->withErrors(['subdominio' => $error]);
        }
        $d['plan'] = isset($d['plan_id']) ? Plan::find($d['plan_id'])?->nombre : null;

        // Si no se escriben, el usuario y la contraseña son el RUC (igual que el registro inicial /config)
        $d['usuario'] = ($d['usuario'] ?? null) ?: $d['ruc'];
        $d['password'] = ($d['password'] ?? null) ?: $d['ruc'];
        // Menú con el que arranca su administrador: lo marcado en el formulario (o el del rubro)
        $d['modulos'] = ! empty($d['modulos']) ? $d['modulos'] : Rubros::urls($d['rubro']);

        set_time_limit(180); // crear la base y correr las migraciones toma unos segundos

        try {
            $cliente = Provisionador::crear($d);
            $cliente->update(['creado_por' => Auth::guard('superadmin')->id()]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No se pudo crear el cliente: '.(config('app.debug') ? $e->getMessage() : 'revisa el log.'));
        }

        return redirect()->route('admin.clientes.index')->with('creado', [
            'razon_social' => $cliente->razon_social,
            'url' => $cliente->url(),
            'base' => $cliente->base_datos,
            'usuario' => $d['usuario'],
            'password' => $d['password'],
        ]);
    }

    public function edit(Cliente $cliente)
    {
        $this->autorizar($cliente);

        return view('admin.clientes.form', ['cliente' => $cliente, 'planes' => $this->planes(),
            'usuarios' => $this->esDueno() ? Superadmin::orderBy('nombre')->pluck('nombre', 'id') : collect(),
            'rubros' => Rubros::RUBROS, 'catalogo' => Rubros::catalogo(), 'menuActual' => $this->menuAdministrador($cliente)]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->autorizar($cliente);
        $d = $request->validate($this->reglas() + ['creado_por' => 'nullable|integer|exists:central.superadmins,id',
            'rubro' => ['nullable', Rule::in(array_keys(Rubros::RUBROS))],
            'cambiar_menu' => 'nullable|boolean', 'modulos' => 'nullable|array', 'modulos.*' => 'string|max:100'], $this->mensajes(), $this->nombres());
        if (! $this->esDueno()) {
            unset($d['creado_por']);   // solo el dueño reasigna empresas
        }
        if ($error = $this->errorSubdominio($d['subdominio'] ?? null, $cliente)) {
            return back()->withInput()->withErrors(['subdominio' => $error]);
        }
        $d['subdominio'] = ($d['subdominio'] ?? null) ?: null;
        $d['plan'] = isset($d['plan_id']) ? Plan::find($d['plan_id'])?->nombre : null;
        $menu = ! empty($d['cambiar_menu']) ? ($d['modulos'] ?? []) : null;
        unset($d['cambiar_menu'], $d['modulos']);
        $cliente->update($d);

        $aviso = '';
        if ($menu !== null) {
            try {
                $n = $this->guardarMenuAdministrador($cliente, $menu);
                $aviso = " Menú de sus administradores actualizado ({$n} opciones).";
            } catch (\Throwable $e) {
                report($e);
                $aviso = ' No se pudo actualizar su menú: '.$e->getMessage();
            }
        }

        return redirect()->route('admin.clientes.index')->with('ok', "Se actualizó {$cliente->razon_social}.".$aviso);
    }

    /** URLs del menú que hoy tiene el administrador principal de la empresa (null si no se pudo leer su base) */
    private function menuAdministrador(Cliente $cliente): ?array
    {
        try {
            return Tenancy::en($cliente->base_datos, function () {
                $admin = DB::table('role_user')->where('role_id', 2)->orderBy('user_IdUsuario')->value('user_IdUsuario');

                return $admin ? DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')
                    ->where('mu.user_IdUsuario', $admin)->pluck('m.mod_url')->unique()->values()->all() : [];
            });
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Pone el menú elegido a todos los administradores de la empresa; los demás usuarios no pueden tener más que eso */
    private function guardarMenuAdministrador(Cliente $cliente, array $urls): int
    {
        return Tenancy::en($cliente->base_datos, function () use ($urls) {
            $ids = Rubros::ids($urls);
            $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique();
            DB::transaction(function () use ($admins, $ids) {
                foreach ($admins as $id) {
                    User::find($id)?->modulos()->sync($ids);
                }
                // Lo que se quitó al administrador tampoco lo ven sus trabajadores
                DB::table('modulos_usuario')->whereNotIn('mod_id', $ids ?: [0])->delete();
            });

            return count($ids);
        });
    }

    /**
     * Advertencia antes de suspender: el cliente la ve en su sistema (todas las pantallas) con la fecha límite.
     * Si se marca, se suspende solo al pasar esa fecha (tarea programada clientes:suspender-vencidos).
     */
    public function aviso(Request $request, Cliente $cliente)
    {
        $this->autorizar($cliente);
        $d = $request->validate([
            'aviso_mensaje' => 'required|string|min:10|max:600',
            'aviso_fecha' => 'nullable|date|after_or_equal:today',
            'aviso_suspender' => 'nullable|boolean',
        ], [], ['aviso_mensaje' => 'el mensaje', 'aviso_fecha' => 'la fecha límite']);
        if (! empty($d['aviso_suspender']) && empty($d['aviso_fecha'])) {
            return back()->withErrors(['aviso_fecha' => 'Para suspender automáticamente elige la fecha límite.']);
        }
        $cliente->update(['aviso_mensaje' => trim($d['aviso_mensaje']), 'aviso_fecha' => $d['aviso_fecha'] ?? null,
            'aviso_suspender' => (int) ($d['aviso_suspender'] ?? 0), 'aviso_creado' => now()]);

        return back()->with('ok', "Advertencia enviada a {$cliente->razon_social}: la verá al entrar a su sistema.");
    }

    public function quitarAviso(Cliente $cliente)
    {
        $this->autorizar($cliente);
        $cliente->update(['aviso_mensaje' => null, 'aviso_fecha' => null, 'aviso_suspender' => 0]);

        return back()->with('ok', "Se quitó la advertencia de {$cliente->razon_social}.");
    }

    /** Suspender (p. ej. por falta de pago) o reactivar: el cliente suspendido ve un aviso en vez del sistema */
    public function estado(Request $request, Cliente $cliente)
    {
        $this->autorizar($cliente);
        if ($cliente->activo()) {
            $d = $request->validate(['motivo' => 'nullable|string|max:150']);
            $cliente->update(['estado' => 'SUSPENDIDO', 'motivo_suspension' => $d['motivo'] ?? null]);

            return back()->with('ok', "Se suspendió {$cliente->razon_social}.");
        }

        // Al reactivar (ya pagó) se quita también la advertencia
        $cliente->update(['estado' => 'ACTIVO', 'motivo_suspension' => null, 'aviso_mensaje' => null, 'aviso_fecha' => null, 'aviso_suspender' => 0]);

        return back()->with('ok', "Se reactivó {$cliente->razon_social}.");
    }

    /**
     * Pide el certificado https al servidor: deja una marca que el servidor (root) atiende al instante
     * con deploy/ssl-clientes.sh (systemd .path) o, como respaldo, en el cron de cada minuto.
     */
    public function https(Cliente $cliente)
    {
        $this->autorizar($cliente);
        @mkdir(storage_path('app'), 0775, true);
        file_put_contents(storage_path('app/ssl-solicitud'), $cliente->host().' '.now()->toDateTimeString()."\n", FILE_APPEND);

        return back()->with('ok', "Se pidió el https de {$cliente->host()}. Estará listo en unos segundos (máximo 1 minuto); recarga la página para ver el candado.");
    }

    // ------------------------------------------------------------------ planes

    public function planesIndex()
    {
        $planes = Plan::orderBy('orden')->orderBy('precio')->get();
        $uso = Cliente::where('estado', 'ACTIVO')->whereNotNull('plan_id')->groupBy('plan_id')->selectRaw('plan_id, COUNT(*) n')->pluck('n', 'plan_id');

        return view('admin.planes.index', compact('planes', 'uso'));
    }

    public function planesGuardar(Request $request, ?Plan $plan = null)
    {
        abort_unless($this->esDueno(), 403, 'Solo el dueño del sistema cambia los planes.');
        $d = $request->validate([
            'nombre' => 'required|string|max:60', 'precio' => 'required|numeric|min:0|max:99999',
            'descripcion' => 'nullable|string|max:255', 'caracteristicas' => 'nullable|string|max:3000',
            'max_usuarios' => 'nullable|integer|min:1|max:9999', 'orden' => 'nullable|integer|min:0|max:999',
        ], [], ['max_usuarios' => 'máximo de usuarios']);
        $d += ['destacado' => $request->boolean('destacado'), 'tienda_virtual' => $request->boolean('tienda_virtual'),
            'activo' => $request->boolean('activo', true)];
        $d['nombre'] = mb_strtoupper(trim($d['nombre']));
        $d['orden'] = $d['orden'] ?? 0;

        if ($d['destacado']) {
            Plan::where('id', '!=', $plan?->id ?? 0)->update(['destacado' => false]);   // un solo "Más popular"
        }
        if ($plan && $plan->exists) {
            $plan->update($d);
            Cliente::where('plan_id', $plan->id)->update(['plan' => $d['nombre']]);
        } else {
            Plan::create($d);
        }

        return redirect()->route('admin.planes.index')->with('ok', "Plan {$d['nombre']} guardado.");
    }

    private function planes()
    {
        return Plan::where('activo', true)->orderBy('orden')->orderBy('precio')->get();
    }

    /** Subdominio propio: letras, números y guiones; no puede ser un RUC, uno reservado ni el de otro cliente */
    private function errorSubdominio(?string $sub, ?Cliente $actual = null): ?string
    {
        if (! $sub) {
            return null;
        }
        if (in_array($sub, Tenancy::subdominiosReservados(), true)) {
            return "El subdominio {$sub} está reservado.";
        }
        if (Cliente::where('subdominio', $sub)->when($actual, fn ($q) => $q->where('id', '!=', $actual->id))->exists()) {
            return "El subdominio {$sub} ya lo usa otro cliente.";
        }

        return null;
    }

    public function consultarRuc(string $ruc)
    {
        try {
            $r = Http::timeout(8)->withOptions(['verify' => false])
                ->get("https://consultas.holape.app/api/v1/ruc/{$ruc}")->json();
            if (! empty($r['success'])) {
                return response()->json([
                    'nom' => $r['data']['razon_social'], 'dir' => $r['data']['direccion'], 'ubigeo' => $r['data']['ubigeo'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            // el servicio no respondió: se llena a mano
        }

        return response()->json(['error' => 'No se encontró el RUC. Ingresa los datos manualmente.'], 404);
    }

    private function reglas(): array
    {
        return [
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'plan_id' => 'nullable|integer|exists:central.planes,id',
            'subdominio' => ['nullable', 'string', 'max:40', 'regex:/^(?!\d+$)[a-z0-9][a-z0-9-]*[a-z0-9]$/'],
            'vence_el' => 'nullable|date',
            'contacto_nombre' => 'nullable|string|max:120',
            'contacto_telefono' => 'nullable|string|max:20',
            'contacto_correo' => 'nullable|email|max:120',
            'notas' => 'nullable|string|max:2000',
        ];
    }

    private function mensajes(): array
    {
        return ['subdominio.regex' => 'El subdominio solo lleva letras minúsculas, números y guiones (ej. demo, mi-tienda), y no puede ser solo números.'];
    }

    private function nombres(): array
    {
        return [
            'ruc' => 'RUC', 'razon_social' => 'razón social', 'nombre_comercial' => 'nombre comercial',
            'direccion' => 'dirección', 'vence_el' => 'vencimiento', 'contacto_correo' => 'correo de contacto',
            'password' => 'contraseña',
        ];
    }
}
