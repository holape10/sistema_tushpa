<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Carta;
use App\Support\Encuestas;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Encuesta de satisfacción: la página pública que abre el cliente con el QR de su ticket (sin sesión)
 * y el reporte del administrador con los promedios, por mozo y los comentarios.
 */
class EncuestaController extends Controller
{
    /** Página pública: /encuesta/{codigo} */
    public function ver(string $codigo): View
    {
        [$cab, $motivo] = Encuestas::venta($codigo);

        return view('encuesta.ver', $this->marca($cab) + ['codigo' => $codigo, 'motivo' => $motivo, 'gracias' => session('gracias')]);
    }

    public function responder(Request $request, string $codigo): RedirectResponse
    {
        [$cab, $motivo] = Encuestas::venta($codigo);
        if (! $cab || $motivo) {
            return redirect()->route('encuesta.ver', $codigo);
        }
        $d = $request->validate([
            'comida' => 'required|integer|between:1,5',
            'atencion' => 'required|integer|between:1,5',
            'rapidez' => 'required|integer|between:1,5',
            'comentario' => 'nullable|string|max:300',
            'telefono' => ['nullable', 'regex:/^\+?[\d\s]{9,15}$/'],
        ], ['required' => 'Toca una carita en cada pregunta.', 'telefono.regex' => 'Escribe tu celular (9 dígitos).']);

        DB::table('encuestas')->insertOrIgnore([
            'id_empresa_negocio' => $cab->id_empresa_negocio, 'IdCpe_cabecera' => $cab->IdCpe_cabecera,
            'comida' => $d['comida'], 'atencion' => $d['atencion'], 'rapidez' => $d['rapidez'],
            'comentario' => trim((string) ($d['comentario'] ?? '')) ?: null,
            'telefono' => preg_replace('/\D/', '', (string) ($d['telefono'] ?? '')) ?: null, 'created_at' => now(),
        ]);

        return redirect()->route('encuesta.ver', $codigo)->with('gracias', Encuestas::promedio((object) $d));
    }

    /** Reporte del administrador */
    public function index(Request $request): View
    {
        $usuario = Auth::user();
        abort_unless($usuario->esAdmin(), 403, 'Solo el administrador ve las encuestas.');
        $suc = (int) $usuario->id_empresa_negocio;
        $desde = Carbon::parse($request->get('desde', now()->startOfMonth()->toDateString()))->toDateString();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->toDateString();

        $encuestas = DB::table('encuestas as e')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'e.IdCpe_cabecera')
            ->leftJoin('users as u', 'u.IdUsuario', '=', DB::raw('COALESCE(c.mozo, c.IdUsuario)'))
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'c.mes_id')
            ->where('e.id_empresa_negocio', $suc)->whereBetween('e.created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->orderByDesc('e.created_at')
            ->get(['e.*', 'c.serdoc', 'c.numdoc', 'c.ccaitv', 'c.ccanom', 'm.mes_nom', DB::raw('COALESCE(c.mozo, c.IdUsuario) as mozo_id'),
                DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as mozo")])
            ->each(fn ($e) => $e->promedio = Encuestas::promedio($e));
        $tickets = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->whereNull('ccabaj')->whereIn('tdocod', ['01', '03', '13'])
            ->whereBetween('fecha_hora', [$desde.' 00:00:00', $hasta.' 23:59:59'])->count();

        $promedio = fn ($lista, $campo) => $lista->isNotEmpty() ? round($lista->avg($campo), 1) : null;
        $porMozo = $encuestas->groupBy('mozo_id')->map(fn ($g) => (object) [
            'mozo' => $g->first()->mozo ?: 'SIN MOZO', 'encuestas' => $g->count(), 'atencion' => $promedio($g, 'atencion'), 'general' => $promedio($g, 'promedio'),
        ])->sortByDesc('general')->values();

        return view('empresas.encuestas.index', [
            'desde' => $desde, 'hasta' => $hasta, 'encuestas' => $encuestas, 'porMozo' => $porMozo, 'tickets' => $tickets,
            'activa' => Encuestas::activa($suc),
            'promedios' => collect(['general' => 'promedio'] + array_combine(array_keys(Encuestas::PREGUNTAS), array_keys(Encuestas::PREGUNTAS)))
                ->map(fn ($campo) => $promedio($encuestas, $campo)),
            'contentos' => $encuestas->isNotEmpty() ? round($encuestas->where('promedio', '>=', 4)->count() / $encuestas->count() * 100) : null,
        ]);
    }

    public function activar(): RedirectResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        $negocio = EmpresaNegocio::findOrFail(Auth::user()->id_empresa_negocio);
        $negocio->update(['encuesta_activa' => ! $negocio->encuesta_activa]);

        return back()->with('success', $negocio->encuesta_activa
            ? 'Encuesta activada: desde ahora cada ticket lleva el QR para calificar.' : 'Encuesta desactivada: los tickets ya no llevan el QR.');
    }

    /** Nombre, logo y color del negocio para la página pública */
    private function marca(?object $cab): array
    {
        $negocio = $cab ? EmpresaNegocio::find($cab->id_empresa_negocio) : null;
        $empresa = $negocio ? Empresa::find($negocio->IdEmpresa) : null;

        return [
            'nombre' => $negocio?->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Nuestro restaurante'),
            'logo' => collect([$negocio?->logo_suc, $empresa->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l))),
            'color' => $negocio ? Carta::color($negocio) : Carta::COLOR,
        ];
    }
}
