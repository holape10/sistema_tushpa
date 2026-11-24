@extends('layouts.app')
@section('title', 'Lote '.$lote->codigo)
@section('content')
    @include('empresas.partials.alert')
    @php($postura = $lote->tipo === 'POSTURA')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('avicola.granja') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Granja</a>
        <div class="flex-1"><h1 class="text-xl font-bold text-gray-800">Lote {{ $lote->codigo }} <span class="text-sm font-normal text-gray-500">{{ $postura ? '🥚 Postura' : '🐔 Engorde' }}{{ $lote->galpon ? ' · '.$lote->galpon : '' }}{{ $lote->linea ? ' · '.$lote->linea : '' }}</span></h1>
            <p class="text-sm text-gray-500">Ingresó el {{ \Carbon\Carbon::parse($lote->fecha_ingreso)->format('d/m/Y') }} con {{ number_format($lote->aves_iniciales) }} aves a S/ {{ number_format((float) $lote->costo_ave, 2) }} · {{ $r['edad_dias'] }} días
                @if ($lote->estado === 'CERRADO') · <b>cerrado el {{ \Carbon\Carbon::parse($lote->fecha_cierre)->format('d/m/Y') }}</b>@endif</p></div>
        @if ($lote->estado === 'ABIERTO')
            <form method="POST" action="{{ route('avicola.lote.cerrar', $lote->id) }}" onsubmit="return confirm('¿Cerrar el lote? Ya no se podrá registrar nada más en él.')">@csrf
                <button class="h-9 px-3 rounded-xl bg-white border border-gray-200 text-sm font-semibold">Cerrar lote</button></form>
        @endif
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3 mb-4 text-center">
        @foreach (array_filter([
            ['Aves vivas', number_format($r['aves_vivas']), null], ['Muertas', number_format($r['muertas']).' ('.$r['mortalidad_pct'].'%)', $r['mortalidad_pct'] > 5 ? 'text-rose-600' : null],
            ['Alimento', number_format($r['alimento_kg'], 1).' kg', null], ['Costo total', 'S/ '.number_format($r['costo_total'], 2), null],
            ['Costo por ave', 'S/ '.number_format($r['costo_ave'], 2), null],
            $postura ? ['Huevos', number_format($r['huevos']), null] : ['Peso promedio', $r['peso_promedio'] !== null ? number_format($r['peso_promedio'], 3).' kg' : '—', null],
            $postura ? ['% postura', $r['postura_pct'] !== null ? $r['postura_pct'].'%' : '—', null] : ['Conversión', $r['conversion'] ?? '—', null],
            $postura ? null : ['Costo por kg', $r['costo_kg'] > 0 ? 'S/ '.number_format($r['costo_kg'], 2) : '—', null],
        ]) as [$t, $v, $c])
            <div class="rounded-2xl bg-white shadow-sm p-3"><p class="text-[11px] text-gray-500">{{ $t }}</p><p class="font-extrabold {{ $c ?? 'text-gray-800' }}">{{ $v }}</p></div>
        @endforeach
    </div>
    @unless ($postura)
        <p class="mb-4 text-xs text-gray-500">Conversión alimenticia = kilos de alimento ÷ kilos de pollo producidos (mientras más bajo, mejor; un engorde bueno está entre 1.6 y 1.9).
            Para que entre a la venta, regístralo en <a href="{{ route('avicola.ingresos') }}" class="text-indigo-600 underline">Ingreso de pollo › De mi granja</a>.</p>
    @endunless

    @if ($lote->estado === 'ABIERTO')
        <form method="POST" action="{{ route('avicola.lote.registro', $lote->id) }}" class="bg-white rounded-2xl shadow-sm p-5 mb-4 grid sm:grid-cols-4 lg:grid-cols-8 gap-3 items-end">
            @csrf
            <p class="sm:col-span-4 lg:col-span-8 font-bold text-gray-800">Registro del día</p>
            <label class="text-xs font-semibold text-gray-500">Fecha<input type="date" name="fecha" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
            <label class="text-xs font-semibold text-rose-700">Murieron<input type="number" min="0" name="mortalidad" class="mt-1 block w-full h-10 rounded-xl border-rose-200 text-center"></label>
            <label class="text-xs font-semibold text-gray-500">Alimento kg<input type="number" step="0.01" min="0" name="alimento_kg" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right"></label>
            <label class="text-xs font-semibold text-gray-500">Costo alimento S/<input type="number" step="0.01" min="0" name="costo_alimento" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right"></label>
            <label class="text-xs font-semibold text-gray-500">Otros gastos S/<input type="number" step="0.01" min="0" name="otros_gastos" placeholder="Vacuna, cama…" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right"></label>
            @if ($postura)
                <label class="text-xs font-semibold text-yellow-700">Huevos<input type="number" min="0" name="huevos" class="mt-1 block w-full h-10 rounded-xl border-yellow-200 text-center font-bold"></label>
            @else
                <label class="text-xs font-semibold text-gray-500">Peso prom. kg<input type="number" step="0.001" min="0" name="peso_promedio" placeholder="Muestra" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right"></label>
            @endif
            <label class="text-xs font-semibold text-gray-500">Nota<input name="observacion" maxlength="150" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
            <button class="h-10 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-bold">Guardar</button>
        </form>
    @endif

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto mb-4">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-right">Murieron</th><th class="px-3 py-2 text-right">Alimento kg</th>
                <th class="px-3 py-2 text-right">Costo alimento</th><th class="px-3 py-2 text-right">Otros</th><th class="px-3 py-2 text-right">{{ $postura ? 'Huevos' : 'Peso prom.' }}</th><th class="px-3 py-2 text-left">Nota</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($registros as $g)
                    <tr><td class="px-3 py-2">{{ \Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                        <td class="px-3 py-2 text-right {{ $g->mortalidad ? 'text-rose-600 font-bold' : 'text-gray-300' }}">{{ $g->mortalidad }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $g->alimento_kg, 2) }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $g->costo_alimento, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $g->otros_gastos, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ $postura ? number_format($g->huevos) : ($g->peso_promedio !== null ? number_format((float) $g->peso_promedio, 3).' kg' : '—') }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $g->observacion }}</td></tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Aún no hay registros diarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($salidas->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <p class="font-bold text-gray-800 mb-2">Salidas a la venta</p>
            @foreach ($salidas as $s)
                <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($s->fecha)->format('d/m/Y') }} · {{ $s->aves }} aves · {{ number_format((float) $s->peso_neto, 2) }} kg a S/ {{ number_format((float) $s->costo_kg, 2) }} el kilo</p>
            @endforeach
        </div>
    @endif
@endsection
