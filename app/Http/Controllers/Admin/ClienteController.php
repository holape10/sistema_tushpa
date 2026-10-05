<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Tenancy\Provisionador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $clientes = Cliente::query()
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('ruc', 'like', $q . '%')
                ->orWhere('razon_social', 'like', '%' . $q . '%')
                ->orWhere('nombre_comercial', 'like', '%' . $q . '%')))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $totales = [
            'activos'     => Cliente::where('estado', 'ACTIVO')->count(),
            'suspendidos' => Cliente::where('estado', 'SUSPENDIDO')->count(),
            'por_vencer'  => Cliente::where('estado', 'ACTIVO')->whereNotNull('vence_el')
                ->where('vence_el', '<=', now()->addDays(7)->toDateString())->count(),
        ];

        return view('admin.clientes.index', compact('clientes', 'totales', 'q'));
    }

    public function create()
    {
        return view('admin.clientes.form', ['cliente' => new Cliente()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->reglas() + [
            'ruc'       => ['required', 'regex:/^(10|15|17|20)\d{9}$/', 'unique:central.clientes,ruc'],
            'direccion' => 'required|string|max:255',
            'ubigeo'    => 'nullable|digits:6',
            'usuario'   => 'nullable|string|max:60',
            'password'  => 'nullable|string|min:8|max:60',
        ], [
            'ruc.regex'  => 'El RUC debe tener 11 dígitos y empezar con 10, 15, 17 o 20.',
            'ruc.unique' => 'Ya existe un cliente con ese RUC.',
        ], $this->nombres());

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
        return view('admin.clientes.form', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $cliente->update($request->validate($this->reglas(), [], $this->nombres()));

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
            'plan'              => 'nullable|string|max:50',
            'vence_el'          => 'nullable|date',
            'contacto_nombre'   => 'nullable|string|max:120',
            'contacto_telefono' => 'nullable|string|max:20',
            'contacto_correo'   => 'nullable|email|max:120',
            'notas'             => 'nullable|string|max:2000',
        ];
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
