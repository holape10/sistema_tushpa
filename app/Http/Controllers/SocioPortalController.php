<?php

namespace App\Http\Controllers;

use App\Support\Gimnasio;
use App\Support\Impresion\ComprobantePdf;
use App\Support\Socios;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Portal del socio: {subdominio}/socio. Sin sesión del sistema (como la tienda virtual).
 * Usuario = DNI/RUC; la primera vez la contraseña es el mismo DNI/RUC y se pide crear una propia.
 * Muestra su estado, deuda, pagos (con PDF), familiares y carnet digital con QR; se actualiza solo cada 20 s.
 * Si la sucursal es un gimnasio, muestra su membresía, congelamientos (los puede pedir él mismo), asistencia y plan de nutrición.
 */
class SocioPortalController extends Controller
{
    private const SESION = 'portal_socio';

    private function negocio(?int $suc = null): ?object
    {
        return DB::table('empresa_negocios as n')->join('empresa as e', 'e.IdEmpresa', '=', 'n.IdEmpresa')
            ->when($suc, fn ($q) => $q->where('n.id_empresa_negocio', $suc))
            ->orderBy('n.id_empresa_negocio')->first(['n.id_empresa_negocio', 'n.nombre_comercial', 'n.logo_suc', 'n.telefono', 'e.NomEmpresa', 'e.LogEmpresa', 'e.IdEmpresa']);
    }

    /** Socio de la sesión (o null si salió, fue retirado o cambió de clave en otro equipo) */
    private function socio(): ?object
    {
        $id = session(self::SESION);
        if (! $id) {
            return null;
        }
        $s = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->leftJoin('socio_categorias as k', 'k.cat_soc_id', '=', 's.cat_soc_id')
            ->where('s.soc_id', $id)->first(['s.*', 'c.clinum', 'c.clinom', 'c.telefono', 'c.clicor', 'k.nombre as categoria', 'k.cuota']);

        return $s && in_array($s->estado, ['ACTIVO', 'SUSPENDIDO'], true) ? $s : null;
    }

    public function index()
    {
        $s = $this->socio();
        if (! $s) {
            session()->forget(self::SESION);

            return view('socio_portal.login', ['negocio' => $this->negocio()]);
        }
        if (Gimnasio::usa((int) $s->id_empresa_negocio)) {
            return view('socio_portal.gimnasio', ['negocio' => $this->negocio((int) $s->id_empresa_negocio), 'socio' => $s,
                'cambiarClave' => ! $s->clave, 'datos' => $this->datosGimnasio($s), 'motivos' => Gimnasio::MOTIVOS]);
        }

        return view('socio_portal.panel', ['negocio' => $this->negocio((int) $s->id_empresa_negocio), 'socio' => $s,
            'cambiarClave' => ! $s->clave, 'datos' => $this->datos($s)]);
    }

    public function entrar(Request $request)
    {
        $d = $request->validate(['doc' => 'required|string|max:15', 'password' => 'required|string|max:100'], [], ['doc' => 'DNI o RUC', 'password' => 'contraseña']);
        $doc = preg_replace('/\D/', '', $d['doc']);
        $clave = 'socio-login:'.$request->ip().':'.$doc;
        if (RateLimiter::tooManyAttempts($clave, 8)) {
            return back()->withInput()->withErrors(['doc' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($clave).' segundos.']);
        }

        $s = $doc !== '' && $doc !== '00000000' ? DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('c.clinum', $doc)->whereIn('s.estado', ['ACTIVO', 'SUSPENDIDO'])->first(['s.soc_id', 's.clave', 'c.clinum']) : null;
        $ok = $s && ($s->clave ? Hash::check($d['password'], $s->clave) : hash_equals($s->clinum, $d['password']));
        if (! $ok) {
            RateLimiter::hit($clave, 300);

            return back()->withInput()->withErrors(['doc' => $s && ! $s->clave
                ? 'Contraseña incorrecta. Si es tu primer ingreso, tu contraseña es tu mismo DNI/RUC.'
                : 'DNI/RUC o contraseña incorrectos. Si no eres socio o estás retirado, comunícate con el club.']);
        }
        RateLimiter::clear($clave);
        $request->session()->regenerate();
        session([self::SESION => $s->soc_id]);
        DB::table('socios')->where('soc_id', $s->soc_id)->update(['acceso' => now()]);

        return redirect()->route('socio.portal');
    }

    public function salir(Request $request)
    {
        $request->session()->forget(self::SESION);
        $request->session()->regenerateToken();

        return redirect()->route('socio.portal');
    }

    public function cambiarClave(Request $request)
    {
        $s = $this->socio();
        abort_unless($s, 403);
        $request->validate(['password' => 'required|string|min:6|max:100|confirmed'], [], ['password' => 'contraseña']);
        if (hash_equals($s->clinum, $request->password)) {
            return back()->withErrors(['password' => 'La contraseña no puede ser tu DNI/RUC.']);
        }
        DB::table('socios')->where('soc_id', $s->soc_id)->update(['clave' => Hash::make($request->password)]);

        return redirect()->route('socio.portal')->with('aviso', 'Tu contraseña se guardó.');
    }

    /** Para refrescar el panel en tiempo real */
    public function estado()
    {
        $s = $this->socio();
        abort_unless($s, 401);

        return response()->json(Gimnasio::usa((int) $s->id_empresa_negocio) ? $this->datosGimnasio($s) : $this->datos($s));
    }

    /** PDF de un comprobante suyo */
    public function comprobante(int $id)
    {
        $s = $this->socio();
        abort_unless($s, 403);
        $propio = DB::table('socio_pagos as p')->join('socio_cargos as c', 'c.car_id', '=', 'p.car_id')
            ->where('c.soc_id', $s->soc_id)->where('p.IdCpe_cabecera', $id)->exists()
            || DB::table('gym_membresias')->where('soc_id', $s->soc_id)->where('IdCpe_cabecera', $id)->exists();
        abort_unless($propio, 404);
        [$nombre, $pdf] = ComprobantePdf::generar($id, (int) $s->id_empresa_negocio);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"']);
    }

    /**
     * El cliente del gimnasio congela sus días (viaje, enfermedad...). Una vez registrado no lo puede editar ni anular:
     * solo el administrador. Si vuelve antes, no entra sin aprobación del administrador (o paga la rutina del día).
     */
    public function congelar(Request $request)
    {
        $s = $this->socio();
        abort_unless($s && Gimnasio::usa((int) $s->id_empresa_negocio), 403);
        $d = $request->validate([
            'desde' => 'required|date|after:today', 'hasta' => 'required|date|after_or_equal:desde',
            'motivo' => 'required|in:'.implode(',', Gimnasio::MOTIVOS), 'detalle' => 'nullable|string|max:200',
            'acepto' => 'accepted',
        ], ['desde.after' => 'Solo puedes congelar desde mañana.', 'acepto.accepted' => 'Confirma que entiendes que no podrás deshacerlo.'],
            ['desde' => 'desde', 'hasta' => 'hasta']);
        try {
            Gimnasio::congelar((int) $s->soc_id, (int) $s->id_empresa_negocio, $d, 'CLIENTE');
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['congelar' => $e->getMessage()]);
        }
        $dias = Carbon::parse($d['desde'])->diffInDays(Carbon::parse($d['hasta'])) + 1;

        return redirect()->route('socio.portal')->with('aviso', "Congelamiento registrado: {$dias} ".($dias === 1 ? 'día' : 'días')
            .'. Tu plan se alargó esos días y vuelve a correr solo cuando termine.');
    }

    private function datosGimnasio(object $s): array
    {
        $suc = (int) $s->id_empresa_negocio;
        $sit = Gimnasio::situacionDe((int) $s->soc_id, $suc);
        $mem = $sit['membresia'];
        $usados = $mem ? (int) DB::table('gym_congelamientos')->where('mem_id', $mem->mem_id)->where('estado', 'ACTIVO')->sum('dias') : 0;
        $f = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;

        return [
            'estado' => $sit['estado'], 'texto' => $sit['texto'], 'color' => $sit['color'], 'dias' => $sit['dias'],
            'plan' => $mem->plan ?? null, 'inicio' => $f($mem->inicio ?? null), 'vence' => $f($sit['vence']),
            'congelado' => $sit['congelamiento'] ? ['desde' => $f($sit['congelamiento']->desde), 'hasta' => $f($sit['congelamiento']->hasta), 'motivo' => $sit['congelamiento']->motivo] : null,
            // Lo que aún puede congelar de la membresía vigente (desde mañana)
            'puede_congelar' => $mem && $mem->dias > 1 && $mem->congelar_max > $usados && $mem->fin > now()->toDateString(),
            'congelar_max' => (int) ($mem->congelar_max ?? 0), 'congelar_quedan' => max(0, (int) ($mem->congelar_max ?? 0) - $usados),
            'congelamientos' => DB::table('gym_congelamientos')->where('soc_id', $s->soc_id)->where('estado', 'ACTIVO')->orderByDesc('desde')->limit(20)
                ->get()->map(fn ($c) => ['desde' => $f($c->desde), 'hasta' => $f($c->hasta), 'dias' => $c->dias, 'motivo' => $c->motivo,
                    'detalle' => $c->detalle, 'origen' => $c->origen, 'nota' => $c->nota, 'activo' => $c->hasta >= now()->toDateString()]),
            'membresias' => DB::table('gym_membresias as m')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'm.IdCpe_cabecera')
                ->where('m.soc_id', $s->soc_id)->where('m.estado', 'ACTIVA')->orderByDesc('m.inicio')->limit(20)
                ->get(['m.plan', 'm.inicio', 'm.fin', 'm.precio', 'm.IdCpe_cabecera', DB::raw("CONCAT(c.serdoc, '-', LPAD(c.numdoc, 8, '0')) as comprobante")])
                ->map(fn ($m) => ['plan' => $m->plan, 'inicio' => $f($m->inicio), 'fin' => $f($m->fin), 'precio' => (float) $m->precio,
                    'comprobante' => $m->comprobante, 'pdf' => $m->IdCpe_cabecera ? route('socio.portal.comprobante', $m->IdCpe_cabecera) : null]),
            'asistencias' => DB::table('gym_asistencias')->where('soc_id', $s->soc_id)->orderByDesc('fecha_hora')->limit(30)
                ->get(['fecha_hora', 'resultado'])->map(fn ($a) => ['fecha' => Carbon::parse($a->fecha_hora)->locale('es')->translatedFormat('D d/m'),
                    'hora' => Carbon::parse($a->fecha_hora)->format('H:i'), 'ok' => $a->resultado === 'PERMITIDO']),
            'mes' => (int) DB::table('gym_asistencias')->where('soc_id', $s->soc_id)->where('resultado', 'PERMITIDO')
                ->where('fecha_hora', '>=', now()->startOfMonth())->value(DB::raw('COUNT(DISTINCT DATE(fecha_hora))')),
            'nutricion' => GimnasioController::nutricionDe((int) $s->soc_id)->map(fn ($n) => $n + ['fecha_txt' => $f($n['fecha'])])->values(),
            'carnet' => $this->qr(route('socios.verificar', $s->token)),
            'actualizado' => now()->format('H:i:s'),
        ];
    }

    private function qr(string $texto): string
    {
        return preg_replace('/^<\?xml[^>]*>\s*/', '', (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($texto));
    }

    private function datos(object $s): array
    {
        $meses = (int) (Socios::mesesDebe((int) $s->id_empresa_negocio)[$s->soc_id] ?? 0);
        $pendientes = DB::table('socio_cargos')->where('soc_id', $s->soc_id)->where('estado', 'PENDIENTE')->orderBy('periodo')->orderBy('car_id')
            ->get(['descripcion', 'monto', 'pagado', 'creado']);
        [$texto, $color] = Socios::situacion($s, $meses, (float) $pendientes->sum(fn ($c) => $c->monto - $c->pagado));
        $pagos = DB::table('socio_pagos as p')->join('socio_cargos as c', 'c.car_id', '=', 'p.car_id')
            ->join('cpe_cabecera as cab', 'cab.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('c.soc_id', $s->soc_id)->where('p.anulado', 0)->orderByDesc('p.pag_id')->limit(50)
            ->get(['p.fecha', 'p.monto', 'c.descripcion', 'cab.IdCpe_cabecera', DB::raw("CONCAT(cab.serdoc, '-', LPAD(cab.numdoc, 8, '0')) as comprobante")]);
        $familiares = DB::table('socio_familiares')->where('soc_id', $s->soc_id)->where('activo', 1)->orderBy('fam_id')->get(['nombre', 'dni', 'parentesco', 'token']);

        $carnets = [['nombre' => $s->clinom, 'tipo' => 'TITULAR', 'qr' => $this->qr(route('socios.verificar', $s->token))]];
        foreach ($familiares as $f) {
            $carnets[] = ['nombre' => $f->nombre, 'tipo' => $f->parentesco, 'qr' => $this->qr(route('socios.verificar', $f->token))];
        }

        return [
            'estado' => $texto, 'color' => $color, 'meses' => $meses,
            'deuda' => round($pendientes->sum(fn ($c) => $c->monto - $c->pagado), 2),
            'pagado_anio' => round((float) DB::table('socio_pagos as p')->join('socio_cargos as c', 'c.car_id', '=', 'p.car_id')
                ->where('c.soc_id', $s->soc_id)->where('p.anulado', 0)->whereYear('p.fecha', now()->year)->sum('p.monto'), 2),
            'pendientes' => $pendientes->map(fn ($c) => ['descripcion' => $c->descripcion, 'saldo' => round($c->monto - $c->pagado, 2), 'a_cuenta' => (float) $c->pagado]),
            'pagos' => $pagos->map(fn ($p) => ['fecha' => $p->fecha, 'monto' => (float) $p->monto, 'descripcion' => $p->descripcion,
                'comprobante' => $p->comprobante, 'pdf' => route('socio.portal.comprobante', $p->IdCpe_cabecera)]),
            'familiares' => $familiares->map(fn ($f) => ['nombre' => $f->nombre, 'dni' => $f->dni, 'parentesco' => $f->parentesco]),
            'carnets' => $carnets,
            'actualizado' => now()->format('H:i:s'),
        ];
    }
}
