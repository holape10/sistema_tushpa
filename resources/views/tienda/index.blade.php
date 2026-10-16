@extends('tienda.layout')
@section('titulo', 'Tienda virtual')

@section('acciones')
    <button type="button" x-data @click="$dispatch('abrir-carrito')" class="relative h-10 px-3 rounded-xl bg-marca hover:opacity-90 text-white text-sm font-bold flex items-center gap-1.5">
        🛒 <span class="hidden sm:inline">Carrito</span>
        <span x-data x-text="$store.carrito.unidades" x-show="$store.carrito.unidades > 0" x-cloak
              class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-rose-500 text-[11px] flex items-center justify-center"></span>
    </button>
@endsection

@section('contenido')
@php $nombreTienda = $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Tienda'); @endphp
<div x-data="tienda()" x-init="iniciar()" @abrir-carrito.window="abierto = true" @keydown.escape.window="detalle = null; abierto = false">

    {{-- Portada --}}
    <section class="relative isolate overflow-hidden">
        @if ($negocio->tienda_banner)
            <img src="{{ asset($negocio->tienda_banner) }}" alt="" class="absolute inset-0 -z-10 w-full h-full object-cover">
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-black/75 via-black/45 to-black/10"></div>
        @else
            <div class="absolute inset-0 -z-10" style="background: radial-gradient(circle at 85% 20%, rgba(255,255,255,.18), transparent 40%), linear-gradient(135deg, var(--marca), #0f172a)"></div>
        @endif
        <div class="max-w-6xl mx-auto px-4 py-10 sm:py-16 text-white">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-white/70">Tienda en línea</p>
            <h1 class="mt-2 text-3xl sm:text-5xl font-black leading-tight max-w-2xl">{{ $nombreTienda }}</h1>
            <p class="mt-3 text-base sm:text-lg text-white/85 max-w-xl">{{ $negocio->tienda_eslogan ?: 'Elige tus productos, envía tu pedido y te confirmamos al instante.' }}</p>
            <div class="mt-5 flex flex-wrap gap-2 text-xs sm:text-sm font-semibold">
                <span class="px-3 py-1.5 rounded-full bg-white/15 backdrop-blur">🛵 Delivery</span>
                <span class="px-3 py-1.5 rounded-full bg-white/15 backdrop-blur">🏪 Recojo en tienda</span>
                <span class="px-3 py-1.5 rounded-full bg-white/15 backdrop-blur">📱 Yape · Plin · Efectivo</span>
                <span class="px-3 py-1.5 rounded-full bg-white/15 backdrop-blur">🧾 Boleta o factura</span>
            </div>
        </div>
    </section>

    {{-- Buscador, categorías y orden (quedan arriba al bajar) --}}
    <div class="sticky top-16 z-20 bg-slate-50/95 backdrop-blur border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 pt-3">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                    <input type="search" x-model="busqueda" placeholder="¿Qué estás buscando?" class="w-full h-11 pl-12 pr-4 rounded-2xl border-slate-200 bg-white shadow-sm focus:ring-marca focus:border-marca">
                </div>
                <select x-model="orden" class="h-11 rounded-2xl border-slate-200 bg-white text-sm shadow-sm" aria-label="Ordenar">
                    <option value="nombre">A - Z</option>
                    <option value="menor">Menor precio</option>
                    <option value="mayor">Mayor precio</option>
                    <option value="ofertas">Ofertas primero</option>
                </select>
            </div>
            <div class="flex gap-2 overflow-x-auto py-3 -mx-4 px-4 scroll-oculto">
                <button type="button" @click="cat = null" :class="cat === null ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                        class="shrink-0 h-9 px-4 rounded-full text-sm font-semibold">Todo <span class="opacity-60" x-text="productos.length"></span></button>
                <template x-for="c in categorias" :key="c.cat_id">
                    <button type="button" @click="cat = c.cat_id" :class="cat === c.cat_id ? 'bg-marca text-white' : 'bg-white text-slate-600 border border-slate-200'"
                            class="shrink-0 h-9 px-4 rounded-full text-sm font-semibold whitespace-nowrap" x-text="c.cat_nom"></button>
                </template>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-5">
        {{-- Ofertas del momento --}}
        <section x-show="ofertas.length && !busqueda && cat === null" class="mb-6">
            <h2 class="text-lg font-extrabold mb-3">🔥 Ofertas</h2>
            <div class="flex gap-3 overflow-x-auto pb-2 -mx-4 px-4 scroll-oculto snap-x">
                <template x-for="p in ofertas" :key="'o' + p.id">
                    <button type="button" @click="abrir(p)" class="snap-start shrink-0 w-40 sm:w-48 text-left bg-white rounded-2xl shadow-sm overflow-hidden border border-slate-100 hover:shadow-md transition">
                        <div class="aspect-square bg-slate-100 relative">
                            <template x-if="p.img"><img :src="p.img" :alt="p.nombre" class="absolute inset-0 w-full h-full object-cover" loading="lazy"></template>
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-lg bg-rose-500 text-white text-[11px] font-bold" x-text="'-' + descuento(p) + '%'"></span>
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-semibold line-clamp-1" x-text="p.nombre"></p>
                            <p class="mt-1"><span class="font-black text-rose-600" x-text="soles(p.precio)"></span> <span class="text-xs text-slate-400 line-through" x-text="soles(p.normal)"></span></p>
                        </div>
                    </button>
                </template>
            </div>
        </section>

        <div class="flex items-baseline justify-between mb-3">
            <h2 class="text-lg font-extrabold" x-text="busqueda ? 'Resultados' : (cat === null ? 'Todos los productos' : (categorias.find(c => c.cat_id === cat)?.cat_nom || ''))"></h2>
            <span class="text-sm text-slate-400" x-text="visibles.length + ' producto(s)'"></span>
        </div>

        {{-- Productos --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
            <template x-for="p in visibles" :key="p.id">
                <div class="group bg-white rounded-2xl shadow-sm overflow-hidden flex flex-col border border-slate-100 hover:shadow-lg hover:-translate-y-0.5 transition">
                    <button type="button" @click="abrir(p)" class="aspect-square bg-gradient-to-br from-slate-100 to-slate-200 relative overflow-hidden" :aria-label="'Ver ' + p.nombre">
                        <template x-if="p.img">
                            <img :src="p.img" :alt="p.nombre" class="absolute inset-0 w-full h-full object-cover transition duration-500 group-hover:scale-105" :class="p.imgs.length > 1 ? 'group-hover:opacity-0' : ''" loading="lazy">
                        </template>
                        {{-- Con más fotos: al pasar el mouse se ve la segunda --}}
                        <template x-if="p.imgs.length > 1">
                            <img :src="p.imgs[1]" alt="" class="absolute inset-0 w-full h-full object-cover opacity-0 group-hover:opacity-100 transition duration-500" loading="lazy">
                        </template>
                        <template x-if="!p.img"><span class="absolute inset-0 flex items-center justify-center text-4xl font-black text-slate-300" x-text="p.nombre.slice(0, 2)"></span></template>
                        <span x-show="agotado(p)" class="absolute top-2 left-2 px-2 py-0.5 rounded-lg bg-slate-900/80 text-white text-[11px] font-bold">AGOTADO</span>
                        <span x-show="p.precio < p.normal" class="absolute top-2 right-2 px-2 py-0.5 rounded-lg bg-rose-500 text-white text-[11px] font-bold" x-text="'-' + descuento(p) + '%'"></span>
                        <span x-show="p.imgs.length > 1" class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded-md bg-black/50 text-white text-[10px] font-semibold" x-text="'📷 ' + p.imgs.length"></span>
                    </button>
                    <div class="p-3 flex-1 flex flex-col">
                        <button type="button" @click="abrir(p)" class="text-left text-sm font-semibold leading-snug line-clamp-2 flex-1 hover:text-marca" x-text="p.nombre"></button>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-lg font-black text-marca" x-text="soles(p.precio)"></span>
                            <span x-show="p.precio < p.normal" class="text-xs text-slate-400 line-through" x-text="soles(p.normal)"></span>
                        </div>
                        <template x-if="!enCarrito(p.id)">
                            <button type="button" @click="sumar(p, 1)" :disabled="agotado(p)"
                                    class="mt-2 h-10 rounded-xl bg-marca hover:opacity-90 text-white text-sm font-bold disabled:bg-slate-200 disabled:text-slate-400" x-text="agotado(p) ? 'Agotado' : 'Agregar'"></button>
                        </template>
                        <template x-if="enCarrito(p.id)">
                            <div class="mt-2 h-10 flex items-center justify-between rounded-xl bg-marca-suave">
                                <button type="button" @click="sumar(p, -1)" class="w-10 h-10 text-xl font-bold text-marca">−</button>
                                <span class="font-extrabold text-marca" x-text="enCarrito(p.id)"></span>
                                <button type="button" @click="sumar(p, 1)" class="w-10 h-10 text-xl font-bold text-marca">+</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        <p x-show="!visibles.length" class="py-16 text-center text-slate-400">No encontramos productos.</p>
    </div>

    {{-- Ficha del producto: galería de fotos, descripción y cantidad --}}
    <div x-show="detalle" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-end sm:items-center justify-center sm:p-4" @click.self="detalle = null">
        <template x-if="detalle">
            <div class="bg-white w-full sm:max-w-3xl max-h-[92vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl shadow-2xl grid sm:grid-cols-2" x-transition>
                <div class="relative bg-slate-100">
                    <div class="aspect-square relative overflow-hidden" @touchstart="toque = $event.touches[0].clientX" @touchend="deslizar($event.changedTouches[0].clientX)">
                        <template x-if="detalle.imgs.length">
                            <img :src="detalle.imgs[foto]" :alt="detalle.nombre" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!detalle.imgs.length"><span class="absolute inset-0 flex items-center justify-center text-6xl font-black text-slate-300" x-text="detalle.nombre.slice(0, 2)"></span></template>
                        <template x-if="detalle.imgs.length > 1">
                            <div>
                                <button type="button" @click="foto = (foto - 1 + detalle.imgs.length) % detalle.imgs.length" class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 shadow text-lg" aria-label="Foto anterior">‹</button>
                                <button type="button" @click="foto = (foto + 1) % detalle.imgs.length" class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 shadow text-lg" aria-label="Foto siguiente">›</button>
                            </div>
                        </template>
                        <button type="button" @click="detalle = null" class="sm:hidden absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 shadow text-xl leading-none" aria-label="Cerrar">×</button>
                    </div>
                    <div x-show="detalle.imgs.length > 1" class="flex gap-2 p-3 overflow-x-auto scroll-oculto">
                        <template x-for="(img, i) in detalle.imgs" :key="img">
                            <button type="button" @click="foto = i" class="w-16 h-16 shrink-0 rounded-xl overflow-hidden ring-2" :class="foto === i ? 'ring-marca' : 'ring-transparent opacity-70'">
                                <img :src="img" alt="" class="w-full h-full object-cover">
                            </button>
                        </template>
                    </div>
                </div>
                <div class="p-5 sm:p-6 flex flex-col">
                    <div class="flex items-start gap-2">
                        <p class="flex-1 text-xs font-bold uppercase tracking-wider text-slate-400" x-text="categorias.find(c => c.cat_id === detalle.cat)?.cat_nom || ''"></p>
                        <button type="button" @click="detalle = null" class="hidden sm:block w-9 h-9 -mt-2 -mr-2 rounded-xl hover:bg-slate-100 text-2xl leading-none" aria-label="Cerrar">×</button>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black leading-tight" x-text="detalle.nombre"></h3>
                    <div class="mt-3 flex items-baseline gap-3">
                        <span class="text-3xl font-black text-marca" x-text="soles(detalle.precio)"></span>
                        <span x-show="detalle.precio < detalle.normal" class="text-slate-400 line-through" x-text="soles(detalle.normal)"></span>
                        <span x-show="detalle.precio < detalle.normal" class="px-2 py-0.5 rounded-lg bg-rose-100 text-rose-600 text-xs font-bold" x-text="'Ahorras ' + soles(detalle.normal - detalle.precio)"></span>
                    </div>
                    <p x-show="detalle.desc" class="mt-4 text-sm text-slate-600 whitespace-pre-line" x-text="detalle.desc"></p>
                    <p class="mt-3 text-xs font-semibold" :class="agotado(detalle) ? 'text-rose-600' : 'text-emerald-600'"
                       x-text="agotado(detalle) ? 'Agotado por ahora' : (detalle.stock !== null && detalle.stock <= 5 ? '¡Quedan solo ' + detalle.stock + '!' : '✔ Disponible')"></p>
                    <div class="mt-auto pt-6 flex gap-3">
                        <div class="h-12 flex items-center rounded-xl bg-slate-100">
                            <button type="button" @click="cantidad = Math.max(1, cantidad - 1)" class="w-11 h-12 text-xl font-bold">−</button>
                            <span class="w-8 text-center font-extrabold" x-text="cantidad"></span>
                            <button type="button" @click="cantidad++" class="w-11 h-12 text-xl font-bold">+</button>
                        </div>
                        <button type="button" @click="sumar(detalle, cantidad); if (!error) detalle = null" :disabled="agotado(detalle)"
                                class="flex-1 h-12 rounded-xl bg-marca hover:opacity-90 text-white font-extrabold disabled:bg-slate-200 disabled:text-slate-400"
                                x-text="'Agregar · ' + soles(detalle.precio * cantidad)"></button>
                    </div>
                    <p x-show="error" class="mt-2 text-sm text-rose-600" x-text="error"></p>
                </div>
            </div>
        </template>
    </div>

    {{-- Barra del carrito en el celular --}}
    <button type="button" x-show="$store.carrito.unidades > 0 && !abierto && !detalle" x-cloak @click="abierto = true"
            class="sm:hidden fixed z-30 left-4 right-20 bottom-4 h-14 rounded-2xl bg-marca text-white shadow-xl px-4 flex items-center justify-between font-bold" style="margin-bottom: env(safe-area-inset-bottom)">
        <span x-text="'🛒 ' + $store.carrito.unidades + ' producto(s)'"></span><span x-text="'Ver pedido · ' + soles($store.carrito.total)"></span>
    </button>

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
                        <p class="text-xs text-slate-500" x-text="soles(it.precio) + ' c/u · ' + soles(it.precio * it.cantidad)"></p>
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
                                <button type="button" @click="entrega = e" :class="entrega === e ? 'bg-white shadow text-marca' : 'text-slate-500'"
                                        class="h-9 rounded-lg text-xs font-bold" x-text="e === 'DELIVERY' ? '🛵 Delivery' : '🏪 Recojo en tienda'"></button>
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
                        <div class="rounded-xl bg-marca-suave text-slate-800 text-sm px-4 py-3">
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
                <a href="{{ route('tienda.login') }}" class="w-full h-12 rounded-xl bg-marca text-white font-extrabold flex items-center justify-center">Ingresar para pedir</a>
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
    const sinTildes = t => String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

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
            busqueda: '', cat: null, orden: 'nombre', abierto: false, enviando: false, error: '', enviado: null,
            detalle: null, foto: 0, cantidad: 1, toque: null,
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
                // Enlace directo a un producto: ...tiendavirtual#p123
                const m = location.hash.match(/^#p(\d+)$/);
                const p = m && this.productos.find(x => x.id === Number(m[1]));
                if (p) this.abrir(p);
            },
            get visibles() {
                const q = sinTildes(this.busqueda.trim());
                const lista = this.productos.filter(p => (q ? sinTildes(p.nombre + ' ' + p.desc).includes(q) : (this.cat === null || p.cat === this.cat)));
                const orden = { menor: (a, b) => a.precio - b.precio, mayor: (a, b) => b.precio - a.precio,
                                ofertas: (a, b) => this.descuento(b) - this.descuento(a) || a.nombre.localeCompare(b.nombre) }[this.orden];
                return orden ? [...lista].sort(orden) : lista;
            },
            get ofertas() { return this.productos.filter(p => p.precio < p.normal && !this.agotado(p)).slice(0, 12); },
            descuento(p) { return p.normal > 0 && p.precio < p.normal ? Math.round((1 - p.precio / p.normal) * 100) : 0; },
            abrir(p) { this.detalle = p; this.foto = 0; this.cantidad = 1; this.error = ''; },
            deslizar(x) {
                if (this.toque === null || !this.detalle || this.detalle.imgs.length < 2) return;
                const d = x - this.toque, n = this.detalle.imgs.length;
                if (Math.abs(d) > 40) this.foto = (this.foto + (d < 0 ? 1 : -1) + n) % n;
                this.toque = null;
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
                if (p.stock !== null && nueva > p.stock) {
                    this.error = 'Solo hay ' + p.stock + ' de ' + p.nombre;
                    if (it.cantidad <= 0) c.items = c.items.filter(i => i.id !== p.id);
                    return;
                }
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
