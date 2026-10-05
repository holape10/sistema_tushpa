@extends('layouts.app')
@section('title', 'Planillas y Boletas')
@section('content')
    @include('empresas.planilla._nav')
    @php
        $mes = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2);
        $nombreMes = \App\Support\Contabilidad\Contabilidad::nombrePeriodo($periodo);
        $editable = $planilla && $planilla->estado === 'BORRADOR';
        $estadoColor = ['BORRADOR' => 'bg-amber-100 text-amber-800', 'CERRADA' => 'bg-emerald-100 text-emerald-700', 'PAGADA' => 'bg-sky-100 text-sky-700'];
    @endphp

    <div class="flex flex-col lg:flex-row gap-4 mb-4">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 flex items-end gap-3">
            <label class="text-sm font-semibold text-gray-600">Mes
                <input type="month" value="{{ $mes }}" onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block mt-1 rounded-xl border-gray-300 font-bold"></label>
            <input type="hidden" name="periodo" value="{{ $periodo }}">
            @if ($planilla)<span class="mb-2 px-3 py-1 rounded-full text-xs font-bold {{ $estadoColor[$planilla->estado] }}">{{ $planilla->estado }}</span>@endif
        </form>
        @if ($planilla)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 flex-1">
                <div class="bg-white rounded-2xl shadow-sm p-3"><p class="text-xs text-gray-500">Total ingresos</p><p class="text-lg font-black" id="t-ingresos">S/ {{ number_format($planilla->total_ingresos, 2) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-3"><p class="text-xs text-gray-500">Descuentos</p><p class="text-lg font-black text-rose-600" id="t-descuentos">S/ {{ number_format($planilla->total_descuentos, 2) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-3"><p class="text-xs text-gray-500">Neto a pagar</p><p class="text-lg font-black text-emerald-700" id="t-neto">S/ {{ number_format($planilla->total_neto, 2) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-3"><p class="text-xs text-gray-500">EsSalud (empleador)</p><p class="text-lg font-black text-indigo-700" id="t-aportes">S/ {{ number_format($planilla->total_aportes, 2) }}</p></div>
            </div>
        @endif
    </div>

    @if (!$planilla)
        <div class="bg-white rounded-2xl shadow-sm p-10 text-center">
            <i class="fas fa-file-invoice-dollar text-5xl text-indigo-200"></i>
            <h2 class="mt-3 text-lg font-bold text-gray-800">Planilla de {{ $nombreMes }}</h2>
            @if ($sinDatos)
                <p class="text-gray-500 mt-1">Se generará con {{ $sinDatos }} trabajador(es). Las faltas y tardanzas se toman del módulo de Asistencia.</p>
                <form method="POST" action="{{ route('planilla.generar') }}" class="mt-4">@csrf <input type="hidden" name="periodo" value="{{ $periodo }}">
                    <button class="px-8 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700"><i class="fas fa-gears"></i> Generar planilla</button></form>
            @else
                <p class="text-gray-500 mt-1">Primero completa el sueldo y el sistema de pensiones de tus trabajadores.</p>
                <a href="{{ route('planilla.trabajadores') }}" class="inline-block mt-4 px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold">Ir a Trabajadores</a>
            @endif
        </div>
    @else
        <div class="flex flex-wrap gap-2 mb-3">
            <a href="{{ route('planilla.boletas', $planilla->id) }}" target="_blank" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold"><i class="fas fa-print"></i> Boletas de pago</a>
            <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
            @if ($editable)
                <form method="POST" action="{{ route('planilla.generar') }}">@csrf <input type="hidden" name="periodo" value="{{ $periodo }}">
                    <button class="px-4 py-2 rounded-xl bg-white shadow-sm text-sm font-semibold text-gray-700" title="Agrega a los trabajadores nuevos"><i class="fas fa-user-plus"></i> Agregar nuevos</button></form>
                <form method="POST" action="{{ route('planilla.cerrar', $planilla->id) }}" class="flex items-center gap-2 ml-auto" onsubmit="return confirm('Al cerrar ya no se podrá editar y se registrará el asiento en el Libro diario. ¿Cerrar?')">
                    @csrf <label class="text-sm text-gray-600">Fecha de pago <input type="date" name="fecha_pago" value="{{ now()->toDateString() }}" required class="rounded-lg border-gray-300 text-sm"></label>
                    <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold"><i class="fas fa-lock"></i> Cerrar planilla</button></form>
            @else
                <form method="POST" action="{{ route('planilla.reabrir', $planilla->id) }}" class="ml-auto" onsubmit="return confirm('¿Abrir la planilla para corregirla?')">@csrf
                    <button class="px-4 py-2 rounded-xl bg-white shadow-sm text-sm font-semibold text-gray-700"><i class="fas fa-lock-open"></i> Reabrir</button></form>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto" x-data="planilla()">
            <table class="w-full text-xs">
                <thead class="text-white uppercase">
                    <tr class="bg-slate-800"><th class="px-2 py-1" colspan="2"></th><th class="px-2 py-1 border-l border-slate-600" colspan="{{ $editable ? 5 : 5 }}">Asistencia y extras</th>
                        <th class="px-2 py-1 border-l border-slate-600" colspan="2">Ingresos</th><th class="px-2 py-1 border-l border-slate-600" colspan="5">Descuentos</th><th class="px-2 py-1 border-l border-slate-600" colspan="3"></th></tr>
                    <tr class="bg-slate-700">
                        <th class="px-2 py-2 text-left sticky left-0 bg-slate-700 min-w-[180px]">Trabajador</th><th class="px-2 py-2">Pensión</th>
                        <th class="px-1 py-2 border-l border-slate-600">Días</th><th class="px-1 py-2">Faltas</th><th class="px-1 py-2">Tard. min</th><th class="px-1 py-2">HE 25%</th><th class="px-1 py-2">HE 35%</th>
                        <th class="px-1 py-2 border-l border-slate-600">Bonos</th><th class="px-2 py-2 text-right">Total</th>
                        <th class="px-2 py-2 text-right border-l border-slate-600">Falt./tard.</th><th class="px-2 py-2 text-right">ONP/AFP</th><th class="px-2 py-2 text-right">5ta</th>
                        <th class="px-1 py-2">Adelantos</th><th class="px-1 py-2">Otros</th>
                        <th class="px-2 py-2 text-right border-l border-slate-600">Neto</th><th class="px-2 py-2 text-right">EsSalud</th><th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($detalle as $d)
                        <tr class="hover:bg-gray-50" x-data="{ f: @js(['dias' => $d->dias, 'faltas' => (float) $d->faltas, 'tardanza_min' => $d->tardanza_min, 'he25' => (float) $d->he25, 'he35' => (float) $d->he35, 'bonos' => (float) $d->bonos, 'adelantos' => (float) $d->adelantos, 'otros_descuentos' => (float) $d->otros_descuentos]),
                            c: @js(['total' => (float) $d->total_ingresos, 'ft' => (float) $d->desc_faltas + (float) $d->desc_tardanza, 'pension' => (float) $d->onp + (float) $d->afp_aporte + (float) $d->afp_prima + (float) $d->afp_comision, 'quinta' => (float) $d->renta_quinta, 'neto' => (float) $d->neto, 'essalud' => (float) $d->essalud]) }">
                            <td class="px-2 py-1.5 sticky left-0 bg-white"><span class="font-semibold text-gray-800 uppercase">{{ $d->nombre }}</span>
                                <span class="block text-[11px] text-gray-400">{{ $d->cargo }} · S/ {{ number_format($d->sueldo, 2) }}{{ $d->asig_familiar > 0 ? ' + asig. ' . number_format($d->asig_familiar, 2) : '' }}</span></td>
                            <td class="px-2 py-1.5 text-center whitespace-nowrap">{{ $d->sistema_pension }}</td>
                            @foreach (['dias' => 'border-l', 'faltas' => '', 'tardanza_min' => '', 'he25' => '', 'he35' => '', 'bonos' => 'border-l'] as $campo => $borde)
                                <td class="px-1 py-1 {{ $borde }}">
                                    @if ($editable)<input type="number" step="any" min="0" x-model.number="f.{{ $campo }}" @change="guardar({{ $d->id }}, f, c, $el)" class="w-16 rounded border-gray-200 text-xs text-right py-1">
                                    @else<span class="block text-right" x-text="f.{{ $campo }}"></span>@endif
                                </td>
                            @endforeach
                            <td class="px-2 py-1.5 text-right font-semibold" x-text="n2(c.total)"></td>
                            <td class="px-2 py-1.5 text-right border-l text-rose-600" x-text="n2(c.ft)"></td>
                            <td class="px-2 py-1.5 text-right" x-text="n2(c.pension)"></td>
                            <td class="px-2 py-1.5 text-right" x-text="n2(c.quinta)"></td>
                            @foreach (['adelantos', 'otros_descuentos'] as $campo)
                                <td class="px-1 py-1">@if ($editable)<input type="number" step="any" min="0" x-model.number="f.{{ $campo }}" @change="guardar({{ $d->id }}, f, c, $el)" class="w-20 rounded border-gray-200 text-xs text-right py-1">
                                    @else<span class="block text-right" x-text="n2(f.{{ $campo }})"></span>@endif</td>
                            @endforeach
                            <td class="px-2 py-1.5 text-right border-l font-black text-emerald-700" x-text="n2(c.neto)"></td>
                            <td class="px-2 py-1.5 text-right text-indigo-700" x-text="n2(c.essalud)"></td>
                            <td class="px-2 py-1.5 whitespace-nowrap">
                                <a href="{{ route('planilla.boletas', [$planilla->id, 'emp' => $d->emp_id]) }}" target="_blank" title="Boleta" class="text-indigo-600"><i class="fas fa-file-lines"></i></a>
                                @if ($editable)
                                    <form method="POST" action="{{ route('planilla.quitar', $d->id) }}" class="inline" onsubmit="return confirm('¿Quitar a este trabajador de la planilla del mes?')">@csrf @method('DELETE')
                                        <button class="text-rose-400 ml-1" title="Quitar"><i class="fas fa-xmark"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">Las <strong>faltas</strong> (días con turno sin marcación) y la <strong>tardanza</strong> salen de Asistencia; puedes corregirlas. Cada cambio se recalcula al instante.
            La renta de 5ta es una estimación mensual. Configura tasas, RMV y UIT en <a href="{{ route('planilla.parametros') }}" class="text-indigo-600 hover:underline">Parámetros</a>.</p>

        @if ($historial->count() > 1)
            <div class="mt-6"><p class="text-sm font-bold text-gray-700 mb-2">Planillas anteriores</p>
                <div class="flex flex-wrap gap-2">@foreach ($historial as $h)
                    <a href="{{ route('planilla.index', ['periodo' => $h->periodo]) }}" class="px-3 py-2 rounded-xl text-sm {{ $h->periodo === $periodo ? 'bg-indigo-600 text-white' : 'bg-white shadow-sm text-gray-700' }}">
                        {{ substr($h->periodo, 4, 2) }}/{{ substr($h->periodo, 0, 4) }} · S/ {{ number_format($h->total_neto, 0) }}</a>@endforeach</div></div>
        @endif
    @endif

    <script>
        function planilla() {
            const n2 = n => (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return {
                n2,
                async guardar(id, f, c, el) {
                    el.classList.add('bg-amber-50');
                    const r = await fetch(@js(url('planilla/fila')) + '/' + id, { method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify(f) });
                    const d = await r.json();
                    el.classList.remove('bg-amber-50');
                    if (r.status === 422) { alert(Object.values(d.errors)[0][0]); return; }
                    if (!d.success) { alert(d.message); return; }
                    const x = d.fila;
                    Object.assign(c, { total: +x.total_ingresos, ft: +x.desc_faltas + +x.desc_tardanza, pension: +x.onp + +x.afp_aporte + +x.afp_prima + +x.afp_comision,
                        quinta: +x.renta_quinta, neto: +x.neto, essalud: +x.essalud });
                    document.getElementById('t-ingresos').textContent = 'S/ ' + n2(d.planilla.total_ingresos);
                    document.getElementById('t-descuentos').textContent = 'S/ ' + n2(d.planilla.total_descuentos);
                    document.getElementById('t-neto').textContent = 'S/ ' + n2(d.planilla.total_neto);
                    document.getElementById('t-aportes').textContent = 'S/ ' + n2(d.planilla.total_aportes);
                },
            };
        }
    </script>
@endsection
