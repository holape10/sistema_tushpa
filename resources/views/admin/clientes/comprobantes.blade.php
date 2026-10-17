@extends('admin.layout')
@section('title', 'Comprobantes · '.$cliente->razon_social)

@section('content')
    @php
        $color = ['ACEPTADO' => 'bg-emerald-100 text-emerald-700', 'OBSERVADO' => 'bg-amber-100 text-amber-800', 'RECHAZADO' => 'bg-rose-100 text-rose-700',
            'ERROR' => 'bg-orange-100 text-orange-700', 'EN RESUMEN' => 'bg-sky-100 text-sky-700', 'GENERADO' => 'bg-slate-100 text-slate-600'];
        $tipos = ['01' => 'Factura', '03' => 'Boleta', '07' => 'N. crédito', '08' => 'N. débito'];
    @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.clientes.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Clientes</a>
        <div>
            <h1 class="text-xl font-bold">Comprobantes SUNAT</h1>
            <p class="text-sm text-slate-500">{{ $cliente->nombre_comercial ?: $cliente->razon_social }} · RUC {{ $cliente->ruc }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex gap-1 p-1 bg-white rounded-xl shadow-sm text-sm">
            @foreach (\App\Http\Controllers\Admin\ComprobanteController::FILTROS as $valor => $texto)
                <a href="{{ route('admin.clientes.comprobantes', [$cliente->id, 'filtro' => $valor]) }}"
                   class="px-3 py-1.5 rounded-lg font-semibold {{ $filtro === $valor ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $texto }}@isset($conteo[$valor]) <span class="opacity-70">({{ $conteo[$valor] }})</span>@endisset</a>
            @endforeach
        </div>
        <form method="GET" class="ml-auto flex gap-2">
            <input type="hidden" name="filtro" value="{{ $filtro }}">
            <select name="anio" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm">
                <option value="">Todos los años</option>
                @foreach ($anios as $a)<option value="{{ $a }}" @selected($anio === (int) $a)>{{ $a }}</option>@endforeach
            </select>
            <input name="q" value="{{ $q }}" placeholder="B001-71, cliente o DNI/RUC" class="rounded-xl border-slate-300 text-sm w-56">
        </form>
    </div>

    <form method="POST" action="{{ route('admin.clientes.comprobantes.accion', $cliente->id) }}" x-data="{ sel: [], accion: '', motivo: '' }"
          @submit="if (!sel.length) { $event.preventDefault(); alert('Marca al menos un comprobante.'); return; }
                   if (['descartar', 'anular'].includes(accion) && !confirm(accion === 'anular' ? '¿Anular en el sistema? El stock vuelve al almacén y se anulan sus cobros.' : '¿Descartar? Solo deja de salir en pendientes; no mueve stock.')) $event.preventDefault();">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm p-3 mb-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold" x-text="sel.length + ' marcado(s)'"></span>
            @foreach (['consultar' => ['🔎 Consultar en SUNAT', 'bg-sky-50 text-sky-700'], 'enviar' => ['📤 Enviar a SUNAT', 'bg-indigo-50 text-indigo-700'],
                'aceptado' => ['✔ Marcar aceptado', 'bg-emerald-50 text-emerald-700'], 'descartar' => ['🚫 Descartar (rechazado)', 'bg-slate-100 text-slate-700'],
                'anular' => ['✖ Anular en el sistema', 'bg-rose-50 text-rose-700']] as $valor => [$texto, $clase])
                <button name="accion" value="{{ $valor }}" @click="accion = '{{ $valor }}'" class="px-3 py-1.5 rounded-lg font-semibold {{ $clase }}">{{ $texto }}</button>
            @endforeach
            <input name="motivo" x-model="motivo" maxlength="70" placeholder="Motivo (para descartar o anular)" class="ml-auto rounded-lg border-slate-300 text-sm w-64">
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr><th class="px-3 py-2"><input type="checkbox" @change="sel = $event.target.checked ? [...$root.querySelectorAll('[data-id]')].map(x => x.value) : []" class="rounded"></th>
                        <th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-left">Cliente</th>
                        <th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-left">SUNAT</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lista as $c)
                        <tr>
                            <td class="px-3 py-2"><input type="checkbox" name="ids[]" value="{{ $c->IdCpe_cabecera }}" data-id x-model="sel" class="rounded"></td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($c->ccafem)->format('d/m/Y') }}
                                @if (\Carbon\Carbon::parse($c->ccafem)->lt(now()->subDays(7)))<span class="block text-[10px] text-rose-600 font-semibold">fuera de plazo</span>@endif</td>
                            <td class="px-3 py-2 whitespace-nowrap"><span class="text-xs text-slate-400">{{ $tipos[$c->tdocod] ?? $c->tdocod }}</span><br><b>{{ $c->serdoc }}-{{ $c->numdoc }}</b></td>
                            <td class="px-3 py-2">{{ $c->ccanom }}<span class="block text-xs text-slate-400">{{ $c->ccandi }}</span></td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($c->ccaitv, 2) }}</td>
                            <td class="px-3 py-2 max-w-sm"><span class="px-2 py-0.5 rounded-lg text-xs font-bold {{ $color[$c->est_sunat] ?? 'bg-slate-100 text-slate-600' }}">{{ $c->est_sunat ?: 'PENDIENTE' }}</span>
                                @if ($c->ccadessun)<span class="block text-[11px] text-slate-500 mt-0.5 line-clamp-2" title="{{ $c->ccadessun }}">{{ $c->ccadessun }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-10 text-center text-slate-400">No hay comprobantes {{ $filtro === 'todos' ? '' : mb_strtolower(\App\Http\Controllers\Admin\ComprobanteController::FILTROS[$filtro]) }} 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
    <div class="mt-4">{{ $lista->links() }}</div>

    <div class="mt-4 grid md:grid-cols-2 gap-3 text-xs text-slate-600">
        <div class="bg-white rounded-2xl shadow-sm p-4 space-y-1">
            <p class="font-bold text-slate-800 text-sm">¿Qué hago con cada uno?</p>
            <p><b>🔎 Consultar:</b> pregunta a SUNAT si lo tiene. Si ya estaba aceptado, se actualiza solo (requiere producción y clave SOL).</p>
            <p><b>📤 Enviar:</b> lo manda ahora a SUNAT (dentro del plazo: facturas 3 días, boletas 7).</p>
            <p><b>✔ Marcar aceptado:</b> cuando ya lo viste aceptado en la web de SUNAT pero aquí quedó pendiente.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4 space-y-1">
            <p><b>🚫 Descartar:</b> para RECHAZADOS que ya no se corregirán (por ejemplo, de años anteriores). Sale de pendientes y no mueve stock.</p>
            <p><b>✖ Anular en el sistema:</b> para lo que nunca llegó a SUNAT y la venta no se hizo. Devuelve el stock y anula sus cobros.</p>
            <p class="text-slate-400">Los comprobantes no se borran: su número ya se usó y debe quedar el rastro. Lo aceptado por SUNAT se anula con nota de crédito o baja desde el sistema de la empresa.</p>
        </div>
    </div>
@endsection
