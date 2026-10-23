@extends('layouts.app')
@section('title', 'Recetas y Food Cost')

@section('content')
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $fila = fn ($p) => [
        'id' => $p->IdProducto, 'nombre' => $p->pronom, 'cat' => $p->cat_nom ?: 'SIN CATEGORÍA', 'precio' => $p->precio,
        'costo' => $p->costo, 'fc' => $p->food_cost, 'ganancia' => $p->ganancia, 'receta' => $p->tiene_receta,
        'ingredientes' => $p->ingredientes, 'nivel' => $p->estado['nivel'], 'color' => $p->estado['color'], 'texto' => $p->estado['texto'],
        'sin_precio' => implode(', ', $p->sin_precio),
        'url' => route('recetas.editar', $p->IdProducto),
    ];
@endphp
<div class="max-w-6xl mx-auto space-y-5" x-data="{ ver: @js($filtro), q: '', platos: @js($platos->map($fila)->values()),
        norm(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },
        get lista() {
            const q = this.norm(this.q.trim());
            return this.platos.filter(p => (!q || this.norm(p.nombre + ' ' + p.cat).includes(q))
                && (this.ver === '' || (this.ver === 'sin' && !p.receta) || (this.ver === 'con' && p.receta) || (this.ver === 'alto' && p.nivel === 'alto') || (this.ver === 'incompleto' && p.nivel === 'incompleto')));
        },
        {{-- Imprimir / descargar: un plato (su botón) o los que se ven en la lista y tienen receta --}}
        menu: null, imprimiendo: false, previa: null,
        verTicket(ids) { this.menu = null; if (!ids.length) return tushpaAviso('No hay platos con receta en esta lista.', 'error'); this.previa = ids; },
        get conReceta() { return this.lista.filter(p => p.receta).map(p => p.id); },
        enlace(tipo, ids) { return { excel: @js(route('recetas.exportar.excel')), pdf: @js(route('recetas.exportar.pdf')), navegador: @js(route('recetas.exportar.ticket.navegador')) }[tipo] + '?ids=' + ids.join(','); },
        descargar(tipo, ids) { this.menu = null; if (!ids.length) return tushpaAviso('No hay platos con receta en esta lista.', 'error'); window.open(this.enlace(tipo, ids), '_blank'); },
        async imprimir(ids) {
            this.menu = null;
            if (!ids.length) return tushpaAviso('No hay platos con receta en esta lista.', 'error');
            this.imprimiendo = true;
            try {
                const r = await fetch(@js(route('recetas.exportar.ticket')), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ ids: ids.join(',') }) });
                const d = await r.json().catch(() => ({}));
                if (d.directa) { tushpaAviso(d.mensaje, 'ok'); this.previa = null; } else window.open(this.enlace('navegador', ids), '_blank');
            } finally { this.imprimiendo = false; }
        } }">
    @include('empresas.partials.alert')

    {{-- Qué es el food cost, en simple --}}
    <section class="rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-600 text-white p-5 sm:p-7 shadow-lg relative isolate overflow-hidden">
        <x-kene-adorno patron="meandro" />
        <div class="flex flex-col md:flex-row gap-6 md:items-center">
            <div class="flex-1">
                <h1 class="text-2xl font-black">🍳 Recetas y Food Cost</h1>
                <p class="mt-2 text-indigo-100 text-sm leading-relaxed">
                    Escribe qué ingredientes lleva cada plato y cuánto. El sistema te dice <b class="text-white">cuánto te cuesta prepararlo</b>,
                    <b class="text-white">cuánto ganas</b> y <b class="text-white">descuenta los ingredientes del almacén</b> cada vez que lo vendes.
                </p>
                <a href="{{ route('recetas.rentabilidad') }}" class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white text-indigo-700 text-sm font-black shadow hover:bg-indigo-50">
                    📊 Rentabilidad del negocio: ingeniería de menú, food cost real, pérdidas y compras</a>
                <div class="mt-4 rounded-2xl bg-white/10 p-4 text-sm">
                    <p class="font-bold">¿Qué es el food cost?</p>
                    <p class="text-indigo-100 mt-1">Es la parte del precio que se va en ingredientes. Un plato de <b class="text-white">S/ 25</b> que gasta
                        <b class="text-white">S/ 7</b> en ingredientes tiene <b class="text-white">28%</b>: de cada S/ 10 que cobras, S/ 2.80 son ingredientes.</p>
                    <div class="flex flex-wrap gap-2 mt-3 text-xs font-bold">
                        <span class="px-2.5 py-1 rounded-full bg-emerald-400 text-emerald-950">Hasta {{ (int) \App\Support\Recetas::BIEN }}% · ¡Muy bien!</span>
                        <span class="px-2.5 py-1 rounded-full bg-amber-300 text-amber-950">{{ (int) \App\Support\Recetas::BIEN }}–{{ (int) \App\Support\Recetas::ALERTA }}% · Revisa</span>
                        <span class="px-2.5 py-1 rounded-full bg-rose-400 text-rose-950">Más de {{ (int) \App\Support\Recetas::ALERTA }}% · Ganas poco</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 md:w-80 shrink-0">
                <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Platos con receta</p>
                    <p class="text-2xl font-black">{{ $resumen['con_receta'] }}<span class="text-base text-indigo-200"> / {{ $resumen['total'] }}</span></p></div>
                <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Food cost promedio</p>
                    <p class="text-2xl font-black">{{ $resumen['promedio'] !== null ? number_format($resumen['promedio'], 1).'%' : '—' }}</p></div>
                <button type="button" @click="ver = 'sin'" class="text-left rounded-2xl bg-white/10 hover:bg-white/20 p-4"><p class="text-xs text-indigo-200">Sin receta</p>
                    <p class="text-2xl font-black">{{ $resumen['total'] - $resumen['con_receta'] }}</p></button>
                <button type="button" @click="ver = 'alto'" class="text-left rounded-2xl {{ $resumen['altos'] ? 'bg-rose-500/80 hover:bg-rose-500' : 'bg-white/10 hover:bg-white/20' }} p-4"><p class="text-xs text-indigo-100">Ganas poco</p>
                    <p class="text-2xl font-black">{{ $resumen['altos'] }}</p></button>
            </div>
        </div>
    </section>

    @if ($sinPrecio->isNotEmpty())
        {{-- Sin precio de compra un ingrediente cuenta como gratis: el food cost sale más bajo de lo real --}}
        <section class="rounded-2xl bg-violet-50 ring-1 ring-violet-200 p-4 sm:p-5">
            <h2 class="font-black text-violet-900">⚠ {{ $sinPrecio->count() }} {{ $sinPrecio->count() === 1 ? 'ingrediente no tiene' : 'ingredientes no tienen' }} precio de compra</h2>
            <p class="text-sm text-violet-800 mt-1">Cuentan como gratis, así que el costo y el food cost de {{ $resumen['incompletos'] }} plato(s) salen <b>más bajos de lo real</b>.
                Ponles su precio (en Productos o al abrir la receta) y todo se recalcula solo.</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($sinPrecio as $i)
                    <a href="{{ route('productos.edit', $i->IdProducto) }}" class="rounded-lg bg-white px-2.5 py-1 text-sm font-semibold text-violet-800 ring-1 ring-violet-200 hover:ring-violet-400">{{ $i->pronom }} →</a>
                @endforeach
            </div>
            <button type="button" @click="ver = 'incompleto'" class="mt-3 text-sm font-bold text-violet-700 underline">Ver los platos afectados</button>
        </section>
    @endif

    @if ($subidas->isNotEmpty())
        <section class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 sm:p-5">
            <h2 class="font-black text-amber-900">📈 Subieron de costo en los últimos 15 días</h2>
            <div class="mt-2 space-y-2">
                @foreach ($subidas as $s)
                    <div class="text-sm text-amber-900">
                        <b>{{ $s->insumo }}</b>: S/ {{ number_format($s->antes, 2) }} → <b>S/ {{ number_format($s->ahora, 2) }}</b>
                        <span class="text-amber-700">(+{{ number_format(($s->ahora - $s->antes) / max($s->antes, 0.01) * 100, 0) }}%)</span>
                        @if ($s->platos->isNotEmpty())
                            · lo usan:
                            @foreach ($s->platos as $pl)
                                <a href="{{ route('recetas.editar', $pl->IdProducto) }}" class="inline-block rounded-lg bg-white px-2 py-0.5 mr-1 mt-1 ring-1 ring-amber-200 hover:ring-amber-400">
                                    {{ $pl->pronom }} <b style="color: {{ $pl->estado['color'] }}">{{ $pl->food_cost }}%</b></a>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($resumen['total'] === 0)
        <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">
            Aún no tienes platos. Las recetas son para los productos de tipo <b>Preparado</b> (los platos y bebidas que se preparan).
            <a href="{{ route('productos.create') }}" class="block mt-3 text-indigo-600 font-bold">Crear un plato →</a>
        </div>
    @else
        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-4 flex flex-wrap gap-3 items-center border-b border-gray-100">
                <div class="flex gap-1 bg-gray-100 rounded-xl p-1 text-sm font-semibold">
                    @foreach (['' => 'Todos', 'con' => 'Con receta', 'sin' => 'Sin receta', 'alto' => 'Ganas poco'] + ($resumen['incompletos'] ? ['incompleto' => 'Falta precio'] : []) as $k => $t)
                        <button type="button" @click="ver = '{{ $k }}'" :class="ver === '{{ $k }}' ? 'bg-white shadow text-indigo-700' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg">{{ $t }}</button>
                    @endforeach
                </div>
                <input type="search" x-model="q" placeholder="Buscar plato…" class="flex-1 min-w-48 h-10 rounded-xl border-gray-300 text-sm">
                {{-- Las recetas que se ven en la lista (según el filtro y la búsqueda) --}}
                <div class="flex flex-wrap items-center gap-1.5 w-full lg:w-auto">
                    <span class="text-xs text-gray-400 mr-1" x-text="conReceta.length + (conReceta.length === 1 ? ' receta' : ' recetas') + ':'"></span>
                    <button type="button" @click="verTicket(conReceta)" :disabled="!conReceta.length" class="h-9 px-3 rounded-xl bg-slate-800 text-white text-xs font-bold hover:bg-slate-700 disabled:opacity-40">🖨 Imprimir todas</button>
                    <button type="button" @click="descargar('pdf', conReceta)" :disabled="!conReceta.length" class="h-9 px-3 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 disabled:opacity-40">PDF</button>
                    <button type="button" @click="descargar('excel', conReceta)" :disabled="!conReceta.length" class="h-9 px-3 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 disabled:opacity-40">Excel</button>
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                <template x-for="p in lista" :key="p.id">
                    <div class="flex items-center hover:bg-indigo-50/50 transition">
                    <a :href="p.url" class="flex-1 min-w-0 flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-2 pl-4 py-3">
                        <div class="flex-1 min-w-48">
                            <p class="font-bold text-gray-800" x-text="p.nombre"></p>
                            <p class="text-xs text-gray-400"><span x-text="p.cat"></span> · <span x-text="p.receta ? p.ingredientes + ' ingrediente(s)' : 'sin receta'"></span></p>
                            <p x-show="p.sin_precio" class="text-xs font-semibold text-violet-700" x-text="'Sin precio de compra: ' + p.sin_precio"></p>
                        </div>
                        <div class="w-20 text-right"><p class="text-[11px] text-gray-400">Precio</p><p class="font-semibold text-gray-700" x-text="'S/ ' + p.precio.toFixed(2)"></p></div>
                        <div class="w-20 text-right"><p class="text-[11px] text-gray-400">Costo</p><p class="font-semibold text-gray-700" x-text="p.receta ? 'S/ ' + p.costo.toFixed(2) : '—'"></p></div>
                        <div class="w-20 text-right"><p class="text-[11px] text-gray-400">Ganas</p><p class="font-bold text-emerald-700" x-text="p.receta ? 'S/ ' + p.ganancia.toFixed(2) : '—'"></p></div>
                        <div class="w-40">
                            <template x-if="p.receta">
                                <div>
                                    <div class="flex justify-between text-xs font-bold"><span :style="'color:' + p.color" x-text="p.texto"></span><span :style="'color:' + p.color" x-text="p.fc !== null ? p.fc + '%' : ''"></span></div>
                                    <div class="h-2 rounded-full bg-gray-100 mt-1 overflow-hidden"><div class="h-full rounded-full" :style="'width:' + Math.min(100, p.fc || 0) + '%; background:' + p.color"></div></div>
                                </div>
                            </template>
                            <template x-if="!p.receta">
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 bg-indigo-50 rounded-lg px-2.5 py-1.5">+ Armar receta</span>
                            </template>
                        </div>
                    </a>
                    {{-- Receta de este plato: ticket, PDF o Excel --}}
                    <div class="w-14 shrink-0 flex justify-center relative" @click.outside="menu === p.id && (menu = null)">
                        <button type="button" x-show="p.receta" @click="menu = menu === p.id ? null : p.id" title="Imprimir o descargar la receta"
                                class="w-9 h-9 rounded-xl bg-gray-100 text-gray-600 hover:bg-indigo-100 hover:text-indigo-700">🖨</button>
                        <div x-show="menu === p.id" x-cloak x-transition class="absolute right-2 top-11 z-20 w-44 rounded-xl bg-white shadow-xl ring-1 ring-black/5 py-1 text-sm">
                            <button type="button" @click="verTicket([p.id])" class="w-full text-left px-3 py-2 hover:bg-gray-50">🖨 Ver e imprimir ticket</button>
                            <button type="button" @click="descargar('pdf', [p.id])" class="w-full text-left px-3 py-2 hover:bg-gray-50">📄 Descargar PDF</button>
                            <button type="button" @click="descargar('excel', [p.id])" class="w-full text-left px-3 py-2 hover:bg-gray-50">📊 Descargar Excel</button>
                        </div>
                    </div>
                    </div>
                </template>
                <p x-show="!lista.length" class="p-8 text-center text-gray-400 text-sm">No hay platos con ese filtro.</p>
            </div>
        </div>

        {{-- Vista previa del ticket: se ve tal como sale de la ticketera y desde aquí se imprime --}}
        <div x-show="previa" x-cloak class="fixed inset-0 z-[70] bg-black/50 flex items-center justify-center p-3" @keydown.escape.window="previa = null" @click.self="previa = null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-full flex flex-col overflow-hidden">
                <div class="px-4 py-3 border-b flex items-center gap-2">
                    <p class="font-bold text-gray-800 flex-1" x-text="previa && previa.length > 1 ? 'Vista previa · ' + previa.length + ' recetas' : 'Vista previa del ticket'"></p>
                    <button type="button" @click="previa = null" class="w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-500" aria-label="Cerrar">✕</button>
                </div>
                <template x-if="previa">
                    <iframe :src="enlace('navegador', previa) + '&vista=1'" class="w-full flex-1 min-h-[60vh] bg-gray-200" title="Vista previa del ticket"></iframe>
                </template>
                <div class="p-3 border-t grid grid-cols-2 gap-2">
                    <button type="button" @click="imprimir(previa)" :disabled="imprimiendo" class="h-11 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-700 disabled:opacity-50"
                            x-text="imprimiendo ? 'Enviando…' : '🖨 Imprimir en la ticketera'"></button>
                    <button type="button" @click="window.open(enlace('navegador', previa), '_blank')" class="h-11 rounded-xl bg-gray-100 text-gray-700 text-sm font-bold hover:bg-gray-200">Imprimir desde esta PC</button>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
