@extends('layouts.app')
@section('title', 'Campañas')

@section('content')
@use('App\Support\Campanas')
@php
    [$nombre, $icono] = Campanas::LISTA[$plan['clave']];
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') ?: '0';
    $fecha = fn ($f) => $f->locale('es')->translatedFormat('d \d\e F');
    $comprar = $plan['productos']->where('comprar', '>', 0);
    $faltan = (int) now()->startOfDay()->diffInDays($plan['inicio']->copy()->startOfDay(), false);
    $lista = "🛒 Compras para {$nombre} – {$negocio}\n\n".$comprar->map(fn ($p) => '• '.$num($p->comprar).' '.$p->nombre)->implode("\n");
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    <section class="rounded-3xl bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-600 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">{{ $icono }} Prepárate para {{ $nombre }}</h1>
        <p class="mt-1 text-white/90">Del {{ $fecha($plan['inicio']) }} al {{ $fecha($plan['fin']) }}
            · {{ $faltan > 0 ? "empieza en {$faltan} día(s)" : ($plan['fin']->isPast() ? 'terminó' : 'ya empezó') }}.
            @if ($plan['base'] === 'anio_pasado')
                En esas fechas del año pasado vendiste <b>{{ $soles($plan['vendido_pasado']) }}</b>. Aquí está lo que te falta para vender igual.
            @else
                Aún no tienes ventas del año pasado en esas fechas: el cálculo es <b>con lo que vendes ahora</b> (últimos 30 días) por los días de la campaña. En campaña se suele vender más.
            @endif
        </p>
    </section>

    <nav class="flex gap-2 overflow-x-auto pb-1">
        @foreach (Campanas::LISTA as $clave => [$n, $i])
            <a href="{{ route('campanas.index', ['campana' => $clave]) }}"
               class="shrink-0 h-10 px-4 inline-flex items-center gap-1.5 rounded-xl shadow-sm text-sm font-bold {{ $plan['clave'] === $clave ? 'bg-gray-900 text-white' : 'bg-white text-gray-700' }}">{{ $i }} {{ $n }}</a>
        @endforeach
    </nav>

    <div class="grid sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold uppercase text-gray-500">Productos por comprar</p>
            <p class="text-2xl font-black text-indigo-700">{{ $comprar->count() }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs font-semibold uppercase text-gray-500">Plata que necesitas (al costo)</p>
            <p class="text-2xl font-black text-emerald-700">{{ $soles($plan['inversion']) }}</p></div>
        <div class="bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-600">Se compra lo vendido {{ $plan['base'] === 'anio_pasado' ? 'el año pasado' : '(estimado)' }} menos tu stock de hoy
            y lo que ya vendiste en esta campaña.</div>
    </div>

    @if ($plan['productos']->isEmpty())
        <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">Todavía no hay ventas para calcular esta campaña.</p>
    @else
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr><th class="px-3 py-2 text-left">Producto</th>
                            <th class="px-3 py-2 text-right">{{ $plan['base'] === 'anio_pasado' ? 'Vendiste el año pasado' : 'Venderías (estimado)' }}</th>
                            <th class="px-3 py-2 text-right">Ya vendido</th><th class="px-3 py-2 text-right">Stock hoy</th>
                            <th class="px-3 py-2 text-right">Comprar</th><th class="px-3 py-2 text-right">Costo aprox.</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($plan['productos']->take(150) as $p)
                            <tr class="border-t border-gray-100 {{ $p->comprar > 0 ? '' : 'text-gray-400' }}">
                                <td class="px-3 py-2 font-semibold">{{ $p->nombre }}</td>
                                <td class="px-3 py-2 text-right">{{ $num($p->cantidad) }} <span class="text-xs text-gray-400">({{ $soles($p->total) }})</span></td>
                                <td class="px-3 py-2 text-right">{{ $num($p->ya_vendido) }}</td>
                                <td class="px-3 py-2 text-right">{{ $num($p->stock) }}</td>
                                <td class="px-3 py-2 text-right font-black {{ $p->comprar > 0 ? 'text-emerald-700' : '' }}">{{ $p->comprar > 0 ? $num($p->comprar) : '✓ alcanza' }}</td>
                                <td class="px-3 py-2 text-right">{{ $p->inversion > 0 ? $soles($p->inversion) : ($p->comprar > 0 ? 'sin costo' : '') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($comprar->isNotEmpty())
                <div class="flex flex-wrap gap-2 p-4 bg-gray-50 border-t border-gray-100">
                    <a href="https://wa.me/?text={{ rawurlencode($lista) }}" target="_blank" rel="noopener" class="h-11 px-5 inline-flex items-center rounded-xl bg-emerald-600 text-white font-bold">💬 Mandar la lista por WhatsApp</a>
                    <button type="button" onclick="window.print()" class="h-11 px-4 rounded-xl bg-gray-100 text-gray-700 font-bold">🖨 Imprimir</button>
                    @if (auth()->user()->esAdmin() || auth()->user()->tieneModulo('/requerimientos'))
                        <a href="{{ route('requerimientos.create') }}" class="h-11 px-4 inline-flex items-center rounded-xl bg-indigo-600 text-white font-bold">Hacer el pedido</a>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
