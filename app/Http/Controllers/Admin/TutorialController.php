<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Tutoriales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tutoriales de tushpa.app/tutorial por rubro: pasos con texto e imagen y video opcional. Solo el dueño. */
class TutorialController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño administra los tutoriales de la web.');
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $editar = $request->filled('editar') ? Tutoriales::db()->where('id', (int) $request->editar)->first() : null;

        return view('admin.tutoriales', [
            'tutoriales' => Tutoriales::db()->orderBy('rubro')->orderBy('orden')->orderBy('id')->get()->map(fn ($t) => Tutoriales::preparar($t)),
            'editar' => $editar ? Tutoriales::preparar($editar) : null, 'rubros' => Tutoriales::rubros(),
        ]);
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $this->autorizar();
        $actual = $id ? Tutoriales::db()->where('id', $id)->first() : null;
        abort_if($id && ! $actual, 404);
        $d = $request->validate([
            'rubro' => ['required', Rule::in(array_keys(Tutoriales::rubros()))], 'titulo' => 'required|string|max:150',
            'resumen' => 'nullable|string|max:300', 'video_url' => 'nullable|url|max:255', 'minutos' => 'nullable|integer|min:1|max:120',
            'orden' => 'nullable|integer|min:0|max:999',
            'pasos' => 'required|array|min:1|max:30', 'pasos.*.titulo' => 'nullable|string|max:150', 'pasos.*.texto' => 'nullable|string|max:3000',
            'pasos.*.imagen_actual' => 'nullable|string|max:255', 'pasos.*.quitar' => 'nullable|boolean',
            'pasos.*.imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
        ], ['video_url.url' => 'Pega el enlace completo del video de YouTube.'], ['pasos.*.imagen' => 'imagen del paso', 'video_url' => 'video']);
        if (($d['video_url'] ?? null) && ! Tutoriales::youtube($d['video_url'])) {
            return back()->withInput()->withErrors(['video_url' => 'Solo videos de YouTube (pega el enlace del video).']);
        }

        // Pasos: texto y su imagen (nueva, la que ya tenía, o ninguna). Las imágenes que ya no se usan se borran
        $antes = collect($actual ? Tutoriales::preparar($actual)->pasos : [])->pluck('imagen')->filter()->all();
        $pasos = [];
        foreach ($d['pasos'] as $i => $paso) {
            $imagen = in_array($paso['imagen_actual'] ?? null, $antes, true) ? $paso['imagen_actual'] : null;
            if ($request->hasFile("pasos.$i.imagen")) {
                $imagen = Tutoriales::guardarImagen($request->file("pasos.$i.imagen"));
            } elseif (! empty($paso['quitar'])) {
                $imagen = null;
            }
            if (trim((string) ($paso['titulo'] ?? '')) === '' && trim((string) ($paso['texto'] ?? '')) === '' && ! $imagen) {
                continue;
            }
            $pasos[] = ['titulo' => trim((string) ($paso['titulo'] ?? '')), 'texto' => trim((string) ($paso['texto'] ?? '')), 'imagen' => $imagen];
        }
        if (! $pasos) {
            return back()->withInput()->withErrors(['pasos' => 'Escribe al menos un paso.']);
        }
        foreach (array_diff($antes, array_column($pasos, 'imagen')) as $sobra) {
            Tutoriales::borrarImagen($sobra);
        }

        $fila = ['rubro' => $d['rubro'], 'titulo' => trim($d['titulo']), 'resumen' => $d['resumen'] ?? null, 'video_url' => $d['video_url'] ?? null,
            'minutos' => (int) ($d['minutos'] ?? 5), 'orden' => (int) ($d['orden'] ?? 0), 'activo' => $request->boolean('activo'),
            'pasos' => json_encode($pasos, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
        if (! $actual) {
            $base = Str::limit(Str::slug($d['titulo']), 140, '') ?: 'tutorial';
            $slug = $base;
            for ($n = 2; Tutoriales::db()->where('slug', $slug)->exists(); $n++) {
                $slug = $base.'-'.$n;
            }
            $id = Tutoriales::db()->insertGetId($fila + ['slug' => $slug, 'created_at' => now()]);
        } else {
            Tutoriales::db()->where('id', $id)->update($fila);
        }

        return redirect()->route('admin.tutoriales.index', ['editar' => $id])->with('ok', 'Tutorial guardado.'.($fila['activo'] ? ' Ya se ve en la web.' : ''));
    }

    public function eliminar(int $id): RedirectResponse
    {
        $this->autorizar();
        $t = Tutoriales::db()->where('id', $id)->first();
        abort_unless($t, 404);
        foreach (Tutoriales::preparar($t)->pasos as $paso) {
            Tutoriales::borrarImagen($paso['imagen'] ?? null);
        }
        Tutoriales::db()->where('id', $id)->delete();

        return redirect()->route('admin.tutoriales.index')->with('ok', 'Tutorial eliminado.');
    }
}
