@extends('layouts.app')
@section('title', 'Arqueo de caja')
@section('content')
<div class="max-w-5xl mx-auto space-y-4">
    @include('empresas.partials.alert')

    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h1 class="text-xl font-extrabold text-gray-800">🧮 Arqueo de caja</h1>
        <p class="text-sm text-gray-500">Cuenta el efectivo de una caja <b>en cualquier momento, sin cerrar el turno</b> (arqueo sorpresa, cambio de cajero o a media jornada).
            Se compara con lo que debería haber en ese momento: fondo inicial + ventas en efectivo + ingresos − egresos. El arqueo del cierre se hace al cerrar el turno en Caja.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm">
        <h2 class="px-5 pt-4 pb-2 font-bold text-gray-700">Cajas abiertas</h2>
        <div class="divide-y divide-gray-100">
            @forelse ($abiertos as $t)
                <div class="px-5 py-3 flex flex-wrap items-center gap-3">
                    <p class="flex-1 min-w-48 text-sm text-gray-700"><b>Turno N° {{ $t->turno }}</b> · {{ $t->usuario->apeusu ?? '' }}
                        <span class="text-gray-400">· abierto el {{ $t->apertura->format('d/m H:i') }}</span></p>
                    <a href="{{ route('arqueos.create', $t->id_turno) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Hacer arqueo</a>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-gray-400">No hay cajas abiertas{{ $admin ? ' en esta sucursal' : ': abre tu turno en Caja' }}.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm">
        <form class="px-5 pt-4 pb-2 flex flex-wrap items-end gap-3">
            <h2 class="flex-1 font-bold text-gray-700">Arqueos hechos</h2>
            <label class="text-xs text-gray-500">Desde <input type="date" name="desde" value="{{ $desde }}" class="block h-9 rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs text-gray-500">Hasta <input type="date" name="hasta" value="{{ $hasta }}" class="block h-9 rounded-lg border-gray-300 text-sm"></label>
            <button class="h-9 px-4 rounded-lg bg-gray-800 text-white text-sm font-semibold">Ver</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Caja (turno)</th><th class="px-4 py-2 text-left">Contó</th>
                        <th class="px-4 py-2 text-right">Debía haber</th><th class="px-4 py-2 text-right">Contado</th><th class="px-4 py-2 text-right">Diferencia</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($arqueos as $a)
                        @php $oculto = ! $admin && $a->estado_turno === 'ABIERTO' && \App\Models\Turno::find($a->id_turno)?->esCiegoPara(auth()->user()); @endphp
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">N° {{ $a->turno }} · {{ $a->nombre_cajero }}</td>
                            <td class="px-4 py-2">{{ $a->conto }}</td>
                            <td class="px-4 py-2 text-right">{{ $oculto ? '—' : 'S/ '.number_format($a->esperado, 2) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">S/ {{ number_format($a->contado, 2) }}</td>
                            <td class="px-4 py-2 text-right font-bold {{ $oculto ? 'text-gray-400' : ((float) $a->diferencia < 0 ? 'text-rose-600' : ((float) $a->diferencia > 0 ? 'text-amber-600' : 'text-emerald-600')) }}">
                                {{ $oculto ? '—' : ((float) $a->diferencia == 0 ? 'Cuadra' : ((float) $a->diferencia < 0 ? 'Falta ' : 'Sobra ').'S/ '.number_format(abs($a->diferencia), 2)) }}</td>
                            <td class="px-4 py-2 text-right"><a href="{{ route('arqueos.show', $a->id) }}" class="text-indigo-600 font-semibold hover:underline">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aún no hay arqueos en estas fechas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2">{{ $arqueos->links() }}</div>
    </div>
</div>
@endsection
