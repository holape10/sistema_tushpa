@extends('layouts.app')
@section('title', 'Comprobantes de contingencia')
@section('content')
<div class="max-w-5xl mx-auto space-y-4">
    @include('empresas.partials.alert')
    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-2">
        <h1 class="text-xl font-extrabold text-gray-800">📝 Comprobantes de contingencia</h1>
        <p class="text-sm text-gray-600">Facturas y boletas que se hicieron <b>en papel</b> (sin luz o sin internet) y luego se pasaron al sistema.
            Mueven el stock, la caja y los reportes, pero <b>no se envían como electrónicos</b>: ya existen en papel.</p>
        <ul class="text-sm text-gray-600 list-disc pl-5 space-y-0.5">
            <li><b>Cómo se pasan:</b> en el Punto de Venta marca <b>"📝 Comprobante en papel (contingencia)"</b>, escribe la serie y el número impresos (ej. 0001-125)
                y pon la fecha del papel en <b>F. Emisión</b>. Al registrar, el siguiente número ya queda puesto.</li>
            <li><b>A SUNAT:</b> se declaran en SUNAT Operaciones en Línea, en la opción de comprobantes emitidos en contingencia, dentro del plazo. Descarga aquí el Excel del mes para tu contador.</li>
            <li>Un comprobante en papel equivocado se anula desde el Panel de ventas (no necesita comunicación de baja).</li>
        </ul>
    </div>

    <div class="bg-white rounded-2xl shadow-sm">
        <form class="px-5 pt-4 pb-2 flex flex-wrap items-end gap-3">
            <label class="text-xs text-gray-500">Mes <input type="month" name="mes" value="{{ $mes }}" class="block h-9 rounded-lg border-gray-300 text-sm"></label>
            <button class="h-9 px-4 rounded-lg bg-gray-800 text-white text-sm font-semibold">Ver</button>
            <a href="{{ route('contingencia.excel', ['mes' => $mes]) }}" class="ml-auto h-9 px-4 inline-flex items-center rounded-lg bg-emerald-600 text-white text-sm font-semibold">⬇ Excel del mes</a>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Comprobante</th><th class="px-4 py-2 text-left">Cliente</th>
                        <th class="px-4 py-2 text-right">IGV</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($lista as $c)
                        <tr class="{{ $c->ccabaj ? 'opacity-50 line-through' : '' }}">
                            <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($c->ccafem)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2"><b>{{ $c->serdoc }}-{{ str_pad($c->numdoc, 8, '0', STR_PAD_LEFT) }}</b> <span class="text-xs text-gray-400">{{ $tipos[$c->tdocod] ?? '' }}</span></td>
                            <td class="px-4 py-2">{{ $c->ccanom }} <span class="text-xs text-gray-400">{{ $c->ccandi }}</span></td>
                            <td class="px-4 py-2 text-right">{{ number_format($c->ccaigv, 2) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ number_format($c->ccaitv, 2) }}</td>
                            <td class="px-4 py-2 text-right text-xs font-bold text-rose-600">{{ $c->ccabaj ? 'ANULADO' : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No hay comprobantes de contingencia en este mes.</td></tr>
                    @endforelse
                </tbody>
                @if ($lista->isNotEmpty())
                    <tfoot><tr class="font-bold bg-gray-50"><td colspan="3" class="px-4 py-2 text-right">Total vigente</td>
                        <td class="px-4 py-2 text-right">{{ number_format($lista->whereNull('ccabaj')->sum('ccaigv'), 2) }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($lista->whereNull('ccabaj')->sum('ccaitv'), 2) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
