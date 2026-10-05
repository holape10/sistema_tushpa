<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\{Cliente, Plan};
use App\Support\Tenancy\{Provisionador, Tenancy};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $clientes = Cliente::query()->with('planContratado')
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('ruc', 'like', $q . '%')
                ->orWhere('subdominio', 'like', $q . '%')
                ->orWhere('razon_social', 'like', '%' . $q . '%')
                ->orWhere('nombre_comercial', 'like', '%' . $q . '%')))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $totales = [
            'activos'     => Cliente::where('estado', 'ACTIVO')->count(),
            'suspendidos' => Cliente::where('estado', 'SUSPENDIDO')->count(),
            'por_vencer'  => Cliente::where('estado', 'ACTIVO')->whereNotNull('vence_el')
                ->where('vence_el', '<=', now()->addDays(7)->toDateString())->count(),
            // Ingreso mensual de los clientes activos según su plan
            'ingreso'     => (float) Cliente::where('clientes.estado', 'ACTIVO')->join('planes', 'planes.id', '=', 'clientes.plan_id')->sum('planes.precio'),
        ];

        return view('admin.clientes.index', compact('clientes', 'totales', 'q'));
    }

    public function create()
    {
        return view('admin.clientes.form', ['cliente' => new Cliente(), 'planes' => $this->planes()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->reglas() + [
            // 99999999999 = empresa de demostración (para que prueben el sistema)
            'ruc'       => ['required', 'regex:/^((10|15|17|20)\d{9}|99999999999)$/', 'unique:central.clientes,ruc'],
            'direccion' => 'required|string|max:255',
            'ubigeo'    => 'nullable|digits:6',
            'usuario'   => 'nullable|string|max:60',
            'password'  => 'nullable|string|min:8|max:60',
        ], [
            'ruc.regex'  => 'El RUC debe tener 11 dígitos y empezar con 10, 15, 17 o 20 (o 99999999999 para una demo).',
            'ruc.unique' => 'Ya existe un cliente con ese RUC.',
        ] + $this->mensajes(), $this->nombres());
        if ($error = $this->errorSubdominio($d['subdominio'] ?? null)) {
            return back()->withInput()->withErrors(['subdominio' => $error]);
        }
        $d['plan'] = isset($d['plan_id']) ? Plan::find($d['plan_id'])?->nombre : null;

        // Si no se escriben, el usuario y la contraseña son el RUC (igual que el registro inicial /config)
        $d['usuario'] = ($d['usuario'] ?? null) ?: $d['ruc'];
        $d['password'] = ($d['password'] ?? null) ?: $d['ruc'];

        set_time_limit(180); // crear la base y correr las migraciones toma unos segundos

        try {
            $cliente = Provisionador::crear($d);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'No se pudo crear el cliente: ' . (config('app.debug') ? $e->getMessage() : 'revisa el log.'));
        }

        return redirect()->route('admin.clientes.index')->with('creado', [
            'razon_social' => $cliente->razon_social,
            'url'          => $cliente->url(),
            'base'         => $cliente->base_datos,
            'usuario'      => $d['usuario'],
            'password'     => $d['password'],
        ]);
    }

    public function edit(Cliente $cliente)
    {
        return view('admin.clientes.form', ['cliente' => $cliente, 'planes' => $this->planes()]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $d = $request->validate($this->reglas(), $this->mensajes(), $this->nombres());
        if ($error = $this->errorSubdominio($d['subdominio'] ?? null, $cliente)) {
            return back()->withInput()->withErrors(['subdominio' => $error]);
        }
        $d['subdominio'] = ($d['subdominio'] ?? null) ?: null;
        $d['plan'] = isset($d['plan_id']) ? Plan::find($d['plan_id'])?->nombre : null;
        $cliente->update($d);

        return redirect()->route('admin.clientes.index')->with('ok', "Se actualizó {$cliente->razon_social}.");
    }

    /** Suspender (p. ej. por falta de pago) o reactivar: el cliente suspendido ve un aviso en vez del sistema */
    public function estado(Request $request, Cliente $cliente)
    {
        if ($cliente->activo()) {
            $d = $request->validate(['motivo' => 'nullable|string|max:150']);
            $cliente->update(['estado' => 'SUSPENDIDO', 'motivo_suspension' => $d['motivo'] ?? null]);
            return back()->with('ok', "Se suspendió {$cliente->razon_social}.");
        }

        $cliente->update(['estado' => 'ACTIVO', 'motivo_suspension' => null]);
        return back()->with('ok', "Se reactivó {$cliente->razon_social}.");
    }

    /**
     * Pide el certificado https al servidor: deja una marca que el servidor (root) atiende al instante
     * con deploy/ssl-clientes.sh (systemd .path) o, como respaldo, en el cron de cada minuto.
     */
    public function https(Cliente $cliente)
    {
        @mkdir(storage_path('app'), 0775, true);
        file_put_contents(storage_path('app/ssl-solicitud'), $cliente->host() . ' ' . now()->toDateTimeString() . "\n", FILE_APPEND);
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
        if (!$sub) {
            return null;
        }
        if (in_array($sub, Tenancy::subdominiosReservados(), true)) {
            return "El subdominio {$sub} está reservado.";
        }
        if (Cliente::where('subdominio', $sub)->when($actual, fn($q) => $q->where('id', '!=', $actual->id))->exists()) {
            return "El subdominio {$sub} ya lo usa otro cliente.";
        }
        return null;
    }

    public function consultarRuc(string $ruc)
    {
        try {
            $r = Http::timeout(8)->withOptions(['verify' => false])
                ->get("https://consultas.holape.app/api/v1/ruc/{$ruc}")->json();
            if (!empty($r['success'])) {
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
            'razon_social'      => 'required|string|max:255',
            'nombre_comercial'  => 'nullable|string|max:255',
            'plan_id'           => 'nullable|integer|exists:central.planes,id',
            'subdominio'        => ['nullable', 'string', 'max:40', 'regex:/^(?!\d+$)[a-z0-9][a-z0-9-]*[a-z0-9]$/'],
            'vence_el'          => 'nullable|date',
            'contacto_nombre'   => 'nullable|string|max:120',
            'contacto_telefono' => 'nullable|string|max:20',
            'contacto_correo'   => 'nullable|email|max:120',
            'notas'             => 'nullable|string|max:2000',
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
