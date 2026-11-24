<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Asistente\Asistente;
use App\Support\Asistente\Manuales;
use App\Support\Asistente\Soporte;
use App\Support\Tutoriales;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'porCliente' => $porCliente, 'q' => $q, 'rubros' => Tutoriales::rubros(),
            'manuales' => Manuales::disponible() ? Manuales::db()->orderByDesc('id')->get()->each(function ($m) {
                $m->cliente_nombre = $m->cliente_id ? Cliente::whereKey($m->cliente_id)->value('razon_social') : null;
            }) : collect(),
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

    /** Sube un tutorial en PDF: se guarda y la IA lo lee después de responder (tarda 1 a 3 minutos) */
    public function subirManual(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:'.Manuales::MAX_KB, 'titulo' => 'nullable|string|max:150',
            'rubro' => ['required', Rule::in(array_keys(Tutoriales::rubros()))], 'ruc' => 'nullable|digits:11',
        ], ['pdf.max' => 'El PDF pesa más de 20 MB: pártelo en dos.', 'pdf.mimes' => 'Sube un archivo PDF.']);
        $clienteId = null;
        if ($d['ruc'] ?? null) {
            $clienteId = Cliente::where('ruc', $d['ruc'])->value('id');
            if (! $clienteId) {
                return back()->withInput()->withErrors(['ruc' => 'No hay una empresa con ese RUC.']);
            }
        }
        // El mismo archivo (aunque tenga otro nombre o título) no se sube ni se paga dos veces
        if ($previo = Manuales::yaSubido(Manuales::huella($request->file('pdf')))) {
            return back()->withInput()->withErrors(['pdf' => "Este PDF ya está subido como «{$previo->titulo}» (".Carbon::parse($previo->created_at)->format('d/m/Y').'). '
                .'Si quieres que la IA lo lea otra vez, usa «Volver a leer» en ese manual.']);
        }
        if ($motivo = Soporte::bloqueo()) {
            return back()->withInput()->withErrors(['pdf' => $motivo]);
        }
        $titulo = trim((string) ($d['titulo'] ?? '')) ?: Str::limit(pathinfo($request->file('pdf')->getClientOriginalName(), PATHINFO_FILENAME), 150, '');
        $id = Manuales::subir($request->file('pdf'), $titulo, $d['rubro'], $clienteId, (string) Auth::guard('superadmin')->user()->nombre);
        dispatch(fn () => Manuales::procesar($id))->afterResponse();

        return redirect()->to(route('admin.asistente.index').'#manuales')->with('ok', "«{$titulo}» subido: la IA lo está leyendo (1 a 3 minutos).");
    }

    public function leerManual(int $id): RedirectResponse
    {
        $this->autorizar();
        abort_unless(Manuales::db()->where('id', $id)->exists(), 404);
        if ($motivo = Soporte::bloqueo()) {
            return back()->withErrors(['pdf' => $motivo]);
        }
        Manuales::db()->where('id', $id)->update(['estado' => 'procesando', 'error' => null, 'updated_at' => now()]);
        dispatch(fn () => Manuales::procesar($id))->afterResponse();

        return redirect()->to(route('admin.asistente.index').'#manuales')->with('ok', 'La IA lo está leyendo de nuevo.');
    }

    public function eliminarManual(int $id): RedirectResponse
    {
        $this->autorizar();
        Manuales::eliminar($id);

        return redirect()->to(route('admin.asistente.index').'#manuales')->with('ok', 'Manual quitado.');
    }
}
