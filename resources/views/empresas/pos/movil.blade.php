<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#312e81">
    <title>PV Móvil - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Datos del servidor para el componente (se registra antes de que Alpine arranque)
        window.POS = {
            usuario: @json(auth()->user()->IdUsuario),
            comprobantes: @json($comprobantes),
            tdocodPred: @json($negocio->tdocod_pred ?? '13'),
            estadopagos: @json($estadopagos),
            medios: @json($mediospagos),
            documentos: @json($documentos),
            hoy: @json(now()->format('Y-m-d')),
            proforma: @json($proforma),
            rutas: {
                proforma: @json(route('proformas.guardar')),
                productos: @json(route('pos.productos')),
                registrar: @json(route('pos.registrar')),
                clientes: @json(route('cobros.clientes')),
                cliente: @json(url('cobros/cliente')),
            },
        };
    </script>
    <script src="{{ asset('js/impresion.js') }}?v={{ filemtime(public_path('js/impresion.js')) }}" data-url="{{ url('impresion/comprobante') }}" data-csrf="{{ csrf_token() }}"></script>
<script src="{{ asset('js/escaner-barras.js') }}?v={{ filemtime(public_path('js/escaner-barras.js')) }}"></script>
<script src="{{ asset('js/pos-movil.js') }}?v={{ filemtime(public_path('js/pos-movil.js')) }}"></script>
<script src="{{ asset('js/whatsapp-cpe.js') }}?v={{ filemtime(public_path('js/whatsapp-cpe.js')) }}" data-base="{{ url('/') }}" data-csrf="{{ csrf_token() }}"></script>
    @include('partials.pwa')
</head>

<body class="bg-slate-100 text-slate-800 antialiased" x-data="posMovil" x-init="iniciar()"
      @keydown.window="teclaGlobal($event)">

{{-- ================= CABECERA ================= --}}
<header class="sticky top-0 z-30 bg-indigo-900 text-white shadow-lg" style="padding-top: env(safe-area-inset-top)">
    <div class="flex items-center gap-2 px-3 py-2.5">
        <a href="{{ route('home.usuario') }}" class="p-2 -ml-1 rounded-xl hover:bg-indigo-800 active:bg-indigo-700" aria-label="Volver al inicio">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="font-bold leading-tight">PV Móvil</h1>
            <p class="text-[11px] text-indigo-200 leading-tight truncate">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1 align-middle"></span>Turno N° {{ $turno->turno }} · {{ auth()->user()->apeusu }}
            </p>
        </div>
        <a href="{{ route('ventas.index') }}" class="p-2 rounded-xl hover:bg-indigo-800 active:bg-indigo-700" title="Panel de ventas" aria-label="Panel de ventas">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </a>
        <button type="button" @click="abrirCobro()" class="relative p-2 rounded-xl hover:bg-indigo-800 active:bg-indigo-700" aria-label="Ver carrito">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span x-show="unidades > 0" x-cloak x-text="unidades > 99 ? '99+' : unidades"
                  class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full bg-rose-500 text-[11px] font-bold flex items-center justify-center"></span>
        </button>
    </div>

    {{-- Buscador: texto, lector de barras (Enter), voz y cámara --}}
    <div class="px-3 pb-3 relative" @click.outside="resultadosAbiertos = false">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input x-ref="buscador" type="search" inputmode="search" enterkeyhint="search" autocomplete="off"
                       x-model="busqueda" @input="cantidadPendiente = 1" @input.debounce.250ms="buscar()" @focus="resultadosAbiertos = resultados.length > 0"
                       @keydown.enter.prevent="enterBuscador()" @keydown.arrow-down.prevent="moverResultado(1)" @keydown.arrow-up.prevent="moverResultado(-1)"
                       @keydown.escape="resultadosAbiertos = false"
                       :placeholder="escuchando ? 'Te escucho…' : 'Buscar o escanear…'"
                       class="w-full h-12 pl-10 pr-9 rounded-xl border-0 text-slate-800 text-base placeholder:text-slate-400 focus:ring-4 focus:ring-indigo-400/50">
                <span x-show="buscando" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></span>
            </div>
            <button type="button" x-show="vozDisponible" @click="alternarVoz()" :aria-pressed="escuchando"
                    :class="escuchando ? 'bg-rose-500 animate-pulse' : 'bg-indigo-600 hover:bg-indigo-500'"
                    class="w-12 h-12 shrink-0 rounded-xl flex items-center justify-center shadow" title="Buscar por voz" aria-label="Buscar por voz">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v4m-4 0h8M12 15a3 3 0 003-3V5a3 3 0 00-6 0v7a3 3 0 003 3z"/></svg>
            </button>
            <button type="button" @click="abrirEscaner()" class="w-12 h-12 shrink-0 rounded-xl bg-emerald-500 hover:bg-emerald-400 flex items-center justify-center shadow"
                    title="Escanear código con la cámara" aria-label="Escanear código con la cámara">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8"/></svg>
            </button>
        </div>

        {{-- Resultados --}}
        <div x-show="resultadosAbiertos" x-cloak x-transition.opacity
             class="absolute left-3 right-3 mt-2 bg-white text-slate-800 rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden max-h-[60vh] overflow-y-auto">
            <template x-for="(p, i) in resultados" :key="p.id">
                <button type="button" @click="agregar(p)" @mouseenter="resultadoActivo = i"
                        :class="i === resultadoActivo ? 'bg-indigo-50' : ''"
                        class="w-full flex items-center gap-3 px-4 py-3 text-left border-b border-slate-100 last:border-0 active:bg-indigo-100">
                    <img x-show="p.imagen" :src="p.imagen" alt="" class="w-11 h-11 rounded-lg object-cover shrink-0" loading="lazy">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm leading-snug" x-text="p.nombre"></p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <span x-text="p.codigo"></span>
                            <template x-if="p.stock !== null">
                                <span :class="p.stock > 0 ? 'text-emerald-600' : 'text-rose-500'" x-text="' · Stock ' + num(p.stock)"></span>
                            </template>
                            <template x-if="p.presentaciones && p.presentaciones.length">
                                <span class="text-indigo-500" x-text="' · ' + p.presentaciones.map(x => x.nombre).join(', ')"></span>
                            </template>
                        </p>
                    </div>
                    <span class="text-right whitespace-nowrap">
                        <span class="block font-bold text-indigo-700" x-text="soles(p.precio)"></span>
                        <span x-show="p.dinamico" class="block text-[10px] font-bold text-emerald-600">⚡ precio dinámico</span>
                    </span>
                    <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xl leading-none shrink-0">+</span>
                </button>
            </template>
            <p x-show="!resultados.length && !buscando" class="px-4 py-6 text-center text-sm text-slate-400">
                No se encontró “<span x-text="busqueda"></span>”.
            </p>
        </div>
    </div>
</header>

{{-- ================= CUERPO ================= --}}
<main class="lg:grid lg:grid-cols-[1fr_420px] lg:gap-5 lg:p-5 max-w-7xl mx-auto">

    {{-- Carrito --}}
    <section class="px-3 pt-3 pb-36 lg:p-0">
        <div class="flex items-center justify-between mb-2 px-1">
            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide">
                Detalle de venta <span x-show="carrito.length" x-cloak class="text-indigo-600" x-text="'(' + carrito.length + ')'"></span>
            </h2>
            <button type="button" x-show="carrito.length" x-cloak @click="vaciar()" class="text-xs font-semibold text-rose-600 px-2 py-1 rounded-lg hover:bg-rose-50">
                Vaciar
            </button>
        </div>

        <div x-show="proformaId" x-cloak class="mb-2 flex items-center gap-2 rounded-2xl bg-violet-600 text-white px-3 py-2.5 text-sm">
            <span class="flex-1"><strong>📄 Proforma <span x-text="proformaNumero"></span></strong><span class="block text-xs text-violet-100">Cobrar la convierte en comprobante.</span></span>
            <button type="button" @click="soltarProforma()" class="text-xs font-semibold px-2.5 py-1.5 rounded-lg bg-white/15">Desvincular</button>
        </div>
        <div x-show="ultimaProforma && !proformaId" x-cloak class="mb-2 flex items-center gap-2 rounded-2xl bg-violet-50 text-violet-800 px-3 py-2.5 text-sm">
            <span class="flex-1">Proforma <strong x-text="ultimaProforma?.numero"></strong> · <span x-text="soles(ultimaProforma?.total)"></span></span>
            <a :href="ultimaProforma?.imprimir" target="_blank" class="text-xs font-bold">Imprimir</a>
            <a href="{{ route('proformas.index') }}" class="text-xs font-bold">Todas</a>
        </div>

        <div x-show="!carrito.length" class="bg-white rounded-2xl shadow-sm py-14 px-6 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-indigo-50 flex items-center justify-center mb-3">
                <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <p class="font-semibold text-slate-600">El carrito está vacío</p>
            <p class="text-sm text-slate-400 mt-1">Escribe, dicta <span class="whitespace-nowrap">(ej. “3 inca kola”)</span> o escanea un código.</p>
        </div>

        <ul class="space-y-2">
            <template x-for="(it, i) in carrito" :key="it.id + '-' + i">
                <li class="bg-white rounded-2xl shadow-sm p-3 transition-colors duration-500" :class="it.flash ? 'bg-emerald-50 ring-2 ring-emerald-300' : ''">
                    <div class="flex items-start gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm leading-snug" x-text="nombreLinea(it)"></p>
                            <p class="text-[11px] text-slate-400"><span x-text="it.codigo || 'Línea libre'"></span><span x-show="it.dinamico && !it.presentacion" class="text-emerald-600 font-semibold"> · ⚡ precio dinámico</span></p>
                            {{-- Presentación elegida al agregar --}}
                            <template x-if="it.presentaciones && it.presentaciones.length">
                                <span class="mt-1 inline-block px-2 py-0.5 rounded-lg bg-indigo-50 text-[11px] font-bold text-indigo-700"
                                      x-text="'📦 ' + (it.presentacion ? (it.presentaciones.find(x => x.id === it.presentacion)?.nombre + ' x' + num(it.factor) + ' ' + it.unidad) : it.unidad)"></span>
                            </template>
                        </div>
                        <button type="button" @click="quitar(i)" class="p-1.5 -mr-1 -mt-1 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Quitar producto">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        {{-- Cantidad --}}
                        <div class="flex items-center bg-slate-100 rounded-xl">
                            <button type="button" @click="cambiarCantidad(i, -1)" class="w-10 h-10 text-xl font-bold text-slate-600 active:bg-slate-200 rounded-l-xl" aria-label="Menos">−</button>
                            <input type="number" inputmode="decimal" min="0.01" step="any" x-model.number="it.cantidad" @change="normalizar(i)"
                                   class="w-14 h-10 text-center font-bold bg-transparent border-0 p-0 focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" aria-label="Cantidad">
                            <button type="button" @click="cambiarCantidad(i, 1)" class="w-10 h-10 text-xl font-bold text-indigo-600 active:bg-slate-200 rounded-r-xl" aria-label="Más">+</button>
                        </div>
                        {{-- Precio editable --}}
                        <label class="flex items-center gap-1 h-10 px-2 rounded-xl border border-dashed border-amber-300 bg-amber-50 text-sm">
                            <span class="text-amber-600 text-xs">S/</span>
                            <input type="number" inputmode="decimal" min="0.01" step="0.10" x-model.number="it.precio" @change="normalizar(i)"
                                   class="w-16 bg-transparent border-0 p-0 font-semibold text-amber-800 focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" aria-label="Precio unitario">
                        </label>
                        <span class="ml-auto font-bold text-indigo-700 whitespace-nowrap" x-text="soles(it.cantidad * it.precio)"></span>
                    </div>
                    <p x-show="it.stock !== null && it.cantidad * (it.factor || 1) > it.stock" x-cloak class="text-[11px] text-rose-500 mt-1.5">
                        ⚠ Stock disponible: <span x-text="num(it.stock)"></span>
                    </p>
                </li>
            </template>
        </ul>
    </section>

    {{-- ================= PANEL DE COBRO (hoja inferior en móvil, columna en escritorio) ================= --}}
    <div x-show="cobroAbierto" x-cloak x-transition.opacity @click="cobroAbierto = false" class="fixed inset-0 bg-black/40 z-40 lg:hidden"></div>

    <aside :class="cobroAbierto ? 'translate-y-0' : 'translate-y-full'"
           class="fixed inset-x-0 bottom-0 z-50 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl transition-transform duration-300
                  lg:static lg:translate-y-0 lg:max-h-none lg:rounded-2xl lg:shadow-sm lg:self-start lg:sticky lg:top-36">
        <div class="lg:hidden pt-2 pb-1 flex justify-center" @click="cobroAbierto = false">
            <span class="w-10 h-1.5 rounded-full bg-slate-300"></span>
        </div>
        <div class="flex items-center justify-between px-4 pt-1 pb-3 lg:pt-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-700">Datos de cobro</h2>
            <button type="button" @click="cobroAbierto = false" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:bg-slate-100" aria-label="Cerrar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-3 space-y-4">
            {{-- Comprobante --}}
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">Comprobante</p>
                <div class="grid gap-1.5 p-1 bg-slate-100 rounded-xl" :style="`grid-template-columns: repeat(${cfg.comprobantes.length}, minmax(0, 1fr))`">
                    <template x-for="c in cfg.comprobantes" :key="c.tdocod">
                        <button type="button" @click="elegirComprobante(c.tdocod)"
                                :class="tdocod === c.tdocod ? 'bg-white text-indigo-700 shadow' : 'text-slate-500'"
                                class="h-10 rounded-lg text-sm font-bold transition" x-text="nombreComprobante(c.tdocod)"></button>
                    </template>
                </div>
            </div>

            {{-- Cliente --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Cliente</p>
                    <button type="button" x-show="!esPortador" x-cloak @click="clienteVarios()" class="text-xs font-semibold text-indigo-600">Cliente varios</button>
                </div>
                <div class="flex gap-2">
                    <select x-model="cliente.tdicod" class="h-11 rounded-xl border-slate-200 text-sm w-24 shrink-0 focus:ring-indigo-400 focus:border-indigo-400" aria-label="Tipo de documento">
                        <template x-for="d in cfg.documentos" :key="d.tdicod">
                            <option :value="d.tdicod" x-text="d.tdides.length > 4 ? d.tdides.slice(0, 9) + '.' : d.tdides" :selected="d.tdicod === cliente.tdicod"></option>
                        </template>
                    </select>
                    <div class="relative flex-1">
                        <input type="text" inputmode="numeric" maxlength="15" x-model="cliente.num" @focus="if (esPortador) $event.target.select()"
                               @input="autoBuscarDoc()" @keydown.enter.prevent="buscarDoc()" @blur="blurDoc()"
                               placeholder="DNI / RUC" class="w-full h-11 rounded-xl border-slate-200 font-semibold focus:ring-indigo-400 focus:border-indigo-400" aria-label="Número de documento">
                    </div>
                    <button type="button" @click="buscarDoc()" :disabled="buscandoDoc" class="h-11 w-11 shrink-0 rounded-xl bg-indigo-600 text-white flex items-center justify-center disabled:opacity-60" aria-label="Buscar documento">
                        <svg x-show="!buscandoDoc" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                        <span x-show="buscandoDoc" x-cloak class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    </button>
                </div>
                <div class="relative mt-2" @click.outside="sugerencias = []">
                    <input type="text" x-model="cliente.nom" @input.debounce.250ms="sugerirClientes()" @focus="if (esPortador) $event.target.select()"
                           @keydown.arrow-down.prevent="sugActiva = Math.min(sugActiva + 1, sugerencias.length - 1)"
                           @keydown.arrow-up.prevent="sugActiva = Math.max(sugActiva - 1, 0)"
                           @keydown.enter.prevent="sugerencias[sugActiva] && usarCliente(sugerencias[sugActiva])"
                           placeholder="Nombre o razón social (escribe para buscar)" autocomplete="off"
                           class="w-full h-11 rounded-xl border-slate-200 text-sm uppercase focus:ring-indigo-400 focus:border-indigo-400" aria-label="Nombre o razón social">
                    <ul x-show="sugerencias.length" x-cloak class="absolute z-10 left-0 right-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-black/5 max-h-56 overflow-y-auto">
                        <template x-for="(c, i) in sugerencias" :key="c.num">
                            <li @mousedown.prevent="usarCliente(c)" :class="i === sugActiva ? 'bg-indigo-50' : ''" class="px-3 py-2 cursor-pointer border-b border-slate-50 last:border-0">
                                <p class="text-sm font-semibold" x-text="c.nom"></p>
                                <p class="text-xs text-slate-400" x-text="(c.tdicod === '6' ? 'RUC ' : 'DOC ') + c.num"></p>
                            </li>
                        </template>
                    </ul>
                </div>
                <input type="text" x-show="tdocod === '01' || cliente.dir" x-cloak x-model="cliente.dir" placeholder="Dirección"
                       class="w-full h-11 mt-2 rounded-xl border-slate-200 text-sm focus:ring-indigo-400 focus:border-indigo-400" aria-label="Dirección">
                <p x-show="msgCliente.texto" x-cloak class="text-xs mt-1.5" :class="msgCliente.ok ? 'text-emerald-600' : 'text-rose-600'" x-text="msgCliente.texto"></p>
            </div>

            {{-- Forma de pago --}}
            <div x-show="cfg.estadopagos.length > 1">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">Forma de pago</p>
                <div class="flex gap-1.5 p-1 bg-slate-100 rounded-xl">
                    <template x-for="e in cfg.estadopagos" :key="e.cre_dia_id">
                        <button type="button" @click="elegirEstadoPago(e)"
                                :class="estadopago == e.cre_dia_id ? 'bg-white text-indigo-700 shadow' : 'text-slate-500'"
                                class="flex-1 h-10 rounded-lg text-sm font-bold transition" x-text="e.cre_dia_nom"></button>
                    </template>
                </div>
                <label x-show="!esContado" x-cloak class="flex items-center gap-2 mt-2 text-sm">
                    <span class="text-slate-500">Vence el</span>
                    <input type="date" x-model="fecVen" :min="cfg.hoy" class="h-10 rounded-xl border-slate-200 text-sm flex-1">
                </label>
            </div>

            {{-- Medios de pago --}}
            <div x-show="esContado">
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Medio de pago</p>
                    <button type="button" x-show="cfg.medios.length > 1" @click="alternarDividir()" class="text-xs font-semibold text-indigo-600"
                            x-text="dividir ? 'Un solo medio' : 'Dividir pago'"></button>
                </div>
                <div x-show="!dividir" class="flex flex-wrap gap-1.5">
                    <template x-for="m in cfg.medios" :key="m.id_med_pag">
                        <button type="button" @click="medioUnico = m.id_med_pag"
                                :class="medioUnico == m.id_med_pag ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200'"
                                class="px-4 h-10 rounded-xl border text-sm font-bold transition" x-text="m.nom_med_pag"></button>
                    </template>
                </div>
                <div x-show="dividir" x-cloak class="space-y-1.5">
                    <template x-for="m in cfg.medios" :key="m.id_med_pag">
                        <label class="flex items-center gap-2">
                            <span class="flex-1 text-sm font-semibold text-slate-600" x-text="m.nom_med_pag"></span>
                            <button type="button" @click="completarMedio(m.id_med_pag)" class="text-[11px] font-semibold text-indigo-600 px-2" title="Completar con lo que falta">Resto</button>
                            <input type="number" inputmode="decimal" min="0" step="0.10" x-model.number="montosMedios[m.id_med_pag]" placeholder="0.00"
                                   class="w-28 h-10 rounded-xl border-slate-200 text-right font-semibold focus:ring-indigo-400 focus:border-indigo-400">
                        </label>
                    </template>
                    <p class="text-xs text-right" :class="Math.abs(faltaMedios) < 0.01 ? 'text-emerald-600' : 'text-rose-600'"
                       x-text="Math.abs(faltaMedios) < 0.01 ? '✔ Cuadra con el total' : (faltaMedios > 0 ? 'Falta ' : 'Sobra ') + soles(Math.abs(faltaMedios))"></p>
                </div>
            </div>

            {{-- Paga con / vuelto --}}
            <div x-show="esContado && efectivoEnJuego" class="bg-slate-50 rounded-2xl p-3">
                <div class="flex items-center gap-2">
                    <label for="paga" class="text-sm font-semibold text-slate-500 shrink-0">Paga con</label>
                    <input id="paga" type="number" inputmode="decimal" min="0" step="0.10" x-model.number="paga" placeholder="0.00"
                           class="flex-1 min-w-0 h-12 rounded-xl border-2 border-emerald-400 text-right text-xl font-bold text-emerald-700 focus:ring-emerald-300 focus:border-emerald-500">
                </div>
                <div class="flex gap-1.5 mt-2 overflow-x-auto pb-0.5">
                    <button type="button" @click="paga = total" class="px-3 h-9 rounded-lg bg-white border border-slate-200 text-sm font-semibold whitespace-nowrap">Exacto</button>
                    <template x-for="b in billetes" :key="b">
                        <button type="button" @click="paga = b" class="px-3 h-9 rounded-lg bg-white border border-slate-200 text-sm font-semibold" x-text="b"></button>
                    </template>
                </div>
                <div class="flex justify-between items-center mt-2" x-show="paga > 0" x-cloak>
                    <span class="text-sm font-semibold text-slate-500" x-text="paga >= total ? 'Vuelto' : 'Falta'"></span>
                    <span class="text-xl font-extrabold" :class="paga >= total ? 'text-emerald-600' : 'text-rose-600'" x-text="soles(Math.abs(paga - total))"></span>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" x-model="imprimir" class="rounded text-indigo-600 focus:ring-indigo-400 w-5 h-5">
                Imprimir ticket al terminar
            </label>
        </div>

        {{-- Total + botón --}}
        <div class="border-t border-slate-100 px-4 pt-3 pb-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
            <div class="flex items-end justify-between mb-3">
                <span class="text-sm font-bold text-slate-500 uppercase">Total a pagar</span>
                <span class="text-3xl font-extrabold text-indigo-700" x-text="soles(total)"></span>
            </div>
            <button type="button" @click="guardarProforma()" :disabled="procesando || !carrito.length"
                    class="w-full h-11 mb-2 rounded-2xl bg-violet-50 text-violet-700 font-bold disabled:opacity-50"
                    x-text="proformaId ? 'Guardar cambios de la proforma ' + proformaNumero : 'Guardar como proforma'"></button>
            <button type="button" @click="cobrar()" :disabled="procesando || !carrito.length"
                    class="w-full h-14 rounded-2xl bg-emerald-500 hover:bg-emerald-600 active:scale-[.98] text-white text-lg font-extrabold tracking-wide shadow-lg shadow-emerald-500/30 transition disabled:opacity-50 disabled:active:scale-100 flex items-center justify-center gap-2">
                <span x-show="procesando" x-cloak class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span x-text="procesando ? 'PROCESANDO…' : 'COBRAR ' + soles(total)"></span>
            </button>
        </div>
    </aside>
</main>

{{-- Barra inferior (solo móvil) --}}
<div x-show="!cobroAbierto" class="lg:hidden fixed inset-x-0 bottom-0 z-30 bg-white/95 backdrop-blur border-t border-slate-200 px-3 pt-3"
     style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
    <button type="button" @click="abrirCobro()" :disabled="!carrito.length"
            class="w-full h-14 rounded-2xl bg-emerald-500 active:scale-[.98] text-white flex items-center justify-between px-5 shadow-lg shadow-emerald-500/30 transition disabled:bg-slate-300 disabled:shadow-none">
        <span class="text-sm font-semibold" x-text="unidades ? num(unidades) + (unidades === 1 ? ' producto' : ' productos') : 'Agrega productos'"></span>
        <span class="text-lg font-extrabold">COBRAR <span x-text="soles(total)"></span></span>
    </button>
</div>

{{-- ================= ELEGIR PRESENTACIÓN ================= --}}
<div x-show="elegir" x-cloak class="fixed inset-0 z-[65] bg-slate-900/60 flex items-end sm:items-center justify-center" @click.self="elegir = null">
    <div x-show="elegir" x-transition class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl p-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
        <div class="sm:hidden flex justify-center mb-2"><span class="w-10 h-1.5 rounded-full bg-slate-300"></span></div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-600 text-center">¿Cómo lo vendes?</p>
        <p class="text-lg font-extrabold text-center leading-tight mb-3" x-text="elegir?.p.nombre"></p>
        <div class="grid gap-2 max-h-[55vh] overflow-y-auto">
            <button type="button" @click="elegirOpcion(null)" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-100 active:bg-slate-200 text-left">
                <span class="flex-1"><span class="block font-bold" x-text="elegir?.p.unidad || 'Unidad'"></span><span class="block text-xs text-slate-500">Unidad base</span></span>
                <span class="text-lg font-extrabold text-indigo-700" x-text="soles(elegir?.p.precio)"></span>
            </button>
            <template x-for="pr in (elegir?.p.presentaciones || [])" :key="pr.id">
                <button type="button" @click="elegirOpcion(pr)" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl bg-indigo-50 active:bg-indigo-100 text-left">
                    <span class="flex-1"><span class="block font-bold text-indigo-900" x-text="pr.nombre"></span>
                        <span class="block text-xs text-indigo-500" x-text="'Trae ' + num(pr.factor) + ' ' + (elegir?.p.unidad || '')"></span></span>
                    <span class="text-lg font-extrabold text-indigo-700" x-text="soles(pr.precio)"></span>
                </button>
            </template>
        </div>
        <button type="button" @click="elegir = null" class="mt-3 w-full h-12 rounded-2xl bg-slate-200 font-bold">Cancelar</button>
    </div>
</div>

{{-- ================= ESCÁNER CON CÁMARA ================= --}}
<div x-show="escaner" x-cloak class="fixed inset-0 z-[60] bg-black flex flex-col">
    <div class="flex items-center justify-between px-4 py-3 text-white" style="padding-top: max(0.75rem, env(safe-area-inset-top))">
        <div>
            <p class="font-bold">Escanear código</p>
            <p class="text-xs text-white/60" x-text="ultimoEscaneo ? '✔ ' + ultimoEscaneo : 'Apunta al código de barras o QR'"></p>
        </div>
        <button type="button" @click="cerrarEscaner()" class="p-2 rounded-full bg-white/10 hover:bg-white/20" aria-label="Cerrar escáner">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="flex-1 flex items-center justify-center overflow-hidden">
        <div id="lector-camara" class="w-full max-w-md"></div>
    </div>
    <div class="p-4 text-white space-y-3" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
        <label class="flex items-center justify-between bg-white/10 rounded-xl px-4 py-3">
            <span class="text-sm">Escaneo continuo <span class="block text-xs text-white/50">Sigue leyendo sin cerrar la cámara</span></span>
            <input type="checkbox" x-model="escaneoContinuo" class="rounded w-5 h-5 text-emerald-500 focus:ring-emerald-400">
        </label>
        <p x-show="errorCamara" x-cloak class="text-sm text-rose-300 text-center" x-text="errorCamara"></p>
    </div>
</div>

{{-- ================= VENTA REGISTRADA ================= --}}
<div x-show="venta" x-cloak class="fixed inset-0 z-[70] bg-slate-900/70 flex items-end sm:items-center justify-center">
    <div x-show="venta" x-transition class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[95vh] flex flex-col overflow-hidden">
        <div class="text-center pt-6 pb-4 px-6">
            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 flex items-center justify-center">
                <svg class="w-9 h-9 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="text-xl font-extrabold mt-3">¡Venta registrada!</p>
            <p class="text-sm text-slate-500" x-text="venta?.numero"></p>
            <div class="flex justify-center gap-6 mt-3">
                <div><p class="text-[11px] uppercase text-slate-400 font-bold">Total</p><p class="text-lg font-extrabold text-indigo-700" x-text="soles(venta?.total)"></p></div>
                <div x-show="venta?.vuelto > 0"><p class="text-[11px] uppercase text-slate-400 font-bold">Vuelto</p><p class="text-lg font-extrabold text-emerald-600" x-text="soles(venta?.vuelto)"></p></div>
            </div>
        </div>
        <div class="flex-1 min-h-0 bg-slate-100 border-y border-slate-200">
            <iframe x-ref="ticket" :src="venta?.ticket" class="w-full h-64 sm:h-80 bg-white" title="Ticket"></iframe>
        </div>
        <div class="grid grid-cols-3 gap-2 p-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
            <button type="button" @click="imprimirTicket()" class="h-12 rounded-xl bg-indigo-50 text-indigo-700 font-bold">Imprimir</button>
            {{-- Envía el comprobante (PDF A4) al WhatsApp del cliente; si no tiene número, lo pide --}}
            <button type="button" @click="TushpaWhatsApp.abrir(venta.id)" class="h-12 rounded-xl bg-[#25d366] text-white font-bold flex items-center justify-center gap-1.5" title="Enviar por WhatsApp">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.05 21.5a9.4 9.4 0 01-4.8-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.4 9.4 0 1117.97-3.55c0 5.2-4.23 9.43-9.43 9.43zm8.02-17.45A11.25 11.25 0 0012.05.75C5.8.75.72 5.83.72 12.08c0 2 .52 3.95 1.52 5.66L.62 23.25l5.65-1.48a11.3 11.3 0 005.41 1.38c6.25 0 11.33-5.08 11.33-11.33 0-3.03-1.18-5.87-3.32-8.01z"/></svg>
                WhatsApp</button>
            <button type="button" @click="nuevaVenta()" class="h-12 rounded-xl bg-emerald-500 text-white font-bold shadow">Nueva venta</button>
        </div>
    </div>
</div>

{{-- Avisos --}}
<div class="fixed top-3 inset-x-3 z-[80] flex flex-col items-center gap-2 pointer-events-none" style="padding-top: env(safe-area-inset-top)">
    <template x-for="t in avisos" :key="t.id">
        <div x-transition class="pointer-events-auto max-w-sm w-full rounded-xl px-4 py-3 text-sm font-semibold shadow-xl"
             :class="t.tipo === 'error' ? 'bg-rose-600 text-white' : (t.tipo === 'ok' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-white')"
             x-text="t.texto"></div>
    </template>
</div>

@include('partials.avisos')
@include('partials.aviso_servicio')
</body>
</html>
