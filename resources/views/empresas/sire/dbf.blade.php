@extends('layouts.app')
@section('title', 'SIRE a FoxPro (DBF)')

@php
    $tipos = ['01' => 'Factura', '02' => 'Rec. honorarios', '03' => 'Boleta', '07' => 'N. Crédito', '08' => 'N. Débito', '12' => 'Ticket', '14' => 'Serv. público'];
    $nombrePeriodo = \Carbon\Carbon::createFromFormat('!Ym', $periodo)->locale('es')->translatedFormat('F Y');
    $compras = $libro === 'compras';
    $tabla = $compras ? 'almaingr' : 'tienfact';
    $sinXml = $compras ? collect($comprobantes)->filter(fn ($c) => ! $c['lineas'])->count() : 0;
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-4" x-data="sireDbf(@js($totales))">

    {{-- Pestañas del SIRE --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('sire.index', ['ventas', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Ventas (RVIE)</a>
        <a href="{{ route('sire.index', ['compras', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Compras (RCE)</a>
        <a href="{{ route('sire.detalle', ['periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Compras con detalle</a>
        <a href="{{ route('sire.ventas.detalle', ['periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Ventas con detalle</a>
        <span class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-indigo-600 text-white">Contabilidad (DBF)</span>
    </div>

    {{-- Periodo, libro y descarga --}}
    <section class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="libro" value="{{ $libro }}">
            <label class="text-xs font-semibold text-slate-500">Periodo
                <input type="month" value="{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}"
                       onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
            </label>
        </form>
        <div class="inline-flex rounded-xl bg-slate-100 p-1">
            @foreach (['compras' => 'Compras · almaingr', 'ventas' => 'Ventas · tienfact'] as $valor => $texto)
                <a href="{{ route('sire.dbf', ['libro' => $valor, 'periodo' => $periodo]) }}"
                   class="px-4 h-9 inline-flex items-center rounded-lg text-sm font-bold {{ $libro === $valor ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' }}">{{ $texto }}</a>
            @endforeach
        </div>
        <div class="flex-1"></div>
        @if ($propuesta && \App\Http\Controllers\SireDbfController::puedeRegistrar($libro))
            {{-- Lo revisado pasa al Registro de Contabilidad con su asiento (sin digitar de nuevo; lo ya registrado no se repite) --}}
            <form method="POST" action="{{ route('sire.dbf.registrar', [$libro, 'periodo' => $periodo]) }}"
                  onsubmit="return confirm('¿Pasar los ' + {{ $totales['registros'] }} + ' comprobantes revisados al Registro de {{ $compras ? 'Compras' : 'Ventas' }} de Contabilidad? Los que ya están registrados no se repiten.')">
                @csrf
                <button class="h-10 px-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold"
                        @click="if (totales.descuadrados) { $event.preventDefault(); tushpaAviso('Hay ' + totales.descuadrados + ' comprobante(s) que no cuadran: corrígelos o no los lleves.', false); }">
                    📒 Pasar al Registro de {{ $compras ? 'Compras' : 'Ventas' }}
                </button>
            </form>
        @endif
        <a href="{{ route('sire.dbf.imprimir', [$libro, 'periodo' => $periodo]) }}" target="_blank"
           class="h-10 px-4 inline-flex items-center gap-2 rounded-xl bg-white border border-slate-400 text-slate-700 hover:bg-slate-50 text-sm font-bold {{ $propuesta ? '' : 'pointer-events-none opacity-50' }}">
            🖨 Imprimir
        </a>
        <a href="{{ route('sire.dbf.excel', [$libro, 'periodo' => $periodo]) }}"
           class="h-10 px-4 inline-flex items-center gap-2 rounded-xl bg-white border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-bold {{ $propuesta ? '' : 'pointer-events-none opacity-50' }}">
            ⬇ Excel
        </a>
        <a href="{{ route('sire.dbf.descargar', [$libro, 'periodo' => $periodo]) }}" @click="if (totales.descuadrados) { $event.preventDefault(); tushpaAviso('Hay ' + totales.descuadrados + ' comprobante(s) que no cuadran: corrígelos o no los lleves.', false); }"
           class="h-10 px-5 inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold {{ $propuesta ? '' : 'pointer-events-none opacity-50' }}">
            ⬇ Descargar {{ $tabla }}.dbf
        </a>
    </section>

    @unless ($propuesta)
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            Primero trae la propuesta de {{ $compras ? 'compras' : 'ventas' }} de <strong>{{ $nombrePeriodo }}</strong> desde SUNAT.
            <a href="{{ route('sire.index', [$libro, 'periodo' => $periodo]) }}" class="font-bold underline">Ir a {{ $compras ? 'Compras (RCE)' : 'Ventas (RVIE)' }}</a>
        </div>
    @else
        @if ($compras && $sinXml)
            <div class="rounded-xl bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 text-sm flex flex-wrap items-center gap-2">
                <span class="flex-1">A <strong>{{ $sinXml }}</strong> compra(s) les falta el detalle (su XML). Con el detalle sabes qué se compró y eliges mejor la cuenta.</span>
                <a href="{{ route('sire.detalle', ['periodo' => $periodo]) }}" class="font-bold underline">Traer XML de SUNAT</a>
            </div>
        @endif

        {{-- Lo que se va a descargar --}}
        @include('empresas.sire.partials.dbf_resumen')

        {{-- Las mismas cuentas a todos --}}
        @include('empresas.sire.partials.dbf_cuentas_todos')

        <div class="flex flex-wrap items-center gap-2 text-sm">
            @foreach (['todos' => 'Todos', 'descuadre' => 'No cuadran / IGV mal', 'excluidos' => 'No se llevan'] + ($compras ? ['sinxml' => 'Sin detalle (XML)', 'sincuenta' => 'Sin Cta. cargo'] : []) as $valor => $texto)
                <button type="button" @click="filtro = '{{ $valor }}'" class="px-3 h-8 rounded-full font-semibold"
                        :class="filtro === '{{ $valor }}' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'">{{ $texto }}</button>
            @endforeach
            <span class="text-xs text-slate-400 ml-auto">Los cambios se guardan solos.</span>
        </div>

        {{-- Comprobantes --}}
        <div class="space-y-2">
            @forelse ($comprobantes as $c)
                @include('empresas.sire.partials.dbf_fila')
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-500">La propuesta de {{ $nombrePeriodo }} no tiene comprobantes.</div>
            @endforelse
        </div>
    @endunless
</div>

@include('empresas.sire.partials.dbf_js')
@endsection
