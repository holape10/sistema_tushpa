@extends('layouts.app')
@section('title', 'Metas de mozos')
@section('content')
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $num = fn ($n) => $n !== null && (float) $n > 0 ? rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.') : '';
        $color = fn (?float $pct) => match (true) { $pct === null => 'bg-gray-300', $pct >= 100 => 'bg-emerald-500', $pct >= 70 => 'bg-amber-400', default => 'bg-rose-500' };
        $general = $metas[0] ?? null;
        $titulos = ['dia' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes'];
    @endphp

    <div class="max-w-6xl space-y-5">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-sky-700 via-indigo-600 to-violet-600 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <p class="text-sky-100 text-sm font-semibold tracking-wide uppercase">🎯 Metas de mozos</p>
                <h1 class="text-2xl sm:text-3xl font-black mt-1">¿Cuánto debe vender cada mozo?</h1>
                <p class="text-sky-50 text-sm mt-1 max-w-2xl">Pon una meta para todos y, si quieres, una distinta para alguno. Cuenta lo cobrado de las mesas que atendió cada mozo
                    (sin anuladas). Cada mozo ve su avance arriba de sus mesas. La semana va de lunes a domingo.</p>
            </div>
        </section>

        {{-- Cómo va cada mozo --}}
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">🏆 Cómo van</h2></div>
            @if ($tablero->isEmpty())
                <p class="p-5 text-sm text-gray-500">No hay mozos en esta sucursal. Crea sus usuarios con el rol <b>Mozo</b> en Usuarios.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr><th class="px-4 py-2 text-left">#</th><th class="px-4 py-2 text-left">Mozo</th>
                            @foreach ($titulos as $t)<th class="px-4 py-2 text-left min-w-44">{{ $t }}</th>@endforeach
                            <th class="px-4 py-2 text-right">Ticket promedio<span class="block normal-case font-normal">(mes)</span></th></tr>
                        </thead>
                        <tbody class="divide-y">
                        @foreach ($tablero as $i => $f)
                            <tr>
                                <td class="px-4 py-3 font-black text-gray-400">{{ $i === 0 && $f->mes['vendido'] > 0 ? '🥇' : ($i === 1 && $f->mes['vendido'] > 0 ? '🥈' : ($i === 2 && $f->mes['vendido'] > 0 ? '🥉' : $i + 1)) }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-800">{{ $f->nombre }}</td>
                                @foreach (array_keys($titulos) as $p)
                                    @php $a = $f->$p; @endphp
                                    <td class="px-4 py-3">
                                        <div class="font-bold">{{ $s($a['vendido']) }}</div>
                                        @if ($a['meta'])
                                            <div class="h-2 rounded-full bg-gray-100 mt-1 overflow-hidden"><div class="h-2 rounded-full {{ $color($a['pct']) }}" style="width: {{ min(100, $a['pct']) }}%"></div></div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $a['pct'] }}% de {{ $s($a['meta']) }}{{ $a['falta'] > 0 ? ' · faltan '.$s($a['falta']) : ' · ¡cumplió!' }}</div>
                                        @else
                                            <div class="text-xs text-gray-400">Sin meta</div>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-right">{{ $s($f->ticket) }}<span class="block text-xs text-gray-400">{{ $f->ventasMes }} {{ $f->ventasMes == 1 ? 'venta' : 'ventas' }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Metas --}}
        <form method="POST" action="{{ route('metas_mozo.guardar') }}" class="bg-white rounded-2xl shadow-sm overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">✏️ Metas</h2>
                <p class="text-xs text-gray-500">Deja vacío lo que no quieras medir. En cada mozo, vacío = usa la meta de todos.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr><th class="px-4 py-2 text-left"></th><th class="px-4 py-2 text-left">Diaria S/</th><th class="px-4 py-2 text-left">Semanal S/</th><th class="px-4 py-2 text-left">Mensual S/</th></tr>
                    </thead>
                    <tbody class="divide-y">
                    <tr class="bg-indigo-50/50">
                        <td class="px-4 py-2 font-bold text-indigo-700">Todos los mozos</td>
                        @foreach (['diaria', 'semanal', 'mensual'] as $c)
                            <td class="px-4 py-2"><input type="number" name="metas[0][{{ $c }}]" step="0.01" min="0" value="{{ old('metas.0.'.$c, $num($general?->$c)) }}"
                                                         class="w-32 rounded-lg border-gray-300 text-sm" placeholder="0.00"></td>
                        @endforeach
                    </tr>
                    @foreach ($tablero as $f)
                        @php $m = $metas[$f->id] ?? null; @endphp
                        <tr>
                            <td class="px-4 py-2 text-gray-700">{{ $f->nombre }}</td>
                            @foreach (['diaria', 'semanal', 'mensual'] as $c)
                                <td class="px-4 py-2"><input type="number" name="metas[{{ $f->id }}][{{ $c }}]" step="0.01" min="0" value="{{ old('metas.'.$f->id.'.'.$c, $num($m?->$c)) }}"
                                                             class="w-32 rounded-lg border-gray-300 text-sm" placeholder="{{ $num($general?->$c) ?: '—' }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($errors->any())<div class="mx-5 mt-3 rounded-lg bg-red-50 text-red-700 text-sm p-3">{{ $errors->first() }}</div>@endif
            <div class="p-5"><button class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Guardar metas</button></div>
        </form>
    </div>
@endsection
