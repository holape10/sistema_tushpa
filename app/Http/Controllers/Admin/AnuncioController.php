<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Anuncios;
use App\Support\Rubros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Anuncios generales para todas las empresas (franja arriba del sistema). Solo el dueño. */
class AnuncioController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño envía anuncios a las empresas.');
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $editar = $request->filled('editar') ? Anuncios::db()->where('id', (int) $request->editar)->first() : null;

        return view('admin.anuncios', [
            'anuncios' => Anuncios::db()->orderByDesc('activo')->orderByDesc('id')->limit(100)->get(),
            'editar' => $editar,
            'rubros' => collect(Rubros::RUBROS)->except('COMPLETO')->map(fn ($r) => $r['nombre']),
            'empresas' => Cliente::where('estado', 'ACTIVO')->groupBy('rubro')->selectRaw('rubro, COUNT(*) as n')->pluck('n', 'rubro'),
        ]);
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $this->autorizar();
        abort_if($id && ! Anuncios::db()->where('id', $id)->exists(), 404);
        $d = $request->validate([
            'mensaje' => 'required|string|min:5|max:600', 'tipo' => ['required', Rule::in(array_keys(Anuncios::TIPOS))],
            'rubros' => 'nullable|array', 'rubros.*' => Rule::in(array_keys(Rubros::RUBROS)),
            'desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde',
            'se_puede_cerrar' => 'nullable|boolean', 'activo' => 'nullable|boolean',
        ], ['hasta.after_or_equal' => 'La fecha "hasta" debe ser después de "desde".'], ['hasta' => 'hasta', 'desde' => 'desde']);

        $fila = [
            'mensaje' => trim($d['mensaje']), 'tipo' => $d['tipo'],
            'rubros' => empty($d['rubros']) ? null : json_encode(array_values($d['rubros'])),
            'desde' => $d['desde'] ?? null, 'hasta' => $d['hasta'] ?? null,
            'se_puede_cerrar' => $request->boolean('se_puede_cerrar'), 'activo' => $request->boolean('activo'), 'updated_at' => now(),
        ];
        $id ? Anuncios::db()->where('id', $id)->update($fila) : Anuncios::db()->insert($fila + ['created_at' => now()]);
        Anuncios::limpiarCache();

        return redirect()->route('admin.anuncios.index')->with('ok', $fila['activo'] ? 'Anuncio guardado: ya lo ven las empresas (en menos de un minuto).' : 'Anuncio guardado (pausado).');
    }

    public function estado(int $id): RedirectResponse
    {
        $this->autorizar();
        $a = Anuncios::db()->where('id', $id)->first();
        abort_unless($a, 404);
        Anuncios::db()->where('id', $id)->update(['activo' => ! $a->activo, 'updated_at' => now()]);
        Anuncios::limpiarCache();

        return back()->with('ok', $a->activo ? 'Anuncio quitado: ya no lo ven las empresas.' : 'Anuncio activado.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        $this->autorizar();
        abort_unless(Anuncios::db()->where('id', $id)->delete(), 404);
        Anuncios::limpiarCache();

        return redirect()->route('admin.anuncios.index')->with('ok', 'Anuncio eliminado.');
    }
}
