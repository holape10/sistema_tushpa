@extends('layouts.app')
@section('title', 'Libro Mayor')
@section('content')
    @include('empresas.contabilidad._nav')

    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
        <datalist id="ctas">@foreach ($nombres as $c => $n)<option value="{{ $c }}">{{ $n }}</option>@endforeach</datalist>
        <label class="text-sm">Cuenta (o inicio de cuenta)
            <input name="cuenta" list="ctas" value="{{ $cuenta }}" placeholder="Ej. 12 o 121201" class="block w-48 rounded-lg border-gray-300 font-mono"></label>
        <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Ver</button>
        @if ($cuenta !== '')
            <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="{{ route('contabilidad.mayor', ['desde' => $desde, 'hasta' => $hasta]) }}" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Todas las cuentas</a>
        @endif
    </form>

    @if ($cuenta === '')
        {{-- Resumen de cuentas con movimiento --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr><th class="px-4 py-2 text-left">Cuenta</th><th class="px-4 py-2 text-left">Denominación</th><th class="px-4 py-2 text-right">Debe</th><th class="px-4 py-2 text-right">Haber</th><th class="px-4 py-2 text-right">Saldo</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($resumen as $c => $s)
                        @php $saldo = $s->debe - $s->haber; @endphp
                        <tr class="hover:bg-indigo-50 cursor-pointer" onclick="location.href='{{ route('contabilidad.mayor', ['cuenta' => $c, 'desde' => $desde, 'hasta' => $hasta]) }}'">
                            <td class="px-4 py-2 font-mono font-semibold text-indigo-700">{{ $c }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $nombres[$c] ?? '' }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($s->debe, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($s->haber, 2) }}</td>
                            <td class="px-4 py-2 text-right font-bold {{ $saldo < 0 ? 'text-rose-600' : '' }}">{{ number_format(abs($saldo), 2) }} {{ $saldo > 0 ? 'D' : ($saldo < 0 ? 'A' : '') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Sin movimientos en el rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">Toca una cuenta para ver su detalle. D = saldo deudor, A = saldo acreedor.</p>
    @else
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-3 flex flex-wrap items-center gap-4">
            <div><p class="text-xs text-gray-400 uppercase font-bold">Cuenta</p><p class="text-xl font-black font-mono">{{ $cuenta }}</p></div>
            <div class="flex-1"><p class="text-gray-700 font-semibold">{{ $nombres[$cuenta] ?? 'Todas las cuentas que empiezan con ' . $cuenta }}</p></div>
            <div class="text-right"><p class="text-xs text-gray-400">Saldo final</p>
                @php $final = $movs->last()->saldo ?? $saldoInicial; @endphp
                <p class="text-2xl font-black {{ $final < 0 ? 'text-rose-600' : 'text-gray-800' }}">{{ number_format(abs($final), 2) }} <span class="text-sm">{{ $final > 0 ? 'D' : ($final < 0 ? 'A' : '') }}</span></p></div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Asiento</th><th class="px-3 py-2 text-left">Glosa</th><th class="px-3 py-2 text-left">Cuenta</th>
                        <th class="px-3 py-2 text-right">Debe</th><th class="px-3 py-2 text-right">Haber</th><th class="px-3 py-2 text-right">Saldo</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="bg-slate-50 font-semibold"><td colspan="6" class="px-3 py-2">SALDO ANTERIOR AL {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($saldoInicial, 2) }}</td></tr>
                    @foreach ($movs as $m)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 whitespace-nowrap">{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                            <td class="px-3 py-1.5 font-mono whitespace-nowrap"><a href="{{ route('contabilidad.diario', ['periodo' => $m->periodo, 'q' => $m->documento ?: '']) }}" class="text-indigo-600 hover:underline">{{ $m->subdiario }}-{{ str_pad($m->numero, 4, '0', STR_PAD_LEFT) }}</a></td>
                            <td class="px-3 py-1.5 text-gray-700">{{ $m->glosa ?: $m->glosa_asiento }}@if ($m->anexo_nombre)<span class="block text-xs text-gray-400">{{ $m->anexo_doc }} {{ $m->anexo_nombre }} {{ $m->documento ? '· ' . $m->documento : '' }}</span>@endif</td>
                            <td class="px-3 py-1.5 font-mono">{{ $m->cuenta }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $m->debe > 0 ? number_format($m->debe, 2) : '' }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $m->haber > 0 ? number_format($m->haber, 2) : '' }}</td>
                            <td class="px-3 py-1.5 text-right font-semibold {{ $m->saldo < 0 ? 'text-rose-600' : '' }}">{{ number_format($m->saldo, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 font-bold">
                    <tr><td colspan="4" class="px-3 py-2 text-right">Movimientos del rango</td><td class="px-3 py-2 text-right">{{ number_format($movs->sum('debe'), 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($movs->sum('haber'), 2) }}</td><td></td></tr>
                </tfoot>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">Saldo = Debe − Haber acumulado (negativo = saldo acreedor).</p>
    @endif
@endsection
