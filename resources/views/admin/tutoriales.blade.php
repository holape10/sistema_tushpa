@extends('admin.layout')
@section('title', 'Tutoriales')

@section('content')
    @use('App\Support\Tutoriales')
    @php
        $e = $editar;
        $in = 'mt-1 w-full rounded-xl border-slate-300 text-sm';
        $pasosIniciales = old('pasos') ? collect(old('pasos'))->map(fn ($p) => ['titulo' => $p['titulo'] ?? '', 'texto' => $p['texto'] ?? '', 'imagen' => $p['imagen_actual'] ?? null])->values()->all()
            : ($e ? $e->pasos : [['titulo' => '', 'texto' => '', 'imagen' => null]]);
    @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Tutoriales de {{ config('tenancy.dominio') }}/tutorial</h1>
            <p class="text-sm text-slate-500">Guías paso a paso por rubro, con texto e imágenes (y un video de YouTube si quieres).</p>
        </div>
        <a href="{{ route('sitio.tutorial') }}" target="_blank" class="ml-auto px-3 py-2 rounded-xl bg-white shadow-sm text-sm font-semibold">Ver en la web ↗</a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">@foreach ($errors->all() as $er)<p>{{ $er }}</p>@endforeach</div>
    @endif

    <div class="grid lg:grid-cols-5 gap-4 items-start">
        {{-- Lista por rubro --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm divide-y divide-slate-100">
            <div class="p-4 flex items-center"><p class="font-bold flex-1">Tutoriales ({{ $tutoriales->count() }})</p>
                <a href="{{ route('admin.tutoriales.index') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold">➕ Nuevo</a></div>
            @forelse ($tutoriales->groupBy('rubro') as $rubro => $lista)
                <div class="p-4">
                    <p class="text-xs font-bold uppercase text-slate-500 mb-2">{{ $rubros[$rubro]['nombre'] ?? $rubro }}</p>
                    @foreach ($lista as $t)
                        <a href="{{ route('admin.tutoriales.index', ['editar' => $t->id]) }}"
                           class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm {{ $e && $e->id === $t->id ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'hover:bg-slate-50' }} {{ $t->activo ? '' : 'opacity-50' }}">
                            <span class="flex-1 truncate">{{ $t->titulo }}</span>
                            <span class="text-[11px] text-slate-400 whitespace-nowrap">{{ count($t->pasos) }} pasos · {{ collect($t->pasos)->whereNotNull('imagen')->count() }} 🖼 · {{ $t->vistas }} 👁</span>
                        </a>
                    @endforeach
                </div>
            @empty
                <p class="p-8 text-center text-slate-400">Aún no hay tutoriales.</p>
            @endforelse
        </div>

        {{-- Formulario --}}
        <form method="POST" action="{{ $e ? route('admin.tutoriales.guardar', $e->id) : route('admin.tutoriales.guardar') }}" enctype="multipart/form-data"
              class="lg:col-span-3 bg-white rounded-2xl shadow-sm p-5 space-y-3"
              x-data="{ pasos: @js(collect($pasosIniciales)->map(fn ($p, $i) => $p + ['uid' => $i, 'vista' => $p['imagen'] ? asset($p['imagen']) : null, 'quitar' => false])->values()), n: 1000 }">
            @csrf
            <div class="flex items-center gap-2">
                <p class="font-bold flex-1">{{ $e ? 'Editar tutorial' : '➕ Nuevo tutorial' }}</p>
                @if ($e)<a href="{{ route('sitio.tutorial.ver', $e->slug) }}" target="_blank" class="text-xs text-indigo-600 underline">Ver en la web ↗</a>@endif
            </div>
            <div class="grid sm:grid-cols-3 gap-2">
                <label class="block text-sm">Rubro
                    <select name="rubro" class="{{ $in }}">@foreach ($rubros as $clave => $r)<option value="{{ $clave }}" @selected(old('rubro', $e->rubro ?? 'TODOS') === $clave)>{{ $r['nombre'] }}</option>@endforeach</select></label>
                <label class="block text-sm sm:col-span-2">Título *<input name="titulo" value="{{ old('titulo', $e->titulo ?? '') }}" required maxlength="150" placeholder="Ej.: Cómo cobrar una mesa" class="{{ $in }}"></label>
            </div>
            <label class="block text-sm">Resumen <span class="text-xs text-slate-400">(una línea: qué aprende)</span>
                <input name="resumen" value="{{ old('resumen', $e->resumen ?? '') }}" maxlength="300" class="{{ $in }}"></label>
            <div class="grid sm:grid-cols-4 gap-2">
                <label class="block text-sm sm:col-span-2">Video de YouTube <span class="text-xs text-slate-400">(opcional)</span>
                    <input name="video_url" type="url" value="{{ old('video_url', $e->video_url ?? '') }}" placeholder="https://youtu.be/…" class="{{ $in }}"></label>
                <label class="block text-sm">Minutos<input type="number" name="minutos" min="1" max="120" value="{{ old('minutos', $e->minutos ?? 5) }}" class="{{ $in }}"></label>
                <label class="block text-sm">Orden<input type="number" name="orden" min="0" max="999" value="{{ old('orden', $e->orden ?? 0) }}" class="{{ $in }}"></label>
            </div>

            <p class="font-semibold text-sm pt-2">Pasos</p>
            <template x-for="(p, i) in pasos" :key="p.uid">
                <div class="rounded-xl border border-slate-200 p-3 space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-bold flex items-center justify-center" x-text="i + 1"></span>
                        <input :name="'pasos[' + i + '][titulo]'" x-model="p.titulo" maxlength="150" placeholder="Título del paso" class="flex-1 rounded-lg border-slate-300 text-sm font-semibold">
                        <button type="button" x-show="pasos.length > 1" @click="pasos.splice(i, 1)" class="px-2 text-slate-400 hover:text-rose-600" title="Quitar paso">&times;</button>
                    </div>
                    <textarea :name="'pasos[' + i + '][texto]'" x-model="p.texto" rows="3" maxlength="3000" placeholder="Explica qué hacer: dónde tocar, qué escribir…" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    <input type="hidden" :name="'pasos[' + i + '][imagen_actual]'" :value="p.imagen || ''">
                    <input type="hidden" :name="'pasos[' + i + '][quitar]'" :value="p.quitar ? 1 : 0">
                    <div class="flex flex-wrap items-center gap-3">
                        <template x-if="p.vista && !p.quitar"><img :src="p.vista" alt="" class="h-20 rounded-lg border object-cover"></template>
                        <label class="text-xs text-slate-600">🖼 Imagen <span class="text-slate-400">(captura de pantalla; se achica sola)</span>
                            <input type="file" :name="'pasos[' + i + '][imagen]'" accept="image/*" @change="const f = $event.target.files[0]; if (f) { p.vista = URL.createObjectURL(f); p.quitar = false }" class="block text-xs mt-1"></label>
                        <label x-show="p.imagen" class="text-xs text-rose-600 flex items-center gap-1"><input type="checkbox" x-model="p.quitar" class="rounded"> Quitar imagen</label>
                    </div>
                </div>
            </template>
            <button type="button" @click="pasos.push({ uid: n++, titulo: '', texto: '', imagen: null, vista: null, quitar: false })" class="w-full py-2 rounded-xl border-2 border-dashed border-indigo-200 text-indigo-700 text-sm font-semibold">+ Agregar paso</button>

            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" @checked(old('activo', $e->activo ?? true)) class="rounded"> Se ve en la web</label>
            <div class="flex gap-2">
                <button class="flex-1 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">{{ $e ? 'Guardar cambios' : 'Crear tutorial' }}</button>
                @if ($e)
                    <button formaction="{{ route('admin.tutoriales.eliminar', $e->id) }}" formnovalidate onclick="return confirm('¿Eliminar este tutorial y sus imágenes?')"
                            class="h-11 px-4 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold">Eliminar</button>
                @endif
            </div>
        </form>
    </div>
@endsection
