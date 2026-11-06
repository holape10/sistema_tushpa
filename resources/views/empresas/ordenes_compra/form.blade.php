@extends('layouts.app')
@section('title', $orden ? 'Editar '.$orden['codigo'] : 'Nueva orden de compra')
@section('content')
<div x-data="ordenCompra" class="max-w-6xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <a href="{{ route('ordenes_compra.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">← Órdenes de compra</a>
            <h1 class="text-xl font-extrabold text-gray-800">{{ $orden ? 'Editar '.$orden['codigo'] : 'Nueva orden de compra' }}</h1>
        </div>
        <button type="button" @click="guardar()" :disabled="guardando" class="h-10 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50"
                x-text="guardando ? 'Guardando…' : 'Guardar orden'"></button>
    </div>
    <p x-show="error" x-cloak x-text="error" class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm"></p>

    <section class="bg-white rounded-2xl shadow-sm p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-xs font-semibold text-gray-600">RUC o DNI del proveedor *
            <div class="mt-1 flex gap-2">
                <input x-model="d.prov_num" @keydown.enter.prevent="buscarProveedor()" @change="buscarProveedor()" maxlength="11" inputmode="numeric" class="flex-1 min-w-0 h-10 rounded-xl border-gray-300 text-sm">
                <button type="button" @click="buscarProveedor()" class="h-10 w-10 rounded-xl bg-indigo-600 text-white" aria-label="Buscar proveedor">🔍</button>
            </div></label>
        <label class="text-xs font-semibold text-gray-600 lg:col-span-3">Proveedor *
            <input x-model="d.prov_nom" maxlength="255" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm uppercase"></label>
        <label class="text-xs font-semibold text-gray-600">Fecha *
            <input type="date" x-model="d.fecha" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
        <label class="text-xs font-semibold text-gray-600">Entrega esperada
            <input type="date" x-model="d.fecha_entrega" :min="d.fecha" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
        <label class="text-xs font-semibold text-gray-600">Moneda
            <select x-model="d.moneda" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"><option value="PEN">Soles</option><option value="USD">Dólares</option></select></label>
        <label class="text-xs font-semibold text-gray-600" x-show="d.moneda === 'USD'" x-cloak>Tipo de cambio
            <input type="number" step="0.001" x-model.number="d.tip_cam" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
        <label class="text-xs font-semibold text-gray-600">Llega al almacén
            <select x-model="d.id_almacen" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
            </select></label>
        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Condiciones (pago, entrega)
            <input x-model="d.condiciones" maxlength="255" placeholder="Ej.: crédito 30 días, puesto en almacén" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
        <label class="text-xs font-semibold text-gray-600 sm:col-span-2 lg:col-span-4">Observaciones
            <input x-model="d.observaciones" maxlength="500" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
    </section>

    <section class="bg-white rounded-2xl shadow-sm">
        <div class="p-4 border-b border-gray-100 relative">
            <input type="search" x-model="busqueda" @input.debounce.250ms="buscar()" @keydown.escape="resultados = []" placeholder="Busca el producto por nombre o código…" autocomplete="off"
                   class="w-full h-11 rounded-xl border-gray-300 text-sm">
            <div x-show="resultados.length" x-cloak class="absolute left-4 right-4 top-16 z-20 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 max-h-80 overflow-y-auto">
                <template x-for="p in resultados" :key="p.id">
                    <button type="button" @click="agregar(p)" class="w-full flex items-center gap-3 px-4 py-2.5 text-left border-b border-gray-100 last:border-0 hover:bg-indigo-50">
                        <span class="flex-1 min-w-0"><span class="font-semibold" x-text="p.nombre"></span> <span class="text-xs text-gray-400" x-text="p.codigo"></span></span>
                        <span class="text-xs text-gray-500" x-text="'Stock ' + num(p.stock) + ' · último costo ' + num2(p.costo)"></span>
                    </button>
                </template>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-2">Producto</th><th class="px-2 py-2 w-28">Cantidad</th><th class="px-2 py-2 w-32">Costo unit. (con IGV)</th>
                        <th class="px-2 py-2 w-36">IGV</th><th class="text-right px-4 py-2 w-28">Total</th><th class="w-10"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(it, i) in items" :key="it.id">
                        <tr>
                            <td class="px-4 py-2"><span class="font-semibold" x-text="it.nombre"></span> <span class="text-xs text-gray-400" x-text="it.unidad"></span></td>
                            <td class="px-2 py-2"><input type="number" step="0.001" min="0" x-model.number="it.cantidad" class="w-full h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                            <td class="px-2 py-2"><input type="number" step="0.01" min="0" x-model.number="it.costo" class="w-full h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                            <td class="px-2 py-2"><select x-model="it.tip_igv" class="w-full h-9 rounded-lg border-gray-300 text-xs"><option value="10">Gravado</option><option value="20">Exonerado</option><option value="30">Inafecto</option></select></td>
                            <td class="px-4 py-2 text-right font-semibold" x-text="num2(it.cantidad * it.costo)"></td>
                            <td class="px-2 py-2"><button type="button" @click="items.splice(i, 1)" class="text-gray-300 hover:text-rose-600" aria-label="Quitar">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p x-show="!items.length" class="py-10 text-center text-gray-400 text-sm">Busca y agrega los productos que vas a pedir.</p>
        </div>
        <div class="p-4 border-t border-gray-100 text-right">
            <span class="text-gray-500 mr-2">Total</span><span class="text-xl font-extrabold text-indigo-700" x-text="(d.moneda === 'USD' ? 'US$ ' : 'S/ ') + num2(total)"></span>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        const ORDEN = @json($orden);
        const RUTAS = {
            guardar: @json($orden ? route('ordenes_compra.update', $orden['id']) : route('ordenes_compra.store')),
            metodo: @json($orden ? 'PUT' : 'POST'),
            productos: @json(route('ordenes_compra.productos')),
            proveedor: @json(url('ordenes-compra/proveedor')),
        };
        const IGV = @json($tipIgvPred);
        Alpine.data('ordenCompra', () => ({
            d: ORDEN ? { ...ORDEN } : { fecha: @json(now()->toDateString()), fecha_entrega: '', prov_tdicod: '6', prov_num: '', prov_nom: '', moneda: 'PEN',
                tip_cam: null, id_almacen: @json($almacenes->first()?->id_almacen), condiciones: '', observaciones: '' },
            items: ORDEN ? ORDEN.items.map(i => ({ ...i })) : [],
            busqueda: '', resultados: [], guardando: false, error: '',
            get total() { return this.items.reduce((s, it) => s + (Number(it.cantidad) || 0) * (Number(it.costo) || 0), 0); },
            num(n) { return (Math.round((Number(n) || 0) * 1000) / 1000).toString(); },
            num2(n) { return (Number(n) || 0).toFixed(2); },
            async buscar() {
                if (this.busqueda.trim().length < 2) { this.resultados = []; return; }
                const r = await fetch(RUTAS.productos + '?q=' + encodeURIComponent(this.busqueda.trim()), { headers: { Accept: 'application/json' } });
                this.resultados = r.ok ? await r.json() : [];
            },
            agregar(p) {
                const ya = this.items.find(it => it.id === p.id);
                if (ya) { ya.cantidad = (Number(ya.cantidad) || 0) + 1; }
                else { this.items.push({ id: p.id, codigo: p.codigo, nombre: p.nombre, unidad: p.unidad, cantidad: 1, costo: p.costo, tip_igv: IGV }); }
                this.busqueda = ''; this.resultados = [];
            },
            async buscarProveedor() {
                const doc = (this.d.prov_num || '').replace(/\D/g, '');
                if (![8, 11].includes(doc.length)) return;
                const r = await fetch(RUTAS.proveedor + '/' + doc, { headers: { Accept: 'application/json' } });
                const j = r.ok ? await r.json() : {};
                if (j.nom) { this.d.prov_nom = j.nom; this.d.prov_tdicod = j.tdicod || (doc.length === 11 ? '6' : '1'); }
                else if (!this.d.prov_nom) { this.error = j.error || 'No se encontró el proveedor: escribe su nombre.'; }
            },
            async guardar() {
                this.error = '';
                if (!this.items.length) { this.error = 'Agrega al menos un producto.'; return; }
                this.guardando = true;
                try {
                    const r = await fetch(RUTAS.guardar, {
                        method: RUTAS.metodo,
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ ...this.d, prov_tdicod: this.d.prov_tdicod || ((this.d.prov_num || '').length === 11 ? '6' : '1'),
                            items: this.items.map(it => ({ id: it.id, cantidad: it.cantidad, costo: it.costo, tip_igv: it.tip_igv })) }),
                    });
                    const j = await r.json();
                    if (r.status === 422) { this.error = Object.values(j.errors)[0][0]; return; }
                    if (j.estado !== 'success') { this.error = j.mensaje || 'No se pudo guardar.'; return; }
                    window.location.href = j.redirect;
                } catch (e) {
                    this.error = 'Error de conexión con el servidor.';
                } finally {
                    this.guardando = false;
                }
            },
        }));
    });
</script>
@endpush
