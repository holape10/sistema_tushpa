<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title>PV Grifo - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <style>
        [x-cloak]{display:none!important}
        html, body { height: 100%; overscroll-behavior: none; }
        * { -webkit-tap-highlight-color: transparent; }
        button { touch-action: manipulation; }
        .scroll-fino { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.PVG = {
            usuario: @json(auth()->user()->IdUsuario),
            csrf: @json(csrf_token()),
            categorias: @json($categorias),
            productos: @json($productos),
            comprobantes: @json($comprobantes),
            tdocodPred: @json($negocio->tdocod_pred ?? '13'),
            estadopagos: @json($estadopagos),
            medios: @json($medios),
            proforma: @json($proforma),
            hoy: @json(now()->toDateString()),
            rutas: {
                registrar: @json(route('pv.grifo.registrar')), cliente: @json(url('cobros/cliente')), placas: @json(route('pv.grifo.placas')),
                precios: @json(route('pv.tactil.precios')), proforma: @json(route('proformas.guardar')), proformas: @json(route('proformas.index')),
            },
        };
    </script>
    <script src="{{ asset('js/impresion.js') }}?v={{ filemtime(public_path('js/impresion.js')) }}" data-url="{{ url('impresion/comprobante') }}" data-csrf="{{ csrf_token() }}"></script>
    <script src="{{ asset('js/pv-grifo.js') }}?v={{ filemtime(public_path('js/pv-grifo.js')) }}"></script>
    @include('partials.pwa')
</head>
<body class="bg-slate-100 text-slate-800 antialiased overflow-hidden" x-data="pvGrifo" x-init="iniciar()" @keydown.window="tecla($event)">

<div class="h-[100dvh] flex flex-col">

    {{-- ================= Cabecera ================= --}}
    <header class="shrink-0 bg-slate-900 text-white" style="padding-top: env(safe-area-inset-top)">
        <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5">
            <a href="{{ route('home.usuario') }}" class="w-10 h-10 shrink-0 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center" title="Salir" aria-label="Salir">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="hidden sm:flex items-center gap-2 shrink-0">
                <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-rose-500 flex items-center justify-center text-xl">⛽</span>
                <div class="leading-tight">
                    <p class="font-extrabold">PV Grifo</p>
                    <p class="text-[11px] text-slate-400"><span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1"></span>Turno {{ $turno->turno }} · {{ auth()->user()->apeusu }}</p>
                </div>
            </div>
            <div class="relative flex-1 min-w-0">
                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input x-ref="buscador" type="search" x-model="busqueda" @keydown.enter.prevent="enterBuscador()" placeholder="Buscar producto o escanear código… (F2)"
                       class="w-full h-11 pl-10 pr-3 rounded-xl border-0 bg-white/10 text-white placeholder:text-slate-400 focus:bg-white focus:text-slate-800 focus:ring-4 focus:ring-orange-400/40">
            </div>
            <a :href="cfg.rutas.proformas" class="hidden md:flex h-10 px-3 rounded-xl bg-white/10 hover:bg-white/20 items-center text-sm font-semibold">Proformas</a>
            <button type="button" @click="pantallaCompleta()" class="hidden sm:flex w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 items-center justify-center" title="Pantalla completa" aria-label="Pantalla completa">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
            </button>
        </div>
    </header>

    <div class="flex-1 min-h-0 flex">

        {{-- ================= Productos ================= --}}
        <main class="flex-1 min-w-0 flex flex-col">
            <div class="shrink-0 flex gap-2 overflow-x-auto scroll-fino px-3 sm:px-4 py-3">
                <button type="button" @click="cat = null" :class="cat === null ? 'bg-slate-900 text-white' : 'bg-white text-slate-600'"
                        class="shrink-0 h-10 px-4 rounded-xl text-sm font-bold shadow-sm">Todos</button>
                <button type="button" x-show="combustibles.length" @click="cat = 'comb'" :class="cat === 'comb' ? 'bg-orange-500 text-white' : 'bg-white text-orange-600'"
                        class="shrink-0 h-10 px-4 rounded-xl text-sm font-bold shadow-sm">⛽ Combustibles</button>
                <template x-for="c in cfg.categorias" :key="c.cat_id">
                    <button type="button" @click="cat = c.cat_id" :class="cat === c.cat_id ? 'text-white' : 'bg-white text-slate-600'"
                            :style="cat === c.cat_id ? `background:${c.color || '#475569'}` : ''"
                            class="shrink-0 h-10 px-4 rounded-xl text-sm font-bold shadow-sm whitespace-nowrap" x-text="c.cat_nom"></button>
                </template>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto scroll-fino px-3 sm:px-4 pb-28 lg:pb-4">
                {{-- Combustibles: botones grandes --}}
                <template x-if="combustiblesVisibles.length">
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 mb-4">
                        <template x-for="p in combustiblesVisibles" :key="p.id">
                            <button type="button" @click="tocar(p)"
                                    class="group relative h-36 sm:h-40 rounded-3xl overflow-hidden shadow-md text-left text-white active:scale-[.97] transition bg-gradient-to-br"
                                    :class="colorCombustible(p)">
                                <template x-if="p.img"><img :src="p.img" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy"></template>
                                <span class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-transparent"></span>
                                <span class="absolute top-3 left-3 w-9 h-9 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center text-lg">⛽</span>
                                <span class="absolute bottom-3 left-3 right-3">
                                    <span class="block font-extrabold text-lg leading-tight uppercase drop-shadow" x-text="p.nombre"></span>
                                    <span class="inline-flex items-baseline gap-1 mt-1 px-2 py-0.5 rounded-lg bg-white/90 text-slate-900">
                                        <span class="font-extrabold" x-text="soles(p.precio)"></span><span class="text-[11px] font-semibold" x-text="'/ ' + p.abrev"></span>
                                    </span>
                                </span>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- Tienda --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 xl:grid-cols-5 2xl:grid-cols-6 gap-3">
                    <template x-for="p in tiendaVisibles" :key="p.id">
                        <button type="button" @click="tocar(p)"
                                class="relative bg-white rounded-2xl shadow-sm overflow-hidden flex flex-col text-left active:scale-[.97] transition hover:shadow-md">
                            <span class="block aspect-[4/3] bg-gradient-to-br from-slate-100 to-slate-200 relative">
                                <template x-if="p.img"><img :src="p.img" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy"></template>
                                <template x-if="!p.img">
                                    <span class="absolute inset-0 flex items-center justify-center text-3xl font-black text-slate-300" x-text="p.nombre.slice(0, 2)"></span>
                                </template>
                                <span x-show="cantidadEn(p.id)" x-cloak x-text="num(cantidadEn(p.id))"
                                      class="absolute top-2 right-2 min-w-7 h-7 px-1.5 rounded-full bg-indigo-600 text-white text-sm font-extrabold flex items-center justify-center shadow"></span>
                                <span x-show="p.pres.length" x-cloak class="absolute top-2 left-2 text-[10px] font-bold text-indigo-700 bg-white/90 px-1.5 py-0.5 rounded" x-text="'+' + p.pres.length + ' pres.'"></span>
                                <span x-show="p.stock !== null && p.stock <= 0" x-cloak class="absolute bottom-2 left-2 text-[10px] font-bold text-white bg-rose-600 px-1.5 py-0.5 rounded">SIN STOCK</span>
                            </span>
                            <span class="p-2.5 flex-1 flex flex-col">
                                <span class="font-bold text-[13px] leading-tight uppercase line-clamp-2 flex-1" x-text="p.nombre"></span>
                                <span class="mt-1 font-extrabold text-emerald-600" x-text="soles(p.precio)"></span>
                            </span>
                        </button>
                    </template>
                </div>
                <p x-show="!combustiblesVisibles.length && !tiendaVisibles.length" x-cloak class="py-16 text-center text-slate-400">
                    No hay productos <span x-show="busqueda" x-text="'para “' + busqueda + '”'"></span>
                </p>
            </div>
        </main>

        {{-- ================= Venta (columna en escritorio, hoja inferior en celular) ================= --}}
        <div x-show="ventaAbierta" x-cloak x-transition.opacity @click="ventaAbierta = false" class="lg:hidden fixed inset-0 bg-black/40 z-40"></div>
        <aside :class="ventaAbierta ? 'translate-y-0' : 'translate-y-full'"
               class="fixed inset-x-0 bottom-0 z-50 max-h-[94dvh] flex flex-col bg-white rounded-t-3xl shadow-2xl transition-transform duration-300
                      lg:static lg:translate-y-0 lg:max-h-none lg:rounded-none lg:shadow-none lg:border-l lg:border-slate-200 lg:w-[440px] xl:w-[800px] shrink-0">
            <div class="lg:hidden pt-2 flex justify-center" @click="ventaAbierta = false"><span class="w-10 h-1.5 rounded-full bg-slate-300"></span></div>

            <div class="flex-1 min-h-0 overflow-y-auto scroll-fino xl:grid xl:grid-cols-2 xl:divide-x xl:divide-slate-100">

                {{-- ----- Cliente + detalle ----- --}}
                <div class="p-4 space-y-4">
                    <div x-show="proformaId" x-cloak class="flex items-center gap-2 rounded-2xl bg-violet-600 text-white px-3 py-2 text-sm">
                        <span class="flex-1"><strong>📄 Proforma <span x-text="proformaNumero"></span></strong></span>
                        <button type="button" @click="soltarProforma()" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white/15">Desvincular</button>
                    </div>

                    <section>
                        <div class="grid gap-1 p-1 bg-slate-100 rounded-2xl" :style="`grid-template-columns: repeat(${cfg.comprobantes.length}, minmax(0,1fr))`">
                            <template x-for="c in cfg.comprobantes" :key="c.tdocod">
                                <button type="button" @click="elegirComprobante(c.tdocod)" :class="tdocod === c.tdocod ? 'bg-white shadow text-orange-600' : 'text-slate-500'"
                                        class="h-10 rounded-xl text-sm font-extrabold" x-text="nombreComprobante(c.tdocod)"></button>
                            </template>
                        </div>
                    </section>

                    <section class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500">Cliente</h2>
                            <button type="button" x-show="cliente.num !== '00000000'" @click="clienteVarios()" class="text-xs font-semibold text-orange-600">Cliente varios</button>
                        </div>
                        <div class="flex gap-2">
                            <input x-ref="doc" type="text" inputmode="numeric" maxlength="11" x-model="cliente.num" @input="autoDoc()" @focus="if (cliente.num === '00000000') $event.target.select()"
                                   :placeholder="tdocod === '01' ? 'RUC (obligatorio)' : 'DNI / RUC'" aria-label="DNI o RUC"
                                   class="flex-1 min-w-0 h-11 rounded-xl border-slate-200 font-semibold focus:border-orange-400 focus:ring-orange-300">
                            <span x-show="buscandoDoc" x-cloak class="h-11 w-11 shrink-0 flex items-center justify-center"><span class="w-5 h-5 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></span></span>
                        </div>
                        <input type="text" x-model="cliente.nom" placeholder="Nombre o razón social" maxlength="120" aria-label="Nombre o razón social"
                               class="w-full h-11 rounded-xl border-slate-200 text-sm font-semibold uppercase focus:border-orange-400 focus:ring-orange-300">
                        <input type="text" x-show="tdocod === '01' || cliente.dir" x-cloak x-model="cliente.dir" placeholder="Dirección" maxlength="150" aria-label="Dirección"
                               class="w-full h-11 rounded-xl border-slate-200 text-sm focus:border-orange-400 focus:ring-orange-300">
                        <p x-show="msgCliente.texto" x-cloak class="text-xs" :class="msgCliente.ok ? 'text-emerald-600' : 'text-rose-600'" x-text="msgCliente.texto"></p>

                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs font-semibold text-slate-500">Placa <span x-show="tdocod === '01'" class="text-rose-600">* obligatoria</span>
                                <input type="text" x-model="placa" @input="placa = placa.toUpperCase()" maxlength="10" placeholder="ABC-123"
                                       :class="faltaPlaca ? 'border-rose-400 ring-2 ring-rose-200' : 'border-slate-200'"
                                       class="mt-1 w-full h-11 rounded-xl text-base font-extrabold tracking-widest uppercase text-center focus:border-orange-400 focus:ring-orange-300">
                            </label>
                            <label class="text-xs font-semibold text-slate-500">Guía de remisión
                                <input type="text" x-model="guia" maxlength="20" placeholder="Opcional"
                                       class="mt-1 w-full h-11 rounded-xl border-slate-200 text-sm uppercase focus:border-orange-400 focus:ring-orange-300">
                            </label>
                        </div>
                        <div x-show="placasCliente.length" x-cloak class="flex flex-wrap gap-1.5">
                            <span class="text-[11px] text-slate-400 self-center">Usadas antes:</span>
                            <template x-for="pl in placasCliente" :key="pl">
                                <button type="button" @click="placa = pl" :class="placa === pl ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-700'"
                                        class="px-2.5 h-7 rounded-lg text-xs font-extrabold tracking-wider" x-text="pl"></button>
                            </template>
                        </div>
                    </section>

                    <section>
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500">Detalle <span x-show="carrito.length" x-text="'(' + carrito.length + ')'"></span></h2>
                            <button type="button" x-show="carrito.length" x-cloak @click="vaciar()" class="text-xs font-semibold text-rose-600">Vaciar</button>
                        </div>
                        <div x-show="!carrito.length" class="rounded-2xl border-2 border-dashed border-slate-200 py-8 text-center text-sm text-slate-400">
                            Toca un combustible o un producto
                        </div>
                        <ul class="space-y-2">
                            <template x-for="(it, i) in carrito" :key="it.key">
                                <li class="rounded-2xl p-3 transition-colors duration-500" :class="it.flash ? 'bg-emerald-50 ring-2 ring-emerald-300' : (it.combustible ? 'bg-orange-50' : 'bg-slate-50')">
                                    <div class="flex items-start gap-2">
                                        <p class="flex-1 min-w-0 font-bold text-sm leading-tight uppercase" x-text="it.nombre"></p>
                                        <button type="button" @click="carrito.splice(i, 1)" class="w-7 h-7 -mr-1 -mt-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50" aria-label="Quitar">✕</button>
                                    </div>
                                    {{-- Cantidad × precio fijo = total. Escribir el total (S/ 5) recalcula la cantidad y eso baja del stock --}}
                                    <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-2 mt-2">
                                        <label class="text-[10px] font-bold uppercase text-slate-400">Cantidad <span class="normal-case" x-text="'(' + it.abrev + ')'"></span>
                                            <input type="number" inputmode="decimal" step="0.001" min="0.001" :value="it.cant" @focus="$event.target.select()"
                                                   @change="editarCantidad(it, $event.target.value); $event.target.value = it.cant"
                                                   class="mt-0.5 w-full h-10 rounded-xl border-slate-200 bg-white text-center font-extrabold text-slate-800">
                                        </label>
                                        <span class="pb-2.5 text-xs font-semibold text-slate-500 whitespace-nowrap" x-text="'× ' + soles(it.precio)"></span>
                                        <label class="text-[10px] font-bold uppercase text-slate-400">Total S/
                                            <input type="number" inputmode="decimal" step="0.01" min="0.01" :value="totalLinea(it).toFixed(2)" @focus="$event.target.select()"
                                                   @change="editarTotal(it, $event.target.value); $event.target.value = totalLinea(it).toFixed(2)"
                                                   class="mt-0.5 w-full h-10 rounded-xl border-2 border-orange-200 bg-white text-right font-extrabold text-orange-700 focus:border-orange-400 focus:ring-orange-200">
                                        </label>
                                    </div>
                                    <p x-show="it.importe !== null" class="mt-1 text-[11px] text-orange-600 font-semibold">Por importe: sale del stock <span x-text="num3(it.cant) + ' ' + it.abrev"></span></p>
                                </li>
                            </template>
                        </ul>
                    </section>
                </div>

                {{-- ----- Pago ----- --}}
                <div class="p-4 space-y-4 xl:bg-slate-50/60">
                    <section x-show="cfg.estadopagos.length > 1">
                        <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Forma de pago</h2>
                        <div class="flex gap-1 p-1 bg-slate-100 rounded-2xl">
                            <template x-for="e in cfg.estadopagos" :key="e.cre_dia_id">
                                <button type="button" @click="elegirEstadoPago(e)" :class="estadopago == e.cre_dia_id ? 'bg-white shadow text-orange-600' : 'text-slate-500'"
                                        class="flex-1 h-10 rounded-xl text-sm font-bold" x-text="e.cre_dia_nom"></button>
                            </template>
                        </div>
                        <label x-show="!esContado" x-cloak class="flex items-center gap-2 mt-2 text-sm">
                            <span class="text-slate-500">Vence el</span>
                            <input type="date" x-model="fecVen" :min="cfg.hoy" class="flex-1 h-10 rounded-xl border-slate-200 text-sm">
                        </label>
                    </section>

                    <section x-show="esContado">
                        <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Medio de pago</h2>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="m in cfg.medios" :key="m.id">
                                <button type="button" @click="medio = m.id" :class="medio === m.id ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200'"
                                        class="h-11 px-3 rounded-xl border text-sm font-bold truncate" x-text="m.nombre + (m.comision > 0 ? ' +' + m.comision + '%' : '')"></button>
                            </template>
                        </div>
                    </section>

                    <section x-show="esContado && esEfectivo" class="rounded-2xl bg-white border border-slate-200 p-3 space-y-2">
                        <label class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-500 shrink-0">Paga con</span>
                            <input type="number" inputmode="decimal" min="0" step="0.10" x-model.number="paga" placeholder="0.00"
                                   class="flex-1 min-w-0 h-12 rounded-xl border-2 border-emerald-300 text-right text-xl font-extrabold text-emerald-700 focus:border-emerald-500 focus:ring-emerald-200">
                        </label>
                        <div class="flex gap-1.5">
                            <button type="button" @click="paga = totalCobrar" class="flex-1 h-9 rounded-lg bg-slate-100 text-xs font-bold">Exacto</button>
                            <template x-for="b in billetes" :key="b">
                                <button type="button" @click="paga = b" class="flex-1 h-9 rounded-lg bg-slate-100 text-xs font-bold" x-text="b"></button>
                            </template>
                        </div>
                        <div x-show="paga > 0" x-cloak class="flex justify-between items-center pt-1">
                            <span class="text-sm font-semibold text-slate-500" x-text="paga >= totalCobrar ? 'Vuelto' : 'Falta'"></span>
                            <span class="text-2xl font-black" :class="paga >= totalCobrar ? 'text-emerald-600' : 'text-rose-600'" x-text="soles(Math.abs(paga - totalCobrar))"></span>
                        </div>
                    </section>

                    <div x-show="comision > 0" x-cloak class="flex justify-between text-sm font-semibold text-rose-600">
                        <span>Recargo del medio de pago</span><span x-text="soles(comision)"></span>
                    </div>
                </div>
            </div>

            {{-- Total y botones --}}
            <div class="shrink-0 border-t border-slate-200 bg-white px-4 pt-3 pb-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
                <div class="flex items-end justify-between mb-3">
                    <span class="text-xs font-bold uppercase text-slate-500">Total a pagar</span>
                    <span class="text-3xl font-black" x-text="soles(totalCobrar)"></span>
                </div>
                <div class="grid grid-cols-[auto_1fr] gap-2">
                    <button type="button" @click="guardarProforma()" :disabled="procesando || !carrito.length"
                            class="h-14 px-4 rounded-2xl bg-violet-50 text-violet-700 text-sm font-extrabold disabled:opacity-40"
                            x-text="proformaId ? 'GUARDAR PROF.' : 'PROFORMA'"></button>
                    <button type="button" @click="cobrar()" :disabled="procesando || !carrito.length"
                            class="h-14 rounded-2xl bg-gradient-to-r from-orange-500 to-rose-500 text-white text-lg font-black tracking-wide shadow-lg shadow-orange-500/30 active:scale-[.98] disabled:opacity-40 flex items-center justify-center gap-2">
                        <span x-show="procesando" x-cloak class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span x-text="procesando ? 'EMITIENDO…' : 'COBRAR (F9)'"></span>
                    </button>
                </div>
            </div>
        </aside>
    </div>
</div>

{{-- Barra inferior en celular --}}
<div x-show="!ventaAbierta" class="lg:hidden fixed inset-x-0 bottom-0 z-30 p-3 bg-gradient-to-t from-slate-100 via-slate-100" style="padding-bottom: max(.75rem, env(safe-area-inset-bottom))">
    <button type="button" @click="ventaAbierta = true" class="w-full h-14 rounded-2xl bg-gradient-to-r from-orange-500 to-rose-500 text-white font-black flex items-center justify-between px-5 shadow-lg active:scale-[.98]">
        <span x-text="carrito.length ? carrito.length + (carrito.length === 1 ? ' línea' : ' líneas') : 'Venta vacía'"></span>
        <span x-text="'VER VENTA ' + soles(totalCobrar)"></span>
    </button>
</div>

{{-- ================= Despacho de combustible: importe o galones ================= --}}
<div x-show="despacho" x-cloak class="fixed inset-0 z-[60] bg-slate-900/70 flex items-end sm:items-center justify-center sm:p-4" @click.self="despacho = null">
    <div x-show="despacho" x-transition class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="relative px-5 pt-5 pb-4 text-white bg-gradient-to-br" :class="despacho ? colorCombustible(despacho.p) : ''">
            <p class="text-xs font-bold uppercase tracking-wider opacity-80">Despacho</p>
            <p class="text-2xl font-black uppercase leading-tight" x-text="despacho?.p.nombre"></p>
            <p class="text-sm opacity-90" x-text="soles(despacho?.p.precio) + ' por ' + (despacho?.p.unidad || 'galón').toLowerCase()"></p>
            <button type="button" @click="despacho = null" class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/20 text-xl" aria-label="Cerrar">×</button>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-2 gap-1 p-1 bg-slate-100 rounded-2xl mb-3">
                <button type="button" @click="cambiarModo('S')" :class="despacho?.modo === 'S' ? 'bg-white shadow text-orange-600' : 'text-slate-500'" class="h-10 rounded-xl font-extrabold">Por importe S/</button>
                <button type="button" @click="cambiarModo('G')" :class="despacho?.modo === 'G' ? 'bg-white shadow text-orange-600' : 'text-slate-500'" class="h-10 rounded-xl font-extrabold" x-text="'Por ' + (despacho?.p.abrev || 'gal')"></button>
            </div>
            <div class="text-center mb-3">
                <p class="text-5xl font-black tracking-tight" x-text="(despacho?.modo === 'S' ? 'S/ ' : '') + (despacho?.valor || '0') + (despacho?.modo === 'G' ? ' ' + despacho.p.abrev : '')"></p>
                <p class="text-sm font-semibold text-slate-500 mt-1" x-text="despachoEquivale"></p>
            </div>
            <div class="flex gap-1.5 mb-3">
                <template x-for="r in (despacho?.modo === 'S' ? [10, 20, 30, 50, 100] : [1, 2, 3, 5, 10])" :key="r">
                    <button type="button" @click="despacho.valor = String(r)" class="flex-1 h-10 rounded-xl bg-orange-50 text-orange-700 text-sm font-extrabold active:bg-orange-100" x-text="r"></button>
                </template>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <template x-for="k in ['7','8','9','4','5','6','1','2','3','.','0','⌫']" :key="k">
                    <button type="button" @click="teclaDespacho(k)" class="h-14 rounded-2xl bg-slate-100 text-2xl font-bold active:bg-slate-200" x-text="k"></button>
                </template>
            </div>
            <button type="button" @click="confirmarDespacho()" class="mt-3 w-full h-14 rounded-2xl bg-slate-900 text-white text-lg font-black active:scale-[.98]"
                    x-text="despacho?.editar !== undefined ? 'GUARDAR' : 'AGREGAR'"></button>
        </div>
    </div>
</div>

{{-- ================= Elegir presentación ================= --}}
<div x-show="elegir" x-cloak class="fixed inset-0 z-[60] bg-slate-900/60 flex items-end sm:items-center justify-center sm:p-4" @click.self="elegir = null">
    <div x-show="elegir" x-transition class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl p-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
        <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-600 text-center">¿Cómo lo vendes?</p>
        <p class="text-lg font-extrabold text-center leading-tight uppercase mb-3" x-text="elegir?.nombre"></p>
        <div class="grid gap-2 max-h-[55vh] overflow-y-auto">
            <button type="button" @click="agregar(elegir, null)" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-100 active:bg-slate-200 text-left">
                <span class="flex-1"><span class="block font-bold" x-text="elegir?.unidad || 'Unidad'"></span><span class="block text-xs text-slate-500">Unidad base</span></span>
                <span class="text-lg font-extrabold text-emerald-600" x-text="soles(elegir?.precio)"></span>
            </button>
            <template x-for="pr in (elegir?.pres || [])" :key="pr.id">
                <button type="button" @click="agregar(elegir, pr)" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl bg-indigo-50 active:bg-indigo-100 text-left">
                    <span class="flex-1"><span class="block font-bold text-indigo-900" x-text="pr.nombre"></span>
                        <span class="block text-xs text-indigo-500" x-text="'Trae ' + num(pr.factor) + ' ' + (elegir?.unidad || '')"></span></span>
                    <span class="text-lg font-extrabold text-emerald-600" x-text="soles(pr.precio)"></span>
                </button>
            </template>
        </div>
        <button type="button" @click="elegir = null" class="mt-3 w-full h-12 rounded-2xl bg-slate-200 font-bold">Cancelar</button>
    </div>
</div>

{{-- ================= Venta emitida ================= --}}
<div x-show="venta" x-cloak x-transition.opacity class="fixed inset-0 z-[70] bg-gradient-to-br from-orange-500 to-rose-600 flex items-center justify-center p-6 text-white text-center" @click="nuevaVenta()">
    <div>
        <div class="w-24 h-24 mx-auto rounded-full bg-white/20 flex items-center justify-center">
            <svg class="w-14 h-14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <p class="text-3xl font-black mt-4">¡Venta registrada!</p>
        <p class="text-lg opacity-90" x-text="venta?.numero"></p>
        <p class="text-5xl font-black mt-4" x-show="venta?.vuelto > 0" x-text="'Vuelto ' + soles(venta?.vuelto)"></p>
        <p class="mt-2 opacity-80">Imprimiendo comprobante…</p>
        <p class="mt-8 text-sm opacity-70">Toca la pantalla o presiona Enter para la siguiente venta</p>
    </div>
</div>

{{-- Colores de los botones de combustible (los arma pv-grifo.js): from-emerald-500 to-green-700 from-amber-400 to-orange-600 from-sky-500 to-indigo-700 from-rose-500 to-red-700 from-slate-600 to-slate-900 from-violet-500 to-fuchsia-700 --}}
<iframe x-ref="impresion" class="hidden" title="Impresión"></iframe>

<div class="fixed top-3 inset-x-3 z-[80] flex flex-col items-center gap-2 pointer-events-none">
    <template x-for="t in avisos" :key="t.id">
        <div class="pointer-events-auto max-w-md w-full rounded-xl px-4 py-3 text-sm font-semibold shadow-xl text-white"
             :class="t.tipo === 'error' ? 'bg-rose-600' : (t.tipo === 'ok' ? 'bg-emerald-600' : 'bg-slate-800')" x-text="t.texto"></div>
    </template>
</div>
@include('partials.avisos')
@include('partials.aviso_servicio')
</body>
</html>
