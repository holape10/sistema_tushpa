<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Promociones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Promociones de tushpa.app (ventanas, barras y páginas de oferta). Solo el dueño. */
class PromocionController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño administra las promociones de la web.');
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $editar = $request->filled('editar') ? Promociones::db()->where('id', (int) $request->editar)->first() : null;

        return view('admin.promociones', ['promos' => Promociones::db()->orderByDesc('activo')->orderBy('orden')->orderByDesc('id')->get(), 'editar' => $editar]);
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $this->autorizar();
        $actual = $id ? Promociones::db()->where('id', $id)->first() : null;
        abort_if($id && ! $actual, 404);
        $d = $request->validate([
            'titulo' => 'required|string|max:120', 'texto' => 'nullable|string|max:600', 'tipo' => 'required|in:MODAL,BARRA',
            'boton_texto' => 'nullable|string|max:60', 'boton_url' => 'nullable|url|max:255',
            'con_pagina' => 'nullable|boolean', 'contenido' => 'nullable|string|max:5000',
            'paginas' => 'required|array|min:1', 'paginas.*' => Rule::in(array_keys(Promociones::PAGINAS)),
            'desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde',
            'frecuencia' => ['required', Rule::in(array_keys(Promociones::FRECUENCIAS))], 'retraso' => 'required|integer|min:0|max:120',
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'orden' => 'nullable|integer|min:0|max:999', 'activo' => 'nullable|boolean',
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096', 'quitar_imagen' => 'nullable|boolean',
            // Cómo se ve la imagen: entera o recortada mostrando la parte elegida (% horizontal y vertical)
            'imagen_ajuste' => 'nullable|in:RECORTAR,COMPLETA', 'imagen_pos' => ['nullable', 'regex:/^\d{1,3} \d{1,3}$/'],
            // Ciudades donde vale la promoción, con el WhatsApp de cada una
            'ciudades' => 'nullable|array|max:20', 'ciudades.*.nombre' => 'nullable|string|max:40',
            'ciudades.*.whatsapp' => ['nullable', 'regex:/^\+?[\d\s]{9,15}$/'],
        ], ['ciudades.*.whatsapp.regex' => 'El WhatsApp de cada ciudad debe tener 9 dígitos (o con código de país).'],
            ['boton_url' => 'enlace del botón', 'paginas' => 'páginas', 'retraso' => 'segundos']);

        $fila = array_merge(collect($d)->except(['imagen', 'quitar_imagen', 'paginas', 'ciudades'])->all(), [
            'paginas' => json_encode(in_array('todas', $d['paginas'], true) ? ['todas'] : array_values($d['paginas'])),
            'ciudades' => ($ciudades = Promociones::limpiarCiudades($d['ciudades'] ?? [])) ? json_encode($ciudades, JSON_UNESCAPED_UNICODE) : null,
            'con_pagina' => $request->boolean('con_pagina'), 'activo' => $request->boolean('activo'), 'orden' => (int) ($d['orden'] ?? 0), 'updated_at' => now(),
            'imagen_ajuste' => $d['imagen_ajuste'] ?? 'RECORTAR', 'imagen_pos' => $d['imagen_pos'] ?? '50 50',
        ]);
        // Su página de oferta: tushpa.app/ofertas/titulo-de-la-promo
        if ($fila['con_pagina'] && ! ($actual->slug ?? null)) {
            $base = Str::limit(Str::slug($d['titulo']), 70, '') ?: 'oferta';
            $slug = $base;
            for ($n = 2; Promociones::db()->where('slug', $slug)->exists(); $n++) {
                $slug = $base.'-'.$n;
            }
            $fila['slug'] = $slug;
        }
        $imagen = $this->imagen($request, $actual?->imagen);
        if ($imagen !== false) {
            $fila['imagen'] = $imagen;
        }

        $id ? Promociones::db()->where('id', $id)->update($fila) : Promociones::db()->insert($fila + ['created_at' => now()]);

        return redirect()->route('admin.promociones.index')->with('ok', 'Promoción guardada.'.($fila['activo'] ? ' Ya se ve en tushpa.app.' : ''));
    }

    public function estado(int $id): RedirectResponse
    {
        $this->autorizar();
        $p = Promociones::db()->where('id', $id)->first();
        abort_unless($p, 404);
        Promociones::db()->where('id', $id)->update(['activo' => ! $p->activo, 'updated_at' => now()]);

        return back()->with('ok', $p->activo ? 'Promoción pausada.' : 'Promoción activada.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        $this->autorizar();
        $p = Promociones::db()->where('id', $id)->first();
        abort_unless($p, 404);
        $this->borrarImagen($p->imagen);
        Promociones::db()->where('id', $id)->delete();

        return redirect()->route('admin.promociones.index')->with('ok', 'Promoción eliminada.');
    }

    /** Nueva imagen (ruta), null si se quitó, false si no cambió */
    private function imagen(Request $request, ?string $anterior): string|null|false
    {
        if ($request->hasFile('imagen')) {
            $nombre = 'promo_'.Str::random(10).'.'.strtolower($request->file('imagen')->guessExtension() ?: 'jpg');
            $request->file('imagen')->move(public_path('imagenes/promos'), $nombre);
            $this->borrarImagen($anterior);

            return 'imagenes/promos/'.$nombre;
        }
        if ($request->boolean('quitar_imagen')) {
            $this->borrarImagen($anterior);

            return null;
        }

        return false;
    }

    private function borrarImagen(?string $ruta): void
    {
        if ($ruta && str_starts_with($ruta, 'imagenes/promos/') && is_file(public_path($ruta))) {
            @unlink(public_path($ruta));
        }
    }
}
