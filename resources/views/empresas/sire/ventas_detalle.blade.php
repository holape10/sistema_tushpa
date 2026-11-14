@extends('layouts.app')
@section('title', 'Ventas con detalle')

@php
    $tipos = ['01' => 'Factura', '03' => 'Boleta', '07' => 'N. Crédito', '08' => 'N. Débito', '12' => 'Ticket'];
    $nombrePeriodo = \Carbon\Carbon::createFromFormat('!Ym', $periodo)->locale('es')->translatedFormat('F Y');
    $compras = false;
    $total = count($dbf);
    $faltan = $total - $conDetalle;
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-4" x-data="Object.assign(sireDbf(@js($totales)), { subiendo: false })">

    {{-- Pestañas del SIRE --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('sire.index', ['ventas', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Ventas (RVIE)</a>
        <a href="{{ route('sire.index', ['compras', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Compras (RCE)</a>
        <a href="{{ route('sire.detalle', ['periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Compras con detalle</a>
        <span class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-indigo-600 text-white">Ventas con detalle</span>
        <a href="{{ route('sire.dbf', ['periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Contabilidad (DBF)</a>
    </div>

    {{-- Periodo, subir XML y descargas --}}
    <section class="bg-white rounded-2xl shadow-sm p-4 grid gap-4 lg:grid-cols-[auto_1fr] xl:grid-cols-[auto_1fr_auto] lg:items-end">
        <form method="GET">
            <label class="text-xs font-semibold text-slate-500">Periodo
                <input type="month" value="{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}"
                       onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
            </label>
        </form>

        <form method="POST" action="{{ route('sire.ventas.detalle.subir', ['periodo' => $periodo]) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2"
              @submit="subiendo = true">
            @csrf
            <label class="flex-1 min-w-[220px] text-xs font-semibold text-slate-500">XML de tus ventas (sueltos o en un .zip) <span class="font-normal text-slate-400">· opcional</span>
                <input type="file" name="archivos[]" multiple accept=".xml,.zip" required
                       class="block w-full mt-1 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700">
            </label>
            <button class="h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50" :disabled="subiendo">
                <span x-text="subiendo ? 'Leyendo XML…' : 'Subir XML'"></span>
            </button>
        </form>

        @if ($propuesta)
            <div class="flex flex-wrap gap-2 lg:justify-end">
                <a href="{{ route('sire.dbf.imprimir', ['ventas', 'periodo' => $periodo]) }}" target="_blank"
                   class="h-10 px-4 inline-flex items-center rounded-xl bg-white border border-slate-400 text-slate-700 hover:bg-slate-50 text-sm font-bold">🖨 Imprimir</a>
                <a href="{{ route('sire.dbf.excel', ['ventas', 'periodo' => $periodo]) }}"
                   class="h-10 px-4 inline-flex items-center rounded-xl bg-white border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-bold">⬇ Excel (DBF)</a>
                <a href="{{ route('sire.dbf.descargar', ['ventas', 'periodo' => $periodo]) }}"
                   @click="if (totales.descuadrados) { $event.preventDefault(); tushpaAviso('Hay ' + totales.descuadrados + ' comprobante(s) que no cuadran o tienen el IGV en otra casilla: corrígelos o no los lleves.', false); }"
                   class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Descargar tienfact.dbf</a>
            </div>
        @endif
    </section>

    @unless ($propuesta)
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            Primero trae la propuesta de ventas de <strong>{{ $nombrePeriodo }}</strong> desde SUNAT.
            <a href="{{ route('sire.index', ['ventas', 'periodo' => $periodo]) }}" class="font-bold underline">Ir a Ventas (RVIE)</a>
        </div>
    @else
        <div class="grid grid-cols-3 gap-3">
            <button type="button" @click="filtro = 'todos'" class="text-left bg-white rounded-2xl shadow-sm p-4 ring-2" :class="filtro === 'todos' ? 'ring-indigo-500' : 'ring-transparent'">
                <p class="text-xs font-semibold text-slate-500">Comprobantes en SUNAT</p>
                <p class="text-2xl font-extrabold">{{ $total }}</p>
            </button>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">Con detalle (XML)</p>
                <p class="text-2xl font-extrabold text-emerald-600">{{ $conDetalle }}</p>
            </div>
            <button type="button" @click="filtro = 'sinxml'" class="text-left bg-white rounded-2xl shadow-sm p-4 ring-2" :class="filtro === 'sinxml' ? 'ring-amber-500' : 'ring-transparent'">
                <p class="text-xs font-semibold text-slate-500">Sin su XML</p>
                <p class="text-2xl font-extrabold {{ $faltan ? 'text-amber-600' : 'text-emerald-600' }}">{{ $faltan }}</p>
                <p class="text-[11px] text-slate-400">No es obligatorio: sirve para ver qué se vendió</p>
            </button>
        </div>

        @include('empresas.sire.partials.dbf_resumen', ['comprobantes' => $dbf])
        @include('empresas.sire.partials.dbf_cuentas_todos')

        <div class="flex flex-wrap items-center gap-2 text-sm">
            @foreach (['todos' => 'Todos', 'descuadre' => 'No cuadran / IGV mal', 'excluidos' => 'No se llevan', 'sinxml' => 'Sin detalle (XML)'] as $valor => $texto)
                <button type="button" @click="filtro = '{{ $valor }}'" class="px-3 h-8 rounded-full font-semibold"
                        :class="filtro === '{{ $valor }}' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'">{{ $texto }}</button>
            @endforeach
            @include('empresas.sire.partials.agregar_comprobante', ['libro' => 'ventas'])
            <span class="text-xs text-slate-400 ml-auto">Los cambios se guardan solos · desmarca «Llevar» lo que no va al .dbf.</span>
        </div>

        <div class="space-y-2">
            @forelse ($dbf as $c)
                @include('empresas.sire.partials.dbf_fila')
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-500">La propuesta de {{ $nombrePeriodo }} no tiene comprobantes.</div>
            @endforelse
        </div>
    @endunless
</div>

@include('empresas.sire.partials.dbf_js')
@endsection
