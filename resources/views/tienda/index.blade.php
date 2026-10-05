@extends('tienda.layout')
@section('titulo', 'Tienda virtual')

@section('acciones')
    <button type="button" x-data @click="$dispatch('abrir-carrito')" class="relative h-10 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold flex items-center gap-1.5">
        🛒 <span class="hidden sm:inline">Carrito</span>
        <span x-data x-text="$store.carrito.unidades" x-show="$store.carrito.unidades > 0" x-cloak
              class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-rose-500 text-[11px] flex items-center justify-center"></span>
    </button>
@endsection

@section('contenido')
<div x-data="tienda()" x-init="iniciar()" @abrir-carrito.window="abierto = true" class="max-w-6xl mx-auto px-4 py-5">

    {{-- Buscador y categorías --}}
    <div class="relative">
        <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
        <input type="search" x-model="busqueda" placeholder="¿Qué estás buscando?" class="w-full h-12 pl-12 pr-4 rounded-2xl border-slate-200 shadow-sm focus:border-indigo-400 focus:ring-indigo-300">
    </div>
    <div class="flex gap-2 overflow-x-auto py-3 -mx-4 px-4">
        <button type="button" @click="cat = null" :class="cat === null ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                class="shrink-0 h-9 px-4 rounded-full text-sm font-semibold">Todo</button>
        <template x-for="c in categorias" :key="c.cat_id">
            <button type="button" @click="cat = c.cat_id" :class="cat === c.cat_id ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                    class="shrink-0 h-9 px-4 rounded-full text-sm font-semibold whitespace-nowrap" x-text="c.cat_nom"></button>
        </template>
    </div>

    {{-- Productos --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
        <template x-for="p in visibles" :key="p.id">
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden flex flex-col border border-slate-100">
                <div class="aspect-square bg-gradient-to-br from-slate-100 to-slate-200 relative">
                    <template x-if="p.img"><img :src="p.img" :alt="p.nombre" class="absolute inset-0 w-full h-full object-cover" loading="lazy"></template>
                    <template x-if="!p.img"><span class="absolute inset-0 flex items-center justify-center text-4xl font-black text-slate-300" x-text="p.nombre.slice(0, 2)"></span></template>
                    <span x-show="agotado(p)" class="absolute top-2 left-2 px-2 py-0.5 rounded-lg bg-slate-900/80 text-white text-[11px] font-bold">AGOTADO</span>
                    <span x-show="p.precio < p.normal" class="absolute top-2 right-2 px-2 py-0.5 rounded-lg bg-rose-500 text-white text-[11px] font-bold">OFERTA</span>
                </div>
                <div class="p-3 flex-1 flex flex-col">
                    <p class="text-sm font-semibold leading-snug line-clamp-2 flex-1" x-text="p.nombre"></p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-lg font-black text-indigo-700" x-text="soles(p.precio)"></span>
                        <span x-show="p.precio < p.normal" class="text-xs text-slate-400 line-through" x-text="soles(p.normal)"></span>
                    </div>
                    <template x-if="!enCarrito(p.id)">
                        <button type="button" @click="sumar(p, 1)" :disabled="agotado(p)"
                                class="mt-2 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:bg-slate-200 disabled:text-slate-400">Agregar</button>
                    </template>
                    <template x-if="enCarrito(p.id)">
                        <div class="mt-2 h-10 flex items-center justify-between rounded-xl bg-indigo-50">
                            <button type="button" @click="sumar(p, -1)" class="w-10 h-10 text-xl font-bold text-indigo-700">−</button>
                            <span class="font-extrabold text-indigo-800" x-text="enCarrito(p.id)"></span>
                            <button type="button" @click="sumar(p, 1)" class="w-10 h-10 text-xl font-bold text-indigo-700">+</button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
    <p x-show="!visibles.length" class="py-16 text-center text-slate-400">No encontramos productos.</p>

    {{-- Carrito y pedido --}}
    <div x-show="abierto" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="abierto = false"></div>
    <aside x-show="abierto" x-cloak x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl flex flex-col" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="flex items-center justify-between px-5 h-16 border-b border-slate-100">
            <h2 class="text-lg font-extrabold">Tu pedido</h2>
            <button type="button" @click="abierto = false" class="w-10 h-10 rounded-xl hover:bg-slate-100 text-2xl leading-none" aria-label="Cerrar">×</button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <p x-show="!$store.carrito.items.length" class="py-10 text-center text-slate-400">Tu carrito está vacío.</p>
            <template x-for="it in $store.carrito.items" :key="it.id">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-xl bg-slate-100 overflow-hidden shrink-0"><template x-if="it.img"><img :src="it.img" alt="" class="w-full h-full object-cover"></template></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold leading-tight line-clamp-2" x-text="it.nombre"></p>
                        <p class="text-xs text-slate-500" x-text="soles(it.precio) + ' c/u'"></p>
                    </div>
                    <div class="flex items-center bg-slate-100 rounded-xl">
                        <button type="button" @click="sumar(it, -1)" class="w-8 h-9 font-bold">−</button>
                        <span class="w-6 text-center text-sm font-bold" x-text="it.cantidad"></span>
                        <button type="button" @click="sumar(it, 1)" class="w-8 h-9 font-bold">+</button>
                    </div>
                </div>
            </template>

            <template x-if="$store.carrito.items.length">
                <div class="space-y-3 pt-3 border-t border-slate-100">
                    @if ($cliente)
                        <p class="text-sm">Pedido a nombre de <strong>{{ $cliente->clinom }}</strong></p>
                        <div class="grid grid-cols-2 gap-1 p-1 bg-slate-100 rounded-xl">
                            <template x-for="e in ['RECOJO EN TIENDA', 'DELIVERY']" :key="e">
                                <button type="button" @click="entrega = e" :class="entrega === e ? 'bg-white shadow text-indigo-700' : 'text-slate-500'"
                                        class="h-9 rounded-lg text-xs font-bold" x-text="e === 'DELIVERY' ? 'Delivery' : 'Recojo en tienda'"></button>
                            </template>
                        </div>
                        <input x-show="entrega === 'DELIVERY'" x-model="direccion" maxlength="150" placeholder="Dirección de entrega"
                               class="w-full h-11 rounded-xl border-slate-200 text-sm">
                        <input x-model="telefono" maxlength="20" inputmode="tel" placeholder="Celular de contacto" class="w-full h-11 rounded-xl border-slate-200 text-sm">
                        <select x-model="pago" class="w-full h-11 rounded-xl border-slate-200 text-sm">
                            <option value="EFECTIVO">Pago en efectivo</option>
                            <option value="YAPE / PLIN">Yape / Plin</option>
                            <option value="TRANSFERENCIA">Transferencia bancaria</option>
                            <option value="TARJETA">Tarjeta al recibir</option>
                        </select>
                        <input x-model="nota" maxlength="100" placeholder="Nota (opcional)" class="w-full h-11 rounded-xl border-slate-200 text-sm">
                        @if ($negocio->tienda_mensaje)
                            <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs px-3 py-2 whitespace-pre-line">{{ $negocio->tienda_mensaje }}</div>
                        @endif
                    @else
                        <div class="rounded-xl bg-indigo-50 text-indigo-900 text-sm px-4 py-3">
                            Para enviar tu pedido <a href="{{ route('tienda.login') }}" class="font-bold underline">ingresa</a> o
                            <a href="{{ route('tienda.registro') }}" class="font-bold underline">crea tu cuenta</a> con tu DNI o RUC. Tu carrito se guarda.
                        </div>
                    @endif
                </div>
            </template>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">
            <div class="flex items-end justify-between mb-3">
                <span class="text-sm font-semibold text-slate-500">Total</span>
                <span class="text-2xl font-black" x-text="soles($store.carrito.total)"></span>
            </div>
            @if ($cliente)
                <button type="button" @click="enviar()" :disabled="enviando || !$store.carrito.items.length"
                        class="w-full h-12 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold disabled:opacity-50"
                        x-text="enviando ? 'Enviando…' : 'Enviar pedido'"></button>
            @else
                <a href="{{ route('tienda.login') }}" class="w-full h-12 rounded-xl bg-indigo-600 text-white font-extrabold flex items-center justify-center">Ingresar para pedir</a>
            @endif
            <p x-show="error" x-cloak class="mt-2 text-sm text-rose-600" x-text="error"></p>
        </div>
    </aside>

    {{-- Pedido enviado --}}
    <div x-show="enviado" x-cloak class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-6 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl">✓</div>
            <p class="text-xl font-extrabold mt-3">¡Pedido enviado!</p>
            <p class="text-slate-500 text-sm mt-1">Número <strong x-text="enviado?.numero"></strong> · <span x-text="soles(enviado?.total)"></span></p>
            <p class="text-slate-500 text-sm mt-2">La tienda revisará tu pedido y se comunicará contigo.</p>
            <a x-show="enviado?.whatsapp" :href="enviado?.whatsapp" target="_blank" class="mt-4 h-12 rounded-xl bg-emerald-500 text-white font-bold flex items-center justify-center">Avisar por WhatsApp</a>
            <button type="button" @click="enviado = null" class="mt-2 w-full h-11 rounded-xl bg-slate-100 font-semibold">Seguir comprando</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const TIENDA = { productos: @json($productos), categorias: @json($categorias), ruta: @json(route('tienda.pedido')),
                     telefono: @json($cliente->telefono ?? ''), direccion: @json(($cliente->clidir ?? '--') !== '--' ? $cliente->clidir : '') };
    const CLAVE = 'tienda_carrito_' + location.host;
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;

    document.addEventListener('alpine:init', () => {
        // Carrito compartido entre la cabecera y la página (se guarda en el navegador)
        Alpine.store('carrito', {
            items: [],
            get unidades() { return this.items.reduce((s, i) => s + i.cantidad, 0); },
            get total() { return r2(this.items.reduce((s, i) => s + i.cantidad * i.precio, 0)); },
            guardar() { try { localStorage.setItem(CLAVE, JSON.stringify(this.items.map(({ id, cantidad }) => ({ id, cantidad })))); } catch (e) {} },
        });
    });

    function tienda() {
        return {
            productos: TIENDA.productos, categorias: TIENDA.categorias,
            busqueda: '', cat: null, abierto: false, enviando: false, error: '', enviado: null,
            entrega: 'RECOJO EN TIENDA', pago: 'EFECTIVO', direccion: TIENDA.direccion, telefono: TIENDA.telefono, nota: '',
            iniciar() {
                // Recupera el carrito con los precios actuales (y quita lo que ya no existe)
                try {
                    const guardado = JSON.parse(localStorage.getItem(CLAVE) || '[]');
                    Alpine.store('carrito').items = guardado.map(g => {
                        const p = this.productos.find(x => x.id === g.id);
                        return p ? { ...p, cantidad: Math.min(g.cantidad, p.stock ?? g.cantidad) } : null;
                    }).filter(i => i && i.cantidad > 0);
                } catch (e) {}
            },
            get visibles() {
                const q = this.busqueda.trim().toLowerCase();
                return this.productos.filter(p => (q ? p.nombre.toLowerCase().includes(q) : (this.cat === null || p.cat === this.cat)));
            },
            soles(n) { return 'S/ ' + (Number(n) || 0).toFixed(2); },
            agotado(p) { return p.stock !== null && p.stock <= 0; },
            enCarrito(id) { return Alpine.store('carrito').items.find(i => i.id === id)?.cantidad || 0; },
            sumar(p, d) {
                const c = Alpine.store('carrito');
                let it = c.items.find(i => i.id === p.id);
                if (!it && d > 0) { c.items.push({ ...p, cantidad: 0 }); it = c.items[c.items.length - 1]; }
                if (!it) return;
                const nueva = it.cantidad + d;
                if (p.stock !== null && nueva > p.stock) { this.error = 'No hay más stock de ' + p.nombre; return; }
                it.cantidad = nueva;
                if (it.cantidad <= 0) c.items = c.items.filter(i => i.id !== p.id);
                this.error = '';
                c.guardar();
            },
            async enviar() {
                this.error = '';
                if (!this.telefono.trim()) { this.error = 'Escribe tu celular de contacto.'; return; }
                if (this.entrega === 'DELIVERY' && !this.direccion.trim()) { this.error = 'Escribe la dirección de entrega.'; return; }
                this.enviando = true;
                try {
                    const c = Alpine.store('carrito');
                    const r = await fetch(TIENDA.ruta, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ items: c.items.map(i => ({ id: i.id, cantidad: i.cantidad })), entrega: this.entrega, pago: this.pago,
                                               direccion: this.direccion, telefono: this.telefono, nota: this.nota }),
                    });
                    if (r.status === 419) { this.error = 'La página caducó. Recárgala (tu carrito se guarda).'; return; }
                    const d = await r.json();
                    if (r.status === 422) { this.error = Object.values(d.errors)[0][0]; return; }
                    if (d.estado === 'login') { location.href = @json(route('tienda.login')); return; }
                    if (d.estado !== 'success') { this.error = d.mensaje || 'No se pudo enviar el pedido.'; return; }
                    c.items = []; c.guardar();
                    this.abierto = false; this.enviado = d;
                } catch (e) { this.error = 'Sin conexión. Intenta otra vez.'; }
                finally { this.enviando = false; }
            },
        };
    }
</script>
@endpush
