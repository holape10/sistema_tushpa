@extends('layouts.app')
@section('title', 'Recetas y Food Cost')

@section('content')
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $fila = fn ($p) => [
        'id' => $p->IdProducto, 'nombre' => $p->pronom, 'cat' => $p->cat_nom ?: 'SIN CATEGORÍA', 'precio' => $p->precio,
        'costo' => $p->costo, 'fc' => $p->food_cost, 'ganancia' => $p->ganancia, 'receta' => $p->tiene_receta,
        'ingredientes' => $p->ingredientes, 'nivel' => $p->estado['nivel'], 'color' => $p->estado['color'], 'texto' => $p->estado['texto'],
        'url' => route('recetas.editar', $p->IdProducto),
    ];
@endphp
<div class="max-w-6xl mx-auto space-y-5" x-data="{ ver: @js($filtro), q: '', platos: @js($platos->map($fila)->values()),
        norm(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },
        get lista() {
            const q = this.norm(this.q.trim());
            return this.platos.filter(p => (!q || this.norm(p.nombre + ' ' + p.cat).includes(q))
                && (this.ver === '' || (this.ver === 'sin' && !p.receta) || (this.ver === 'con' && p.receta) || (this.ver === 'alto' && p.nivel === 'alto')));
        } }">
    @include('empresas.partials.alert')

    {{-- Qué es el food cost, en simple --}}
    <section class="rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-600 text-white p-5 sm:p-7 shadow-lg">
        <div class="flex flex-col md:flex-row gap-6 md:items-center">
            <div class="flex-1">
                <h1 class="text-2xl font-black">🍳 Recetas y Food Cost</h1>
                <p class="mt-2 text-indigo-100 text-sm leading-relaxed">
                    Escribe qué ingredientes lleva cada plato y cuánto. El sistema te dice <b class="text-white">cuánto te cuesta prepararlo</b>,
                    <b class="text-white">cuánto ganas</b> y <b class="text-white">descuenta los ingredientes del almacén</b> cada vez que lo vendes.
                </p>
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
                    @foreach (['' => 'Todos', 'con' => 'Con receta', 'sin' => 'Sin receta', 'alto' => 'Ganas poco'] as $k => $t)
                        <button type="button" @click="ver = '{{ $k }}'" :class="ver === '{{ $k }}' ? 'bg-white shadow text-indigo-700' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg">{{ $t }}</button>
                    @endforeach
                </div>
                <input type="search" x-model="q" placeholder="Buscar plato…" class="flex-1 min-w-48 h-10 rounded-xl border-gray-300 text-sm">
            </div>

            <div class="divide-y divide-gray-100">
                <template x-for="p in lista" :key="p.id">
                    <a :href="p.url" class="flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-2 px-4 py-3 hover:bg-indigo-50/50 transition">
                        <div class="flex-1 min-w-48">
                            <p class="font-bold text-gray-800" x-text="p.nombre"></p>
                            <p class="text-xs text-gray-400"><span x-text="p.cat"></span> · <span x-text="p.receta ? p.ingredientes + ' ingrediente(s)' : 'sin receta'"></span></p>
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
                </template>
                <p x-show="!lista.length" class="p-8 text-center text-gray-400 text-sm">No hay platos con ese filtro.</p>
            </div>
        </div>
    @endif
</div>
@endsection
