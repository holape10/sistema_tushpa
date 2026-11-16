@extends('admin.layout')
@section('title', 'Base de datos')

@section('content')
    @php
        $t = fn ($b) => \App\Support\Servidor::tamano($b);
        $elegida = $base ? $bases->firstWhere('base', $base) : null;
    @endphp

    <h1 class="text-xl font-bold mb-1">🗄️ Base de datos
        @if ($edicion)
            <span class="ml-1 rounded-full bg-rose-100 px-2.5 py-0.5 align-middle text-xs font-bold text-rose-700">modo edición abierto</span>
        @else
            <span class="ml-1 rounded-full bg-emerald-100 px-2.5 py-0.5 align-middle text-xs font-bold text-emerald-700">solo lectura</span>
        @endif
    </h1>
    <p class="text-sm text-slate-500 mb-4">Mira las tablas y filas de cualquier base y corre consultas SELECT (las claves salen tapadas). Para cambiar datos abre el modo edición con tu segunda clave.</p>

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
                {{-- Consulta SQL --}}
                <form method="POST" action="{{ route('admin.basedatos.consulta') }}" class="rounded-2xl bg-white shadow-sm p-4 space-y-2">
                    @csrf
                    <input type="hidden" name="base" value="{{ $base }}">
                    <label class="block text-sm font-bold text-slate-700">Consulta (SELECT, SHOW, DESCRIBE o EXPLAIN)
                        <textarea id="sql-lectura" name="sql" rows="4" spellcheck="false" placeholder="SELECT tdocod, COUNT(*) FROM cpe_cabecera WHERE est_sunat = 'PENDIENTE' GROUP BY tdocod"
                                  class="mt-1 w-full rounded-xl border-slate-300 font-mono text-sm" @keydown.ctrl.enter="$el.form.submit()">{{ $sql }}</textarea></label>
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="h-10 px-5 rounded-xl bg-indigo-600 text-white text-sm font-bold">▶ Ejecutar</button>
                        <button name="excel" value="1" class="h-10 px-4 rounded-xl bg-white border border-emerald-600 text-emerald-700 text-sm font-bold">⬇ Excel (hasta {{ number_format(\App\Support\ExploradorBd::FILAS_EXCEL) }} filas)</button>
                        <span class="text-xs text-slate-400">Ctrl + Enter ejecuta · máximo 8 segundos · una sola sentencia</span>
                    </div>
                    @if ($error)<p class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ $error }}</p>@endif
                </form>

                {{-- Asistente IA: sugiere la consulta, no ejecuta nada --}}
                <section class="rounded-2xl bg-white shadow-sm p-4 space-y-2" x-data="{ enviando: false }">
                    <form method="POST" action="{{ route('admin.basedatos.ia') }}" @submit="enviando = true" class="space-y-2">
                        @csrf
                        <input type="hidden" name="base" value="{{ $base }}">
                        <label class="block text-sm font-bold text-slate-700">🤖 Pregúntale al asistente qué consulta escribir
                            <textarea name="pregunta" rows="2" required maxlength="2000" placeholder="Ej.: ¿cómo veo las boletas pendientes de hoy? · ¿cómo le pongo precio 25 al producto AF00016?"
                                      class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ $pregunta ?? '' }}</textarea></label>
                        <button class="h-10 px-5 rounded-xl bg-violet-600 text-white text-sm font-bold disabled:opacity-60" :disabled="enviando"
                                x-text="enviando ? 'Pensando… (puede tardar un minuto)' : 'Preguntar'">Preguntar</button>
                        <span class="text-xs text-slate-400">Solo mira la base y sugiere: tú revisas y ejecutas.</span>
                    </form>
                    @isset($errorIa)<p class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ $errorIa }}</p>@endisset
                    @isset($respuestaIa)
                        <div class="rounded-xl bg-violet-50 p-3 text-sm space-y-2">
                            @foreach (preg_split('/```(?:sql|mysql)?[ \t]*\n?(.*?)```/is', $respuestaIa, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $parte)
                                @if ($i % 2 === 0)
                                    @if (trim($parte) !== '')<div class="whitespace-pre-line text-slate-700">{{ trim($parte) }}</div>@endif
                                @else
                                    @php $esLectura = (bool) preg_match('/^\s*(select|show|describe|desc|explain|with)\b/i', $parte); @endphp
                                    <div class="rounded-lg bg-slate-900 p-3">
                                        <pre class="whitespace-pre-wrap font-mono text-xs text-emerald-200">{{ trim($parte) }}</pre>
                                        <button type="button" class="mt-2 rounded-lg bg-white px-3 py-1 text-xs font-bold text-slate-800"
                                                @click="const c = document.getElementById(@js($esLectura ? 'sql-lectura' : 'sql-cambio')); if (c) { c.value = @js(trim($parte)); c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.focus() } else { alert('Abre primero el modo edición con tu segunda clave.') }">
                                            {{ $esLectura ? '↑ Usar en la consulta' : '↓ Pasar al modo edición' }}</button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endisset
                </section>

                {{-- Modo edición: segunda clave, probar y luego ejecutar --}}
                <section class="rounded-2xl shadow-sm p-4 space-y-3 {{ $edicion ? 'bg-rose-50 border border-rose-200' : 'bg-white' }}">
                    <h2 class="font-bold text-slate-700">✏️ Cambiar datos</h2>
                    @if (! $tieneClave)
                        <p class="text-sm text-slate-600">Primero crea tu <b>segunda clave</b> (distinta a la del panel). Solo con ella se podrá cambiar algo en las bases.</p>
                        <form method="POST" action="{{ route('admin.basedatos.clave') }}" class="grid sm:grid-cols-3 gap-2">
                            @csrf <input type="hidden" name="base" value="{{ $base }}">
                            <input type="password" name="actual" required placeholder="Tu clave del panel" autocomplete="current-password" class="h-10 rounded-xl border-slate-300 text-sm">
                            <input type="password" name="nueva" required minlength="8" placeholder="Segunda clave (mín. 8)" autocomplete="new-password" class="h-10 rounded-xl border-slate-300 text-sm">
                            <input type="password" name="nueva_confirmation" required minlength="8" placeholder="Repite la segunda clave" autocomplete="new-password" class="h-10 rounded-xl border-slate-300 text-sm">
                            <button class="sm:col-span-3 h-10 rounded-xl bg-slate-800 text-white text-sm font-bold">Guardar segunda clave</button>
                        </form>
                    @elseif (! $edicion)
                        <form method="POST" action="{{ route('admin.basedatos.desbloquear') }}" class="flex flex-wrap gap-2">
                            @csrf <input type="hidden" name="base" value="{{ $base }}">
                            <input type="password" name="clave_bd" required placeholder="Segunda clave" autocomplete="off" class="h-10 w-64 rounded-xl border-slate-300 text-sm">
                            <button class="h-10 px-5 rounded-xl bg-rose-600 text-white text-sm font-bold">🔓 Abrir modo edición ({{ \App\Support\EdicionBd::MINUTOS }} min)</button>
                        </form>
                        <details class="text-xs text-slate-500"><summary class="cursor-pointer">Cambiar mi segunda clave</summary>
                            <form method="POST" action="{{ route('admin.basedatos.clave') }}" class="mt-2 grid sm:grid-cols-3 gap-2">
                                @csrf <input type="hidden" name="base" value="{{ $base }}">
                                <input type="password" name="actual" required placeholder="Segunda clave actual" class="h-9 rounded-lg border-slate-300 text-sm">
                                <input type="password" name="nueva" required minlength="8" placeholder="Nueva (mín. 8)" class="h-9 rounded-lg border-slate-300 text-sm">
                                <input type="password" name="nueva_confirmation" required minlength="8" placeholder="Repite la nueva" class="h-9 rounded-lg border-slate-300 text-sm">
                                <button class="sm:col-span-3 h-9 rounded-lg bg-slate-700 text-white text-sm font-bold">Cambiar</button>
                            </form>
                        </details>
                    @else
                        <div class="flex flex-wrap items-center gap-2 text-sm" x-data="{ resta: {{ max(0, $edicion - now()->timestamp) }} }" x-init="setInterval(() => { if (resta > 0) resta-- }, 1000)">
                            <span class="font-semibold text-rose-700">Abierto · se cierra en <span x-text="Math.floor(resta / 60) + ':' + String(resta % 60).padStart(2, '0')"></span></span>
                            <form method="POST" action="{{ route('admin.basedatos.bloquear') }}">@csrf <input type="hidden" name="base" value="{{ $base }}">
                                <button class="h-8 px-3 rounded-lg bg-white border text-xs font-bold">🔒 Cerrar ya</button></form>
                        </div>
                        <form method="POST" action="{{ route('admin.basedatos.probar') }}" class="space-y-2">
                            @csrf <input type="hidden" name="base" value="{{ $base }}">
                            <label class="block text-sm font-bold text-slate-700">Orden (UPDATE, DELETE, INSERT o REPLACE · una sola · UPDATE/DELETE siempre con WHERE)
                                <textarea id="sql-cambio" name="sql_cambio" rows="4" spellcheck="false" required placeholder="UPDATE productos SET precio_venta = 25 WHERE IdProducto = 123"
                                          class="mt-1 w-full rounded-xl border-rose-300 font-mono text-sm">{{ isset($prueba) ? $prueba['sql'] : $sqlCambio }}</textarea></label>
                            <button class="h-10 px-5 rounded-xl bg-amber-500 text-white text-sm font-bold">🧪 Probar (no cambia nada)</button>
                            <span class="text-xs text-slate-500">Primero se prueba y se deshace, para ver cuántas filas cambiaría. Máximo {{ number_format(\App\Support\EdicionBd::FILAS_MAXIMAS) }} filas.</span>
                        </form>
                        @isset($prueba)
                            <form method="POST" action="{{ route('admin.basedatos.ejecutar') }}" class="rounded-xl bg-white border border-rose-300 p-3 space-y-2"
                                  onsubmit="return confirm(@js('¿Cambiar de verdad '.$prueba['filas'].' fila(s) en '.$base.'? No se puede deshacer desde aquí.'))">
                                @csrf <input type="hidden" name="base" value="{{ $base }}">
                                <input type="hidden" name="sql_cambio" value="{{ $prueba['sql'] }}"><input type="hidden" name="firma" value="{{ $prueba['firma'] }}">
                                <p class="text-sm">Esta orden cambiaría <b class="text-rose-700">{{ number_format($prueba['filas']) }} fila(s)</b>. Si es lo que esperabas, ejecútala.</p>
                                <button class="h-10 px-5 rounded-xl bg-rose-600 text-white text-sm font-bold">⚠ Ejecutar de verdad</button>
                            </form>
                        @endisset
                    @endif
                    @isset($errorCambio)<p class="rounded-xl bg-rose-100 px-3 py-2 text-sm font-semibold text-rose-800">{{ $errorCambio }}</p>@endisset
                    @isset($okCambio)<p class="rounded-xl bg-emerald-100 px-3 py-2 text-sm font-semibold text-emerald-800">✅ {{ $okCambio }}</p>@endisset
                    <p class="text-xs text-slate-400">Consejo: saca una copia de seguridad de la base antes de un cambio grande. Cada cambio queda registrado (quién, base, orden y filas).</p>
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
