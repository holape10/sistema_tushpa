@extends('layouts.app')
@section('title', 'Resumen Diario de Boletas')
@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
        @if ((string) $empresa->produccion === '1')
            <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 font-bold">PRODUCCIÓN</span>
        @else
            <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 font-bold">BETA (pruebas, sin validez tributaria)</span>
        @endif
        <a href="{{ route('sunat.envios') }}" class="ml-auto px-4 py-2 rounded-xl bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">← Envío individual</a>
    </div>

    @if ($diasPendientes->isNotEmpty())
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            <strong>Días con boletas sin resumen:</strong>
            @foreach ($diasPendientes as $d)
                <a href="{{ route('sunat.resumenes', ['fecha' => $d->ccafem]) }}" class="inline-block ml-2 underline {{ $d->ccafem == $fecha ? 'font-bold' : '' }}">
                    {{ \Carbon\Carbon::parse($d->ccafem)->format('d/m/Y') }} ({{ $d->cantidad }} · S/ {{ number_format($d->total, 2) }})</a>
            @endforeach
            <span class="block text-xs mt-1">SUNAT da 7 días calendario desde la emisión para enviar el resumen.</span>
        </div>
    @endif

    <div class="grid lg:grid-cols-5 gap-6">
        {{-- Generar resumen --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-4 h-fit">
            <h3 class="font-semibold text-gray-700 mb-3">Nuevo resumen</h3>
            <form method="GET" class="flex items-end gap-2 mb-4">
                <label class="text-sm flex-1">Fecha de emisión de las boletas
                    <input type="date" name="fecha" value="{{ $fecha }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()" class="block w-full rounded-lg border-gray-300 text-sm">
                </label>
            </form>

            @if ($pendientes->isEmpty())
                <p class="text-sm text-gray-400 text-center py-6">No hay boletas pendientes de resumen para el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}.</p>
            @else
                @php $dias = \Carbon\Carbon::parse($fecha)->diffInDays(now()->startOfDay()); @endphp
                @if ($dias > 7)
                    <div class="mb-3 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-xs">
                        ⚠️ Estas boletas tienen {{ (int) $dias }} días. SUNAT podría rechazar el resumen por estar fuera de plazo.
                    </div>
                @endif
                <div class="max-h-80 overflow-y-auto mb-3">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500 uppercase"><tr><th class="text-left py-1">Comprobante</th><th class="text-left">Estado</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            @foreach ($pendientes as $p)
                                <tr class="border-t border-gray-100">
                                    <td class="py-1.5">{{ $p->serdoc }}-{{ str_pad($p->numdoc, 8, '0', STR_PAD_LEFT) }}
                                        @if ($p->tdocod !== '03')<span class="text-xs text-gray-400">(nota de {{ $p->serie_ref }}-{{ $p->num_ref }})</span>@endif
                                    </td>
                                    <td>@include('empresas.sunat._estado', ['estado' => $p->est_sunat])</td>
                                    <td class="text-right">{{ number_format($p->ccaitv, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-between text-sm font-semibold mb-3">
                    <span>{{ $pendientes->count() }} comprobante(s)</span>
                    <span>S/ {{ number_format($pendientes->sum('ccaitv'), 2) }}</span>
                </div>
                <form method="POST" action="{{ route('sunat.resumen_enviar') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'ENVIANDO A SUNAT...';">
                    @csrf
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                    <button class="w-full py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700">ENVIAR RESUMEN DEL {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</button>
                </form>
                <p class="text-xs text-gray-400 mt-2">Si después emites más boletas de este día, podrás enviar otro resumen: cada uno lleva su propio correlativo.</p>
            @endif
        </div>

        {{-- Resúmenes enviados --}}
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm overflow-x-auto">
            <h3 class="font-semibold text-gray-700 p-4 pb-2">Resúmenes enviados</h3>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Resumen</th><th class="px-3 py-2 text-left">Boletas del</th><th class="px-3 py-2 text-right">Docs / Total</th><th class="px-3 py-2 text-left">Estado</th><th class="px-3 py-2 text-right"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($resumenes as $r)
                        <tr class="align-top">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="font-semibold">{{ $r->res_tip ?: 'RC' }}-{{ str_replace('-', '', $r->res_fec_gen) }}-{{ str_pad($r->res_cor, 3, '0', STR_PAD_LEFT) }}</span>
                                @if ($r->es_baja ?? 0)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">BAJA {{ $r->tip_res_com === '01' ? 'FACTURA' : 'BOLETA' }}</span>@endif
                                <span class="block text-xs text-gray-400">Ticket: {{ $r->res_ticket ?? '—' }}</span>
                                <span class="block text-xs text-gray-400">{{ \Carbon\Carbon::parse($r->fecha_hora)->format('d/m/Y H:i') }} · {{ $r->apeusu }}</span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($r->res_fec_com)->format('d/m/Y') }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $r->res_cant }}<span class="block text-xs text-gray-500">S/ {{ number_format($r->res_total, 2) }}</span></td>
                            <td class="px-3 py-2">
                                @include('empresas.sunat._estado', ['estado' => $r->est_sunat])
                                <span class="block text-xs text-gray-500 mt-1 break-words">{{ $r->res_est }}</span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                                @if ($r->res_ticket && in_array($r->est_sunat, ['ENVIADO', 'EN PROCESO'], true))
                                    <form method="POST" action="{{ route('sunat.resumen_consultar', $r->res_id) }}" class="inline">
                                        @csrf<button class="px-3 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700">Consultar ticket</button>
                                    </form>
                                @endif
                                <a href="{{ route('sunat.resumen_descargar', [$r->res_id, 'xml']) }}" class="text-xs text-indigo-600 hover:underline">XML</a>
                                <a href="{{ route('sunat.resumen_descargar', [$r->res_id, 'cdr']) }}" class="text-xs text-indigo-600 hover:underline">CDR</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aún no se envió ningún resumen.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $resumenes->links() }}</div>
        </div>
    </div>
@endsection
