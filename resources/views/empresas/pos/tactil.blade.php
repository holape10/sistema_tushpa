<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#1e1b4b">
    <meta name="mobile-web-app-capable" content="yes">
    <title>PV - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <style>
        [x-cloak]{display:none!important}
        html, body { height: 100%; overscroll-behavior: none; }
        * { -webkit-tap-highlight-color: transparent; }
        button, [role=button] { touch-action: manipulation; user-select: none; }
        .scroll-fino { scrollbar-width: thin; scrollbar-color: #c7d2fe transparent; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.PVT = {
            usuario: @json(auth()->user()->IdUsuario),
            csrf: @json(csrf_token()),
            categorias: @json($categorias),
            productos: @json($productos),
            comprobantes: @json($comprobantes),
            tdocodPred: @json($negocio->tdocod_pred ?? '13'),
            contado: @json($contado),
            medios: @json($medios),
            nombreDesde: @json(config('pv.nombre_obligatorio_desde')),
            hoy: @json(now()->toDateString()),
            rutas: { registrar: @json(route('pv.tactil.registrar')), cliente: @json(url('cobros/cliente')),
                     precios: @json(route('pv.tactil.precios')), proforma: @json(route('proformas.guardar')) },
        };
    </script>
    <script src="{{ asset('js/impresion.js') }}?v={{ filemtime(public_path('js/impresion.js')) }}" data-url="{{ url('impresion/comprobante') }}" data-csrf="{{ csrf_token() }}"></script>
<script src="{{ asset('js/pv-tactil.js') }}?v={{ filemtime(public_path('js/pv-tactil.js')) }}"></script>
    @include('partials.pwa')
</head>
<body class="bg-slate-100 text-slate-800 antialiased overflow-hidden select-none" x-data="pvTactil" x-init="iniciar()" @keydown.window="tecla($event)">

<div class="h-[100dvh] flex flex-col md:grid md:grid-cols-[1fr_340px] lg:grid-cols-[200px_1fr_390px] gap-0 md:gap-3 md:p-3">

    {{-- ===== Categorías (columna en pantallas grandes) ===== --}}
    <aside class="hidden lg:flex flex-col bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="px-3 pt-3 pb-2 flex items-center gap-2">
            <img src="{{ asset('imagenes/512.png') }}" alt="" class="w-8 h-8 rounded-lg">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Categorías</span>
        </div>
        <div class="flex-1 overflow-y-auto scroll-fino px-2.5 pb-3 space-y-2">
            <button type="button" @click="cat = null" :class="cat === null ? 'ring-4 ring-slate-800/30 scale-[1.02]' : ''"
                    class="w-full min-h-[56px] rounded-xl bg-slate-800 text-white font-bold text-sm px-2 transition active:scale-95">TODOS</button>
            <template x-for="c in cfg.categorias" :key="c.cat_id">
                <button type="button" @click="cat = c.cat_id" :style="`background:${c.color || '#64748b'}`"
                        :class="cat === c.cat_id ? 'ring-4 ring-slate-800/40 scale-[1.02]' : ''"
                        class="w-full min-h-[56px] rounded-xl text-white font-bold text-sm px-2 leading-tight [text-shadow:0_1px_2px_rgb(0_0_0/0.35)] transition active:scale-95"
                        x-text="c.cat_nom"></button>
            </template>
        </div>
    </aside>

    {{-- ===== Productos ===== --}}
    <main class="flex-1 min-h-0 flex flex-col">
        <div class="flex items-center gap-2 px-3 pt-3 md:p-0 md:pb-3">
            <div class="relative flex-1">
                <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input x-ref="buscador" type="search" x-model="busqueda" @keydown.enter.prevent="enterBuscador()" placeholder="Buscar producto o escanear código…"
                       class="w-full h-12 pl-12 pr-4 rounded-full border-0 shadow-sm text-base focus:ring-4 focus:ring-indigo-300">
            </div>
            <span class="hidden sm:inline-flex items-center gap-1.5 h-12 px-3 rounded-full bg-white shadow-sm text-xs font-semibold text-emerald-700">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>Turno {{ $turno->turno }}
            </span>
            <button type="button" @click="pantallaCompleta()" class="w-12 h-12 shrink-0 rounded-full bg-white shadow-sm flex items-center justify-center text-slate-500 active:scale-95" title="Pantalla completa" aria-label="Pantalla completa">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
            </button>
            <a href="{{ route('home.usuario') }}" class="w-12 h-12 shrink-0 rounded-full bg-white shadow-sm flex items-center justify-center text-slate-500 active:scale-95" title="Salir" aria-label="Salir">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </a>
        </div>

        {{-- Categorías como fichas (tablet y celular) --}}
        <div class="lg:hidden flex gap-2 overflow-x-auto px-3 py-2.5 md:px-0 md:pt-0 scroll-fino">
            <button type="button" @click="cat = null" :class="cat === null ? 'ring-4 ring-slate-800/30' : ''"
                    class="shrink-0 h-11 px-4 rounded-xl bg-slate-800 text-white text-sm font-bold active:scale-95">TODOS</button>
            <template x-for="c in cfg.categorias" :key="c.cat_id">
                <button type="button" @click="cat = c.cat_id" :style="`background:${c.color || '#64748b'}`" :class="cat === c.cat_id ? 'ring-4 ring-slate-800/40' : ''"
                        class="shrink-0 h-11 px-4 rounded-xl text-white text-sm font-bold whitespace-nowrap [text-shadow:0_1px_2px_rgb(0_0_0/0.35)] active:scale-95" x-text="c.cat_nom"></button>
            </template>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto scroll-fino px-3 pb-28 md:px-0 md:pb-2">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 md:gap-3">
                <template x-for="p in visibles" :key="p.id">
                    <button type="button" @click="agregar(p)"
                            class="relative bg-white rounded-2xl shadow-sm h-32 sm:h-36 p-2.5 flex flex-col text-center overflow-hidden active:scale-[.97] active:bg-indigo-50 transition">
                        <span class="absolute top-0 inset-x-0 h-1.5" :style="`background:${colorCat(p.cat)}`"></span>
                        <span x-show="cantidadEn(p.id)" x-cloak x-text="cantidadEn(p.id)"
                              class="absolute top-2.5 right-2.5 min-w-7 h-7 px-1.5 rounded-full bg-indigo-600 text-white text-sm font-extrabold flex items-center justify-center shadow"></span>
                        <template x-if="p.img">
                            <img :src="p.img" alt="" loading="lazy" class="mx-auto h-12 sm:h-14 w-full object-contain mt-1">
                        </template>
                        <span class="flex-1 flex items-center justify-center px-1">
                            <span class="font-bold text-[13px] sm:text-sm leading-tight uppercase" :class="p.img ? 'line-clamp-2' : 'line-clamp-3'" x-text="p.nombre"></span>
                        </span>
                        <span x-show="p.pres.length" x-cloak class="absolute top-8 left-2.5 text-[10px] font-bold text-indigo-700 bg-indigo-50 px-1.5 rounded" x-text="'+' + p.pres.length + ' pres.'"></span>
                        <span class="rounded-lg bg-slate-50 py-1 font-extrabold text-emerald-600" x-text="soles(p.precio)"></span>
                        <span x-show="p.stock !== null && p.stock <= 0" x-cloak class="absolute top-2.5 left-2.5 text-[10px] font-bold text-rose-600 bg-rose-50 px-1.5 rounded">SIN STOCK</span>
                    </button>
                </template>
            </div>
            <p x-show="!visibles.length" x-cloak class="py-16 text-center text-slate-400">No hay productos <span x-show="busqueda" x-text="'para “' + busqueda + '”'"></span></p>
        </div>
    </main>

    {{-- ===== Pedido ===== --}}
    <div x-show="pedidoAbierto" x-cloak x-transition.opacity @click="pedidoAbierto = false" class="md:hidden fixed inset-0 bg-black/40 z-40"></div>
    <section :class="pedidoAbierto ? 'translate-y-0' : 'translate-y-full'"
             class="fixed md:static inset-x-0 bottom-0 z-50 max-h-[92dvh] md:max-h-none md:translate-y-0 flex flex-col bg-white rounded-t-3xl md:rounded-2xl shadow-2xl md:shadow-sm transition-transform duration-300 min-h-0">
        <div class="flex items-center justify-between px-4 pt-4 pb-3 border-b border-slate-100">
            <h2 class="text-xl font-extrabold text-slate-700">Mi Pedido <span x-show="unidades" x-cloak class="text-indigo-600" x-text="'(' + unidades + ')'"></span></h2>
            <div class="flex items-center gap-1">
                <button type="button" x-show="carrito.length" x-cloak @click="vaciar()" class="h-10 px-3 rounded-xl text-rose-600 text-sm font-semibold active:bg-rose-50" aria-label="Vaciar pedido">Vaciar</button>
                <button type="button" @click="pedidoAbierto = false" class="md:hidden w-10 h-10 rounded-xl text-slate-400 text-2xl" aria-label="Cerrar">×</button>
            </div>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto scroll-fino px-3 py-2">
            <div x-show="!carrito.length" class="h-full min-h-[160px] flex flex-col items-center justify-center text-slate-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <p class="mt-2 font-semibold">Toca un producto para agregarlo</p>
            </div>
            <ul class="divide-y divide-slate-100">
                <template x-for="(it, i) in carrito" :key="it.key">
                    <li class="py-2.5" :class="it.flash ? 'bg-emerald-50' : ''" style="transition: background-color .5s">
                        <div class="flex items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm leading-tight uppercase" x-text="it.nombre"></p>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                    <button type="button" @click="editarPrecio(i)" class="h-8 px-2.5 rounded-lg text-sm font-bold"
                                            :class="it.desc50 ? 'bg-orange-50 text-orange-700' : 'bg-indigo-50 text-indigo-700'" x-text="soles(it.precio)"></button>
                                    <button type="button" @click="toggle50(i)" class="h-8 px-2.5 rounded-lg text-xs font-bold border"
                                            :class="it.desc50 ? 'bg-orange-500 border-orange-500 text-white' : 'border-orange-300 text-orange-600'">-50%</button>
                                    <button type="button" @click="it.verNota = !it.verNota; $nextTick(() => document.getElementById('nota-' + it.key)?.focus())"
                                            class="h-8 px-2.5 rounded-lg text-xs font-semibold border border-slate-200 text-slate-500" x-text="it.nota ? '✎ nota' : '+ nota'"></button>
                                </div>
                            </div>
                            <div class="flex items-center bg-slate-100 rounded-xl shrink-0">
                                <button type="button" @click="cambiar(i, -1)" class="w-10 h-11 text-2xl font-bold text-rose-600 active:bg-slate-200 rounded-l-xl" aria-label="Menos">−</button>
                                <span class="w-7 text-center font-extrabold" x-text="it.cant"></span>
                                <button type="button" @click="cambiar(i, 1)" class="w-10 h-11 text-2xl font-bold text-indigo-600 active:bg-slate-200 rounded-r-xl" aria-label="Más">+</button>
                            </div>
                            <span class="w-16 text-right font-extrabold pt-2 shrink-0" x-text="num(it.precio * it.cant)"></span>
                        </div>
                        <input x-show="it.verNota || it.nota" x-cloak :id="'nota-' + it.key" x-model="it.nota" maxlength="60" placeholder="Nota: sin cebolla, para llevar…"
                               class="mt-1.5 w-full h-9 rounded-lg border-amber-200 bg-amber-50 text-xs uppercase select-text">
                    </li>
                </template>
            </ul>
        </div>

        {{-- Cobro --}}
        <div class="border-t border-slate-100 bg-slate-50 rounded-b-2xl px-4 pt-3 pb-4 space-y-2.5" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
            <div class="flex items-end justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase">Total a pagar</span>
                <span class="text-3xl font-black" x-text="soles(total)"></span>
            </div>

            <div class="grid gap-1 p-1 bg-slate-200/70 rounded-xl" :style="`grid-template-columns: repeat(${cfg.comprobantes.length}, minmax(0,1fr))`">
                <template x-for="c in cfg.comprobantes" :key="c.tdocod">
                    <button type="button" @click="tdocod = c.tdocod" :class="tdocod === c.tdocod ? 'bg-white shadow text-indigo-700' : 'text-slate-500'"
                            class="h-9 rounded-lg text-xs font-bold" x-text="nombreComprobante(c.tdocod)"></button>
                </template>
            </div>

            {{-- Datos del comprobante: DNI o RUC (el nombre se busca solo) y nombre o razón social --}}
            <div x-show="tdocod !== '13'" x-cloak class="space-y-2">
                <div class="grid grid-cols-[1fr_auto] gap-2 items-end">
                    <label class="text-[11px] font-semibold text-slate-500" x-text="tdocod === '01' ? 'RUC del cliente' : 'DNI o RUC del cliente'"></label>
                    <span class="text-[11px] max-w-[160px] truncate text-right" :class="docOk ? 'text-emerald-600' : 'text-slate-400'"
                          x-text="buscandoDoc ? 'Buscando…' : (docOk ? '✔ Encontrado' : (docNombre || ''))"></span>
                </div>
                <input x-model="doc" @input="autoDoc()" inputmode="numeric" maxlength="11" :placeholder="tdocod === '01' ? 'RUC de 11 dígitos' : 'DNI de 8 dígitos o RUC'"
                       :class="faltaDoc ? 'border-rose-400 ring-2 ring-rose-200' : 'border-slate-200'"
                       class="-mt-1 w-full h-11 rounded-xl text-sm font-semibold select-text">
                <input x-model="nombre" maxlength="150" :placeholder="tdocod === '01' ? 'Razón social' : 'Nombres y apellidos'"
                       :class="faltaDoc && doc.trim() ? 'border-rose-400 ring-2 ring-rose-200' : 'border-slate-200'"
                       class="w-full h-11 rounded-xl text-sm uppercase select-text">
                <p x-show="tdocod === '03' && !doc.trim()" class="text-[11px] text-slate-500 -mt-1">Sin DNI la boleta sale a <b>VENTA AL PORTADOR</b> (SUNAT lo permite hasta S/ 699.99).</p>
            </div>

            <label class="block text-[11px] font-semibold text-slate-500">Paga con
                <input type="number" inputmode="decimal" step="0.10" min="0" x-model.number="paga" placeholder="0.00"
                       class="mt-0.5 w-full h-11 rounded-xl border-emerald-300 text-right text-lg font-bold select-text">
            </label>

            <div class="flex gap-1.5 overflow-x-auto scroll-fino">
                <template x-for="m in cfg.medios" :key="m.id">
                    <button type="button" @click="medio = m.id" :class="medio === m.id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'"
                            class="shrink-0 h-9 px-3 rounded-lg border text-xs font-bold" x-text="m.nombre + (m.comision > 0 ? ' +' + m.comision + '%' : '')"></button>
                </template>
            </div>
            <div x-show="esEfectivo" class="flex gap-1.5">
                <button type="button" @click="paga = totalCobrar" class="flex-1 h-9 rounded-lg bg-white border border-slate-200 text-xs font-bold">Exacto</button>
                <template x-for="b in billetes" :key="b">
                    <button type="button" @click="paga = b" class="flex-1 h-9 rounded-lg bg-white border border-slate-200 text-xs font-bold" x-text="b"></button>
                </template>
            </div>

            <div class="flex justify-between text-sm font-bold" x-show="paga > 0 || comision > 0" x-cloak>
                <span x-show="comision > 0" class="text-rose-600" x-text="'Recargo ' + soles(comision)"></span>
                <span x-show="paga > 0" class="ml-auto" :class="paga >= totalCobrar ? 'text-emerald-600' : 'text-rose-600'"
                      x-text="(paga >= totalCobrar ? 'Vuelto ' : 'Falta ') + soles(Math.abs(paga - totalCobrar))"></span>
            </div>

            <button type="button" @click="guardarProforma()" :disabled="procesando || !carrito.length"
                    class="w-full h-10 rounded-xl bg-violet-50 text-violet-700 text-sm font-bold active:scale-[.98] disabled:opacity-40">
                PROFORMA (cotizar e imprimir)
            </button>
            <button type="button" @click="cobrar()" :disabled="procesando || !carrito.length"
                    class="w-full h-14 rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-400 text-white text-lg font-black tracking-wide shadow-lg shadow-emerald-500/30 active:scale-[.98] disabled:opacity-40 flex items-center justify-center gap-2">
                <span x-show="procesando" x-cloak class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span x-text="procesando ? 'EMITIENDO…' : 'COBRAR E IMPRIMIR ' + soles(totalCobrar)"></span>
            </button>
        </div>
    </section>
</div>

{{-- Barra inferior en celular --}}
<div x-show="!pedidoAbierto" class="md:hidden fixed inset-x-0 bottom-0 z-30 p-3 bg-gradient-to-t from-slate-100 via-slate-100" style="padding-bottom: max(.75rem, env(safe-area-inset-bottom))">
    <button type="button" @click="pedidoAbierto = true" class="w-full h-14 rounded-2xl bg-emerald-500 text-white font-black flex items-center justify-between px-5 shadow-lg active:scale-[.98]">
        <span x-text="unidades ? unidades + (unidades === 1 ? ' producto' : ' productos') : 'Pedido vacío'"></span>
        <span x-text="'VER PEDIDO ' + soles(total)"></span>
    </button>
</div>

{{-- Elegir presentación (unidad, SACO, CAJA…) --}}
<div x-show="elegir" x-cloak class="fixed inset-0 z-[60] bg-slate-900/60 flex items-center justify-center p-4" @click.self="elegir = null">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-4" x-show="elegir">
        <p class="text-center font-extrabold text-lg uppercase leading-tight" x-text="elegir?.nombre"></p>
        <p class="text-center text-sm text-slate-500 mb-3">¿Cómo lo vendes?</p>
        <div class="grid gap-2">
            <button type="button" @click="agregar(elegir, null)" class="h-14 px-4 rounded-2xl bg-slate-100 active:bg-slate-200 flex items-center justify-between font-bold">
                <span>Unidad</span><span class="text-emerald-600" x-text="soles(elegir?.precio)"></span>
            </button>
            <template x-for="pr in (elegir?.pres || [])" :key="pr.id">
                <button type="button" @click="agregar(elegir, pr)" class="h-14 px-4 rounded-2xl bg-indigo-50 active:bg-indigo-100 flex items-center justify-between font-bold text-indigo-800">
                    <span x-text="pr.nombre + ' (x' + pr.factor + ')'"></span><span class="text-emerald-600" x-text="soles(pr.precio)"></span>
                </button>
            </template>
        </div>
        <button type="button" @click="elegir = null" class="mt-3 w-full h-11 rounded-xl bg-slate-200 font-bold">Cancelar</button>
    </div>
</div>

{{-- Teclado numérico para cambiar el precio --}}
<div x-show="teclado" x-cloak class="fixed inset-0 z-[60] bg-slate-900/60 flex items-center justify-center p-4" @click.self="teclado = null">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xs p-4" x-show="teclado">
        <p class="text-sm text-slate-500 text-center" x-text="teclado?.nombre"></p>
        <p class="text-4xl font-black text-center my-3" x-text="'S/ ' + (teclado?.valor || '0')"></p>
        <div class="grid grid-cols-3 gap-2">
            <template x-for="k in ['7','8','9','4','5','6','1','2','3','.','0','⌫']" :key="k">
                <button type="button" @click="teclaNum(k)" class="h-14 rounded-xl bg-slate-100 text-2xl font-bold active:bg-slate-200" x-text="k"></button>
            </template>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-2">
            <button type="button" @click="teclado = null" class="h-12 rounded-xl bg-slate-200 font-bold">Cancelar</button>
            <button type="button" @click="guardarPrecio()" class="h-12 rounded-xl bg-indigo-600 text-white font-bold">Aceptar</button>
        </div>
    </div>
</div>

{{-- Venta emitida --}}
<div x-show="venta" x-cloak x-transition.opacity class="fixed inset-0 z-[70] bg-emerald-600/95 flex items-center justify-center p-6 text-white text-center" @click="nuevaVenta()">
    <div>
        <div class="w-24 h-24 mx-auto rounded-full bg-white/20 flex items-center justify-center">
            <svg class="w-14 h-14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <p class="text-3xl font-black mt-4">¡Venta registrada!</p>
        <p class="text-lg opacity-90" x-text="venta?.numero"></p>
        <p class="text-5xl font-black mt-4" x-show="venta?.vuelto > 0" x-text="'Vuelto ' + soles(venta?.vuelto)"></p>
        <p class="mt-2 opacity-80" x-text="'Imprimiendo comprobante…'"></p>
        <p class="mt-8 text-sm opacity-70">Toca la pantalla para la siguiente venta</p>
    </div>
</div>

<iframe x-ref="impresion" class="hidden" title="Impresión"></iframe>

<div class="fixed top-3 inset-x-3 z-[80] flex flex-col items-center gap-2 pointer-events-none">
    <template x-for="t in avisos" :key="t.id">
        <div class="pointer-events-auto max-w-md w-full rounded-xl px-4 py-3 text-sm font-semibold shadow-xl text-white" :class="t.tipo === 'error' ? 'bg-rose-600' : 'bg-slate-800'" x-text="t.texto"></div>
    </template>
</div>
@include('partials.avisos')
@include('partials.aviso_servicio')
</body>
</html>
