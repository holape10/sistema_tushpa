@extends('layouts.app')
@section('title', 'Mis comprobantes')

@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $tipos = \App\Http\Controllers\MisComprobantesController::TIPOS;
        $colores = ['01' => 'bg-emerald-100 text-emerald-800', '03' => 'bg-blue-100 text-blue-800', '07' => 'bg-rose-100 text-rose-800', '08' => 'bg-amber-100 text-amber-800', '13' => 'bg-slate-100 text-slate-700'];
    @endphp
    <div class="max-w-6xl mx-auto space-y-4">
        <section class="rounded-3xl overflow-hidden shadow-sm bg-gradient-to-br from-indigo-700 to-indigo-900 text-white relative isolate">
            <x-kene-adorno patron="laberinto" />
            <div class="p-6 sm:p-8">
                <h1 class="text-2xl font-extrabold">🧾 Mis comprobantes</h1>
                <p class="text-indigo-200 text-sm mt-1">Los comprobantes que te emitió {{ $emisor ?: 'TUSHPA' }} por tu sistema. Descárgalos o imprímelos cuando quieras; de las facturas, boletas y notas también tienes el XML y el CDR de SUNAT.</p>
            </div>
        </section>

        @if (! $docs)
            <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">Aquí verás los comprobantes que TUSHPA le emite a tu empresa.</div>
        @else
            {{-- Filtro por tipo --}}
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('mis-comprobantes.index') }}" class="px-3 py-1.5 rounded-full font-semibold {{ $tipo === '' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 shadow-sm hover:bg-gray-50' }}">Todos ({{ $conteo->sum() }})</a>
                @foreach ($tipos as $cod => $nombre)
                    @if ($conteo[$cod] ?? 0)
                        <a href="{{ route('mis-comprobantes.index', ['tipo' => $cod]) }}" class="px-3 py-1.5 rounded-full font-semibold {{ $tipo === $cod ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 shadow-sm hover:bg-gray-50' }}">{{ $nombre }} ({{ $conteo[$cod] }})</a>
                    @endif
                @endforeach
            </div>

            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700 text-white text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-left">Comprobante</th>
                            <th class="px-4 py-3 text-left">Detalle</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-center">SUNAT</th>
                            <th class="px-4 py-3 text-right">Descargar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($comprobantes as $c)
                            @php
                                $electronico = $c->tdocod !== '13';
                                $anulado = $c->ccabaj || $c->anulado_nc;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $anulado ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($c->ccafem)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $colores[$c->tdocod] }}">{{ $tipos[$c->tdocod] }}</span>
                                    <span class="block font-semibold text-gray-800 mt-1">{{ $c->serdoc }}-{{ str_pad($c->numdoc, 8, '0', STR_PAD_LEFT) }}</span>
                                    @if ($c->serie_ref)<span class="block text-xs text-gray-400">Modifica: {{ $c->serie_ref }}-{{ $c->num_ref }}</span>@endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-xs">{{ $c->detalle }}</td>
                                <td class="px-4 py-3 text-right font-bold whitespace-nowrap">{{ $c->moncod === 'USD' ? '$' : 'S/' }} {{ number_format($c->ccaitv, 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($anulado)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">ANULADO</span>
                                    @elseif ($electronico)
                                        @include('empresas.sunat._estado', ['estado' => $c->est_sunat ?: 'PENDIENTE'])
                                    @else
                                        <span class="text-xs text-gray-400">Interno</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('mis-comprobantes.pdf', $c->IdCpe_cabecera) }}" target="_blank" title="Ver e imprimir" class="btn-ico text-slate-700"><i class="fas fa-print"></i></a>
                                        <a href="{{ route('mis-comprobantes.pdf', [$c->IdCpe_cabecera, 'descargar' => 1]) }}" title="Descargar PDF" class="btn-ico text-red-600"><i class="fas fa-file-pdf"></i></a>
                                        @if ($c->tieneXml)<a href="{{ route('mis-comprobantes.archivo', [$c->IdCpe_cabecera, 'xml']) }}" title="XML" class="btn-ico text-sky-700"><i class="fas fa-file-code"></i></a>@endif
                                        @if ($c->tieneCdr)<a href="{{ route('mis-comprobantes.archivo', [$c->IdCpe_cabecera, 'cdr']) }}" title="CDR (constancia de SUNAT)" class="btn-ico text-amber-700"><i class="fas fa-file-zipper"></i></a>@endif
                                    </div>
                                    @if ($electronico && ! $c->tieneCdr && $c->tdocod === '03')<span class="block text-[10px] text-gray-400">Boleta: va a SUNAT en el resumen diario</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Todavía no hay comprobantes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $comprobantes->links() }}</div>
        @endif
    </div>
    <style>.btn-ico{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:.6rem;background:#f3f4f6}.btn-ico:hover{background:#e5e7eb}</style>
@endsection
