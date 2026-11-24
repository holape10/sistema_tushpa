@extends('layouts.app')
@section('title', 'Ingreso de pollo')
@section('content')
    @include('empresas.partials.alert')
    @php($tara = (float) $cfg->tara_jaba)

    <form method="POST" action="{{ route('avicola.ingresar') }}" x-data="{ origen: '{{ old('lote_id') ? 'lote' : 'compra' }}', tara: {{ $tara }}, avesJaba: {{ (int) $cfg->aves_jaba }},
            pesadas: [{ jabas: 0, aves: 0, peso: null }], muertas: 0, costo: {{ (float) old('costo_kg', 0) ?: 'null' }},
            get jabas() { return this.pesadas.reduce((s, p) => s + (Number(p.jabas) || 0), 0) },
            get bruto() { return this.pesadas.reduce((s, p) => s + (Number(p.peso) || 0), 0) },
            get neto() { return Math.max(0, this.bruto - this.jabas * this.tara - (Number(this.muertas) || 0)) } }"
          class="bg-white rounded-2xl shadow-sm p-5 mb-6 space-y-4">
        @csrf
        <div class="flex flex-wrap items-center gap-3">
            <p class="font-bold text-gray-800 flex-1"><i class="fas fa-truck-ramp-box text-sky-600"></i> Registrar ingreso de pollo</p>
            <div class="flex gap-1 p-1 bg-gray-100 rounded-xl text-sm font-bold">
                <button type="button" @click="origen = 'compra'" class="h-9 px-3 rounded-lg" :class="origen === 'compra' ? 'bg-white shadow text-gray-800' : 'text-gray-500'">Comprado (granja o proveedor)</button>
                <button type="button" @click="origen = 'lote'" class="h-9 px-3 rounded-lg" :class="origen === 'lote' ? 'bg-white shadow text-gray-800' : 'text-gray-500'" @disabled($lotes->isEmpty())>De mi granja (lote)</button>
            </div>
        </div>

        <div class="grid sm:grid-cols-4 gap-3">
            <label class="text-xs font-semibold text-gray-500">Producto
                <select name="IdProducto" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                    @foreach ($productos as $p)<option value="{{ $p->IdProducto }}" @selected(old('IdProducto') == $p->IdProducto)>{{ $p->pronom }}</option>@endforeach
                </select></label>
            <template x-if="origen === 'lote'">
                <label class="sm:col-span-3 text-xs font-semibold text-gray-500">Lote de la granja
                    <select name="lote_id" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                        @foreach ($lotes as $l)<option value="{{ $l->id }}">{{ $l->codigo }}{{ $l->galpon ? ' · '.$l->galpon : '' }}</option>@endforeach
                    </select></label>
            </template>
            <template x-if="origen === 'compra'">
                <div class="sm:col-span-3 grid sm:grid-cols-3 gap-3">
                    <label class="text-xs font-semibold text-gray-500">RUC o DNI del proveedor<input name="prov_num" value="{{ old('prov_num') }}" maxlength="15" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
                    <label class="sm:col-span-2 text-xs font-semibold text-gray-500">Proveedor<input name="prov_nom" value="{{ old('prov_nom') }}" maxlength="150" class="mt-1 block w-full h-10 rounded-xl border-gray-300 uppercase"></label>
                </div>
            </template>
        </div>

        <template x-if="origen === 'compra'">
            <div class="grid sm:grid-cols-6 gap-3">
                <label class="text-xs font-semibold text-gray-500">Comprobante<select name="tdocod" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                    <option value="00">Sin documento</option><option value="01">Factura</option><option value="03">Boleta</option><option value="12">Ticket</option><option value="13">Nota de venta</option></select></label>
                <label class="text-xs font-semibold text-gray-500">Serie<input name="serie" maxlength="4" class="mt-1 block w-full h-10 rounded-xl border-gray-300 uppercase"></label>
                <label class="text-xs font-semibold text-gray-500">Número<input name="numero" maxlength="8" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
                <label class="text-xs font-semibold text-gray-500">Costo S/ por kilo<input type="number" step="0.0001" min="0" name="costo_kg" x-model.number="costo" class="mt-1 block w-full h-10 rounded-xl border-sky-300 bg-sky-50 text-right font-bold"></label>
                <label class="text-xs font-semibold text-gray-500">IGV<select name="tip_igv" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                    <option value="20">Exonerado (pollo vivo)</option><option value="10">Gravado</option><option value="30">Inafecto</option></select></label>
                <label class="flex items-center gap-2 text-sm text-gray-700 mt-5"><input type="checkbox" name="credito" value="1" class="rounded"> Al crédito</label>
            </div>
        </template>

        <div>
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-bold text-gray-700">Pesadas</p>
                <button type="button" @click="pesadas.push({ jabas: pesadas.at(-1).jabas, aves: pesadas.at(-1).aves, peso: null })" class="h-8 px-3 rounded-lg bg-sky-600 text-white text-xs font-bold">+ Pesada</button>
            </div>
            <template x-for="(p, i) in pesadas" :key="i">
                <div class="grid grid-cols-12 gap-2 mb-1.5">
                    <input type="number" min="0" :name="`pesadas[${i}][jabas]`" x-model.number="p.jabas" @input="p.aves = p.jabas * avesJaba" placeholder="Jabas" class="col-span-3 h-10 rounded-lg border-gray-300 text-center">
                    <input type="number" min="0" :name="`pesadas[${i}][aves]`" x-model.number="p.aves" placeholder="Aves" class="col-span-3 h-10 rounded-lg border-gray-300 text-center">
                    <input type="number" step="0.001" min="0" :name="`pesadas[${i}][peso]`" x-model.number="p.peso" placeholder="Peso bruto kg" class="col-span-5 h-10 rounded-lg border-gray-300 text-right font-bold">
                    <button type="button" @click="pesadas.splice(i, 1)" x-show="pesadas.length > 1" class="col-span-1 text-gray-300 hover:text-rose-600">✕</button>
                </div>
            </template>
        </div>
        <div class="grid sm:grid-cols-4 gap-3 items-end">
            <label class="text-xs font-semibold text-rose-700">Llegaron muertas (aves)<input type="number" min="0" name="aves_muertas" class="mt-1 block w-full h-10 rounded-xl border-rose-200 text-center"></label>
            <label class="text-xs font-semibold text-rose-700">Peso de las muertas (kg)<input type="number" step="0.001" min="0" name="peso_muertas" x-model.number="muertas" class="mt-1 block w-full h-10 rounded-xl border-rose-200 text-right"></label>
            <div class="rounded-xl bg-sky-50 p-2 text-center"><p class="text-[11px] text-sky-700">Peso neto que entra</p><p class="text-xl font-extrabold text-sky-800" x-text="neto.toFixed(3) + ' kg'"></p></div>
            <div class="rounded-xl bg-gray-50 p-2 text-center" x-show="origen === 'compra'"><p class="text-[11px] text-gray-500">Total</p><p class="text-xl font-extrabold" x-text="'S/ ' + (neto * (costo || 0)).toFixed(2)"></p></div>
        </div>
        <p class="text-xs text-gray-400">Comprado: se registra como una compra (sube el stock, el costo y, si es al crédito, la cuenta por pagar). De la granja: entra con el costo por kilo del lote. Las aves que llegan muertas no se pagan y quedan en Mermas.</p>
        <button class="h-11 px-6 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold">Registrar ingreso</button>
    </form>

    <form class="flex flex-wrap items-end gap-2 mb-3">
        <label class="text-xs text-gray-500">Desde<input type="date" name="desde" value="{{ $desde->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-xs text-gray-500">Hasta<input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="h-9 px-3 rounded-lg bg-gray-800 text-white text-sm font-semibold">Ver</button>
    </form>
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">De</th><th class="px-3 py-2 text-left">Producto</th>
                <th class="px-3 py-2 text-right">Jabas</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Muertas</th><th class="px-3 py-2 text-right">Neto kg</th><th class="px-3 py-2 text-right">S/ kg</th><th class="px-3 py-2 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($ingresos as $i)
                    <tr><td class="px-3 py-2 text-gray-500">{{ \Carbon\Carbon::parse($i->fecha)->format('d/m H:i') }}</td><td class="px-3 py-2">{{ $i->proveedor }}</td><td class="px-3 py-2">{{ $i->pronom }}</td>
                        <td class="px-3 py-2 text-right">{{ $i->jabas }}</td><td class="px-3 py-2 text-right">{{ $i->aves }}</td>
                        <td class="px-3 py-2 text-right {{ $i->aves_muertas ? 'text-rose-600 font-semibold' : 'text-gray-300' }}">{{ $i->aves_muertas }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $i->peso_neto, 2) }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $i->costo_kg, 2) }}</td>
                        <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $i->total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">Sin ingresos en estas fechas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
