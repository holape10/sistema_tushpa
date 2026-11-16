@extends('admin.layout')
@section('title', 'Base de datos')

@section('content')
    @php
        $t = fn ($b) => \App\Support\Servidor::tamano($b);
        $elegida = $base ? $bases->firstWhere('base', $base) : null;
    @endphp

    <h1 class="text-xl font-bold mb-1">🗄️ Base de datos</h1>
    <p class="text-sm text-slate-500 mb-4">Elige la base, mira sus tablas y escribe tu orden SQL. Para cambiar datos pon tu segunda clave.</p>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Base: se busca escribiendo nombre, RUC, subdominio o base --}}
    @php
        $opciones = $bases->map(fn ($b) => ['base' => $b->base, 'texto' => ($etiquetas[$b->base] ?? ($b->empresa ?: 'sistema')).' · '.$b->base.' ('.$t($b->total).')'])->values();
        $textoElegida = $base ? ($opciones->firstWhere('base', $base)['texto'] ?? $base) : '';
    @endphp
    <form method="GET" class="mb-4" x-data="{ q: '', abierto: false, opciones: @js($opciones),
            get lista() { const p = this.q.toLowerCase().split(/\s+/).filter(Boolean); return this.opciones.filter(o => p.every(x => o.texto.toLowerCase().includes(x))).slice(0, 60); } }"
          @click.outside="abierto = false">
        <label class="block text-sm font-semibold text-slate-600">Base
            <input x-model="q" @focus="abierto = true" @input="abierto = true" @keydown.escape="abierto = false"
                   @keydown.enter.prevent="if (lista.length) { $refs.base.value = lista[0].base; $el.form.submit() }"
                   placeholder="Escribe nombre, RUC, subdominio o base…"
                   class="block mt-1 h-10 w-full max-w-2xl rounded-xl border-slate-300 text-sm"></label>
        <input type="hidden" name="base" x-ref="base" value="{{ $base }}">
        @if ($textoElegida)<p class="mt-1 text-xs text-slate-500">Elegida: <b>{{ $textoElegida }}</b></p>@endif
        <div x-show="abierto" x-cloak class="mt-1 max-w-2xl max-h-80 overflow-y-auto rounded-xl border bg-white shadow-lg">
            <template x-for="o in lista" :key="o.base">
                <button type="button" @click="$refs.base.value = o.base; $el.form.submit()" class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50" x-text="o.texto"></button>
            </template>
            <p x-show="!lista.length" class="px-3 py-2 text-sm text-slate-500">Ninguna base coincide.</p>
        </div>
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
                {{-- Un solo cuadro de SQL: lo de lectura muestra filas; los cambios se ejecutan directo con la segunda clave puesta --}}
                <section class="rounded-2xl bg-white shadow-sm p-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        @if ($edicion)
                            <span class="font-semibold text-rose-700" x-data="{ resta: {{ max(0, $edicion - now()->timestamp) }} }" x-init="setInterval(() => { if (resta > 0) resta-- }, 1000)">
                                🔓 Puedes cambiar datos · <span x-text="Math.floor(resta / 60) + ' min'"></span></span>
                            <form method="POST" action="{{ route('admin.basedatos.bloquear') }}">@csrf <input type="hidden" name="base" value="{{ $base }}">
                                <button class="h-8 px-3 rounded-lg bg-slate-100 text-xs font-bold">🔒 Cerrar</button></form>
                        @elseif ($tieneClave)
                            <form method="POST" action="{{ route('admin.basedatos.desbloquear') }}" class="flex flex-wrap items-center gap-2">
                                @csrf <input type="hidden" name="base" value="{{ $base }}">
                                <span class="text-slate-500">🔒 Solo lectura. Para cambiar datos:</span>
                                <input type="password" name="clave_bd" required placeholder="Segunda clave" autocomplete="off" class="h-9 w-44 rounded-lg border-slate-300 text-sm">
                                <button class="h-9 px-4 rounded-lg bg-rose-600 text-white text-xs font-bold">Abrir</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.basedatos.clave') }}" class="flex flex-wrap items-center gap-2">
                                @csrf <input type="hidden" name="base" value="{{ $base }}">
                                <span class="text-slate-500">🔒 Para poder cambiar datos crea tu segunda clave:</span>
                                <input type="password" name="actual" required placeholder="Clave del panel" autocomplete="current-password" class="h-9 w-40 rounded-lg border-slate-300 text-sm">
                                <input type="password" name="nueva" required minlength="6" placeholder="Segunda clave nueva" autocomplete="new-password" class="h-9 w-44 rounded-lg border-slate-300 text-sm">
                                <button class="h-9 px-4 rounded-lg bg-slate-800 text-white text-xs font-bold">Guardar</button>
                            </form>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.basedatos.consulta') }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="base" value="{{ $base }}">
                        <textarea id="sql" name="sql" rows="5" spellcheck="false" placeholder="SELECT * FROM productos WHERE procod = 'AF00016'&#10;UPDATE productos SET propun = 25 WHERE IdProducto = 123"
                                  class="w-full rounded-xl border-slate-300 font-mono text-sm" @keydown.ctrl.enter="$el.form.submit()">{{ $sql }}</textarea>
                        <div class="flex flex-wrap items-center gap-2">
                            <button class="h-10 px-6 rounded-xl bg-indigo-600 text-white text-sm font-bold">▶ Ejecutar</button>
                            <button name="excel" value="1" class="h-10 px-4 rounded-xl bg-white border border-emerald-600 text-emerald-700 text-sm font-bold">⬇ Excel</button>
                            <span class="text-xs text-slate-400">Ctrl + Enter ejecuta · una orden a la vez</span>
                        </div>
                    </form>
                    @if ($error)<p class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ $error }}</p>@endif
                    @isset($okCambio)<p class="rounded-xl bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">✅ {{ $okCambio }}</p>@endisset
                </section>

                {{-- Asistente IA: escribe la consulta; con «Usar» pasa al cuadro de arriba --}}
                <section class="rounded-2xl bg-white shadow-sm p-4 space-y-2" x-data="{ enviando: false }">
                    <form method="POST" action="{{ route('admin.basedatos.ia') }}" @submit="enviando = true" class="flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="base" value="{{ $base }}">
                        <input name="pregunta" required maxlength="2000" value="{{ $pregunta ?? '' }}" placeholder="🤖 Pídele la consulta: «ponle precio 25 al producto AF00016»"
                               class="flex-1 min-w-[240px] h-10 rounded-xl border-slate-300 text-sm">
                        <button class="h-10 px-5 rounded-xl bg-violet-600 text-white text-sm font-bold disabled:opacity-60" :disabled="enviando"
                                x-text="enviando ? 'Escribiendo…' : 'Pedir'">Pedir</button>
                    </form>
                    @isset($errorIa)<p class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ $errorIa }}</p>@endisset
                    @isset($respuestaIa)
                        <div class="rounded-xl bg-violet-50 p-3 text-sm space-y-2">
                            @foreach (preg_split('/```(?:sql|mysql)?[ \t]*\n?(.*?)```/is', $respuestaIa, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $parte)
                                @if ($i % 2 === 0)
                                    @if (trim($parte) !== '')<div class="whitespace-pre-line text-slate-700">{{ trim($parte) }}</div>@endif
                                @else
                                    <div class="flex items-start gap-2 rounded-lg bg-slate-900 p-3">
                                        <pre class="flex-1 whitespace-pre-wrap font-mono text-xs text-emerald-200">{{ trim($parte) }}</pre>
                                        <button type="button" class="shrink-0 rounded-lg bg-white px-3 py-1 text-xs font-bold text-slate-800"
                                                @click="const c = document.getElementById('sql'); c.value = @js(trim($parte)); c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.focus()">↑ Usar</button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endisset
                </section>

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
