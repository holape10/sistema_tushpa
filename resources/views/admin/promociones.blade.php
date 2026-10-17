@extends('admin.layout')
@section('title', 'Promociones web')

@section('content')
    @use('App\Support\Promociones')
    @php
        $e = $editar;
        $paginasSel = old('paginas', $e ? (json_decode((string) $e->paginas, true) ?: ['todas']) : ['inicio']);
        $in = 'mt-1 w-full rounded-xl border-slate-300 text-sm';
    @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Promociones en {{ config('tenancy.dominio') }}</h1>
            <p class="text-sm text-slate-500">Ventanas con tu oferta, barras de aviso y páginas de oferta. Tú decides dónde, cuándo y cada cuánto salen.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">@foreach ($errors->all() as $er)<p>{{ $er }}</p>@endforeach</div>
    @endif

    <div class="grid lg:grid-cols-5 gap-4 items-start">
        {{-- Formulario --}}
        <form method="POST" action="{{ $e ? route('admin.promociones.guardar', $e->id) : route('admin.promociones.guardar') }}" enctype="multipart/form-data"
              class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-5 space-y-3"
              x-data="{ tipo: @js(old('tipo', $e->tipo ?? 'MODAL')), pagina: @js((bool) old('con_pagina', $e->con_pagina ?? false)), color: @js(old('color', $e->color ?? '#2563eb')),
                        titulo: @js(old('titulo', $e->titulo ?? '')), texto: @js(old('texto', $e->texto ?? '')), boton: @js(old('boton_texto', $e->boton_texto ?? 'Ver oferta')),
                        img: @js($e && $e->imagen ? asset($e->imagen) : null) }">
            @csrf
            <div class="flex items-center gap-2">
                <p class="font-bold flex-1">{{ $e ? 'Editar promoción' : '➕ Nueva promoción' }}</p>
                @if ($e)<a href="{{ route('admin.promociones.index') }}" class="text-xs text-slate-500 underline">Nueva</a>@endif
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach (['MODAL' => ['🪟 Ventana emergente', 'Aparece al centro con imagen'], 'BARRA' => ['📢 Barra de aviso', 'Una franja abajo de la pantalla']] as $valor => [$n, $d])
                    <label class="rounded-xl border-2 px-3 py-2 cursor-pointer" :class="tipo === '{{ $valor }}' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'">
                        <input type="radio" name="tipo" value="{{ $valor }}" x-model="tipo" class="sr-only"><span class="block text-sm font-bold">{{ $n }}</span><span class="text-[11px] text-slate-500">{{ $d }}</span></label>
                @endforeach
            </div>
            <label class="block text-sm">Título *<input name="titulo" x-model="titulo" required maxlength="120" placeholder="Ej.: ¡50% de descuento el primer mes!" class="{{ $in }}"></label>
            <label class="block text-sm">Texto<textarea name="texto" x-model="texto" rows="2" maxlength="600" placeholder="Ej.: Solo hasta el 31 de octubre para restaurantes de Iquitos." class="{{ $in }}"></textarea></label>
            <div x-show="tipo === 'MODAL'" class="text-sm">Imagen <span class="text-xs text-slate-400">(opcional, JPG/PNG/WEBP/GIF hasta 4 MB)</span>
                <input type="file" name="imagen" accept="image/*" @change="const f = $event.target.files[0]; if (f) img = URL.createObjectURL(f)" class="mt-1 block w-full text-sm">
                @if ($e && $e->imagen)<label class="mt-1 flex items-center gap-1 text-xs text-rose-600"><input type="checkbox" name="quitar_imagen" value="1" @change="img = $event.target.checked ? null : @js(asset($e->imagen))" class="rounded"> Quitar imagen</label>@endif
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm">Texto del botón<input name="boton_texto" x-model="boton" maxlength="60" class="{{ $in }}"></label>
                <label class="block text-sm">Color<input type="color" name="color" x-model="color" class="mt-1 h-10 w-full rounded-xl border-slate-300 p-1"></label>
            </div>
            <label class="block text-sm">Enlace del botón <span class="text-xs text-slate-400">(vacío = su página de oferta o el registro)</span>
                <input name="boton_url" type="url" value="{{ old('boton_url', $e->boton_url ?? '') }}" maxlength="255" placeholder="https://wa.me/51965… o https://{{ config('tenancy.dominio') }}/registro" class="{{ $in }}"></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="con_pagina" value="1" x-model="pagina" class="rounded"> Tiene su propia página de oferta</label>
            <label x-show="pagina" class="block text-sm">Contenido de la página
                <textarea name="contenido" rows="4" maxlength="5000" placeholder="Detalles, condiciones, qué incluye…" class="{{ $in }}">{{ old('contenido', $e->contenido ?? '') }}</textarea>
                @if ($e && $e->slug)<a href="{{ route('sitio.oferta', $e->slug) }}" target="_blank" class="text-xs text-indigo-600 underline">{{ route('sitio.oferta', $e->slug) }}</a>@endif</label>

            <div class="text-sm">¿En qué páginas sale?
                <div class="mt-1 grid grid-cols-2 gap-1">
                    @foreach (Promociones::PAGINAS as $valor => $nombre)
                        <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="paginas[]" value="{{ $valor }}" @checked(in_array($valor, $paginasSel, true)) class="rounded"> {{ $nombre }}</label>
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm">Desde<input type="date" name="desde" value="{{ old('desde', $e->desde ?? '') }}" class="{{ $in }}"></label>
                <label class="block text-sm">Hasta<input type="date" name="hasta" value="{{ old('hasta', $e->hasta ?? '') }}" class="{{ $in }}"></label>
                <label class="block text-sm">¿Cada cuánto?
                    <select name="frecuencia" class="{{ $in }}">@foreach (Promociones::FRECUENCIAS as $v => $n)<option value="{{ $v }}" @selected(old('frecuencia', $e->frecuencia ?? 'DIA') === $v)>{{ $n }}</option>@endforeach</select></label>
                <label class="block text-sm">Aparece a los (seg.)<input type="number" name="retraso" min="0" max="120" value="{{ old('retraso', $e->retraso ?? 3) }}" class="{{ $in }}"></label>
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" @checked(old('activo', $e->activo ?? true)) class="rounded"> Activa</label>
                <label class="text-sm flex items-center gap-1 ml-auto">Orden <input type="number" name="orden" min="0" max="999" value="{{ old('orden', $e->orden ?? 0) }}" class="w-16 rounded-lg border-slate-300 text-sm"></label>
            </div>

            {{-- Vista previa --}}
            <div class="rounded-xl bg-slate-100 p-3">
                <p class="text-[11px] font-semibold text-slate-500 mb-2">VISTA PREVIA</p>
                <div x-show="tipo === 'MODAL'" class="mx-auto max-w-xs bg-white rounded-2xl overflow-hidden shadow-lg text-center">
                    <template x-if="img"><img :src="img" alt="" class="w-full max-h-40 object-cover"></template>
                    <div class="p-4"><p class="font-black text-lg leading-tight" x-text="titulo || 'Título de tu oferta'"></p>
                        <p class="text-xs text-slate-500 mt-1" x-text="texto"></p>
                        <span class="mt-3 inline-block px-4 py-2 rounded-xl text-white text-sm font-bold" :style="'background:' + color" x-text="boton || 'Ver oferta'"></span></div>
                </div>
                <div x-show="tipo === 'BARRA'" class="rounded-lg px-3 py-2 text-white text-sm flex items-center gap-2" :style="'background:' + color">
                    <b x-text="titulo || 'Título'"></b><span class="opacity-90 truncate" x-text="texto"></span>
                    <span class="ml-auto px-2 py-0.5 rounded bg-white/20 text-xs font-bold" x-text="boton || 'Ver'"></span></div>
            </div>
            <button class="w-full h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">{{ $e ? 'Guardar cambios' : 'Crear promoción' }}</button>
        </form>

        {{-- Lista --}}
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm divide-y divide-slate-100">
            @forelse ($promos as $p)
                @php $vigente = $p->activo && (! $p->hasta || $p->hasta >= now()->toDateString()) && (! $p->desde || $p->desde <= now()->toDateString()); @endphp
                <div class="p-4 flex gap-3 {{ $vigente ? '' : 'opacity-60' }}">
                    <div class="w-16 h-16 rounded-xl shrink-0 overflow-hidden flex items-center justify-center text-2xl" style="background: {{ $p->color }}">
                        @if ($p->imagen)<img src="{{ asset($p->imagen) }}" alt="" class="w-full h-full object-cover">@else{{ $p->tipo === 'BARRA' ? '📢' : '🪟' }}@endif</div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold">{{ $p->titulo }}
                            <span class="ml-1 px-1.5 rounded text-[10px] font-bold {{ $vigente ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $vigente ? 'SE VE AHORA' : ($p->activo ? 'FUERA DE FECHA' : 'PAUSADA') }}</span></p>
                        <p class="text-xs text-slate-500">{{ $p->tipo === 'BARRA' ? 'Barra' : 'Ventana' }} · {{ collect(json_decode((string) $p->paginas, true) ?: ['todas'])->map(fn ($x) => Promociones::PAGINAS[$x] ?? $x)->implode(', ') }}
                            · {{ Promociones::FRECUENCIAS[$p->frecuencia] ?? $p->frecuencia }}{{ $p->desde || $p->hasta ? ' · '.($p->desde ? \Carbon\Carbon::parse($p->desde)->format('d/m') : '…').' al '.($p->hasta ? \Carbon\Carbon::parse($p->hasta)->format('d/m') : '…') : '' }}</p>
                        <p class="text-xs mt-1"><b>{{ number_format($p->vistas) }}</b> vistas · <b>{{ number_format($p->clics) }}</b> clics
                            @if ($p->vistas)<span class="text-slate-400">({{ round($p->clics / $p->vistas * 100, 1) }}%)</span>@endif
                            @if ($p->con_pagina && $p->slug) · <a href="{{ route('sitio.oferta', $p->slug) }}" target="_blank" class="text-indigo-600 underline">ver su página</a>@endif</p>
                    </div>
                    <div class="flex flex-col gap-1 items-end">
                        <a href="{{ route('admin.promociones.index', ['editar' => $p->id]) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 text-xs font-semibold">Editar</a>
                        <form method="POST" action="{{ route('admin.promociones.estado', $p->id) }}">@csrf<button class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $p->activo ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $p->activo ? 'Pausar' : 'Activar' }}</button></form>
                        <form method="POST" action="{{ route('admin.promociones.eliminar', $p->id) }}" onsubmit="return confirm('¿Eliminar la promoción?')">@csrf<button class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">Eliminar</button></form>
                    </div>
                </div>
            @empty
                <p class="p-10 text-center text-slate-400">Aún no tienes promociones. Crea la primera a la izquierda.</p>
            @endforelse
        </div>
    </div>
@endsection
