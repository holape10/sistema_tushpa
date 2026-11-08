<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Asistente\Asistente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Asistente IA en el panel: cuánto se gastó este mes, quién lo usa y a qué empresas se les activa (solo el dueño) */
class AsistenteController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema administra el asistente.');
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $central = DB::connection('central');
        $inicio = now()->startOfMonth();
        $delMes = fn () => $central->table('asistente_uso')->where('created_at', '>=', $inicio);
        $porCliente = $delMes()->whereNull('error')->groupBy('cliente_id')
            ->selectRaw('cliente_id, COUNT(*) as preguntas, SUM(costo_usd) as costo, MAX(created_at) as ultima')->get()->keyBy('cliente_id');
        $q = trim((string) $request->get('q'));

        return view('admin.asistente', [
            'configurado' => Asistente::configurado(), 'modelo' => Asistente::modelo(),
            'tope' => (float) config('services.anthropic.tope_mensual_usd', 20),
            'limitePorDefecto' => (int) config('services.anthropic.limite_empresa', 100),
            'gasto' => (float) $delMes()->sum('costo_usd'), 'preguntas' => (int) $delMes()->whereNull('error')->count(),
            'errores' => (int) $delMes()->whereNotNull('error')->count(),
            // Proyección simple: lo gastado al día de hoy, llevado a todo el mes
            'proyeccion' => now()->day > 0 ? (float) $delMes()->sum('costo_usd') / now()->day * now()->daysInMonth : 0,
            'porDia' => $delMes()->groupByRaw('DATE(created_at)')->selectRaw('DATE(created_at) as dia, COUNT(*) as preguntas, SUM(costo_usd) as costo')->orderBy('dia')->get(),
            'clientes' => Cliente::query()->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('razon_social', 'like', "%{$q}%")->orWhere('ruc', 'like', "{$q}%")))
                ->orderByDesc('asistente_activo')->orderBy('razon_social')->limit(200)->get(['id', 'ruc', 'razon_social', 'asistente_activo', 'asistente_limite']),
            'porCliente' => $porCliente, 'q' => $q,
            'ultimas' => $delMes()->orderByDesc('id')->limit(30)->get(['cliente_id', 'usuario', 'pregunta', 'consultas', 'costo_usd', 'error', 'created_at']),
        ]);
    }

    /** Activar o desactivar el asistente de una empresa y su límite de preguntas al mes */
    public function guardar(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['activo' => 'required|boolean', 'limite' => 'nullable|integer|min:1|max:100000']);
        $cliente->update(['asistente_activo' => (bool) $d['activo'], 'asistente_limite' => $d['limite'] ?? null]);

        return back()->with('ok', "{$cliente->razon_social}: asistente ".($d['activo'] ? 'activado' : 'desactivado').'.');
    }
}
