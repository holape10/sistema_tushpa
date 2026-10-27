<?php

namespace App\Http\Controllers;

use App\Models\Central\Cliente;
use App\Models\Central\Plan;
use App\Support\Captcha;
use App\Support\ConsultaPeru;
use App\Support\Rubros;
use App\Support\SeoSitio;
use App\Support\Tenancy\RegistroWeb;
use App\Support\Tenancy\Tenancy;
use App\Support\Tutoriales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Web pública en el dominio principal (tushpa.app): información, planes, tutoriales, contacto,
 * "Ingresar" (lleva a la empresa por su RUC o subdominio) y registro con prueba gratis.
 */
class SitioController extends Controller
{
    public function inicio(): View
    {
        return view('sitio.inicio', ['planes' => $this->planes()]);
    }

    /** Tutoriales por rubro (se crean en el panel › Tutoriales) */
    /** Programa de revendedores: venden TUSHPA con su propio panel; los precios se consultan por WhatsApp */
    public function reseller(): View
    {
        return view('sitio.reseller');
    }

    public function tutorial(): View
    {
        return view('sitio.tutorial', ['tutoriales' => Tutoriales::activos(), 'rubros' => Tutoriales::rubros()]);
    }

    /** Un tutorial paso a paso, con otros del mismo rubro */
    public function tutorialVer(string $slug): View
    {
        $todos = Tutoriales::activos();
        $t = $todos->firstWhere('slug', $slug);
        abort_unless($t, 404);
        Tutoriales::db()->where('id', $t->id)->increment('vistas');

        return view('sitio.tutorial_ver', ['t' => $t, 'rubros' => Tutoriales::rubros(),
            'otros' => $todos->where('rubro', $t->rubro)->where('id', '!=', $t->id)->take(6)->values()]);
    }

    /** Página de SEO local de una ciudad (se define en config/ciudades.php) */
    public function ciudad(string $ciudad): View
    {
        $datos = SeoSitio::ciudad($ciudad);
        abort_unless($datos, 404);

        return view('sitio.ciudad', ['c' => $datos]);
    }

    /** sitemap.xml para Google y Bing: portada, tutoriales y una página por ciudad */
    public function sitemap(): Response
    {
        return response(SeoSitio::sitemapXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Formulario de contacto: queda en el panel admin y, si se configuró, también llega por correo */
    public function contacto(Request $request): JsonResponse
    {
        if ($request->filled('web')) {   // campo trampa que solo llenan los robots
            return response()->json(['success' => true, 'message' => 'Gracias, te responderemos pronto.']);
        }
        $d = $request->validate([
            'nombre' => 'required|string|max:120', 'email' => 'required|email|max:120', 'telefono' => 'nullable|string|max:30',
            'empresa' => 'nullable|string|max:150', 'servicio' => 'nullable|string|max:40', 'mensaje' => 'required|string|max:3000',
        ], [], ['email' => 'correo', 'mensaje' => 'mensaje']);

        DB::connection('central')->table('web_contactos')->insert([
            'nombre' => $d['nombre'], 'correo' => $d['email'], 'telefono' => $d['telefono'] ?? null, 'empresa' => $d['empresa'] ?? null,
            'servicio' => $d['servicio'] ?? null, 'mensaje' => $d['mensaje'], 'ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($correo = config('sitio.correo_contacto')) {
            try {
                Mail::raw("Nombre: {$d['nombre']}\nCorreo: {$d['email']}\nTeléfono: ".($d['telefono'] ?? '-')."\nEmpresa: ".($d['empresa'] ?? '-')
                    ."\nServicio: ".($d['servicio'] ?? '-')."\n\n{$d['mensaje']}", fn ($m) => $m->to($correo)->replyTo($d['email'], $d['nombre'])
                        ->subject('Nuevo contacto web - '.$d['nombre']));
            } catch (\Throwable $e) {
                report($e);   // el mensaje ya quedó guardado en el panel
            }
        }

        return response()->json(['success' => true, 'message' => '¡Gracias! Recibimos tu mensaje y te responderemos en menos de 24 horas.']);
    }

    public function ingresar(): View
    {
        return view('sitio.ingresar');
    }

    /** Lleva a la empresa por su RUC o su subdominio */
    public function irAEmpresa(Request $request): RedirectResponse
    {
        $cuenta = mb_strtolower(trim((string) $request->validate(['cuenta' => 'required|string|max:60'], [], ['cuenta' => 'RUC o subdominio'])['cuenta']));
        $cuenta = preg_replace('~^(https?://)?([a-z0-9-]+)(\.'.preg_quote((string) config('tenancy.dominio'), '~').')?/?.*$~', '$2', $cuenta);
        $cliente = Cliente::where('estado', '!=', 'ELIMINADO')
            ->where(fn ($q) => $q->where('ruc', $cuenta)->orWhere('subdominio', $cuenta))->first();

        return $cliente
            ? redirect()->away($this->urlEmpresa($cliente).'/login')
            : back()->withInput()->withErrors(['cuenta' => 'No encontramos una empresa con ese RUC o subdominio.']);
    }

    public function registro(Request $request): View
    {
        $planes = $this->planes();

        return view('sitio.registro', [
            'planes' => $planes,
            'rubros' => collect(Rubros::RUBROS)->except('COMPLETO')->map(fn ($r) => $r['nombre'].' — '.$r['ejemplos']),
            'planElegido' => (int) $request->get('plan', $planes->firstWhere('destacado', true)?->id ?? $planes->first()?->id),
            'turnstile' => config('sitio.turnstile.sitio'),
        ]);
    }

    /** Datos del RUC para llenar el formulario (y avisar si ya tiene cuenta) */
    /** Aviso en vivo en el registro: ¿la dirección (subdominio) está libre? */
    public function revisarSubdominio(string $sub): JsonResponse
    {
        return response()->json(Tenancy::revisarSubdominio($sub));
    }

    public function consultarRuc(string $ruc): JsonResponse
    {
        if ($error = $this->errorRucTomado($ruc)) {
            return response()->json(['error' => $error], 409);
        }
        $d = $this->datosRuc($ruc);
        if (! $d) {
            return response()->json(['error' => 'No encontramos ese RUC en SUNAT. Revísalo o escríbenos por WhatsApp.'], 404);
        }

        return response()->json(['nombre' => $d['nombre'], 'direccion' => $d['direccion'], 'estado' => $d['estado'], 'condicion' => $d['condicion']]);
    }

    public function registrar(Request $request): RedirectResponse
    {
        if ($request->filled('web')) {   // campo trampa
            abort(422);
        }
        $d = $request->validate([
            'ruc' => ['required', 'regex:/^(10|15|17|20)\d{9}$/'],
            'nombre_comercial' => 'nullable|string|max:120',
            'rubro' => ['required', Rule::in(collect(Rubros::RUBROS)->except('COMPLETO')->keys())],
            'plan_id' => ['required', 'integer', Rule::exists('central.planes', 'id')->where('activo', 1)],
            'subdominio' => 'nullable|string|max:40',
            'contacto_nombre' => 'required|string|max:120',
            'contacto_telefono' => ['required', 'regex:/^9\d{8}$/'],
            'contacto_correo' => 'required|email|max:120',
            'password' => 'required|string|min:8|max:60|confirmed',
            'acepto' => 'accepted',
        ], [
            'ruc.regex' => 'El RUC tiene 11 dígitos y empieza con 10, 15, 17 o 20.',
            'contacto_telefono.regex' => 'Escribe tu celular de 9 dígitos (empieza con 9).',
            'acepto.accepted' => 'Marca que entiendes cómo funciona la prueba gratis.',
        ], [
            'nombre_comercial' => 'nombre comercial', 'plan_id' => 'plan', 'contacto_nombre' => 'tu nombre',
            'contacto_telefono' => 'celular', 'contacto_correo' => 'correo', 'password' => 'contraseña',
        ]);
        $d['subdominio'] = mb_strtolower(trim((string) ($d['subdominio'] ?? ''))) ?: null;

        if ($error = $this->errorRucTomado($d['ruc'])) {
            return back()->withInput()->withErrors(['ruc' => $error]);
        }
        if ($error = Tenancy::errorSubdominio($d['subdominio'])) {
            return back()->withInput()->withErrors(['subdominio' => $error]);
        }
        if (! $this->captchaValido($request)) {
            return back()->withInput()->withErrors(['captcha' => 'Confirma que no eres un robot.']);
        }
        $clave = 'registro-web:'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, config('sitio.registros_por_ip'))) {
            return back()->withInput()->withErrors(['ruc' => 'Se hicieron muchos registros desde tu conexión. Escríbenos por WhatsApp y te ayudamos.']);
        }

        // Los datos de la empresa salen de SUNAT, no de lo que se escribe
        $sunat = $this->datosRuc($d['ruc']);
        if (! $sunat) {
            return back()->withInput()->withErrors(['ruc' => 'No pudimos verificar tu RUC en SUNAT. Intenta en unos minutos o escríbenos por WhatsApp.']);
        }
        if ($sunat['estado'] && mb_strtoupper($sunat['estado']) !== 'ACTIVO') {
            return back()->withInput()->withErrors(['ruc' => "Tu RUC figura como {$sunat['estado']} en SUNAT. Para emitir comprobantes debe estar ACTIVO."]);
        }

        RateLimiter::hit($clave, 86400);
        set_time_limit(180);   // crear la base y sus tablas toma unos segundos
        try {
            $cliente = RegistroWeb::crear($d + ['razon_social' => $sunat['nombre'], 'direccion' => $sunat['direccion'] ?: '-', 'ubigeo' => $sunat['ubigeo']], $request->ip());
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['ruc' => 'No pudimos crear tu sistema en este momento. Inténtalo otra vez o escríbenos por WhatsApp.']);
        }

        return redirect()->to(URL::temporarySignedRoute('sitio.registro.listo', now()->addDay(), ['cliente' => $cliente->id]));
    }

    /** "¡Tu sistema está listo!": la dirección, el usuario y hasta cuándo es la prueba */
    public function listo(Cliente $cliente): View
    {
        return view('sitio.listo', ['cliente' => $cliente, 'url' => $this->urlEmpresa($cliente),
            'estadoUrl' => URL::temporarySignedRoute('sitio.registro.https', now()->addDay(), ['cliente' => $cliente->id])]);
    }

    /** La página "listo" pregunta cada pocos segundos si ya está el https del subdominio nuevo */
    public function estadoHttps(Cliente $cliente): JsonResponse
    {
        return response()->json(['https' => $cliente->tieneHttps(), 'url' => $this->urlEmpresa($cliente)]);
    }

    /** https cuando ya tiene certificado; mientras tanto http (el servidor lo saca en menos de un minuto) */
    private function urlEmpresa(Cliente $cliente): string
    {
        $url = $cliente->url();

        return config('tenancy.esquema') === 'https' && ! $cliente->tieneHttps() ? preg_replace('~^https://~', 'http://', $url) : $url;
    }

    /**
     * Datos del RUC en SUNAT. Se guardan una hora: al registrarse se usan los mismos que se mostraron en el formulario
     * (el servicio de consulta a veces no responde dos veces seguidas). Si falla, se reintenta una vez.
     *
     * @return array{nombre: string, direccion: ?string, estado: ?string, condicion: ?string, ubigeo: ?string}|null
     */
    private function datosRuc(string $ruc): ?array
    {
        $clave = 'sitio-ruc:'.$ruc;
        if ($guardado = Cache::get($clave)) {
            return $guardado;
        }
        $d = ConsultaPeru::ruc($ruc) ?? ConsultaPeru::ruc($ruc);
        if ($d) {
            Cache::put($clave, $d, 3600);
        }

        return $d;
    }

    private function errorRucTomado(string $ruc): ?string
    {
        $cliente = Cliente::where('ruc', $ruc)->first();
        if (! $cliente) {
            return null;
        }

        return $cliente->estado === 'ELIMINADO'
            ? 'Este RUC ya usó su prueba gratis. Escríbenos por WhatsApp para activar un plan.'
            : 'Este RUC ya tiene su sistema en '.$cliente->host().'. Usa "Ingresar" o escríbenos si olvidaste tu contraseña.';
    }

    private function captchaValido(Request $request): bool
    {
        return Captcha::valido($request);
    }

    private function planes()
    {
        return Plan::where('activo', true)->orderBy('orden')->orderBy('precio')->get();
    }
}
