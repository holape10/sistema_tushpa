@extends('layouts.app')
@section('title', 'Balance de Comprobación')
@section('content')
    @include('empresas.contabilidad._nav')
    @php $f2 = fn($n) => $n != 0 ? number_format($n, 2) : ''; @endphp

    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3 print:hidden">
        <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Nivel de cuentas
            <select name="nivel" class="block rounded-lg border-gray-300 text-sm">
                @foreach ([2 => '2 dígitos (cuentas)', 3 => '3 dígitos (subcuentas)', 4 => '4 dígitos (divisionarias)', 6 => 'Detalle (6 dígitos)'] as $v => $n)
                    <option value="{{ $v }}" @selected($nivel == $v)>{{ $n }}</option>@endforeach
            </select></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Ver</button>
        <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
        <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold"><i class="fas fa-print"></i> Imprimir</button>
    </form>

    <div class="hidden print:block text-center mb-2"><h2 class="font-bold">BALANCE DE COMPROBACIÓN</h2>
        <p class="text-sm">Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p></div>

    <div class="grid sm:grid-cols-3 gap-3 mb-4 print:hidden">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Sumas Debe / Haber</p>
            <p class="text-lg font-black">{{ number_format($tot['debe'], 2) }} / {{ number_format($tot['haber'], 2) }}</p>
            <p class="text-xs font-bold {{ abs($tot['debe'] - $tot['haber']) < 0.01 ? 'text-emerald-600' : 'text-rose-600' }}">{{ abs($tot['debe'] - $tot['haber']) < 0.01 ? '✔ Cuadrado' : '✖ Descuadrado' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Resultado según inventario (Activo − Pasivo)</p>
            <p class="text-lg font-black {{ $resultadoBalance < 0 ? 'text-rose-600' : 'text-emerald-600' }}">S/ {{ number_format($resultadoBalance, 2) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-gray-500">Resultado por naturaleza (Ganancias − Pérdidas)</p>
            <p class="text-lg font-black {{ $resultadoNaturaleza < 0 ? 'text-rose-600' : 'text-emerald-600' }}">S/ {{ number_format($resultadoNaturaleza, 2) }}
                <span class="text-xs font-bold {{ abs($resultadoBalance - $resultadoNaturaleza) < 0.01 ? 'text-emerald-600' : 'text-amber-600' }}">{{ abs($resultadoBalance - $resultadoNaturaleza) < 0.01 ? '✔ coincide' : '≠ revisar' }}</span></p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="text-white uppercase">
                <tr class="bg-slate-800">
                    <th colspan="2" class="px-2 py-1"></th>
                    <th colspan="2" class="px-2 py-1 border-l border-slate-600">Sumas</th>
                    <th colspan="2" class="px-2 py-1 border-l border-slate-600">Saldos</th>
                    <th colspan="2" class="px-2 py-1 border-l border-slate-600">Inventario</th>
                    <th colspan="2" class="px-2 py-1 border-l border-slate-600">Resultados por naturaleza</th>
                </tr>
                <tr class="bg-slate-700">
                    <th class="px-2 py-2 text-left">Cuenta</th><th class="px-2 py-2 text-left">Denominación</th>
                    <th class="px-2 py-2 text-right border-l border-slate-600">Debe</th><th class="px-2 py-2 text-right">Haber</th>
                    <th class="px-2 py-2 text-right border-l border-slate-600">Deudor</th><th class="px-2 py-2 text-right">Acreedor</th>
                    <th class="px-2 py-2 text-right border-l border-slate-600">Activo</th><th class="px-2 py-2 text-right">Pasivo y Patr.</th>
                    <th class="px-2 py-2 text-right border-l border-slate-600">Pérdidas</th><th class="px-2 py-2 text-right">Ganancias</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($filas as $f)
                    <tr class="hover:bg-gray-50">
                        <td class="px-2 py-1.5 font-mono font-semibold"><a href="{{ route('contabilidad.mayor', ['cuenta' => $f['cuenta'], 'desde' => $desde, 'hasta' => $hasta]) }}" class="text-indigo-700 hover:underline">{{ $f['cuenta'] }}</a></td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $f['nombre'] }}</td>
                        <td class="px-2 py-1.5 text-right border-l">{{ $f2($f['debe']) }}</td><td class="px-2 py-1.5 text-right">{{ $f2($f['haber']) }}</td>
                        <td class="px-2 py-1.5 text-right border-l">{{ $f2($f['deudor']) }}</td><td class="px-2 py-1.5 text-right">{{ $f2($f['acreedor']) }}</td>
                        <td class="px-2 py-1.5 text-right border-l">{{ $f2($f['activo']) }}</td><td class="px-2 py-1.5 text-right">{{ $f2($f['pasivo']) }}</td>
                        <td class="px-2 py-1.5 text-right border-l">{{ $f2($f['perdida']) }}</td><td class="px-2 py-1.5 text-right">{{ $f2($f['ganancia']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-gray-400 text-sm">Sin movimientos en el rango.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="font-bold">
                <tr class="bg-slate-100">
                    <td colspan="2" class="px-2 py-2 text-right">TOTALES</td>
                    @foreach (['debe', 'haber', 'deudor', 'acreedor', 'activo', 'pasivo', 'perdida', 'ganancia'] as $k)<td class="px-2 py-2 text-right {{ in_array($k, ['debe', 'deudor', 'activo', 'perdida']) ? 'border-l' : '' }}">{{ number_format($tot[$k], 2) }}</td>@endforeach
                </tr>
                <tr class="bg-emerald-50 text-emerald-800">
                    <td colspan="2" class="px-2 py-2 text-right">{{ $resultadoBalance >= 0 ? 'UTILIDAD' : 'PÉRDIDA' }} DEL EJERCICIO</td>
                    <td colspan="4"></td>
                    <td class="px-2 py-2 text-right border-l">{{ $resultadoBalance < 0 ? number_format(-$resultadoBalance, 2) : '' }}</td>
                    <td class="px-2 py-2 text-right">{{ $resultadoBalance > 0 ? number_format($resultadoBalance, 2) : '' }}</td>
                    <td class="px-2 py-2 text-right border-l">{{ $resultadoNaturaleza > 0 ? number_format($resultadoNaturaleza, 2) : '' }}</td>
                    <td class="px-2 py-2 text-right">{{ $resultadoNaturaleza < 0 ? number_format(-$resultadoNaturaleza, 2) : '' }}</td>
                </tr>
                <tr class="bg-slate-800 text-white">
                    <td colspan="2" class="px-2 py-2 text-right">SUMAS IGUALES</td><td colspan="4"></td>
                    <td class="px-2 py-2 text-right">{{ number_format(max($tot['activo'], $tot['pasivo']), 2) }}</td><td class="px-2 py-2 text-right">{{ number_format(max($tot['activo'], $tot['pasivo']), 2) }}</td>
                    <td class="px-2 py-2 text-right">{{ number_format(max($tot['perdida'], $tot['ganancia']), 2) }}</td><td class="px-2 py-2 text-right">{{ number_format(max($tot['perdida'], $tot['ganancia']), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-2">Hoja de trabajo: los elementos 1 a 5 pasan al inventario (balance) y los elementos 6, 7 (menos la 79) y 8 a resultados por naturaleza. El elemento 9 y la 79 (contabilidad analítica) se compensan entre sí y no se trasladan.</p>
    <style>@media print { @page { size: A4 landscape; margin: 8mm; } aside, header, nav { display: none !important; } }</style>
@endsection
