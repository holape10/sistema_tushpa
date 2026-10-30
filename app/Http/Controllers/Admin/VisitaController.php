<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Visitas a la web pública (tushpa.app): personas, páginas vistas, de dónde llegan y cuántos se registran. Solo el dueño. */
class VisitaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema ve las visitas.');
        $dias = in_array((int) $request->get('dias'), [7, 30, 90], true) ? (int) $request->get('dias') : 30;
        $desde = now()->subDays($dias - 1)->startOfDay();
        $central = DB::connection('central');
        $base = fn () => $central->table('web_visitas')->where('fecha', '>=', $desde);

        $resumen = fn ($desdeResumen) => $central->table('web_visitas')->where('fecha', '>=', $desdeResumen)
            ->selectRaw('COUNT(*) as vistas, COUNT(DISTINCT visitante) as personas')->first();

        $porDia = $base()->groupByRaw('DATE(fecha)')->selectRaw('DATE(fecha) as dia, COUNT(*) as vistas, COUNT(DISTINCT visitante) as personas')
            ->pluck('personas', 'dia');
        $serie = collect(range($dias - 1, 0))->map(fn ($n) => now()->subDays($n)->toDateString())
            ->map(fn ($d) => (object) ['dia' => $d, 'personas' => (int) ($porDia[$d] ?? 0)]);

        $registros = $central->table('clientes')->where('origen', 'web')->where('created_at', '>=', $desde)->count();
        $personas = (int) $base()->distinct()->count('visitante');

        return view('admin.visitas', [
            'dias' => $dias,
            'hoy' => $resumen(now()->startOfDay()),
            'semana' => $resumen(now()->subDays(6)->startOfDay()),
            'mes' => $resumen(now()->subDays(29)->startOfDay()),
            'serie' => $serie,
            'personas' => $personas,
            'vistas' => (int) $base()->count(),
            'paginas' => $base()->groupBy('ruta')->selectRaw('ruta, COUNT(*) as vistas, COUNT(DISTINCT visitante) as personas')->orderByDesc('vistas')->limit(10)->get(),
            'origenes' => $base()->groupBy('origen')->selectRaw("COALESCE(origen, 'Directo') as origen, COUNT(DISTINCT visitante) as personas")->orderByDesc('personas')->limit(10)->get(),
            'dispositivos' => $base()->groupBy('dispositivo')->selectRaw('dispositivo, COUNT(DISTINCT visitante) as personas')->orderByDesc('personas')->get(),
            // Desde dónde: países y, en el Perú, ciudades (las visitas de antes de guardar la ubicación no cuentan)
            'paises' => $base()->whereNotNull('pais')->groupBy('pais')->selectRaw('pais, COUNT(DISTINCT visitante) as personas')->orderByDesc('personas')->limit(10)->get(),
            'ciudades' => $base()->where('pais', 'PE')->groupBy('region', 'ciudad')
                ->selectRaw("region, COALESCE(ciudad, region, 'Perú') as ciudad, COUNT(DISTINCT visitante) as personas")->orderByDesc('personas')->limit(12)->get(),
            'sinUbicacion' => (int) $base()->whereNull('pais')->distinct()->count('visitante'),
            'porHora' => $base()->groupByRaw('HOUR(fecha)')->selectRaw('HOUR(fecha) as hora, COUNT(*) as vistas')->pluck('vistas', 'hora'),
            'registros' => $registros,
            'conversion' => $personas > 0 ? round($registros / $personas * 100, 1) : 0,
            'mensajes' => $central->table('web_contactos')->where('created_at', '>=', $desde)->count(),
            'cartas' => $central->table('cartas_web')->orderByDesc('id')->limit(50)->get(),
            'cartasPeriodo' => $central->table('cartas_web')->where('created_at', '>=', $desde)->count(),
        ]);
    }

    /** Bloquear (o volver a activar) una carta de "Crea tu carta" con contenido indebido */
    public function cartaEstado(int $id): RedirectResponse
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403);
        $carta = DB::connection('central')->table('cartas_web')->where('id', $id)->first();
        abort_unless($carta, 404);
        DB::connection('central')->table('cartas_web')->where('id', $id)->update(['estado' => $carta->estado === 'ACTIVA' ? 'BLOQUEADA' : 'ACTIVA']);

        return back()->with('ok', $carta->estado === 'ACTIVA' ? "Carta {$carta->nombre} bloqueada." : "Carta {$carta->nombre} activa otra vez.");
    }
}
