<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Impresion\Impresion;
use App\Support\Transportes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Pasajes por internet (sin sesión del sistema): el pasajero crea su cuenta, elige su asiento y reserva diciendo con qué
 * paga (Yape, Plin…). La reserva sale impresa en la caja; el cajero lo llama, confirma el pago y la cobra. Desde "Mis
 * pasajes" el pasajero baja su boleto en PDF con el QR que se lee en el embarque.
 */
class PasajesWebController extends Controller
{
    /** Asientos por reserva y reservas sin pagar a la vez, por cuenta */
    private const MAX_ASIENTOS = 6;

    private const MAX_PENDIENTES = 2;

    private function negocio(?int $sucursal): EmpresaNegocio
    {
        $negocio = EmpresaNegocio::where('estado', 'Activo')
            ->whereIn('id_empresa_negocio', DB::table('tra_config')->where('web_activo', true)->pluck('id_empresa_negocio'))
            ->when($sucursal, fn ($q) => $q->where('id_empresa_negocio', $sucursal))->orderBy('id_empresa_negocio')->first();
        abort_unless($negocio, 404);

        return $negocio;
    }

    /** El pasajero con sesión (la sesión vale solo para esta empresa) */
    private function pasajero(): ?object
    {
        $s = session('pasajero_web');
        if (! is_array($s) || ($s['base'] ?? null) !== DB::connection()->getDatabaseName()) {
            return null;
        }

        return DB::table('tra_web_usuarios')->where('id', $s['id'] ?? 0)->first();
    }

    private function datos(EmpresaNegocio $negocio): array
    {
        $empresa = Empresa::find($negocio->IdEmpresa);

        return ['negocio' => $negocio, 'nombre' => $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Pasajes'),
            'logo' => collect([$negocio->logo_suc, $empresa->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l))),
            'config' => Transportes::config($negocio->id_empresa_negocio), 'pasajero' => $this->pasajero()];
    }

    /** La salida que se vende por internet: programada y que aún no parte */
    private function salida(int $sucursal, int $id): object
    {
        $s = DB::table('tra_salidas as s')->join('tra_rutas as r', 'r.id', '=', 's.ruta_id')->join('tra_unidades as u', 'u.id', '=', 's.unidad_id')
            ->where('s.id', $id)->where('s.id_empresa_negocio', $sucursal)->where('s.estado', 'PROGRAMADA')->where('s.salida', '>', now())
            ->first(['s.*', 'r.origen', 'r.destino', 'r.paradas', 'u.nombre as unidad', 'u.tipo', 'u.plano']);
        abort_unless($s, 404);

        return $s;
    }

    public function index(?int $sucursal = null): View
    {
        $negocio = $this->negocio($sucursal);
        $salidas = DB::table('tra_salidas as s')->join('tra_rutas as r', 'r.id', '=', 's.ruta_id')->join('tra_unidades as u', 'u.id', '=', 's.unidad_id')
            ->where('s.id_empresa_negocio', $negocio->id_empresa_negocio)->where('s.estado', 'PROGRAMADA')
            ->whereBetween('s.salida', [now(), now()->addDays(30)])->orderBy('s.salida')->limit(200)
            ->get(['s.id', 's.salida', 's.precio', 'r.origen', 'r.destino', 'u.nombre as unidad', 'u.tipo', 'u.asientos']);

        return view('pasajes.index', $this->datos($negocio) + ['salidas' => $salidas]);
    }

    public function ver(int $sucursal, int $id): View
    {
        $negocio = $this->negocio($sucursal);
        $salida = $this->salida($negocio->id_empresa_negocio, $id);
        $plano = Transportes::plano($salida);
        $precios = [];
        foreach (Transportes::asientosDe($plano) as $a) {
            $precios[$a['cat']] ??= Transportes::precio($salida, $a['cat']);
        }
        // Solo qué asiento y tramo está tomado: nada de quién lo compró
        $tomados = array_map(fn ($p) => ['asiento' => $p['asiento'], 'desde' => $p['desde'], 'hasta' => $p['hasta']], Transportes::paraPlano($id));
        $medios = DB::table('medios_pagos')->where('id_empresa_negocio', $negocio->id_empresa_negocio)->where('nom_med_pag', '!=', 'EFECTIVO')
            ->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag', 'qr'])
            ->map(fn ($m) => ['id' => $m->id_med_pag, 'nombre' => $m->nom_med_pag, 'qr' => $m->qr && is_file(public_path($m->qr)) ? asset($m->qr) : null]);

        return view('pasajes.salida', $this->datos($negocio) + ['salida' => $salida, 'plano' => $plano, 'precios' => $precios, 'tomados' => $tomados,
            'paradas' => Transportes::paradas($salida), 'medios' => $medios]);
    }

    public function reservar(Request $request, int $sucursal, int $id): JsonResponse
    {
        $negocio = $this->negocio($sucursal);
        $pasajero = $this->pasajero();
        if (! $pasajero) {
            return response()->json(['ok' => false, 'mensaje' => 'Ingresa a tu cuenta para reservar.', 'cuenta' => true]);
        }
        $this->salida($negocio->id_empresa_negocio, $id);
        $d = $request->validate([
            'pasajeros' => 'required|array|min:1|max:'.self::MAX_ASIENTOS, 'pasajeros.*.asiento' => 'required|string|max:8',
            'pasajeros.*.doc' => 'required|string|max:15', 'pasajeros.*.nombre' => 'required|string|max:150',
            'desde' => 'nullable|integer|min:0|max:99', 'hasta' => 'nullable|integer|min:1|max:99',
            'medio' => 'required|integer', 'operacion' => 'nullable|string|max:30',
        ], [], ['pasajeros.*.doc' => 'documento del pasajero', 'pasajeros.*.nombre' => 'nombre del pasajero', 'medio' => 'medio de pago']);
        $medio = DB::table('medios_pagos')->where('id_empresa_negocio', $negocio->id_empresa_negocio)->where('id_med_pag', $d['medio'])->value('nom_med_pag');
        if (! $medio) {
            return response()->json(['ok' => false, 'mensaje' => 'Elige con qué vas a pagar.']);
        }
        $pendientes = DB::table('tra_pasajes')->where('web_usuario_id', $pasajero->id)->where('estado', 'RESERVADO')->where('reservado_hasta', '>', now())
            ->distinct()->count('grupo');
        if ($pendientes >= self::MAX_PENDIENTES) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya tienes reservas esperando tu pago: espera que la agencia las confirme.']);
        }
        // El precio lo pone la empresa (el de la categoría del asiento), nunca lo que llegue del navegador
        $pasajeros = array_map(fn ($p) => ['asiento' => $p['asiento'], 'doc' => trim($p['doc']), 'nombre' => $p['nombre'],
            'tdicod' => preg_match('/^\d{8}$/', trim($p['doc'])) ? '1' : '4', 'telefono' => $pasajero->celular], $d['pasajeros']);
        try {
            $grupo = Transportes::reservar($negocio->id_empresa_negocio, null, $id, ['pasajeros' => $pasajeros, 'desde' => $d['desde'] ?? null,
                'hasta' => $d['hasta'] ?? null], ['origen' => 'WEB', 'minutos' => (int) Transportes::config($negocio->id_empresa_negocio)->web_reserva_min,
                    'web_usuario_id' => $pasajero->id, 'medio_web' => $medio, 'operacion_web' => ($d['operacion'] ?? null) ? mb_strtoupper(trim($d['operacion'])) : null]);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        try {
            Impresion::reservaTransporte($grupo, $negocio->id_empresa_negocio);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true, 'ir' => route('pasajes.mis', $negocio->id_empresa_negocio)]);
    }

    // ------------------------------------------------------------------ cuenta del pasajero

    public function cuenta(Request $request, int $sucursal): View|RedirectResponse
    {
        $negocio = $this->negocio($sucursal);
        if ($this->pasajero()) {
            return redirect()->route('pasajes.mis', $sucursal);
        }

        return view('pasajes.cuenta', $this->datos($negocio) + ['volver' => $this->volver($request, $sucursal)]);
    }

    /** A dónde vuelve después de entrar: solo a una página de pasajes de esta empresa */
    private function volver(Request $request, int $sucursal): string
    {
        $volver = (string) $request->input('volver');

        return str_starts_with($volver, route('pasajes.web', $sucursal)) ? $volver : route('pasajes.web', $sucursal);
    }

    private function entrar(object $pasajero): void
    {
        session()->regenerate();
        session(['pasajero_web' => ['id' => $pasajero->id, 'base' => DB::connection()->getDatabaseName()]]);
    }

    public function registro(Request $request, int $sucursal): RedirectResponse
    {
        $this->negocio($sucursal);
        $d = $request->validate(['doc' => 'required|string|min:8|max:15|regex:/^[0-9A-Za-z]+$/', 'nombre' => 'required|string|max:150',
            'celular' => 'required|string|min:9|max:20|regex:/^[0-9+ ]+$/', 'email' => 'nullable|email|max:120', 'password' => 'required|string|min:6|max:60|confirmed'],
            [], ['doc' => 'documento', 'password' => 'contraseña']);
        $doc = mb_strtoupper(trim($d['doc']));
        if (DB::table('tra_web_usuarios')->where('doc', $doc)->exists()) {
            return back()->withInput()->withErrors(['doc' => 'Ya hay una cuenta con ese documento: ingresa con tu contraseña.']);
        }
        $id = DB::table('tra_web_usuarios')->insertGetId(['tdicod' => preg_match('/^\d{8}$/', $doc) ? '1' : '4', 'doc' => $doc,
            'nombre' => mb_strtoupper(trim($d['nombre'])), 'celular' => trim($d['celular']), 'email' => $d['email'] ?? null,
            'password' => Hash::make($d['password']), 'created_at' => now(), 'updated_at' => now()]);
        $this->entrar(DB::table('tra_web_usuarios')->where('id', $id)->first());

        return redirect()->to($this->volver($request, $sucursal));
    }

    public function ingresar(Request $request, int $sucursal): RedirectResponse
    {
        $this->negocio($sucursal);
        $d = $request->validate(['doc' => 'required|string|max:15', 'password' => 'required|string|max:60'], [], ['doc' => 'documento', 'password' => 'contraseña']);
        $pasajero = DB::table('tra_web_usuarios')->where('doc', mb_strtoupper(trim($d['doc'])))->first();
        if (! $pasajero || ! Hash::check($d['password'], $pasajero->password)) {
            return back()->withInput($request->only('doc', 'volver'))->withErrors(['doc' => 'Documento o contraseña incorrectos.']);
        }
        $this->entrar($pasajero);

        return redirect()->to($this->volver($request, $sucursal));
    }

    public function salir(int $sucursal): RedirectResponse
    {
        session()->forget('pasajero_web');

        return redirect()->route('pasajes.web', $sucursal);
    }

    public function mis(int $sucursal): View|RedirectResponse
    {
        $negocio = $this->negocio($sucursal);
        $pasajero = $this->pasajero();
        if (! $pasajero) {
            return redirect()->route('pasajes.cuenta', $sucursal);
        }
        $grupos = DB::table('tra_pasajes as p')->join('tra_salidas as s', 's.id', '=', 'p.salida_id')->join('tra_rutas as r', 'r.id', '=', 's.ruta_id')
            ->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('s.id_empresa_negocio', $sucursal)->where('p.web_usuario_id', $pasajero->id)->where('s.salida', '>=', now()->subDays(60))
            ->orderByDesc('s.salida')->orderBy('p.id')
            ->get(['p.grupo', 'p.asiento', 'p.nombre', 'p.precio', 'p.estado', 'p.reservado_hasta', 'p.tramo_desde', 'p.tramo_hasta', 'p.embarcado_at',
                's.salida', 's.estado as salida_estado', 'r.origen', 'r.destino', 'r.paradas', 'c.ccabaj'])
            ->groupBy('grupo');

        return view('pasajes.mis', $this->datos($negocio) + ['grupos' => $grupos]);
    }

    public function boleto(int $sucursal, string $grupo): Response
    {
        $negocio = $this->negocio($sucursal);
        $pasajero = $this->pasajero();
        abort_unless($pasajero && DB::table('tra_pasajes')->where('grupo', $grupo)->where('web_usuario_id', $pasajero->id)->exists(), 404);
        $pdf = Transportes::boletoPdf($negocio->id_empresa_negocio, $grupo);
        abort_unless($pdf, 404);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="BOLETO '.$grupo.'.pdf"']);
    }
}
