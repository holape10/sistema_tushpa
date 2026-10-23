<?php

namespace App\Http\Controllers;

use App\Support\PanelDueno;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Panel del dueño (administrador): cómo va el negocio, horas pico, ranking del equipo, alertas y el resumen
 * del día para mandar por WhatsApp.
 */
class PanelDuenoController extends Controller
{
    public const VISTAS = ['panel' => ['Cómo va mi negocio', '📊'], 'horas' => ['Horas pico', '🔥'], 'equipo' => ['Mi equipo', '👥'], 'alertas' => ['Alertas', '🚨']];

    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador ve el panel del dueño.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    /** @return array{0: string, 1: string} desde y hasta (por defecto, de las últimas 4 semanas para el mapa de calor y del mes para lo demás) */
    private function rango(Request $request, string $ver): array
    {
        $defecto = $ver === 'horas' ? now()->subDays(27)->toDateString() : now()->startOfMonth()->toDateString();
        try {
            $desde = Carbon::parse((string) $request->get('desde', $defecto))->toDateString();
            $hasta = Carbon::parse((string) $request->get('hasta', now()->toDateString()))->toDateString();
        } catch (\Throwable) {
            [$desde, $hasta] = [$defecto, now()->toDateString()];
        }

        return $hasta < $desde ? [$hasta, $desde] : [$desde, $hasta];
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $ver = array_key_exists($request->get('ver'), self::VISTAS) ? $request->get('ver') : 'panel';
        [$desde, $hasta] = $this->rango($request, $ver);
        $alertas = PanelDueno::alertas($suc, $desde, $hasta);

        return view('empresas.dueno.index', [
            'ver' => $ver, 'desde' => $desde, 'hasta' => $hasta, 'alertas' => $alertas,
            'resumen' => $ver === 'panel' ? PanelDueno::resumen($suc) : null,
            'mapa' => $ver === 'horas' ? PanelDueno::mapaCalor($suc, $desde, $hasta) : null,
            'equipo' => $ver === 'equipo' ? PanelDueno::equipo($suc, $desde, $hasta) : null,
            'config' => DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(['meta_mensual', 'gastos_fijos_mes', 'whatsapp_dueno']),
        ]);
    }

    /** El resumen del día listo para WhatsApp (texto y enlace al número del dueño) */
    public function resumenDia(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        try {
            $fecha = Carbon::parse((string) $request->get('fecha', now()->toDateString()));
        } catch (\Throwable) {
            $fecha = now();
        }
        $texto = PanelDueno::mensajeDia($suc, $fecha->isToday() ? now() : $fecha);
        $tel = preg_replace('/\D/', '', (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('whatsapp_dueno'));

        return response()->json(['texto' => $texto,
            'whatsapp' => 'https://wa.me/'.($tel !== '' ? (strlen($tel) === 9 ? '51'.$tel : $tel) : '').'?text='.rawurlencode($texto)]);
    }

    /** Meta del mes, gastos fijos (punto de equilibrio) y WhatsApp del dueño */
    public function configuracion(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'meta_mensual' => 'nullable|numeric|min:0|max:99999999', 'gastos_fijos_mes' => 'nullable|numeric|min:0|max:99999999',
            'whatsapp_dueno' => ['nullable', 'regex:/^\+?[\d\s]{9,15}$/'],
        ], ['whatsapp_dueno.regex' => 'Escribe el celular con 9 dígitos (o con el código de país).'], ['meta_mensual' => 'meta', 'gastos_fijos_mes' => 'gastos fijos']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update([
            'meta_mensual' => $d['meta_mensual'] ?? null, 'gastos_fijos_mes' => $d['gastos_fijos_mes'] ?? null,
            'whatsapp_dueno' => isset($d['whatsapp_dueno']) ? preg_replace('/\s/', '', $d['whatsapp_dueno']) : null,
        ]);

        return response()->json(['ok' => true, 'mensaje' => 'Guardado.']);
    }
}
