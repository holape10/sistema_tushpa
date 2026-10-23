@extends('layouts.app')
@section('title', 'Rentabilidad del negocio')

@section('content')
@use('App\Http\Controllers\RentabilidadController')
@use('App\Support\Rentabilidad')
@use('App\Support\Unidades')
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $pct = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 1), '0'), '.').'%';
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $cant = fn ($o, $n) => Unidades::texto((float) $n, $o->umecod, $o->ume_equivalente, (float) $o->factor);
    $colorFc = fn ($fc) => $fc === null ? 'text-gray-400' : ($fc <= \App\Support\Recetas::BIEN ? 'text-emerald-600' : ($fc <= \App\Support\Recetas::ALERTA ? 'text-amber-600' : 'text-rose-600'));
    $url = fn (array $cambios = []) => route('recetas.rentabilidad', array_filter(array_merge(['ver' => $ver, 'desde' => $desde, 'hasta' => $hasta,
        'categoria' => $categoria, 'dias' => $dias, 'cubrir' => $cubrir], $cambios)));
    $porPeriodo = in_array($ver, ['menu', 'foodcost', 'teorico', 'precios'], true);
@endphp
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-wrap items-end gap-3">
        <div class="mr-auto">
            <a href="{{ route('recetas.index') }}" class="text-sm text-gray-500">← Recetas y Food Cost</a>
            <h1 class="text-2xl font-black text-gray-800">📊 Rentabilidad del negocio</h1>
            <p class="text-sm text-gray-500">Qué te deja más plata, dónde se pierde y qué comprar.</p>
        </div>
        <form class="flex flex-wrap items-end gap-2 text-xs">
            <input type="hidden" name="ver" value="{{ $ver }}">
            @if ($porPeriodo)
                <label class="font-semibold text-gray-500">Desde<input type="date" name="desde" value="{{ $desde }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
                <label class="font-semibold text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"></label>
            @endif
            @if ($ver === 'menu')
                <label class="font-semibold text-gray-500">Categoría
                    <select name="categoria" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm"><option value="">Todas</option>
                        @foreach ($categorias as $id => $nombre)<option value="{{ $id }}" @selected($categoria == $id)>{{ $nombre }}</option>@endforeach</select></label>
            @endif
            @if ($ver === 'compras')
                <label class="font-semibold text-gray-500">Consumo de los últimos<select name="dias" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm">
                    @foreach ([7, 14, 30, 60] as $d)<option value="{{ $d }}" @selected($dias === $d)>{{ $d }} días</option>@endforeach</select></label>
                <label class="font-semibold text-gray-500">Comprar para<select name="cubrir" class="mt-1 block h-9 rounded-lg border-gray-300 text-sm">
                    @foreach ([3, 7, 15, 30] as $d)<option value="{{ $d }}" @selected($cubrir === $d)>{{ $d }} días</option>@endforeach</select></label>
            @endif
            <button class="h-9 px-4 rounded-lg bg-indigo-600 text-white font-bold">Ver</button>
            <a href="{{ route('recetas.rentabilidad.excel', request()->query() + ['ver' => $ver]) }}" class="h-9 px-3 inline-flex items-center rounded-lg bg-emerald-600 text-white font-bold">⬇ Excel</a>
        </form>
    </div>

    {{-- Resumen del periodo --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Vendiste</p><p class="text-2xl font-black text-gray-800">{{ $soles($resumen->ventas) }}</p>
            <p class="text-xs text-gray-400">del {{ \Carbon\Carbon::parse($desde)->format('d/m') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Costo de lo vendido</p><p class="text-2xl font-black text-gray-800">{{ $soles($resumen->costo) }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-emerald-600">Ganancia bruta</p><p class="text-2xl font-black text-emerald-700">{{ $soles($resumen->ganancia) }}</p>
            <p class="text-xs text-gray-400">antes de sueldos, alquiler y servicios</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Food cost real</p><p class="text-2xl font-black {{ $colorFc($resumen->food_cost) }}">{{ $pct($resumen->food_cost) }}</p>
            <p class="text-xs text-gray-400">de cada S/ 10 vendidos, S/ {{ $resumen->food_cost !== null ? number_format($resumen->food_cost / 10, 2) : '—' }} son costo</p></div>
    </div>
    @if ($resumen->sin_costo > 0)
        <p class="rounded-xl bg-violet-50 ring-1 ring-violet-200 px-4 py-2 text-sm text-violet-800">⚠️ {{ $soles($resumen->sin_costo) }} se vendieron de productos <b>sin costo registrado</b>:
            su ganancia sale igual a la venta. Arma su receta o registra su costo para que los números sean reales.</p>
    @endif

    <nav class="flex gap-2 overflow-x-auto pb-1">
        @foreach (RentabilidadController::VISTAS as $clave => [$nombre, $icono])
            <a href="{{ $url(['ver' => $clave]) }}" class="shrink-0 h-10 px-4 inline-flex items-center rounded-xl shadow-sm text-sm font-bold {{ $ver === $clave ? 'bg-gray-900 text-white' : 'bg-white text-gray-700' }}">{{ $icono }} {{ $nombre }}</a>
        @endforeach
    </nav>

    {{-- ===================================================== Ingeniería de menú --}}
    @if ($ver === 'menu')
        <p class="text-sm text-gray-600">Cada plato o trago según <b>cuánto se vende</b> y <b>cuánto te deja por unidad</b>. Se compara con el promedio de tu carta:
            se considera que se vende bien si lleva al menos el {{ $num($popularidad_min) }}% de las unidades vendidas, y que deja bien si gana más de {{ $soles($ganancia_promedio) }} por unidad.</p>
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach (Rentabilidad::CLASES as $clase => [$nombre, $icono, $color, $consejo])
                @php $grupo = $platos->where('clase', $clase); @endphp
                <section class="bg-white rounded-2xl shadow-sm overflow-hidden" style="border-top: 4px solid {{ $color }}">
                    <div class="p-4 pb-2">
                        <p class="text-lg font-black" style="color: {{ $color }}">{{ $icono }} {{ $nombre }} <span class="text-sm text-gray-400 font-bold">({{ $grupo->count() }})</span></p>
                        <p class="text-xs text-gray-500">{{ $consejo }}</p>
                        @if ($grupo->isNotEmpty())<p class="text-xs font-semibold text-gray-600 mt-1">Ganancia: {{ $soles($grupo->sum('ganancia')) }} · {{ $num($grupo->sum('cantidad')) }} vendidos</p>@endif
                    </div>
                    <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                        @forelse ($grupo as $p)
                            <div class="px-4 py-2 flex items-center gap-3 text-sm">
                                <div class="flex-1 min-w-0"><p class="font-semibold text-gray-800 truncate">{{ $p->nombre }}</p>
                                    <p class="text-xs text-gray-400">{{ $num($p->cantidad) }} vendidos · {{ $soles($p->ganancia_unidad) }} c/u · food cost <span class="{{ $colorFc($p->food_cost) }}">{{ $pct($p->food_cost) }}</span></p></div>
                                <p class="text-right font-bold text-gray-800 whitespace-nowrap">{{ $soles($p->ganancia) }}</p>
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-gray-400">Ninguno en este periodo.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
        @if ($sinCosto->isNotEmpty())
            <details class="bg-white rounded-2xl shadow-sm p-4">
                <summary class="cursor-pointer font-bold text-violet-700">{{ $sinCosto->count() }} producto(s) vendidos sin costo: no se pueden clasificar</summary>
                <p class="text-xs text-gray-500 mt-1">Arma su receta en Recetas y Food Cost o pon su costo en Productos.</p>
                <div class="mt-2 grid sm:grid-cols-2 gap-x-6 text-sm">
                    @foreach ($sinCosto as $p)<p class="flex justify-between border-b border-gray-50 py-1"><span>{{ $p->nombre }}</span><span class="text-gray-500">{{ $num($p->cantidad) }} · {{ $soles($p->ventas) }}</span></p>@endforeach
                </div>
            </details>
        @endif
        @if ($platos->isEmpty() && $sinCosto->isEmpty())
            <p class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">Sin ventas en estas fechas.</p>
        @endif
    @endif

    {{-- ===================================================== Food cost real --}}
    @if ($ver === 'foodcost')
        @php $maxVentas = max(1, (float) $meses->max('ventas')); @endphp
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-black text-gray-800">Mes a mes</h2>
            <p class="text-xs text-gray-500 mb-4">La barra es lo vendido; la parte oscura, lo que se fue en costo. Lo sano es hasta {{ (int) \App\Support\Recetas::BIEN }}% (verde); de {{ (int) \App\Support\Recetas::BIEN }}% a {{ (int) \App\Support\Recetas::ALERTA }}% revisa precios (naranja); más es ganar poco (rojo).</p>
            <div class="space-y-3">
                @foreach ($meses as $m)
                    <div class="grid grid-cols-[80px_1fr_auto] items-center gap-3 text-sm">
                        <span class="font-semibold text-gray-600">{{ $m->nombre }}</span>
                        <div class="h-6 rounded-lg bg-emerald-100 relative overflow-hidden" style="width: {{ max(2, $m->ventas / $maxVentas * 100) }}%">
                            <div class="absolute inset-y-0 left-0 bg-emerald-700/70" style="width: {{ $m->ventas > 0 ? min(100, $m->costo / $m->ventas * 100) : 0 }}%"></div>
                        </div>
                        <span class="whitespace-nowrap text-right"><b>{{ $soles($m->ventas) }}</b> · <span class="font-bold {{ $colorFc($m->food_cost) }}">{{ $pct($m->food_cost) }}</span></span>
                    </div>
                @endforeach
            </div>
        </section>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h2 class="p-4 font-black text-gray-800">Por categoría · {{ \Carbon\Carbon::parse($desde)->format('d/m') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</h2>
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-gray-50 text-xs text-gray-500 text-left"><tr><th class="px-4 py-2">Categoría</th><th class="px-4 py-2 text-right">Vendido</th><th class="px-4 py-2 text-right">Costo</th>
                    <th class="px-4 py-2 text-right">Ganancia</th><th class="px-4 py-2 text-right">Food cost</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($categorias as $c)
                        <tr><td class="px-4 py-2 font-semibold">{{ $c->nombre }} @if ($c->sin_costo > 0)<span class="text-[11px] text-violet-600" title="Vendido sin costo registrado">· {{ $soles($c->sin_costo) }} sin costo</span>@endif</td>
                            <td class="px-4 py-2 text-right">{{ $soles($c->ventas) }}</td><td class="px-4 py-2 text-right">{{ $soles($c->costo) }}</td>
                            <td class="px-4 py-2 text-right font-bold text-emerald-700">{{ $soles($c->ganancia) }}</td>
                            <td class="px-4 py-2 text-right font-bold {{ $colorFc($c->food_cost) }}">{{ $pct($c->food_cost) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin ventas en estas fechas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    {{-- ===================================================== Teórico vs. real --}}
    @if ($ver === 'teorico')
        <div class="rounded-2xl bg-sky-50 ring-1 ring-sky-200 p-4 text-sm text-sky-900 space-y-1">
            <p><b>¿Cómo se lee?</b> "Debió gastarse" es lo que dicen las recetas de lo vendido. Al contar el inventario, si falta más de eso, la diferencia es lo que
                <b>se perdió sin explicación</b>: tragos más cargados, porciones grandes, robo o desperdicio sin anotar como merma.</p>
            <p>Para que funcione: haz un <a href="{{ route('inventarios.create') }}" class="font-bold underline">conteo de inventario</a> al inicio y al final del periodo
                (por ejemplo cada semana) y anota las mermas. En estas fechas hay <b>{{ $conteos }}</b> conteo(s).</p>
        </div>
        @php $perdida = $insumos->where('perdida', '>', 0)->sum('perdida'); $sobrante = $insumos->where('perdida', '<', 0)->sum('perdida'); @endphp
        <div class="grid sm:grid-cols-3 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Debió gastarse (recetas)</p>
                <p class="text-2xl font-black text-gray-800">{{ $soles($insumos->sum(fn ($i) => $i->teorico * $i->costo)) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Mermas anotadas</p>
                <p class="text-2xl font-black text-gray-800">{{ $soles($insumos->sum(fn ($i) => $i->mermas * $i->costo)) }}</p></div>
            <div class="rounded-2xl shadow-sm p-4 {{ $perdida > 0 ? 'bg-rose-50' : 'bg-white' }}"><p class="text-xs font-semibold text-rose-600">Faltó sin explicación</p>
                <p class="text-2xl font-black text-rose-700">{{ $soles($perdida) }}</p>
                @if ($sobrante < 0)<p class="text-xs text-gray-500">y sobró {{ $soles(abs($sobrante)) }} (recetas con más cantidad de la real)</p>@endif</div>
        </div>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm min-w-[820px]">
                <thead class="bg-gray-50 text-xs text-gray-500 text-left"><tr><th class="px-4 py-2">Insumo</th><th class="px-4 py-2 text-right">Debió gastarse</th>
                    <th class="px-4 py-2 text-right">Mermas</th><th class="px-4 py-2 text-right">Faltó / sobró al contar</th><th class="px-4 py-2 text-right">Gasto real</th>
                    <th class="px-4 py-2 text-right">Desviación</th><th class="px-4 py-2 text-right">Pérdida</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($insumos as $i)
                        <tr class="{{ $i->perdida > 0 && ($i->desviacion === null || $i->desviacion > 5) ? 'bg-rose-50/40' : '' }}">
                            <td class="px-4 py-2 font-semibold">{{ $i->nombre }}</td>
                            <td class="px-4 py-2 text-right">{{ $cant($i, $i->teorico) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $i->mermas ? $cant($i, $i->mermas) : '—' }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ $i->diferencia > 0 ? 'text-rose-600' : ($i->diferencia < 0 ? 'text-emerald-600' : 'text-gray-400') }}">
                                {{ $i->diferencia ? ($i->diferencia > 0 ? 'faltó ' : 'sobró ').$cant($i, abs($i->diferencia)) : '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ $cant($i, $i->real) }}</td>
                            <td class="px-4 py-2 text-right font-bold {{ ($i->desviacion ?? 0) > 5 ? 'text-rose-600' : 'text-gray-500' }}">{{ $i->desviacion !== null ? ($i->desviacion > 0 ? '+' : '').$pct($i->desviacion) : '—' }}</td>
                            <td class="px-4 py-2 text-right font-bold {{ $i->perdida > 0 ? 'text-rose-700' : 'text-gray-400' }}">{{ $i->perdida ? $soles($i->perdida) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin consumo de insumos por recetas en estas fechas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    {{-- ===================================================== Lista de compras --}}
    @if ($ver === 'compras')
        <p class="text-sm text-gray-600">Con lo que se gastó en los últimos <b>{{ $dias }} días</b> (ventas, recetas y mermas) y el stock de todos tus almacenes:
            lo que debes comprar para <b>{{ $cubrir }} días</b>, más el stock mínimo de cada producto.</p>
        <div class="grid sm:grid-cols-3 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Productos por comprar</p><p class="text-2xl font-black text-gray-800">{{ $lista->count() }}</p></div>
            <div class="rounded-2xl shadow-sm p-4 bg-rose-50"><p class="text-xs font-semibold text-rose-600">Se acaban en 2 días o menos</p>
                <p class="text-2xl font-black text-rose-700">{{ $lista->filter(fn ($p) => $p->dias !== null && $p->dias <= 2)->count() }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold text-gray-500">Costo estimado</p><p class="text-2xl font-black text-gray-800">{{ $soles($lista->sum('importe')) }}</p></div>
        </div>
        <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm min-w-[860px]">
                <thead class="bg-gray-50 text-xs text-gray-500 text-left"><tr><th class="px-4 py-2">Producto</th><th class="px-4 py-2 text-right">Hay</th>
                    <th class="px-4 py-2 text-right">Gasta por día</th><th class="px-4 py-2 text-right">Alcanza</th><th class="px-4 py-2 text-right">Comprar</th>
                    <th class="px-4 py-2 text-right">Costo aprox.</th><th class="px-4 py-2">Último proveedor</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($lista as $p)
                        <tr>
                            <td class="px-4 py-2"><span class="font-semibold">{{ $p->nombre }}</span> <span class="text-[11px] text-gray-400">{{ $p->insumo ? 'insumo' : 'producto' }}</span></td>
                            <td class="px-4 py-2 text-right {{ $p->stock <= 0 ? 'text-rose-600 font-bold' : '' }}">{{ $cant($p, $p->stock) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $p->por_dia > 0 ? $cant($p, $p->por_dia) : '—' }}</td>
                            <td class="px-4 py-2 text-right font-bold {{ $p->dias !== null && $p->dias <= 2 ? 'text-rose-600' : 'text-gray-700' }}">{{ $p->dias !== null ? $num($p->dias).' días' : 'stock mínimo' }}</td>
                            <td class="px-4 py-2 text-right font-black text-indigo-700 whitespace-nowrap">{{ $num($p->comprar) }} {{ Unidades::nombre($p->umecod, $p->comprar) }}</td>
                            <td class="px-4 py-2 text-right">{{ $soles($p->importe) }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $p->proveedor ?? '—' }}@if ($p->ultima_compra)<span class="block text-[11px]">{{ \Carbon\Carbon::parse($p->ultima_compra)->format('d/m/Y') }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-emerald-600 font-semibold">Tienes stock suficiente para {{ $cubrir }} días. 👍</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    {{-- ===================================================== Historial de precios --}}
    @if ($ver === 'precios')
        <p class="text-sm text-gray-600">Cómo cambió el costo de tus insumos y productos (por compras o al editarlos) y a cuántos platos afecta. Si subió, revisa el precio de esos platos en
            <a href="{{ route('recetas.index') }}" class="font-semibold text-indigo-600 underline">Recetas y Food Cost</a>.</p>
        <section class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
            @forelse ($lista as $c)
                <details class="px-4 py-3">
                    <summary class="flex flex-wrap items-center gap-3 cursor-pointer text-sm">
                        <span class="flex-1 min-w-48 font-semibold text-gray-800">{{ $c->nombre }}</span>
                        <span class="text-gray-500">{{ $soles($c->antes) }} → <b class="text-gray-800">{{ $soles($c->ahora) }}</b></span>
                        <span class="w-20 text-right font-black {{ ($c->variacion ?? 0) > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $c->variacion !== null ? ($c->variacion > 0 ? '▲ ' : '▼ ').$pct(abs($c->variacion)) : 'nuevo' }}</span>
                        <span class="w-32 text-right text-xs text-gray-500">{{ $c->platos ? $c->platos.' plato(s) afectados' : 'no está en recetas' }}</span>
                    </summary>
                    <div class="mt-2 pl-2 text-xs text-gray-600 space-y-1">
                        @foreach ($c->cambios as $h)
                            <p>{{ \Carbon\Carbon::parse($h->fecha)->format('d/m/Y H:i') }} · {{ $soles($h->antes) }} → {{ $soles($h->ahora) }} <span class="text-gray-400">({{ mb_strtolower($h->origen) }})</span></p>
                        @endforeach
                    </div>
                </details>
            @empty
                <p class="p-10 text-center text-gray-400">Ningún costo cambió en estas fechas.</p>
            @endforelse
        </section>
    @endif
</div>
@endsection
