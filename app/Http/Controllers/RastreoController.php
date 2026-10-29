<?php

namespace App\Http\Controllers;

use App\Support\Courier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Rastreo público del courier: el cliente escribe su número de guía (o escanea el QR) y ve dónde está su envío,
 * sin iniciar sesión. Los nombres salen recortados y nunca se muestra la clave de recojo.
 */
class RastreoController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->query('codigo')));
        if ($codigo !== '') {
            return Courier::esCodigo($codigo)
                ? redirect()->route('rastreo.ver', $codigo)
                : back()->withInput()->with('error', 'El número de guía tiene 10 letras y números. Revísalo, por favor.');
        }

        return view('courier.rastreo', $this->marca() + ['e' => null]);
    }

    public function ver(string $codigo): View
    {
        $codigo = strtoupper($codigo);
        $e = Courier::esCodigo($codigo) ? DB::table('cou_envios')->where('codigo', $codigo)->first() : null;
        if (! $e) {
            return view('courier.rastreo', $this->marca() + ['e' => null, 'noEncontrado' => $codigo]);
        }
        $agencias = DB::table('cou_agencias')->whereIn('age_id', [$e->origen_id, $e->destino_id])->get()->keyBy('age_id');

        return view('courier.rastreo', $this->marca() + [
            'e' => $e, 'origen' => $agencias[$e->origen_id] ?? null, 'destino' => $agencias[$e->destino_id] ?? null,
            'eventos' => DB::table('cou_eventos as v')->leftJoin('cou_agencias as a', 'a.age_id', '=', 'v.age_id')->where('v.env_id', $e->env_id)
                ->orderByDesc('v.evt_id')->get(['v.estado', 'v.detalle', 'v.fecha', 'a.ciudad']),
        ]);
    }

    /** Nombre y logo de la empresa para la cabecera */
    private function marca(): array
    {
        $empresa = DB::table('empresa')->first(['NomEmpresa', 'LogEmpresa']);
        $negocio = DB::table('empresa_negocios')->orderBy('id_empresa_negocio')->first(['nombre_comercial', 'telefono']);

        return [
            'nombre' => $negocio->nombre_comercial ?? $empresa->NomEmpresa ?? 'Rastreo de envíos',
            'logo' => $empresa && $empresa->LogEmpresa && is_file(public_path($empresa->LogEmpresa)) ? asset($empresa->LogEmpresa) : null,
            'telefono' => $negocio->telefono ?? null,
            // Cotizar, pre-registrar y reclamar desde la web (si la empresa lo tiene activo)
            'web' => (bool) (DB::table('cou_config')->value('web_publico') ?? true),
        ];
    }
}
