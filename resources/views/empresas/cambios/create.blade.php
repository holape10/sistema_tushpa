@extends('layouts.app')
@section('title', 'Cambio de producto')

@section('content')
{{-- Cambio de producto: "cambio sí, devolución no". Devuelve algo de la venta y se lleva otro de igual o mayor valor. --}}
<div class="max-w-5xl mx-auto space-y-4">
    <section class="rounded-3xl bg-gradient-to-br from-sky-600 to-indigo-700 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">🔄 Cambio de producto</h1>
        <p class="mt-1 text-white/90">El cliente devuelve algo de su compra y se lleva otro producto de <b>igual o mayor valor</b> (en un cambio no se devuelve dinero).
            Lo devuelto vuelve al stock; si lo nuevo cuesta más, se cobra solo la diferencia con su comprobante. La venta original no cambia.</p>
    </section>

    @if ($error)
        <div class="rounded-2xl bg-rose-50 ring-1 ring-rose-200 text-rose-800 p-5">{{ $error }}
            <a href="{{ route('ventas.index') }}" class="block mt-2 font-bold underline">← Volver al Panel de Ventas</a></div>
    @else
    <div x-data="cambioProducto(@js($lineas->map(fn ($l) => ['id' => (int) $l->IdCpe_detalle, 'nombre' => $l->cdedes, 'vendido' => (float) $l->vendido,
            'queda' => max((float) $l->queda, 0), 'precio' => (float) $l->precio, 'devuelve' => 0])->values()))" class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-600">
            Venta <b class="text-gray-800">{{ $venta->serdoc }}-{{ $venta->numdoc }}</b> del {{ date('d/m/Y', strtotime($venta->ccafem)) }} · {{ $venta->ccanom }}
            · Total S/ {{ number_format((float) $venta->ccaitv, 2) }}
        </div>

        {{-- 1. Lo que devuelve --}}
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <h2 class="px-4 pt-4 font-extrabold text-gray-800">1. ¿Qué devuelve el cliente?</h2>
            <table class="w-full text-sm mt-2">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr><th class="px-4 py-2 text-left">Producto</th><th class="px-2 py-2 text-right">Compró</th><th class="px-2 py-2 text-right">Precio pagado</th><th class="px-2 py-2 w-32">Devuelve</th></tr>
                </thead>
                <tbody>
                    <template x-for="l in lineas" :key="l.id">
                        <tr class="border-t border-gray-100" :class="l.devuelve > 0 && 'bg-sky-50'">
                            <td class="px-4 py-2 font-semibold text-gray-800" x-text="l.nombre"></td>
                            <td class="px-2 py-2 text-right" x-text="num(l.vendido) + (l.queda < l.vendido ? ' (quedan ' + num(l.queda) + ')' : '')"></td>
                            <td class="px-2 py-2 text-right" x-text="soles(l.precio)"></td>
                            <td class="px-2 py-2"><input type="number" step="any" min="0" :max="l.queda" x-model.number="l.devuelve" :disabled="l.queda <= 0"
                                       class="w-full h-9 rounded-lg border-gray-300 text-right font-bold disabled:bg-gray-100"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p class="px-4 py-3 text-right text-sm">Devuelve: <b x-text="soles(devuelto())"></b></p>
        </section>

        {{-- 2. Lo que se lleva --}}
        <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
            <h2 class="font-extrabold text-gray-800">2. ¿Qué se lleva a cambio?</h2>
            <div class="relative">
                <input type="search" x-model="busqueda" @input.debounce.250ms="buscar()" placeholder="Busca el producto (nombre o código)…" class="w-full h-11 rounded-xl border-gray-300">
                <div x-show="resultados.length" x-cloak class="absolute z-20 left-0 right-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-gray-200 max-h-72 overflow-y-auto">
                    <template x-for="p in resultados" :key="p.id">
                        <button type="button" @click="agregar(p)" class="w-full flex justify-between gap-3 px-4 py-2 text-left hover:bg-indigo-50 border-b border-gray-100 last:border-0">
                            <span><b x-text="p.nombre"></b> <span class="text-xs text-gray-400" x-show="p.stock !== null" x-text="'· Stock ' + num(p.stock)"></span></span>
                            <b x-text="soles(p.precio)"></b></button>
                    </template>
                </div>
            </div>
            <template x-for="(n, i) in lleva" :key="n.id">
                <div class="flex items-center gap-3 rounded-xl bg-emerald-50 px-3 py-2 text-sm">
                    <span class="flex-1 font-semibold text-gray-800" x-text="n.nombre"></span>
                    <input type="number" step="any" min="0.001" x-model.number="n.cantidad" class="w-24 h-9 rounded-lg border-gray-300 text-right font-bold">
                    <span class="w-24 text-right" x-text="soles(n.cantidad * n.precio)"></span>
                    <button type="button" @click="lleva.splice(i, 1)" class="text-gray-400 hover:text-rose-600">✕</button>
                </div>
            </template>
            <p class="text-right text-sm">Se lleva: <b x-text="soles(llevaTotal())"></b></p>
        </section>

        {{-- 3. La diferencia --}}
        <section class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-3 gap-3 items-end">
            <div class="sm:col-span-1">
                <p class="text-sm text-gray-500">Diferencia a cobrar</p>
                <p class="text-3xl font-black" :class="diferencia() < 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="soles(Math.max(diferencia(), 0))"></p>
                <p x-show="diferencia() < 0" class="text-xs font-bold text-rose-600">Lo que se lleva vale menos: debe elegir algo de igual o mayor valor.</p>
            </div>
            <label class="text-xs font-semibold text-gray-500" x-show="diferencia() > 0">Paga la diferencia con
                <select x-model.number="medio" class="block w-full mt-1 h-10 rounded-xl border-gray-300 text-sm">
                    @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                </select></label>
            <label class="text-xs font-semibold text-gray-500">Motivo (opcional)
                <input x-model="motivo" maxlength="150" placeholder="Ej.: no le quedó la talla" class="block w-full mt-1 h-10 rounded-xl border-gray-300 text-sm"></label>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="hacer()" :disabled="enviando || devuelto() <= 0 || !lleva.length || diferencia() < 0"
                    class="h-12 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-black disabled:opacity-40"
                    x-text="enviando ? 'Registrando…' : (diferencia() > 0 ? '✓ Hacer el cambio y cobrar ' + soles(diferencia()) : '✓ Hacer el cambio')"></button>
            <a href="{{ route('ventas.index') }}" class="h-12 px-5 inline-flex items-center rounded-xl bg-gray-100 text-gray-700 font-bold">Cancelar</a>
        </div>
    </div>

    <script>
        function cambioProducto(lineas) {
            return {
                lineas, lleva: [], busqueda: '', resultados: [], medio: @js(optional($medios->first())->id_med_pag), motivo: '', enviando: false,
                soles(n) { return 'S/ ' + (Number(n) || 0).toFixed(2); },
                num(n) { return String(Math.round(n * 1000) / 1000); },
                devuelto() { return Math.round(this.lineas.reduce((s, l) => s + (Number(l.devuelve) || 0) * l.precio, 0) * 100) / 100; },
                llevaTotal() { return Math.round(this.lleva.reduce((s, n) => s + (Number(n.cantidad) || 0) * n.precio, 0) * 100) / 100; },
                diferencia() { return Math.round((this.llevaTotal() - this.devuelto()) * 100) / 100; },
                async buscar() {
                    const q = this.busqueda.trim();
                    if (q.length < 2) { this.resultados = []; return; }
                    const r = await fetch(@js(route('pos.productos')) + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                    this.resultados = (await r.json()).slice(0, 15);
                },
                agregar(p) {
                    const ya = this.lleva.find(x => x.id === p.id);
                    if (ya) ya.cantidad = (Number(ya.cantidad) || 0) + 1; else this.lleva.push({ id: p.id, nombre: p.nombre, precio: Number(p.precio_normal ?? p.precio), cantidad: 1 });
                    this.busqueda = ''; this.resultados = [];
                },
                async hacer() {
                    this.enviando = true;
                    try {
                        const r = await fetch(@js(route('cambios.store', $venta->IdCpe_cabecera)), {
                            method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                            body: JSON.stringify({ devuelve: this.lineas.filter(l => l.devuelve > 0).map(l => ({ linea: l.id, cantidad: l.devuelve })),
                                lleva: this.lleva.map(n => ({ id: n.id, cantidad: n.cantidad })), medio: this.medio, motivo: this.motivo }),
                        });
                        const d = await r.json();
                        if (!d.ok) { tushpaAviso(d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo hacer el cambio.'), 'error'); return; }
                        if (d.ticket) window.open(d.ticket + '&imprimir=1', '_blank');
                        window.location.href = d.ver;
                    } catch (e) {
                        tushpaAviso('Sin conexión con el servidor.', 'error');
                    } finally {
                        this.enviando = false;
                    }
                },
            };
        }
    </script>
    @endif
</div>
@endsection
