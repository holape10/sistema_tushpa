@extends('layouts.app')
@section('title', 'Estados Financieros')
@section('content')
    @include('empresas.contabilidad._nav')
    @php
        $m = fn($n) => ($n < 0 ? '(' : '') . number_format(abs($n), 2) . ($n < 0 ? ')' : '');
        $linea = fn($t, $v, $clase = '') => "<div class='flex justify-between py-1.5 {$clase}'><span>{$t}</span><span class='tabular-nums'>" . $m($v) . '</span></div>';
        $cuadre = round($esf['totActivo'] - $esf['totPasivo'] - $esf['totPatrimonio'], 2);
    @endphp

    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3 print:hidden">
        <label class="text-sm">Resultados desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta (corte del balance)<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Ver</button>
        <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
        <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold"><i class="fas fa-print"></i> Imprimir</button>
    </form>

    <div class="grid lg:grid-cols-2 gap-5 items-start">
        {{-- Estado de resultados --}}
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <header class="px-5 py-4 bg-gradient-to-r from-emerald-600 to-teal-600 text-white">
                <h2 class="font-black text-lg">Estado de Resultados</h2>
                <p class="text-sm text-emerald-100">Por naturaleza · del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
            </header>
            <div class="p-5 text-sm text-gray-700 divide-y divide-gray-100">
                {!! $linea('Ventas netas', $er['ventas'] + $er['descuentos']) !!}
                {!! $linea('(-) Costo de ventas', -$er['costo']) !!}
                {!! $linea('UTILIDAD BRUTA', $er['bruta'], 'font-bold text-gray-900 bg-gray-50 px-2 -mx-2') !!}
                @foreach ($er['gastos'] as $t => $v)
                    @if ($v != 0){!! $linea('(-) ' . $t, -$v) !!}@endif
                @endforeach
                @if ($er['otrosIngresos'] != 0){!! $linea('Otros ingresos (gastos) de gestión', $er['otrosIngresos']) !!}@endif
                {!! $linea('RESULTADO DE OPERACIÓN', $er['operativo'], 'font-bold text-gray-900 bg-gray-50 px-2 -mx-2') !!}
                @if ($er['finIngresos'] != 0){!! $linea('Ingresos financieros', $er['finIngresos']) !!}@endif
                @if ($er['finGastos'] != 0){!! $linea('(-) Gastos financieros', -$er['finGastos']) !!}@endif
                {!! $linea('RESULTADO ANTES DE IMPUESTOS', $er['antesImp'], 'font-bold text-gray-900') !!}
                @if ($er['renta'] != 0){!! $linea('(-) Impuesto a la renta', -$er['renta']) !!}@endif
                <div class="flex justify-between py-3 mt-1 text-lg font-black {{ $er['resultado'] < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                    <span>{{ $er['resultado'] < 0 ? 'PÉRDIDA' : 'UTILIDAD' }} DEL PERIODO</span><span>S/ {{ $m($er['resultado']) }}</span></div>
                @if ($er['ventas'] > 0)
                    <div class="grid grid-cols-3 gap-2 pt-3 text-center text-xs">
                        <div class="rounded-xl bg-gray-50 p-2"><p class="text-gray-400">Margen bruto</p><p class="font-bold text-base">{{ number_format($er['bruta'] / $er['ventas'] * 100, 1) }}%</p></div>
                        <div class="rounded-xl bg-gray-50 p-2"><p class="text-gray-400">Margen operativo</p><p class="font-bold text-base">{{ number_format($er['operativo'] / $er['ventas'] * 100, 1) }}%</p></div>
                        <div class="rounded-xl bg-gray-50 p-2"><p class="text-gray-400">Margen neto</p><p class="font-bold text-base">{{ number_format($er['resultado'] / $er['ventas'] * 100, 1) }}%</p></div>
                    </div>
                @endif
            </div>
        </section>

        {{-- Estado de situación financiera --}}
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <header class="px-5 py-4 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                <h2 class="font-black text-lg">Estado de Situación Financiera</h2>
                <p class="text-sm text-indigo-100">Al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
            </header>
            <div class="p-5 text-sm text-gray-700">
                @foreach (['ACTIVO CORRIENTE' => $esf['activoCorriente'], 'ACTIVO NO CORRIENTE' => $esf['activoNoCorriente']] as $t => $g)
                    <p class="font-bold text-gray-900 mt-2">{{ $t }}</p>
                    @foreach ($g as $k => $v)@if ($v != 0){!! $linea('<span class="pl-3">' . e($k) . '</span>', $v, 'border-b border-gray-50') !!}@endif @endforeach
                    {!! $linea('Total ' . mb_strtolower($t), array_sum($g), 'font-semibold') !!}
                @endforeach
                {!! $linea('TOTAL ACTIVO', $esf['totActivo'], 'font-black text-indigo-700 text-base bg-indigo-50 px-2 -mx-2 mt-2') !!}

                @foreach (['PASIVO CORRIENTE' => $esf['pasivoCorriente'], 'PASIVO NO CORRIENTE' => $esf['pasivoNoCorriente'], 'PATRIMONIO' => $esf['patrimonio']] as $t => $g)
                    <p class="font-bold text-gray-900 mt-3">{{ $t }}</p>
                    @foreach ($g as $k => $v)@if ($v != 0){!! $linea('<span class="pl-3">' . e($k) . '</span>', $v, 'border-b border-gray-50') !!}@endif @endforeach
                    {!! $linea('Total ' . mb_strtolower($t), array_sum($g), 'font-semibold') !!}
                @endforeach
                {!! $linea('TOTAL PASIVO Y PATRIMONIO', $esf['totPasivo'] + $esf['totPatrimonio'], 'font-black text-indigo-700 text-base bg-indigo-50 px-2 -mx-2 mt-2') !!}
                <p class="mt-3 text-xs font-bold {{ abs($cuadre) < 0.01 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ abs($cuadre) < 0.01 ? '✔ Activo = Pasivo + Patrimonio' : '✖ Diferencia de S/ ' . number_format($cuadre, 2) . ': revisa asientos de apertura o cuentas fuera del PCGE' }}</p>
            </div>
        </section>
    </div>
    <p class="text-xs text-gray-400 mt-3">Montos en soles. Los estados se arman con los asientos del Libro diario: centraliza ventas y compras de cada mes y registra el asiento de apertura (capital, saldos iniciales) y los gastos (alquiler, planilla, servicios) como asientos manuales.</p>
    <style>@media print { aside, header.app, nav { display: none !important; } }</style>
@endsection
