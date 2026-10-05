@extends('layouts.app')
@section('title', 'Notas de Crédito y Débito')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-3 mb-4">
        <form method="GET" class="grid grid-cols-2 sm:flex sm:flex-wrap items-end gap-2">
            <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm">Tipo
                <select name="tipo" class="block w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Todas</option>
                    <option value="07" @selected(request('tipo') === '07')>Nota de crédito</option>
                    <option value="08" @selected(request('tipo') === '08')>Nota de débito</option>
                </select></label>
            <label class="text-sm">Buscar<input name="q" value="{{ request('q') }}" placeholder="Cliente o número" class="block w-full rounded-lg border-gray-300 text-sm"></label>
            <button class="col-span-2 sm:col-span-1 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Filtrar</button>
        </form>
        <a href="{{ route('notas.create') }}" class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 whitespace-nowrap">+ Nueva nota</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white text-xs uppercase">
                <tr>
                    <th class="px-3 py-3 text-left">Fecha</th>
                    <th class="px-3 py-3 text-left">Nota</th>
                    <th class="px-3 py-3 text-left">Modifica a</th>
                    <th class="px-3 py-3 text-left">Cliente</th>
                    <th class="px-3 py-3 text-left">Motivo</th>
                    <th class="px-3 py-3 text-right">Total</th>
                    <th class="px-3 py-3 text-center">SUNAT</th>
                    <th class="px-3 py-3 text-center">Opciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($notas as $n)
                    @php
                        $numero = $n->serdoc . '-' . str_pad($n->numdoc, 8, '0', STR_PAD_LEFT);
                        $reenviable = in_array($n->est_sunat ?? 'PENDIENTE', \App\Support\Sunat\SunatService::ESTADOS_REENVIABLES, true);
                    @endphp
                    <tr class="hover:bg-gray-50" data-id="{{ $n->IdCpe_cabecera }}">
                        <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($n->fecha_hora)->format('d/m/Y') }}
                            <span class="block text-xs text-gray-400">{{ \Carbon\Carbon::parse($n->fecha_hora)->format('H:i') }}</span></td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold text-white {{ $n->tdocod === '07' ? 'bg-rose-600' : 'bg-amber-600' }}">{{ $n->tdocod === '07' ? 'N. CRÉDITO' : 'N. DÉBITO' }}</span>
                            <span class="block font-bold text-gray-800">{{ $numero }}</span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <a href="{{ route('ventas.index', ['comprobante' => $n->serie_ref . '-' . $n->num_ref, 'desde' => $n->ccafem_ref, 'hasta' => $n->ccafem_ref]) }}" class="text-indigo-600 hover:underline font-semibold">
                                {{ $n->tdocod_ref === '01' ? 'Factura' : 'Boleta' }} {{ $n->serie_ref }}-{{ str_pad($n->num_ref, 8, '0', STR_PAD_LEFT) }}</a>
                            <span class="block text-xs text-gray-400">{{ $n->ccafem_ref ? \Carbon\Carbon::parse($n->ccafem_ref)->format('d/m/Y') : '' }}</span>
                        </td>
                        <td class="px-3 py-2"><span class="font-semibold text-gray-700">{{ $n->ccanom }}</span><span class="block text-xs text-gray-400">{{ $n->ccandi }}</span></td>
                        <td class="px-3 py-2 text-xs"><span class="font-semibold">{{ $n->tipnot }} - {{ $n->motivo_des }}</span><span class="block text-gray-400">{{ $n->ccaobs }}</span></td>
                        <td class="px-3 py-2 text-right font-bold whitespace-nowrap {{ $n->tdocod === '07' ? 'text-rose-600' : 'text-amber-700' }}">{{ $n->tdocod === '07' ? '-' : '+' }}{{ number_format($n->ccaitv, 2) }}</td>
                        <td class="px-3 py-2 text-center">
                            @include('empresas.sunat._estado', ['estado' => $n->est_sunat])
                            @if ($n->ccadessun && !in_array($n->est_sunat, ['ACEPTADO'], true))
                                <span class="block text-[10px] text-gray-500 max-w-[180px] mx-auto truncate" title="{{ $n->ccadessun }}">{{ $n->ccadessun }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('cobros.voucher', $n->IdCpe_cabecera) }}" target="_blank" title="Ver / imprimir" class="btn-ico text-red-600"><i class="fas fa-file-pdf"></i></a>
                                <a href="{{ route('sunat.descargar', [$n->IdCpe_cabecera, 'xml']) }}" title="XML" class="btn-ico text-sky-700"><i class="fas fa-file-code"></i></a>
                                <a href="{{ route('sunat.descargar', [$n->IdCpe_cabecera, 'cdr']) }}" title="CDR" class="btn-ico text-amber-700"><i class="fas fa-file-zipper"></i></a>
                                @if ($reenviable && $n->serdoc[0] === 'F')
                                    <button type="button" onclick="enviarNota(this, {{ $n->IdCpe_cabecera }})" title="Enviar a SUNAT" class="btn-ico text-indigo-600"><i class="fas fa-paper-plane"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No hay notas en este periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $notas->links() }}</div>
    <p class="text-xs text-gray-400 mt-2">Las notas de <strong>facturas</strong> se envían a SUNAT al emitirlas; las de <strong>boletas</strong> viajan en el Resumen diario.</p>

    <style>.btn-ico{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:.6rem;background:#f3f4f6}.btn-ico:hover{background:#e5e7eb}</style>
    <script>
        async function enviarNota(btn, id) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            try {
                const r = await fetch(@json(url('sunat/enviar')) + '/' + id, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), Accept: 'application/json' },
                });
                const d = await r.json();
                alert((d.estado ? d.estado + ': ' : '') + (d.mensaje || ''));
                location.reload();
            } catch (e) {
                alert('No se pudo conectar con el servidor.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            }
        }
    </script>
@endsection
