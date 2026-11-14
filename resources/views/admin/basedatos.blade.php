@extends('admin.layout')
@section('title', 'Base de datos')

@section('content')
    @php
        $t = fn ($b) => \App\Support\Servidor::tamano($b);
        $elegida = $base ? $bases->firstWhere('base', $base) : null;
    @endphp

    <h1 class="text-xl font-bold mb-1">🗄️ Base de datos <span class="ml-1 rounded-full bg-emerald-100 px-2.5 py-0.5 align-middle text-xs font-bold text-emerald-700">solo lectura</span></h1>
    <p class="text-sm text-slate-500 mb-4">Mira las tablas y filas de cualquier base y corre consultas SELECT. No se puede cambiar nada: las claves y contraseñas salen tapadas.</p>

    {{-- Base --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <label class="text-sm font-semibold text-slate-600">Base
            <select name="base" onchange="this.form.submit()" class="block mt-1 h-10 min-w-[320px] rounded-xl border-slate-300 text-sm">
                <option value="">— Elige una base —</option>
                @foreach ($bases as $b)
                    <option value="{{ $b->base }}" @selected($b->base === $base)>{{ $b->base }}{{ $b->empresa ? ' · '.$b->empresa : '' }} ({{ $t($b->total) }})</option>
                @endforeach
            </select></label>
    </form>

    @if ($base)
        <div class="grid lg:grid-cols-[260px_1fr] gap-4 items-start">
            {{-- Tablas --}}
            <aside class="rounded-2xl bg-white shadow-sm overflow-hidden lg:sticky lg:top-4" x-data="{ filtro: '' }">
                <div class="p-3 border-b">
                    <p class="text-xs text-slate-500">{{ $elegida?->empresa ?? 'del sistema' }} · {{ $tablas->count() }} tablas</p>
                    <input x-model="filtro" placeholder="Buscar tabla…" class="mt-2 w-full h-9 rounded-lg border-slate-300 text-sm">
                </div>
                <div class="max-h-[70vh] overflow-y-auto">
                    @foreach ($tablas as $tb)
                        <a href="{{ route('admin.basedatos.index', ['base' => $base, 'tabla' => $tb->tabla]) }}" x-show="!filtro || @js($tb->tabla).includes(filtro.toLowerCase())"
                           class="flex items-center gap-2 px-3 py-1.5 text-sm {{ $tb->tabla === $tabla ? 'bg-indigo-600 text-white' : 'hover:bg-slate-50' }}">
                            <span class="flex-1 truncate font-mono text-xs">{{ $tb->tabla }}</span>
                            <span class="text-[11px] {{ $tb->tabla === $tabla ? 'text-indigo-100' : 'text-slate-400' }}">{{ number_format($tb->filas) }}</span>
                        </a>
                    @endforeach
                </div>
            </aside>

            <div class="space-y-4 min-w-0">
                {{-- Consulta SQL --}}
                <form method="POST" action="{{ route('admin.basedatos.consulta') }}" class="rounded-2xl bg-white shadow-sm p-4 space-y-2">
                    @csrf
                    <input type="hidden" name="base" value="{{ $base }}">
                    <label class="block text-sm font-bold text-slate-700">Consulta (SELECT, SHOW, DESCRIBE o EXPLAIN)
                        <textarea name="sql" rows="4" spellcheck="false" placeholder="SELECT tdocod, COUNT(*) FROM cpe_cabecera WHERE est_sunat = 'PENDIENTE' GROUP BY tdocod"
                                  class="mt-1 w-full rounded-xl border-slate-300 font-mono text-sm" @keydown.ctrl.enter="$el.form.submit()">{{ $sql }}</textarea></label>
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="h-10 px-5 rounded-xl bg-indigo-600 text-white text-sm font-bold">▶ Ejecutar</button>
                        <button name="excel" value="1" class="h-10 px-4 rounded-xl bg-white border border-emerald-600 text-emerald-700 text-sm font-bold">⬇ Excel (hasta {{ number_format(\App\Support\ExploradorBd::FILAS_EXCEL) }} filas)</button>
                        <span class="text-xs text-slate-400">Ctrl + Enter ejecuta · máximo 8 segundos · una sola sentencia</span>
                    </div>
                    @if ($error)<p class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ $error }}</p>@endif
                </form>

                {{-- Resultado de la consulta --}}
                @if ($resultado)
                    <section class="rounded-2xl bg-white shadow-sm overflow-hidden">
                        <p class="px-4 py-2 border-b text-sm font-bold">Resultado <span class="font-normal text-slate-500">· {{ number_format($resultado['total']) }} fila(s){{ $resultado['cortado'] ? ' · se muestran las primeras '.count($resultado['filas']) : '' }}</span></p>
                        @include('admin.partials.tabla_filas', ['filas' => $resultado['filas'], 'titulos' => array_keys($resultado['filas'][0] ?? [])])
                    </section>
                @endif

                {{-- Filas de la tabla elegida --}}
                @if ($tabla && ! $resultado)
                    <section class="rounded-2xl bg-white shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b flex flex-wrap items-center gap-2">
                            <h2 class="flex-1 font-bold font-mono">{{ $tabla }}</h2>
                            <form method="GET" class="flex gap-2">
                                <input type="hidden" name="base" value="{{ $base }}"><input type="hidden" name="tabla" value="{{ $tabla }}">
                                <input name="q" value="{{ $q }}" placeholder="Buscar en las columnas…" class="h-9 w-56 rounded-lg border-slate-300 text-sm">
                                <button class="h-9 px-3 rounded-lg bg-slate-100 text-sm font-bold">Buscar</button>
                            </form>
                        </div>
                        @if ($errorTabla)
                            <p class="p-4 text-sm font-semibold text-rose-700">{{ $errorTabla }}</p>
                        @elseif ($vista)
                            <details class="px-4 py-2 border-b text-sm">
                                <summary class="cursor-pointer font-semibold text-slate-600">Columnas ({{ count($vista['columnas']) }})</summary>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($vista['columnas'] as $c)
                                        <span class="rounded-lg bg-slate-100 px-2 py-0.5 font-mono text-[11px]">{{ $c['llave'] ? '🔑 ' : '' }}{{ $c['nombre'] }} <span class="text-slate-400">{{ $c['tipo'] }}{{ $c['nulo'] ? '' : ' · obligatorio' }}</span></span>
                                    @endforeach
                                </div>
                            </details>
                            @include('admin.partials.tabla_filas', ['filas' => $vista['filas'], 'titulos' => array_column($vista['columnas'], 'nombre')])
                            <div class="px-4 py-2 flex items-center gap-3 text-sm">
                                @if ($pagina > 1)<a href="{{ route('admin.basedatos.index', ['base' => $base, 'tabla' => $tabla, 'q' => $q, 'pagina' => $pagina - 1]) }}" class="font-bold text-indigo-700">← Anteriores</a>@endif
                                <span class="text-slate-500">Página {{ $pagina }}{{ $q !== '' ? ' · buscando «'.$q.'»' : '' }}</span>
                                @if ($vista['hay_mas'])<a href="{{ route('admin.basedatos.index', ['base' => $base, 'tabla' => $tabla, 'q' => $q, 'pagina' => $pagina + 1]) }}" class="font-bold text-indigo-700">Siguientes →</a>@endif
                                <span class="ml-auto text-xs text-slate-400">{{ \App\Support\ExploradorBd::POR_PAGINA }} por página, las más nuevas primero</span>
                            </div>
                        @endif
                    </section>
                @elseif (! $resultado)
                    <p class="rounded-2xl bg-white shadow-sm p-8 text-center text-sm text-slate-500">Elige una tabla a la izquierda o escribe una consulta.</p>
                @endif
            </div>
        </div>
    @endif
@endsection
