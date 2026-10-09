@extends('layouts.app')
@section('title', $farmacia ? 'PV Farmacia' : 'Punto Venta')

@section('content')
<div x-data="puntoVenta" x-init="iniciar()" @keydown.window="teclaGlobal($event)" class="max-w-[1600px] mx-auto">

    @if ($farmacia)
        <div class="flex flex-wrap items-center gap-2 mb-3 text-xs">
            <span class="px-3 py-1 rounded-full bg-teal-600 text-white font-bold">💊 PV FARMACIA</span>
            <span class="text-slate-500">Sale primero el lote que vence antes.</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Vigente</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Vence en {{ $diasAlerta }} días o menos</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>Vencido</span>
            <a href="{{ route('lotes.index') }}" class="ml-auto text-teal-700 font-semibold hover:underline">Ver lotes y vencimientos →</a>
        </div>
    @endif

    <div class="grid lg:grid-cols-12 gap-4 items-start">

        {{-- ================= IZQUIERDA: búsqueda, detalle y medios de pago ================= --}}
        <div class="lg:col-span-7 space-y-4 min-w-0">

            {{-- Buscador: nombre, código o lector de barras --}}
            <div class="relative" @click.outside="resultadosAbiertos = false">
                <div class="relative">
                    <svg class="w-7 h-7 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                    <input x-ref="buscador" type="search" autocomplete="off" x-model="busqueda"
                           @input="cantidadPendiente = 1" @input.debounce.200ms="buscar()" @focus="resultadosAbiertos = resultados.length > 0"
                           @keydown.enter.prevent="enterBuscador()" @keydown.arrow-down.prevent="moverResultado(1)" @keydown.arrow-up.prevent="moverResultado(-1)"
                           @keydown.escape="resultadosAbiertos = false"
                           placeholder="Buscar producto o escanear código…  (F2)"
                           class="w-full h-16 pl-14 pr-12 rounded-2xl border-0 shadow-sm text-2xl font-bold text-slate-800 placeholder:text-slate-300 placeholder:font-normal focus:ring-4 focus:ring-indigo-300">
                    <span x-show="buscando" x-cloak class="absolute right-5 top-1/2 -translate-y-1/2 w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></span>
                </div>
                <div x-show="resultadosAbiertos" x-cloak
                     class="absolute z-30 left-0 right-0 mt-2 bg-white rounded-2xl shadow-2xl ring-2 ring-indigo-400 overflow-hidden max-h-[65vh] overflow-y-auto">
                    <template x-for="(p, i) in resultados" :key="p.id">
                        <button type="button" @click="agregar(p)" @mouseenter="resultadoActivo = i"
                                :class="i === resultadoActivo ? 'bg-indigo-600 text-white' : 'text-slate-800'"
                                class="w-full flex items-center gap-4 px-5 py-3 text-left border-b border-slate-100 last:border-0">
                            <span class="flex-1 min-w-0">
                                <span class="block text-xl font-bold leading-snug" x-text="p.nombre"></span>
                                <span class="block text-sm opacity-70">
                                    <span x-text="p.codigo"></span>
                                    <template x-if="p.stock !== null"><span x-text="' · Stock ' + num(p.stock) + ' ' + (p.unidad || '')"></span></template>
                                    <template x-if="p.presentaciones && p.presentaciones.length">
                                        <span x-text="' · ' + p.presentaciones.map(x => x.nombre).join(', ')"></span>
                                    </template>
                                </span>
                                {{-- Farmacia: el lote que saldrá primero --}}
                                <template x-if="cfg.farmacia && p.lotes && p.lotes.length">
                                    <span class="mt-1 inline-flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                                        <span class="px-2 py-0.5 rounded-full text-white" :class="colorLote(p.lotes[0])"
                                              x-text="'Lote ' + p.lotes[0].lote + ' · ' + textoVence(p.lotes[0])"></span>
                                        <span x-show="p.lotes.length > 1" class="opacity-70" x-text="'+' + (p.lotes.length - 1) + ' lote(s) más'"></span>
                                    </span>
                                </template>
                                <template x-if="cfg.farmacia && p.control_lote && (!p.lotes || !p.lotes.length)">
                                    <span class="mt-1 inline-block text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Sin lotes registrados</span>
                                </template>
                            </span>
                            <span class="text-right whitespace-nowrap">
                                <span class="block text-xl font-extrabold" x-text="soles(p.precio)"></span>
                                <span x-show="p.dinamico" class="block text-xs font-bold opacity-80" x-text="'⚡ precio dinámico · normal ' + soles(p.precio_normal)"></span>
                            </span>
                        </button>
                    </template>
                    <p x-show="!resultados.length && !buscando" class="px-5 py-6 text-center text-slate-400">No se encontró “<span x-text="busqueda"></span>”.</p>
                </div>
            </div>

            {{-- Proforma abierta --}}
            <div x-show="proformaId" x-cloak class="flex flex-wrap items-center gap-2 rounded-2xl bg-violet-600 text-white px-4 py-3 shadow-sm">
                <span class="font-bold">📄 Proforma <span x-text="proformaNumero"></span></span>
                <span class="text-sm text-violet-100 flex-1">REGISTRAR o IMPRIMIR la cobra (emite el comprobante) · PROFORMA guarda los cambios.</span>
                <button type="button" @click="soltarProforma()" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-white/15 hover:bg-white/25">Desvincular</button>
            </div>

            {{-- Detalle --}}
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 px-3 sm:px-4 py-3 bg-slate-800 text-white">
                    <h2 class="font-bold tracking-wide flex-1">DETALLE <span x-show="carrito.length" x-cloak class="text-slate-300 font-normal" x-text="'· ' + carrito.length + (carrito.length === 1 ? ' línea' : ' líneas')"></span></h2>
                    <button type="button" x-show="carrito.length" x-cloak @click="vaciar()" class="text-xs px-2.5 py-1.5 rounded-lg text-rose-200 hover:bg-white/10">Vaciar</button>
                    <button type="button" @click="lineaLibre()" title="Agregar una línea escrita a mano (sin producto)"
                            class="text-sm font-bold px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400">+ Línea libre</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                            <tr>
                                <th class="text-left px-2 sm:px-4 py-2">Producto</th>
                                <th class="px-1 sm:px-2 py-2 w-16 sm:w-24">Cant.</th>
                                <th class="px-1 sm:px-2 py-2 w-20 sm:w-28">P. Unit.</th>
                                <th class="hidden sm:table-cell text-right px-4 py-2 w-28">Total</th>
                                <th class="w-8 sm:w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(it, i) in carrito" :key="it.key">
                                <tr :class="it.flash ? 'bg-emerald-50' : ''" class="transition-colors duration-500">
                                    <td class="px-2 sm:px-4 py-1.5">
                                        <input type="text" x-model="it.descripcion" :id="'des-' + it.key" maxlength="150"
                                               @keydown.enter.prevent="enfocar('cant-' + it.key)"
                                               :placeholder="it.id ? '' : 'Descripción (línea libre)'"
                                               class="w-full bg-transparent border-0 border-b border-transparent hover:border-slate-200 focus:border-indigo-400 focus:ring-0 px-0 py-1 font-semibold uppercase text-slate-800">
                                        <span class="block text-[11px] text-slate-400 -mt-0.5">
                                            <span class="sm:hidden font-bold text-slate-700" x-text="soles(redondear(it.cantidad * it.precio)) + ' · '"></span>
                                            <span x-text="it.id ? it.codigo : 'Línea libre · no mueve stock'"></span>
                                            <span x-show="it.dinamico && !it.presentacion" class="text-emerald-600 font-semibold"> · ⚡ precio dinámico</span>
                                            <span x-show="it.stock !== null && it.cantidad * (it.factor || 1) > it.stock" class="text-rose-500" x-text="' · Stock disponible: ' + num(it.stock) + ' ' + (it.unidad || '')"></span>
                                        </span>
                                        {{-- Presentación elegida al agregar (para cambiarla, quita la línea y agrégala otra vez) --}}
                                        <template x-if="it.presentaciones && it.presentaciones.length">
                                            <span class="mt-1 inline-block px-2 py-0.5 rounded-lg bg-indigo-50 text-[11px] font-bold text-indigo-800" x-text="'📦 ' + nombrePresentacion(it)"></span>
                                        </template>
                                        {{-- Farmacia: lote a vender (automático = el que vence antes) y de qué lotes sale --}}
                                        <template x-if="cfg.farmacia && it.lotes && it.lotes.length">
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                <select x-model="it.lote" class="h-7 py-0 pl-2 pr-7 rounded-lg border-teal-200 bg-teal-50 text-[11px] font-semibold text-teal-800">
                                                    <option value="">Automático (vence primero)</option>
                                                    <template x-for="l in it.lotes" :key="l.lote">
                                                        <option :value="l.lote" :disabled="l.vencido" :selected="it.lote === l.lote"
                                                                x-text="'Lote ' + l.lote + ' · ' + textoVence(l) + ' · stock ' + num(l.stock)"></option>
                                                    </template>
                                                </select>
                                                <template x-for="pl in planLotes(it)" :key="pl.lote ?? 'sin'">
                                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                                          :class="pl.lote ? colorLote(pl, true) : 'bg-slate-100 text-slate-500'"
                                                          x-text="(pl.lote ? 'L. ' + pl.lote + ' (' + textoVence(pl) + ')' : 'Sin lote') + ' × ' + num(pl.cantidad)"></span>
                                                </template>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-1 sm:px-2 py-1.5">
                                        <input type="number" step="any" min="0.01" x-model.number="it.cantidad" :id="'cant-' + it.key"
                                               @keydown.enter.prevent="enfocar('pre-' + it.key)" @change="normalizar(it)"
                                               class="w-full rounded-lg border-slate-200 text-center font-bold focus:ring-indigo-300 focus:border-indigo-400">
                                    </td>
                                    <td class="px-1 sm:px-2 py-1.5">
                                        <input type="number" step="any" min="0.01" x-model.number="it.precio" :id="'pre-' + it.key"
                                               @keydown.enter.prevent="$refs.buscador.focus()" @change="normalizar(it)"
                                               class="w-full rounded-lg border-dashed border-amber-300 bg-amber-50 text-right font-semibold text-amber-900 focus:ring-amber-200 focus:border-amber-400">
                                    </td>
                                    <td class="hidden sm:table-cell text-right px-4 py-1.5 font-bold text-slate-800 whitespace-nowrap" x-text="soles(redondear(it.cantidad * it.precio))"></td>
                                    <td class="pr-1 sm:pr-2">
                                        <button type="button" @click="quitar(i)" class="p-1.5 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50" aria-label="Quitar línea">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="!carrito.length" class="py-12 text-center text-slate-400">
                        Busca un producto o escanea su código para empezar.<br>
                        <span class="text-xs">Enter en cantidad → precio → vuelve al buscador</span>
                    </p>
                </div>
                <div class="px-4 py-3 border-t border-slate-100">
                    <label class="text-xs font-bold text-slate-500 uppercase">Observaciones</label>
                    <textarea x-model="observaciones" rows="1" maxlength="100" class="mt-1 w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-300 focus:border-indigo-400"></textarea>
                </div>
            </section>

            {{-- Medios de pago (solo contado) --}}
            <section x-show="esContado" class="bg-white rounded-2xl shadow-sm p-4">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="flex-1 min-w-[160px]">
                        <span class="text-xs font-bold text-slate-500 uppercase">Medio de pago</span>
                        <select x-model="nuevoMedio" class="mt-1 w-full h-11 rounded-xl border-slate-200 font-semibold">
                            <template x-for="m in cfg.medios" :key="m.id">
                                <option :value="m.id" x-text="m.nombre + (m.comision > 0 ? ' (+' + num(m.comision) + '%)' : '')"></option>
                            </template>
                        </select>
                    </label>
                    <label class="w-40">
                        <span class="text-xs font-bold text-slate-500 uppercase">Monto</span>
                        <input type="number" step="any" min="0" x-model.number="nuevoMonto" @keydown.enter.prevent="agregarMedio()" :placeholder="num(Math.max(0, diferencia))"
                               class="mt-1 w-full h-11 rounded-xl border-slate-200 text-right font-semibold">
                    </label>
                    <button type="button" @click="agregarMedio()" class="h-11 px-5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold">+ Agregar</button>
                </div>

                <table class="w-full text-sm mt-3">
                    <thead class="text-xs uppercase text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="text-left py-2">Medio</th>
                            <th class="py-2 w-24 sm:w-32">Monto</th>
                            <th class="hidden sm:table-cell text-center py-2">% Com.</th>
                            <th class="hidden sm:table-cell text-right py-2">Comisión</th>
                            <th class="text-right py-2">Total</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <template x-for="(m, i) in medios" :key="m.id">
                            <tr>
                                <td class="py-1.5 font-semibold">
                                    <span x-text="m.nombre"></span>
                                    <span x-show="m.auto" class="ml-1 text-[10px] font-semibold text-indigo-500 bg-indigo-50 px-1.5 py-0.5 rounded" title="Se ajusta solo con el resto del total">AUTO</span>
                                </td>
                                <td class="py-1.5">
                                    <input type="number" step="any" min="0" :value="montoDe(m).toFixed(2)" @change="editarMonto(m, $event.target.value)"
                                           class="w-full h-9 rounded-lg border-slate-200 text-right font-semibold">
                                </td>
                                <td class="hidden sm:table-cell text-center py-1.5 text-slate-500" x-text="num(m.comision) + '%'"></td>
                                <td class="hidden sm:table-cell text-right py-1.5 text-rose-600" x-text="soles(comisionDe(m))"></td>
                                <td class="text-right py-1.5 font-semibold" x-text="soles(montoDe(m) + comisionDe(m))"></td>
                                <td class="text-right">
                                    <button type="button" @click="quitarMedio(i)" class="p-1 rounded text-slate-300 hover:text-rose-600" aria-label="Quitar medio">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200">
                            <td class="pt-2 font-bold text-slate-500 uppercase text-xs">Total pagado</td>
                            <td colspan="4" class="pt-2 text-right text-lg font-extrabold whitespace-nowrap" x-text="soles(sumaMedios + comisionTotal)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <p x-show="carrito.length && Math.abs(diferencia) > 0.009" x-cloak class="mt-2 rounded-lg bg-amber-50 text-amber-800 text-sm px-3 py-2">
                    <strong x-text="diferencia > 0 ? 'Falta por pagar:' : 'Excede el total por:'"></strong> <span x-text="soles(Math.abs(diferencia))"></span>
                </p>
            </section>
        </div>

        {{-- ================= DERECHA: comprobante, cliente y totales ================= --}}
        <div class="lg:col-span-5 space-y-4 lg:sticky lg:top-0 min-w-0">

            <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold text-slate-500 uppercase">Comprobante</h2>
                    <span class="text-[11px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full font-semibold">Turno N° {{ $turno->turno }}</span>
                </div>
                <div class="grid gap-1.5 p-1 bg-slate-100 rounded-xl" :style="`grid-template-columns: repeat(${cfg.comprobantes.length}, minmax(0, 1fr))`">
                    <template x-for="c in cfg.comprobantes" :key="c.tdocod">
                        <button type="button" @click="elegirComprobante(c.tdocod)"
                                :class="tdocod === c.tdocod ? 'bg-white text-indigo-700 shadow' : 'text-slate-500 hover:text-slate-700'"
                                class="h-10 rounded-lg text-sm font-bold transition" x-text="nombreComprobante(c.tdocod)"></button>
                    </template>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <label>
                        <span class="text-xs font-semibold text-slate-500">F. Pago</span>
                        <select x-model="estadopago" @change="cambioEstadoPago()" class="mt-1 w-full h-10 rounded-xl border-slate-200 text-sm font-semibold">
                            <template x-for="e in cfg.estadopagos" :key="e.cre_dia_id">
                                <option :value="e.cre_dia_id" x-text="e.cre_dia_nom" :selected="e.cre_dia_id == estadopago"></option>
                            </template>
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-500">F. Emisión</span>
                        <input type="text" value="{{ now()->format('d/m/Y') }}" readonly class="mt-1 w-full h-10 rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-500">
                    </label>
                    <label x-show="!esContado" x-cloak>
                        <span class="text-xs font-semibold text-slate-500">F. Vencim.</span>
                        <input type="date" x-model="fecVen" :min="cfg.hoy" class="mt-1 w-full h-10 rounded-xl border-slate-200 text-sm">
                    </label>
                </div>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold text-slate-500 uppercase">Cliente</h2>
                    <button type="button" @click="nuevoCliente()" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white">Nuevo cliente</button>
                </div>
                <div class="flex gap-2">
                    <select x-model="cliente.tdicod" class="h-10 w-28 shrink-0 rounded-xl border-slate-200 text-sm" aria-label="Tipo de documento">
                        <template x-for="d in cfg.documentos" :key="d.tdicod">
                            <option :value="d.tdicod" x-text="d.tdides" :selected="d.tdicod === cliente.tdicod"></option>
                        </template>
                    </select>
                    <input x-ref="doc" type="text" inputmode="numeric" maxlength="15" x-model="cliente.num" @focus="if (esPortador) $event.target.select()"
                           @input="autoBuscarDoc()" @keydown.enter.prevent="buscarDoc()" @blur="blurDoc()"
                           placeholder="DNI / RUC" class="flex-1 min-w-0 h-10 rounded-xl border-slate-200 font-semibold" aria-label="Número de documento">
                    <button type="button" @click="buscarDoc()" :disabled="buscandoDoc" class="h-10 w-10 shrink-0 rounded-xl bg-indigo-600 text-white flex items-center justify-center disabled:opacity-60" aria-label="Buscar documento">
                        <svg x-show="!buscandoDoc" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                        <span x-show="buscandoDoc" x-cloak class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    </button>
                </div>
                <div class="relative" @click.outside="sugerencias = []">
                    <input type="text" x-model="cliente.nom" @input.debounce.250ms="sugerirClientes()" @focus="if (esPortador) $event.target.select()"
                           @keydown.arrow-down.prevent="sugActiva = Math.min(sugActiva + 1, sugerencias.length - 1)"
                           @keydown.arrow-up.prevent="sugActiva = Math.max(sugActiva - 1, 0)"
                           @keydown.enter.prevent="sugerencias[sugActiva] && usarCliente(sugerencias[sugActiva])"
                           placeholder="Nombre o razón social (escribe para buscar)" autocomplete="off"
                           class="w-full h-10 rounded-xl border-slate-200 text-sm uppercase font-semibold" aria-label="Nombre o razón social">
                    <ul x-show="sugerencias.length" x-cloak class="absolute z-20 left-0 right-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-black/5 max-h-56 overflow-y-auto">
                        <template x-for="(c, i) in sugerencias" :key="c.num">
                            <li @mousedown.prevent="usarCliente(c)" :class="i === sugActiva ? 'bg-indigo-50' : ''" class="px-3 py-2 cursor-pointer border-b border-slate-50 last:border-0">
                                <p class="text-sm font-semibold" x-text="c.nom"></p>
                                <p class="text-xs text-slate-400" x-text="(c.tdicod === '6' ? 'RUC ' : 'DOC ') + c.num"></p>
                            </li>
                        </template>
                    </ul>
                </div>
                <input type="text" x-model="cliente.dir" placeholder="Dirección" maxlength="150" class="w-full h-10 rounded-xl border-slate-200 text-sm" aria-label="Dirección">
                <p x-show="msgCliente.texto" x-cloak class="text-xs" :class="msgCliente.ok ? 'text-emerald-600' : 'text-rose-600'" x-text="msgCliente.texto"></p>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Total</p>
                        <p class="text-2xl font-extrabold text-indigo-700" x-text="num2(total)"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Comisión (+)</p>
                        <p class="text-2xl font-extrabold" :class="comisionTotal > 0 ? 'text-rose-600' : 'text-slate-300'" x-text="num2(comisionTotal)"></p>
                    </div>
                    <label>
                        <span class="text-xs font-semibold text-slate-500">Paga con</span>
                        <input type="number" step="any" min="0" x-model.number="paga" placeholder="0.00" :disabled="!esContado"
                               class="w-full h-10 rounded-xl border-2 border-emerald-300 text-right text-lg font-bold focus:ring-emerald-200 focus:border-emerald-500 disabled:bg-slate-50">
                    </label>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Vuelto</p>
                        <p class="text-2xl font-extrabold" :class="paga > 0 && paga < total ? 'text-rose-600' : 'text-emerald-600'" x-text="paga > 0 ? num2(paga - total) : '0.00'"></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                    <button type="button" @click="registrar(true)" :disabled="procesando"
                            class="h-14 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold tracking-wide shadow disabled:opacity-50">
                        IMPRIMIR<span class="block text-[10px] font-semibold opacity-80">F9</span>
                    </button>
                    <button type="button" @click="registrar(false)" :disabled="procesando"
                            class="h-14 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-extrabold tracking-wide shadow disabled:opacity-50">
                        REGISTRAR<span class="block text-[10px] font-semibold opacity-80">F10</span>
                    </button>
                    <div data-fidelizacion data-doc="cliente.num" data-total="total"></div>
                    <button type="button" @click="guardarProforma()" :disabled="procesando" title="Guardar como proforma (no emite comprobante)"
                            class="h-14 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-extrabold tracking-wide shadow disabled:opacity-50">
                        <span x-text="proformaId ? 'GUARDAR PROF.' : 'PROFORMA'"></span><span class="block text-[10px] font-semibold opacity-80">F8</span>
                    </button>
                    <button type="button" @click="cancelar()" :disabled="procesando"
                            class="h-14 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-extrabold tracking-wide shadow disabled:opacity-50">
                        CANCELAR
                    </button>
                </div>
                <p x-show="procesando" x-cloak class="text-center text-sm text-slate-500 mt-2">Registrando…</p>

                {{-- Última proforma --}}
                <template x-if="ultimaProforma">
                    <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-violet-50 px-3 py-2 text-sm">
                        <span class="text-violet-800">Proforma: <strong x-text="ultimaProforma.numero"></strong> · <span x-text="soles(ultimaProforma.total)"></span></span>
                        <span class="flex gap-2 shrink-0">
                            <button type="button" @click="imprimirProforma()" class="text-xs font-semibold text-violet-700 hover:underline">Imprimir</button>
                            <a :href="ultimaProforma.imprimir" target="_blank" class="text-xs font-semibold text-violet-700 hover:underline">Ver</a>
                            <a href="{{ route('proformas.index') }}" class="text-xs font-semibold text-violet-700 hover:underline">Todas</a>
                        </span>
                    </div>
                </template>

                {{-- Última venta --}}
                <template x-if="ultima">
                    <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">Última: <strong x-text="ultima.numero"></strong> · <span x-text="soles(ultima.total)"></span>
                            <template x-if="ultima.vuelto > 0"><span class="text-emerald-600 font-semibold" x-text="' · Vuelto ' + soles(ultima.vuelto)"></span></template>
                        </span>
                        <span class="flex gap-2 shrink-0">
                            <button type="button" @click="imprimirUltima()" class="text-xs font-semibold text-indigo-700 hover:underline">Reimprimir</button>
                            <a :href="ultima.ticket.replace('embed=1', 'embed=0')" target="_blank" class="text-xs font-semibold text-indigo-700 hover:underline">Ver</a>
                        </span>
                    </div>
                </template>
            </section>
        </div>
    </div>

    {{-- Modal: elegir presentación al seleccionar un producto que se vende de varias formas --}}
    <div x-show="elegir" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-center justify-center p-4" @click.self="cerrarElegir()">
        <div x-show="elegir" x-transition class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="px-6 pt-5 pb-3">
                <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">¿Cómo lo vendes?</p>
                <p class="text-xl font-extrabold text-slate-800 leading-tight" x-text="elegir?.p.nombre"></p>
                <p class="text-xs text-slate-400 mt-1">Flechas + Enter, o el número de la opción · Esc para cancelar</p>
            </div>
            <div class="px-4 pb-4 space-y-2 max-h-[60vh] overflow-y-auto">
                <template x-for="(op, i) in (elegir?.opciones || [])" :key="i">
                    <button type="button" @click="elegirOpcion(i)" @mouseenter="elegirActivo = i"
                            :class="i === elegirActivo ? 'bg-indigo-600 text-white ring-4 ring-indigo-200' : 'bg-slate-50 text-slate-800 hover:bg-slate-100'"
                            class="w-full flex items-center gap-4 px-4 py-3 rounded-2xl text-left transition">
                        <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center font-extrabold"
                              :class="i === elegirActivo ? 'bg-white/20' : 'bg-white shadow-sm text-indigo-600'" x-text="i + 1"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-lg font-bold leading-tight" x-text="op ? op.nombre : (elegir?.p.unidad || 'Unidad')"></span>
                            <span class="block text-xs opacity-75"
                                  x-text="(op ? 'Trae ' + num(op.factor) + ' ' + (elegir?.p.unidad || '') : 'Unidad base')
                                          + (elegir?.p.stock !== null && elegir?.p.stock !== undefined ? ' · Stock ' + num((elegir.p.stock) / (op ? op.factor : 1)) : '')"></span>
                        </span>
                        <span class="text-xl font-extrabold whitespace-nowrap" x-text="soles(op ? op.precio : elegir?.p.precio)"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Impresión del ticket sin salir de la pantalla --}}
    <iframe x-ref="impresion" class="hidden" title="Impresión"></iframe>

    {{-- Avisos --}}
    <div class="fixed top-16 right-4 z-[80] flex flex-col items-end gap-2 pointer-events-none">
        <template x-for="t in avisos" :key="t.id">
            <div class="pointer-events-auto max-w-sm rounded-xl px-4 py-3 text-sm font-semibold shadow-xl"
                 :class="t.tipo === 'error' ? 'bg-rose-600 text-white' : (t.tipo === 'ok' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-white')"
                 x-text="t.texto"></div>
        </template>
    </div>
</div>
<script src="{{ asset('js/fidelizacion-pos.js') }}?v={{ filemtime(public_path('js/fidelizacion-pos.js')) }}" data-previa="{{ route('fidelizacion.previa') }}" data-reservar="{{ route('fidelizacion.reservar') }}" data-csrf="{{ csrf_token() }}"></script>
@endsection

@push('scripts')
<script>
    window.PV = {
        usuario: @json(auth()->user()->IdUsuario),
        csrf: @json(csrf_token()),
        comprobantes: @json($comprobantes),
        tdocodPred: @json($negocio->tdocod_pred ?? '13'),
        estadopagos: @json($estadopagos),
        medios: @json($mediospagos),
        documentos: @json($documentos),
        hoy: @json(now()->format('Y-m-d')),
        farmacia: @json($farmacia),
        diasAlerta: @json($diasAlerta),
        proforma: @json($proforma),
        rutas: {
            proforma: @json(route('proformas.guardar')),
            productos: @json(route('pos.productos')),
            registrar: @json($farmacia ? route('pv.farmacia.registrar') : route('pv.registrar')),
            clientes: @json(route('cobros.clientes')),
            cliente: @json(url('cobros/cliente')),
        },
    };
</script>
<script src="{{ asset('js/impresion.js') }}?v={{ filemtime(public_path('js/impresion.js')) }}" data-url="{{ url('impresion/comprobante') }}" data-csrf="{{ csrf_token() }}"></script>
<script src="{{ asset('js/punto-venta.js') }}?v={{ filemtime(public_path('js/punto-venta.js')) }}"></script>
@endpush
