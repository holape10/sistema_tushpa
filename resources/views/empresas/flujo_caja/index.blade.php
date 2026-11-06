@extends('layouts.app')
@section('title', 'Flujo de caja proyectado')
@section('content')
@php
    $soles = fn ($n) => ($n < 0 ? '- ' : '').'S/ '.number_format(abs((float) $n), 2);
    $dia = fn ($f) => \Carbon\Carbon::parse($f)->locale('es')->isoFormat('D MMM');
    $semanas = $flujo['semanas'];
    $mayor = max(1, collect($semanas)->max(fn ($s) => max($s['entra'], $s['sale'])));
    $tipos = \App\Http\Controllers\FlujoCajaController::TIPOS;
    $url = fn (array $cambios) => route('flujo_caja.index', array_merge(['semanas' => $nSemanas, 'estimados' => $estimados ? 1 : 0], $cambios));
    $saldo = $flujo['saldo_inicial'];
@endphp

<div class="max-w-6xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @error('saldo')<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $message }}</div>@enderror

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">📈 Flujo de caja proyectado</h1>
            <p class="text-sm text-gray-500">Lo que vas a cobrar y pagar semana a semana, y con cuánto dinero te quedas.</p>
        </div>
        <a href="{{ route('flujo_caja.exportar', ['semanas' => $nSemanas, 'estimados' => $estimados ? 1 : 0]) }}"
           class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Exportar Excel</a>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        {{-- Saldo de hoy: el punto de partida --}}
        <form method="POST" action="{{ route('flujo_caja.saldo') }}" class="bg-white rounded-2xl shadow-sm p-4 md:col-span-1">
            @csrf
            <p class="text-xs font-bold uppercase text-gray-500">¿Con cuánto dinero cuentas hoy?</p>
            <p class="text-xs text-gray-400 mb-2">Caja + bancos + billeteras. Actualízalo cuando quieras.</p>
            <div class="flex gap-2">
                <input type="number" step="0.01" name="saldo" value="{{ old('saldo', $saldo['saldo'] ?? '') }}" placeholder="0.00" required
                       class="flex-1 min-w-0 h-10 rounded-xl border-gray-300 text-sm font-semibold">
                <button class="h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Guardar</button>
            </div>
            @if ($saldo)
                <p class="mt-1 text-xs text-gray-400">Puesto el {{ \Carbon\Carbon::parse($saldo['fecha'])->format('d/m/Y') }}</p>
            @endif
        </form>

        {{-- Lo atrasado: no se sabe cuándo entra o sale --}}
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-bold uppercase text-gray-500">Vencido sin cobrar</p>
            <p class="text-2xl font-extrabold {{ $flujo['atrasado']['cobrar'] > 0 ? 'text-amber-600' : 'text-gray-300' }}">{{ $soles($flujo['atrasado']['cobrar']) }}</p>
            <p class="text-xs text-gray-400">No se cuenta en las semanas: cóbralo y entrará como dinero de hoy.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-bold uppercase text-gray-500">Vencido sin pagar</p>
            <p class="text-2xl font-extrabold {{ $flujo['atrasado']['pagar'] > 0 ? 'text-rose-600' : 'text-gray-300' }}">{{ $soles($flujo['atrasado']['pagar']) }}</p>
            <p class="text-xs text-gray-400">Pagos a proveedores y órdenes aprobadas cuya fecha ya pasó.</p>
        </div>
    </div>

    @if (! $saldo)
        <div class="rounded-xl bg-indigo-50 text-indigo-800 px-4 py-3 text-sm">Escribe con cuánto dinero cuentas hoy para ver tu saldo semana a semana. Mientras tanto ves solo lo que entra y sale.</div>
    @elseif ($flujo['negativa'] !== null)
        @php $s = $semanas[$flujo['negativa']]; @endphp
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm font-semibold">
            ⚠ La semana del {{ $dia($s['desde']) }} al {{ $dia($s['hasta']) }} te quedarías sin dinero ({{ $soles($s['saldo']) }}). Cobra lo vencido o conversa con tus proveedores antes.
        </div>
    @endif

    {{-- Opciones --}}
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-gray-500">Ver:</span>
        @foreach ([4, 8, 12, 26] as $n)
            <a href="{{ $url(['semanas' => $n]) }}" class="px-3 py-1.5 rounded-full font-semibold ring-1 {{ $nSemanas === $n ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200' }}">{{ $n }} semanas</a>
        @endforeach
        <a href="{{ $url(['estimados' => $estimados ? 0 : 1]) }}" class="ml-auto px-3 py-1.5 rounded-full font-semibold ring-1 {{ $estimados ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-white text-gray-500 ring-gray-200' }}">
            {{ $estimados ? '✔' : '○' }} Incluir estimados del día a día</a>
    </div>
    @if ($estimados)
        <p class="text-xs text-gray-500">Estimados = promedio semanal de las últimas 4 semanas: ventas al contado {{ $soles($flujo['promedios']['ventas'] ?? 0) }},
            compras al contado {{ $soles($flujo['promedios']['compras'] ?? 0) }} y gastos {{ $soles($flujo['promedios']['gastos'] ?? 0) }}.</p>
    @endif

    {{-- Semana a semana --}}
    <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
        @foreach ($semanas as $i => $s)
            <details class="group">
                <summary class="list-none cursor-pointer px-4 py-3 hover:bg-gray-50 grid grid-cols-2 sm:grid-cols-12 gap-x-4 gap-y-2 items-center">
                    <div class="col-span-2 sm:col-span-3">
                        <p class="font-bold text-gray-800">{{ $i === 0 ? 'Esta semana' : 'Semana '.($i + 1) }}</p>
                        <p class="text-xs text-gray-500">{{ $dia($s['desde']) }} al {{ $dia($s['hasta']) }} · {{ count($s['detalle']) }} documento(s)</p>
                    </div>
                    <div class="col-span-2 sm:col-span-3 space-y-1" aria-hidden="true">
                        <div class="h-2.5 rounded-full bg-emerald-500" style="width: {{ max(1, round($s['entra'] / $mayor * 100)) }}%"></div>
                        <div class="h-2.5 rounded-full bg-rose-400" style="width: {{ max(1, round($s['sale'] / $mayor * 100)) }}%"></div>
                    </div>
                    <div class="text-right sm:col-span-2"><p class="text-[11px] text-gray-400">Entra</p><p class="font-semibold text-emerald-700">{{ $soles($s['entra']) }}</p></div>
                    <div class="text-right sm:col-span-2"><p class="text-[11px] text-gray-400">Sale</p><p class="font-semibold text-rose-600">{{ $soles($s['sale']) }}</p></div>
                    <div class="text-right col-span-2 sm:col-span-2">
                        <p class="text-[11px] text-gray-400">{{ $saldo ? 'Te quedan' : 'Neto' }}</p>
                        <p class="font-extrabold {{ ($saldo ? $s['saldo'] : $s['neto']) < 0 ? 'text-rose-600' : 'text-gray-900' }}">{{ $soles($saldo ? $s['saldo'] : $s['neto']) }}</p>
                    </div>
                </summary>
                <div class="px-4 pb-4 grid gap-4 md:grid-cols-2 text-sm">
                    <div>
                        @foreach (['Entra' => [$s['entradas'], 'text-emerald-700'], 'Sale' => [$s['salidas'], 'text-rose-600']] as $titulo => [$conceptos, $color])
                            <p class="text-xs font-bold uppercase {{ $color }} {{ $loop->first ? '' : 'mt-3' }} mb-1">{{ $titulo }}</p>
                            @forelse (array_filter($conceptos, fn ($m) => $m > 0) as $concepto => $monto)
                                <div class="flex justify-between gap-3"><span class="text-gray-600">{{ $concepto }}</span><span class="font-semibold">{{ $soles($monto) }}</span></div>
                            @empty
                                <p class="text-gray-400">Nada</p>
                            @endforelse
                        @endforeach
                    </div>
                    <div class="overflow-x-auto">
                        @if ($s['detalle'])
                            <table class="w-full text-left text-xs">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($s['detalle'] as $d)
                                        <tr>
                                            <td class="py-1 pr-2 whitespace-nowrap text-gray-500">{{ $dia($d['fecha']) }}</td>
                                            <td class="py-1 pr-2"><span class="font-semibold text-gray-700">{{ $d['nombre'] ?: '—' }}</span>
                                                <span class="block text-gray-400">{{ $tipos[$d['tipo']] }} · {{ $d['documento'] }}{{ $d['dolares'] ? ' · US$ '.number_format($d['dolares'], 2) : '' }}</span></td>
                                            <td class="py-1 text-right font-semibold whitespace-nowrap {{ in_array($d['tipo'], \App\Http\Controllers\FlujoCajaController::SALEN, true) ? 'text-rose-600' : 'text-emerald-700' }}">{{ in_array($d['tipo'], \App\Http\Controllers\FlujoCajaController::SALEN, true) ? '−' : '+' }} {{ $soles($d['monto']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-gray-400">Sin cobros ni pagos con fecha esta semana.</p>
                        @endif
                    </div>
                </div>
            </details>
        @endforeach
    </div>

    @if ($flujo['atrasado']['detalle'])
        <details class="bg-white rounded-2xl shadow-sm">
            <summary class="cursor-pointer list-none px-4 py-3 font-bold text-gray-700">Ver lo vencido ({{ count($flujo['atrasado']['detalle']) }}) ▾</summary>
            <div class="px-4 pb-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($flujo['atrasado']['detalle'] as $d)
                            <tr>
                                <td class="py-1.5 pr-3 whitespace-nowrap text-rose-600">Venció {{ \Carbon\Carbon::parse($d['fecha'])->format('d/m/Y') }}</td>
                                <td class="py-1.5 pr-3"><span class="font-semibold">{{ $d['nombre'] ?: '—' }}</span> <span class="text-gray-400">{{ $tipos[$d['tipo']] }} · {{ $d['documento'] }}</span></td>
                                <td class="py-1.5 text-right font-semibold whitespace-nowrap">{{ $soles($d['monto']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
    @if ($flujo['tipo_cambio'])
        <p class="text-xs text-gray-400">Lo que está en dólares se convierte a soles (por cobrar: último tipo de cambio S/ {{ number_format($flujo['tipo_cambio'], 3) }}; por pagar: el de cada compra).</p>
    @endif
</div>
@endsection
