<?php
namespace App\Http\Controllers;

use App\Models\{Empresa, EmpresaNegocio};
use App\Support\Tienda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, RateLimiter};

/**
 * Tienda virtual pública: {subdominio}/tiendavirtual.
 * Ingreso: usuario = DNI/RUC. Si ya compró antes y nunca cambió su contraseña, entra con su DNI/RUC como contraseña
 * y se le pide cambiarla. Si es nuevo, se registra con su DNI/RUC.
 */
class TiendaController extends Controller
{
    /** Sucursal de la tienda o la página "no disponible" */
    private function negocio(): EmpresaNegocio
    {
        $negocio = Tienda::permitidaPorPlan() ? Tienda::sucursal() : null;
        if (!$negocio) {
            abort(response()->view('tienda.no_disponible', [], 404));
        }
        return $negocio;
    }

    private function comunes(EmpresaNegocio $negocio): array
    {
        return [
            'negocio' => $negocio,
            'empresa' => Empresa::find($negocio->IdEmpresa),
            'cliente' => Tienda::cliente($negocio),
        ];
    }

    public function index()
    {
        $negocio = $this->negocio();
        return view('tienda.index', $this->comunes($negocio) + Tienda::catalogo($negocio));
    }

    // ------------------------------------------------------------------ acceso

    public function login()
    {
        $negocio = $this->negocio();
        return view('tienda.login', $this->comunes($negocio));
    }

    public function entrar(Request $request)
    {
        $negocio = $this->negocio();
        $d = $request->validate(['doc' => 'required|string|max:15', 'password' => 'required|string|max:100'], [], ['doc' => 'DNI o RUC', 'password' => 'contraseña']);
        $doc = preg_replace('/\D/', '', $d['doc']);

        $clave = 'tienda-login:' . $request->ip() . ':' . $doc;
        if (RateLimiter::tooManyAttempts($clave, 8)) {
            return back()->withInput()->withErrors(['doc' => 'Demasiados intentos. Espera ' . RateLimiter::availableIn($clave) . ' segundos.']);
        }

        $cli = $doc !== '00000000' ? DB::table('cliente')->where('rucemp', $negocio->IdEmpresa)->where('clinum', $doc)->first() : null;
        if (!$cli) {
            return back()->withInput()->withErrors(['doc' => 'No encontramos ese DNI/RUC. Si es tu primera compra, crea tu cuenta.']);
        }
        // Sin contraseña propia: la primera vez entra con su DNI/RUC
        $ok = $cli->tienda_password ? Hash::check($d['password'], $cli->tienda_password) : hash_equals($cli->clinum, $d['password']);
        if (!$ok) {
            RateLimiter::hit($clave, 300);
            return back()->withInput()->withErrors(['password' => $cli->tienda_password
                ? 'Contraseña incorrecta.' : 'Contraseña incorrecta. Si es tu primer ingreso, tu contraseña es tu mismo DNI/RUC.']);
        }
        RateLimiter::clear($clave);

        $request->session()->regenerate();
        session(['tienda_cliente' => $cli->clicod, 'tienda_cambiar_clave' => !$cli->tienda_password]);
        DB::table('cliente')->where('clicod', $cli->clicod)->update(['tienda_acceso' => now()]);

        return !$cli->tienda_password
            ? redirect()->route('tienda.cuenta')->with('aviso', 'Bienvenido. Por seguridad, crea tu propia contraseña.')
            : redirect()->intended(route('tienda.index'));
    }

    public function registro()
    {
        $negocio = $this->negocio();
        return view('tienda.registro', $this->comunes($negocio));
    }

    public function registrar(Request $request)
    {
        $negocio = $this->negocio();
        $d = $request->validate([
            'doc' => 'required|string|max:15', 'nombre' => 'required|string|max:150', 'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:50', 'direccion' => 'nullable|string|max:200',
            'password' => 'required|string|min:6|max:100|confirmed',
        ], [], ['doc' => 'DNI o RUC', 'nombre' => 'nombre o razón social', 'password' => 'contraseña']);
        $doc = preg_replace('/\D/', '', $d['doc']);
        if (!preg_match('/^(\d{8}|(10|15|17|20)\d{9})$/', $doc) || $doc === '00000000') {
            return back()->withInput()->withErrors(['doc' => 'Escribe un DNI de 8 dígitos o un RUC de 11 dígitos.']);
        }
        if (DB::table('cliente')->where('rucemp', $negocio->IdEmpresa)->where('clinum', $doc)->exists()) {
            return back()->withInput()->withErrors(['doc' => 'Ya eres cliente: ingresa con tu DNI/RUC (si es tu primer ingreso, la contraseña es tu mismo DNI/RUC).']);
        }

        $id = DB::table('cliente')->insertGetId([
            'tdicod' => strlen($doc) === 11 ? '6' : '1', 'clinum' => $doc, 'clinom' => mb_strtoupper(trim($d['nombre'])),
            'rucemp' => $negocio->IdEmpresa, 'clidir' => mb_strtoupper(trim((string) ($d['direccion'] ?? ''))) ?: '--',
            'clicor' => $d['correo'] ?? null, 'telefono' => $d['telefono'] ?? null, 'cliest' => 'Activo',
            'tienda_password' => Hash::make($d['password']), 'tienda_acceso' => now(),
        ]);
        $request->session()->regenerate();
        session(['tienda_cliente' => $id, 'tienda_cambiar_clave' => false]);

        return redirect()->route('tienda.index')->with('aviso', '¡Listo! Tu cuenta fue creada.');
    }

    public function salir(Request $request)
    {
        $request->session()->forget(['tienda_cliente', 'tienda_cambiar_clave']);
        $request->session()->regenerateToken();
        return redirect()->route('tienda.index');
    }

    // ------------------------------------------------------------------ mi cuenta

    public function cuenta()
    {
        $negocio = $this->negocio();
        $datos = $this->comunes($negocio);
        if (!$datos['cliente']) {
            return redirect()->route('tienda.login');
        }
        $pedidos = DB::table('proformas')->where('id_empresa_negocio', $negocio->id_empresa_negocio)
            ->where('clinum', $datos['cliente']->clinum)->where('origen', 'WEB')->orderByDesc('id_proforma')->limit(30)->get();
        $compras = DB::table('cpe_cabecera')->where('id_empresa_negocio', $negocio->id_empresa_negocio)->where('ccandi', $datos['cliente']->clinum)
            ->whereNull('ccabaj')->orderByDesc('IdCpe_cabecera')->limit(30)->get(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccafem', 'ccaitv']);
        return view('tienda.cuenta', $datos + compact('pedidos', 'compras'));
    }

    public function cambiarClave(Request $request)
    {
        $negocio = $this->negocio();
        $cli = Tienda::cliente($negocio);
        abort_unless($cli, 403);
        $request->validate(['password' => 'required|string|min:6|max:100|confirmed'], [], ['password' => 'nueva contraseña']);
        if ($request->password === $cli->clinum) {
            return back()->withErrors(['password' => 'La nueva contraseña no puede ser tu DNI/RUC.']);
        }
        DB::table('cliente')->where('clicod', $cli->clicod)->update(['tienda_password' => Hash::make($request->password)]);
        session(['tienda_cambiar_clave' => false]);
        return back()->with('aviso', 'Tu contraseña fue cambiada.');
    }

    // ------------------------------------------------------------------ pedido

    public function pedido(Request $request)
    {
        $negocio = $this->negocio();
        $cli = Tienda::cliente($negocio);
        if (!$cli) {
            return response()->json(['estado' => 'login', 'mensaje' => 'Ingresa o crea tu cuenta para enviar el pedido.']);
        }
        $d = $request->validate([
            'items' => 'required|array|min:1|max:100', 'items.*.id' => 'required|integer', 'items.*.cantidad' => 'required|numeric|min:0.01|max:9999',
            'entrega' => 'required|in:RECOJO EN TIENDA,DELIVERY', 'pago' => 'required|string|max:30',
            'direccion' => 'nullable|required_if:entrega,DELIVERY|string|max:150', 'telefono' => 'required|string|max:20', 'nota' => 'nullable|string|max:100',
        ], ['direccion.required_if' => 'Escribe la dirección de entrega.'], ['telefono' => 'teléfono']);

        try {
            $p = Tienda::pedido($negocio, $cli, $d['items'], [
                'entrega' => $d['entrega'], 'pago' => mb_strtoupper($d['pago']), 'direccion' => mb_strtoupper(trim((string) ($d['direccion'] ?? ''))),
                'telefono' => $d['telefono'], 'nota' => mb_strtoupper(trim((string) ($d['nota'] ?? ''))),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        }
        if ($d['telefono'] && !$cli->telefono) {
            DB::table('cliente')->where('clicod', $cli->clicod)->update(['telefono' => $d['telefono']]);
        }

        // WhatsApp de la empresa con el resumen del pedido (opcional)
        $wa = preg_replace('/\D/', '', (string) $negocio->tienda_whatsapp);
        $texto = "Hola, hice el pedido {$p['numero']} por S/ " . number_format($p['total'], 2) . " ({$d['entrega']}, pago: {$d['pago']}). Soy {$cli->clinom}.";
        return response()->json($p + ['estado' => 'success',
            'whatsapp' => $wa ? 'https://wa.me/' . (strlen($wa) === 9 ? '51' . $wa : $wa) . '?text=' . rawurlencode($texto) : null]);
    }
}
