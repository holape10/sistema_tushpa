@extends('layouts.app')
@section('title', 'Nueva Nota de Crédito / Débito')
@section('content')
    @include('empresas.partials.alert')
    @if (session('error'))<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">{{ session('error') }}</div>@endif

    {{-- 1. Buscar el comprobante --}}
    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4">
        <label class="text-sm font-semibold text-gray-700">Factura o boleta a modificar</label>
        <div class="flex flex-col sm:flex-row gap-2 mt-1">
            <input name="doc" value="{{ request('doc', $ref ? $ref->serdoc . '-' . $ref->numdoc : '') }}" placeholder="Ej. F001-125 o B001-3480" required
                   class="flex-1 rounded-lg border-gray-300 text-lg font-semibold uppercase" autofocus>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Buscar</button>
            <a href="{{ route('notas.index') }}" class="px-5 py-2 rounded-xl bg-gray-200 text-gray-700 font-semibold text-center">Volver</a>
        </div>
        <p class="text-xs text-gray-400 mt-1">También puedes llegar aquí con el botón <strong>Nota de crédito</strong> del Panel de ventas.</p>
    </form>

    @if ($error)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">{{ $error }}</div>
    @endif

    @if ($ref)
        @php
            $numRef = $ref->serdoc . '-' . str_pad($ref->numdoc, 8, '0', STR_PAD_LEFT);
            $saldo = round((float) $ref->ccaitv - $usado['total'], 2);
            $letra = $ref->serdoc[0] === 'F' ? 'F' : 'B';
            $proxNc = $sucursal->{'SerNC' . $letra} . '-' . str_pad($sucursal->{'NumNC' . $letra} + 1, 8, '0', STR_PAD_LEFT);
            $proxNd = $sucursal->{'SerND' . $letra} . '-' . str_pad($sucursal->{'NumND' . $letra} + 1, 8, '0', STR_PAD_LEFT);
        @endphp

        {{-- 2. Datos del comprobante --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
            <div><p class="text-xs text-gray-400 uppercase">{{ $ref->tdocod === '01' ? 'Factura' : 'Boleta' }}</p><p class="font-bold text-gray-800">{{ $numRef }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Emisión</p><p class="font-semibold">{{ \Carbon\Carbon::parse($ref->ccafem)->format('d/m/Y') }}</p></div>
            <div class="col-span-2 md:col-span-1"><p class="text-xs text-gray-400 uppercase">Cliente</p><p class="font-semibold truncate">{{ $ref->ccanom }}</p><p class="text-xs text-gray-400">{{ $ref->ccandi }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Total</p><p class="font-bold">S/ {{ number_format($ref->ccaitv, 2) }}</p></div>
            <div><p class="text-xs text-gray-400 uppercase">Saldo para N. crédito</p><p class="font-bold text-rose-600">S/ {{ number_format($saldo, 2) }}</p></div>
            <div class="col-span-2 md:col-span-5 flex flex-wrap items-center gap-2">
                <span class="text-xs text-gray-400 uppercase">SUNAT</span> @include('empresas.sunat._estado', ['estado' => $ref->est_sunat])
                @foreach ($notasPrevias as $np)
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $np->tdocod === '07' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $np->tdocod === '07' ? 'NC' : 'ND' }} {{ $np->serdoc }}-{{ $np->numdoc }} · S/ {{ number_format($np->ccaitv, 2) }}</span>
                @endforeach
            </div>
        </div>

        @if (!$error)
            {{-- 3. La nota --}}
            <form method="POST" action="{{ route('notas.store') }}" class="bg-white rounded-2xl shadow-sm p-4"
                  x-data="nota(@js($lineas), @js($saldo), @js(old('tdocod', '07')), @js(old('tipnot', '01')), {{ $usado['notas'] ? 'true' : 'false' }})"
                  @submit="enviar($event)">
                @csrf
                <input type="hidden" name="ref" value="{{ $ref->IdCpe_cabecera }}">
                <input type="hidden" name="tdocod" :value="tdocod">

                <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-xl mb-4 max-w-md">
                    <button type="button" @click="cambiarTipo('07')" :class="tdocod === '07' ? 'bg-white text-rose-700 shadow' : 'text-gray-500'" class="h-11 rounded-lg font-bold text-sm">
                        Nota de crédito <span class="block text-[10px] font-normal">{{ $proxNc }}</span></button>
                    <button type="button" @click="cambiarTipo('08')" :class="tdocod === '08' ? 'bg-white text-amber-700 shadow' : 'text-gray-500'" class="h-11 rounded-lg font-bold text-sm">
                        Nota de débito <span class="block text-[10px] font-normal">{{ $proxNd }}</span></button>
                </div>

                <div class="grid md:grid-cols-2 gap-3 mb-4">
                    <label class="text-sm">Tipo de nota (motivo SUNAT)
                        <select x-show="tdocod === '07'" :name="tdocod === '07' ? 'tipnot' : ''" x-model="tipnot" @change="prepararLineas()" class="block w-full rounded-lg border-gray-300 text-sm font-semibold">
                            @foreach ($motivosNc as $cod => $des)
                                <option value="{{ $cod }}" @if (in_array($cod, \App\Support\Notas::MOTIVOS_TOTALES)) :disabled="tieneNotas" @endif>{{ $cod }} - {{ $des }}</option>
                            @endforeach
                        </select>
                        <select x-show="tdocod === '08'" x-cloak :name="tdocod === '08' ? 'tipnot' : ''" x-model="tipnot" @change="prepararLineas()" class="block w-full rounded-lg border-gray-300 text-sm font-semibold">
                            @foreach ($motivosNd as $cod => $des)<option value="{{ $cod }}">{{ $cod }} - {{ $des }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm">Sustento (se envía a SUNAT)
                        <input name="motivo" value="{{ old('motivo') }}" required minlength="5" maxlength="100" placeholder="Ej. Cliente devolvió la mercadería" class="block w-full rounded-lg border-gray-300 text-sm">
                    </label>
                </div>

                <div class="rounded-xl px-4 py-2 mb-3 text-sm" :class="tdocod === '07' ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800'" x-text="ayuda"></div>

                {{-- Líneas --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">Descripción</th>
                                <th class="px-3 py-2 text-right" x-show="modo === 'devolucion'">Vendido</th>
                                <th class="px-3 py-2 w-28">Cantidad</th>
                                <th class="px-3 py-2 w-32" x-text="modo === 'descuento_item' ? 'Descuento' : 'Precio unit.'"></th>
                                <th class="px-3 py-2 text-right w-28">Total</th>
                                <th class="w-8" x-show="modo === 'libre'"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(it, i) in items" :key="i">
                                <tr>
                                    <td class="px-3 py-1.5">
                                        <input type="hidden" :name="`items[${i}][IdCpe_detalle]`" :value="it.ref || ''">
                                        <template x-if="modo === 'libre'">
                                            <input :name="`items[${i}][descripcion]`" x-model="it.descripcion" maxlength="150" required class="w-full rounded-lg border-gray-300 text-sm uppercase">
                                        </template>
                                        <template x-if="modo !== 'libre'">
                                            <div><input type="hidden" :name="`items[${i}][descripcion]`" :value="it.descripcion">
                                                <span class="font-semibold text-gray-700" x-text="it.descripcion"></span>
                                                <span x-show="it.lotes" class="block text-[11px] text-teal-700" x-text="'Lote: ' + it.lotes"></span></div>
                                        </template>
                                    </td>
                                    <td class="px-3 py-1.5 text-right text-xs text-gray-500" x-show="modo === 'devolucion'"
                                        x-text="num(it.vendido) + (it.devuelto ? ' (devuelto ' + num(it.devuelto) + ')' : '')"></td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" step="any" min="0" :max="modo === 'devolucion' ? it.vendido - it.devuelto : null"
                                               :name="`items[${i}][cantidad]`" x-model.number="it.cantidad" :readonly="modo === 'total' || modo === 'descuento_item'"
                                               class="w-full rounded-lg border-gray-300 text-sm text-right read-only:bg-gray-50">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" step="0.01" min="0" :name="`items[${i}][precio]`" x-model.number="it.precio"
                                               :readonly="modo === 'total' || modo === 'devolucion'" class="w-full rounded-lg border-gray-300 text-sm text-right read-only:bg-gray-50">
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-semibold" x-text="num2(r2(it.cantidad * it.precio))"></td>
                                    <td x-show="modo === 'libre'" class="text-center"><button type="button" @click="items.splice(i, 1)" class="text-rose-500 font-bold">✕</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" x-show="modo === 'libre'" @click="items.push({ ref: null, descripcion: '', cantidad: 1, precio: 0 })"
                        class="mt-2 px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">+ Agregar línea</button>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4 pt-4 border-t border-gray-100">
                    <p class="text-sm text-gray-600">Total de la nota:
                        <strong class="text-2xl" :class="tdocod === '07' ? 'text-rose-600' : 'text-amber-600'" x-text="'S/ ' + num2(total)"></strong>
                        <span x-show="tdocod === '07' && total > saldo + 0.009" class="block text-xs text-rose-600 font-semibold">Supera el saldo del comprobante (S/ <span x-text="num2(saldo)"></span>)</span>
                    </p>
                    <button class="w-full sm:w-auto px-8 py-3 rounded-xl text-white font-bold" :class="tdocod === '07' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700'"
                            x-text="tdocod === '07' ? 'EMITIR NOTA DE CRÉDITO' : 'EMITIR NOTA DE DÉBITO'"></button>
                </div>
            </form>
        @endif
    @endif

    <script>
        function nota(lineas, saldo, tdocod, tipnot, tieneNotas) {
            const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
            return {
                lineas, saldo, tdocod, tipnot, tieneNotas, items: [],
                r2,
                num(n) { return String(r2(n)); },
                num2(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                init() {
                    if (this.tieneNotas && ['01', '02', '06'].includes(this.tipnot) && this.tdocod === '07') this.tipnot = '07';
                    this.prepararLineas();
                },
                get modo() {
                    if (this.tdocod === '08') return 'libre';
                    if (['01', '02', '06'].includes(this.tipnot)) return 'total';
                    if (this.tipnot === '07') return 'devolucion';
                    if (this.tipnot === '05') return 'descuento_item';
                    return 'libre'; // 04 descuento global, 09 disminución en el valor
                },
                get ayuda() {
                    return {
                        total: 'Anula todo el comprobante: el stock vuelve al almacén, se anula la cuenta por cobrar y el comprobante queda marcado como anulado.',
                        devolucion: 'Escribe cuánto devuelve el cliente de cada producto. Ese stock vuelve al almacén (al mismo lote).',
                        descuento_item: 'Escribe el descuento (importe total con IGV) de cada producto. No mueve stock.',
                        libre: this.tdocod === '08'
                            ? 'La nota de débito AUMENTA el importe del comprobante (intereses, penalidades, aumento de valor).'
                            : 'Escribe el importe a rebajar (con IGV). No mueve stock.',
                    }[this.modo];
                },
                get total() { return r2(this.items.reduce((s, it) => s + r2(it.cantidad * it.precio), 0)); },
                cambiarTipo(t) {
                    this.tdocod = t;
                    this.tipnot = t === '07' ? (this.tieneNotas ? '07' : '01') : '01';
                    this.prepararLineas();
                },
                prepararLineas() {
                    const m = this.modo;
                    if (m === 'total') {
                        this.items = this.lineas.map(l => ({ ref: l.id, descripcion: l.descripcion, cantidad: l.cantidad, precio: l.precio, lotes: l.lotes }));
                    } else if (m === 'devolucion') {
                        this.items = this.lineas.filter(l => l.producto && l.cantidad - l.devuelto > 0)
                            .map(l => ({ ref: l.id, descripcion: l.descripcion, cantidad: 0, precio: l.precio, vendido: l.cantidad, devuelto: l.devuelto, lotes: l.lotes }));
                    } else if (m === 'descuento_item') {
                        this.items = this.lineas.map(l => ({ ref: l.id, descripcion: 'DESCUENTO: ' + l.descripcion, cantidad: 1, precio: 0 }));
                    } else {
                        const desc = this.tdocod === '08'
                            ? { '01': 'INTERESES POR MORA', '02': 'AUMENTO EN EL VALOR', '03': 'PENALIDAD' }[this.tipnot]
                            : (this.tipnot === '04' ? 'DESCUENTO GLOBAL' : 'DISMINUCION EN EL VALOR');
                        this.items = [{ ref: null, descripcion: desc, cantidad: 1, precio: 0 }];
                    }
                },
                enviar(e) {
                    if (!(this.total > 0)) { e.preventDefault(); alert('La nota debe tener un importe mayor a 0.'); return; }
                    if (this.tdocod === '07' && this.total > this.saldo + 0.009) { e.preventDefault(); alert('La nota de crédito supera el saldo del comprobante.'); return; }
                    const tipo = this.tdocod === '07' ? 'nota de crédito' : 'nota de débito';
                    if (!confirm(`Se emitirá la ${tipo} por S/ ${this.num2(this.total)}. Es un comprobante electrónico y no se puede borrar. ¿Continuar?`)) e.preventDefault();
                },
            };
        }
    </script>
@endsection
