@extends('layouts.app')
@section('title', $compra ? 'Compra ' . $compra['serie'] . '-' . $compra['numero'] : 'Nueva compra')

@section('content')
<div x-data="compraForm" x-init="iniciar()" @keydown.window="teclaGlobal($event)" class="max-w-[1600px] mx-auto space-y-4">

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('compras.index') }}" class="text-sm text-slate-500 hover:text-indigo-700">← Compras</a>
        <h1 class="text-lg font-bold flex-1" x-text="titulo"></h1>
        <template x-if="!soloLectura">
            <div class="flex gap-2">
                <a href="{{ route('compras.index') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-white border border-slate-300 text-sm font-semibold">Cancelar</a>
                <button type="button" @click="guardar()" :disabled="guardando"
                        class="h-10 px-5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold disabled:opacity-50">
                    <span x-text="guardando ? 'Guardando…' : (cfg.compra ? 'Guardar cambios' : 'Registrar compra') + '  (F9)'"></span>
                </button>
            </div>
        </template>
    </div>

    <div x-show="soloLectura" x-cloak class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
        Esta compra está <strong>anulada</strong>: su mercadería ya salió del stock. Solo se puede consultar.
    </div>

    <fieldset :disabled="soloLectura" class="grid xl:grid-cols-12 gap-4">
        {{-- Documento --}}
        <section class="xl:col-span-7 bg-white rounded-2xl shadow-sm p-4">
            <h2 class="text-xs font-bold text-slate-500 uppercase mb-3">Documento</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label class="col-span-2 sm:col-span-1 text-xs font-semibold text-slate-500">Tipo
                    <select x-model="doc.tdocod" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm font-semibold">
                        <template x-for="(nombre, cod) in cfg.documentos" :key="cod">
                            <option :value="cod" x-text="nombre" :selected="cod === doc.tdocod"></option>
                        </template>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">Serie
                    <input x-model="doc.serie" maxlength="4" placeholder="F001" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm font-semibold uppercase">
                </label>
                <label class="text-xs font-semibold text-slate-500">Número
                    <input x-model="doc.numero" maxlength="8" inputmode="numeric" placeholder="123" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm font-semibold">
                </label>
                <label class="text-xs font-semibold text-slate-500">Almacén
                    <select x-model="doc.id_almacen" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm">
                        <template x-for="a in cfg.almacenes" :key="a.id_almacen">
                            <option :value="a.id_almacen" x-text="a.descripcion" :selected="a.id_almacen == doc.id_almacen"></option>
                        </template>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">F. Emisión
                    <input type="date" x-model="doc.fecEmi" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-500" title="Fecha en la que la mercadería entra al kardex">F. Ingreso mercadería
                    <input type="date" x-model="doc.fecIng" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-500">F. Pago
                    <select x-model="doc.estadopago" @change="cambioPago()" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm font-semibold">
                        <template x-for="e in cfg.estadopagos" :key="e.cre_dia_id">
                            <option :value="e.cre_dia_id" x-text="e.cre_dia_nom" :selected="e.cre_dia_id == doc.estadopago"></option>
                        </template>
                    </select>
                </label>
                <label x-show="!esContado" x-cloak class="text-xs font-semibold text-slate-500">F. Vencimiento
                    <input type="date" x-model="doc.fecVen" :min="doc.fecEmi" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-500">Moneda
                    <select x-model="doc.moneda" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm">
                        <option value="PEN">Soles (S/)</option>
                        <option value="USD">Dólares ($)</option>
                    </select>
                </label>
                <label x-show="doc.moneda === 'USD'" x-cloak class="text-xs font-semibold text-slate-500">Tipo de cambio
                    <input type="number" step="0.001" min="0" x-model.number="doc.tip_cam" class="mt-1 w-full h-10 rounded-xl border-slate-300 text-sm text-right">
                </label>
            </div>
        </section>

        {{-- Proveedor --}}
        <section class="xl:col-span-5 bg-white rounded-2xl shadow-sm p-4 space-y-2">
            <h2 class="text-xs font-bold text-slate-500 uppercase">Proveedor</h2>
            <div class="flex gap-2">
                <select x-model="prov.tdicod" class="h-10 w-28 shrink-0 rounded-xl border-slate-300 text-sm" aria-label="Tipo de documento">
                    <template x-for="d in cfg.documentosIdentidad" :key="d.tdicod">
                        <option :value="d.tdicod" x-text="d.tdides" :selected="d.tdicod === prov.tdicod"></option>
                    </template>
                </select>
                <input x-ref="provNum" x-model="prov.num" @input="autoBuscarProv()" @keydown.enter.prevent="buscarProv()" maxlength="11" inputmode="numeric"
                       placeholder="RUC / DNI" class="flex-1 min-w-0 h-10 rounded-xl border-slate-300 font-semibold" aria-label="Documento del proveedor">
                <button type="button" @click="buscarProv()" :disabled="buscandoProv" class="h-10 w-10 shrink-0 rounded-xl bg-indigo-600 text-white flex items-center justify-center disabled:opacity-60" aria-label="Buscar proveedor">
                    <svg x-show="!buscandoProv" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                    <span x-show="buscandoProv" x-cloak class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                </button>
            </div>
            <div class="relative" @click.outside="sugerencias = []">
                <input x-model="prov.nom" @input.debounce.250ms="sugerirProv()" autocomplete="off" placeholder="Nombre o razón social (escribe para buscar)"
                       @keydown.arrow-down.prevent="sugActiva = Math.min(sugActiva + 1, sugerencias.length - 1)" @keydown.arrow-up.prevent="sugActiva = Math.max(sugActiva - 1, 0)"
                       @keydown.enter.prevent="sugerencias[sugActiva] && usarProv(sugerencias[sugActiva])"
                       class="w-full h-10 rounded-xl border-slate-300 text-sm font-semibold uppercase" aria-label="Nombre del proveedor">
                <ul x-show="sugerencias.length" x-cloak class="absolute z-20 left-0 right-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-black/5 max-h-56 overflow-y-auto">
                    <template x-for="(p, i) in sugerencias" :key="p.num">
                        <li @mousedown.prevent="usarProv(p)" :class="i === sugActiva ? 'bg-indigo-50' : ''" class="px-3 py-2 cursor-pointer border-b border-slate-50 last:border-0">
                            <p class="text-sm font-semibold" x-text="p.nom"></p>
                            <p class="text-xs text-slate-400" x-text="p.num"></p>
                        </li>
                    </template>
                </ul>
            </div>
            <input x-model="prov.dir" placeholder="Dirección" class="w-full h-10 rounded-xl border-slate-300 text-sm" aria-label="Dirección del proveedor">
            <p x-show="msgProv.texto" x-cloak class="text-xs" :class="msgProv.ok ? 'text-emerald-600' : 'text-rose-600'" x-text="msgProv.texto"></p>
        </section>

        {{-- Detalle --}}
        <section class="xl:col-span-12 bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 flex flex-wrap items-center gap-3 border-b border-slate-100">
                <div class="relative flex-1 min-w-[260px]" @click.outside="resultadosAbiertos = false">
                    <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                    <input x-ref="buscador" type="search" x-model="busqueda" autocomplete="off" @input.debounce.200ms="buscar()"
                           @keydown.enter.prevent="enterBuscador()" @keydown.arrow-down.prevent="moverResultado(1)" @keydown.arrow-up.prevent="moverResultado(-1)" @keydown.escape="resultadosAbiertos = false"
                           placeholder="Buscar producto o insumo, o escanear código…  (F2)"
                           class="w-full h-12 pl-10 pr-3 rounded-xl border-slate-300 text-lg font-semibold focus:ring-indigo-300 focus:border-indigo-400">
                    <div x-show="resultadosAbiertos" x-cloak class="absolute z-30 left-0 right-0 mt-1 bg-white rounded-xl shadow-2xl ring-2 ring-indigo-400 max-h-[55vh] overflow-y-auto">
                        <template x-for="(p, i) in resultados" :key="p.id">
                            <button type="button" @click="agregar(p)" @mouseenter="resultadoActivo = i"
                                    :class="i === resultadoActivo ? 'bg-indigo-600 text-white' : ''" class="w-full flex items-center gap-3 px-4 py-2.5 text-left border-b border-slate-100 last:border-0">
                                <span class="flex-1 min-w-0">
                                    <span class="block font-bold" x-text="p.nombre"></span>
                                    <span class="block text-xs opacity-70" x-text="p.codigo + (p.insumo ? ' · INSUMO' : '') + (p.control_lote ? ' · LOTE' : '') + ' · Stock ' + num(p.stock)"></span>
                                </span>
                                <span class="text-sm font-semibold whitespace-nowrap" x-text="'Últ. costo ' + num2(p.costo)"></span>
                            </button>
                        </template>
                        <p x-show="!resultados.length && !buscando" class="px-4 py-5 text-center text-slate-400 text-sm">Sin resultados (combos y preparados no se compran).</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" x-model="conLote" class="rounded text-indigo-600"> Lote y vencimiento
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800 text-white text-[11px] uppercase">
                        <tr>
                            <th class="px-2 py-2 w-28">Tipo IGV</th>
                            <th class="text-left px-2 py-2 min-w-[200px]">Producto</th>
                            <th class="px-2 py-2 w-20">Cant.</th>
                            <th class="px-2 py-2 w-24 bg-lime-700/60">Costo s/IGV</th>
                            <th class="px-2 py-2 w-24 bg-amber-600/60">Costo U.</th>
                            <th class="px-2 py-2 w-24 bg-lime-700/60">Subtotal</th>
                            <th class="px-2 py-2 w-28 bg-amber-600/60">Total</th>
                            <th class="px-2 py-2 w-20">Flete total</th>
                            <th class="px-2 py-2 w-20">Flete und.</th>
                            <th class="px-2 py-2 w-24" title="Costo unitario + flete por unidad (en soles): es el que se guarda en el producto">Costo final</th>
                            <th x-show="conLote" class="px-2 py-2 w-28">Lote</th>
                            <th x-show="conLote" class="px-2 py-2 w-36">Vence</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(it, i) in items" :key="it.key">
                            <tr :class="it.flash ? 'bg-emerald-50' : ''" class="transition-colors duration-500">
                                <td class="px-2 py-1">
                                    <select x-model="it.tip_igv" class="w-full h-9 rounded-lg border-slate-200 text-xs">
                                        <template x-for="(nombre, cod) in cfg.tiposIgv" :key="cod">
                                            <option :value="cod" x-text="nombre" :selected="cod === it.tip_igv"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="px-2 py-1">
                                    <span class="block font-semibold leading-tight" x-text="it.nombre"></span>
                                    <span x-show="it.presentacion_nombre" class="inline-block mt-0.5 px-2 py-0.5 rounded-lg bg-indigo-50 text-[11px] font-bold text-indigo-800" x-text="'📦 ' + it.presentacion_nombre + ' (entran ' + num(it.cantidad * it.factor) + ')'"></span>
                                    <span class="block text-[11px] text-slate-400"><span x-text="it.codigo"></span><span x-show="it.control_lote" class="ml-1 px-1.5 rounded bg-teal-100 text-teal-700 font-semibold">LOTE</span></span>
                                </td>
                                <td class="px-2 py-1"><input type="number" step="any" min="0.01" :id="'cant-' + it.key" x-model.number="it.cantidad" @keydown.enter.prevent="enfocar('costo-' + it.key)"
                                                             class="w-full h-9 rounded-lg border-slate-200 text-center font-bold"></td>
                                <td class="px-2 py-1 bg-lime-50"><input type="number" step="any" min="0" :value="fmt4(valUni(it))" @change="setValUni(it, $event.target.value)"
                                                                        class="w-full h-9 rounded-lg border-lime-200 bg-transparent text-right"></td>
                                <td class="px-2 py-1 bg-amber-50"><input type="number" step="any" min="0" :id="'costo-' + it.key" x-model.number="it.costo" @keydown.enter.prevent="$refs.buscador.focus()"
                                                                         class="w-full h-9 rounded-lg border-amber-200 bg-transparent text-right font-semibold"></td>
                                <td class="px-2 py-1 bg-lime-50"><input type="number" step="any" min="0" :value="subtotal(it).toFixed(2)" @change="setSubtotal(it, $event.target.value)"
                                                                        class="w-full h-9 rounded-lg border-lime-200 bg-transparent text-right"></td>
                                <td class="px-2 py-1 bg-amber-50"><input type="number" step="any" min="0" :value="total(it).toFixed(2)" @change="setTotal(it, $event.target.value)"
                                                                         class="w-full h-9 rounded-lg border-amber-200 bg-transparent text-right font-bold"></td>
                                <td class="px-2 py-1"><input type="number" step="any" min="0" x-model.number="it.flete" class="w-full h-9 rounded-lg border-slate-200 text-right"></td>
                                <td class="px-2 py-1 text-right text-slate-500" x-text="num2(fleteUnd(it))"></td>
                                <td class="px-2 py-1 text-right font-bold text-indigo-700" x-text="num2(costoFinal(it))"></td>
                                <td x-show="conLote" class="px-2 py-1"><input x-model="it.lote" maxlength="50" :placeholder="it.control_lote ? 'Obligatorio' : ''"
                                    :class="it.control_lote && !String(it.lote || '').trim() ? 'border-rose-300 bg-rose-50' : 'border-slate-200'" class="w-full h-9 rounded-lg text-xs uppercase"></td>
                                <td x-show="conLote" class="px-2 py-1"><input type="date" x-model="it.vencimiento" :min="doc.fecIng"
                                    :class="it.control_lote && !it.vencimiento ? 'border-rose-300 bg-rose-50' : 'border-slate-200'" class="w-full h-9 rounded-lg text-xs"></td>
                                <td class="pr-2 text-right">
                                    <button type="button" @click="items.splice(i, 1)" class="p-1 rounded text-slate-300 hover:text-rose-600" aria-label="Quitar">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!items.length" class="py-10 text-center text-slate-400 text-sm">
                    Busca o escanea los productos de la factura. Enter en cantidad → costo → vuelve al buscador.
                </p>
            </div>

            <div class="grid sm:grid-cols-[1fr_auto] gap-4 p-4 border-t border-slate-100 bg-slate-50/60">
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-slate-500">Observaciones
                        <textarea x-model="doc.observaciones" rows="2" maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" x-model="actualizarCosto" class="rounded text-indigo-600">
                        Actualizar el costo de los productos con el <strong>costo final</strong> de esta compra
                    </label>
                </div>
                <dl class="grid grid-cols-5 sm:grid-cols-1 gap-x-6 gap-y-1 text-sm min-w-[220px]">
                    <div class="sm:flex sm:justify-between"><dt class="text-slate-500 text-xs sm:text-sm">Gravada</dt><dd class="font-semibold" x-text="num2(totales.grav)"></dd></div>
                    <div class="sm:flex sm:justify-between"><dt class="text-slate-500 text-xs sm:text-sm">Exonerada</dt><dd class="font-semibold" x-text="num2(totales.exo)"></dd></div>
                    <div class="sm:flex sm:justify-between"><dt class="text-slate-500 text-xs sm:text-sm">Inafecta</dt><dd class="font-semibold" x-text="num2(totales.inaf)"></dd></div>
                    <div class="sm:flex sm:justify-between"><dt class="text-slate-500 text-xs sm:text-sm">IGV</dt><dd class="font-semibold" x-text="num2(totales.igv)"></dd></div>
                    <div class="sm:flex sm:justify-between sm:border-t sm:border-slate-200 sm:pt-1"><dt class="text-slate-700 font-bold">Total</dt>
                        <dd class="text-xl font-extrabold text-indigo-700" x-text="(doc.moneda === 'USD' ? '$ ' : 'S/ ') + num2(totales.total)"></dd></div>
                </dl>
            </div>
        </section>
    </fieldset>

    <div class="fixed top-16 right-4 z-[80] flex flex-col items-end gap-2 pointer-events-none">
        <template x-for="t in avisos" :key="t.id">
            <div class="pointer-events-auto max-w-sm rounded-xl px-4 py-3 text-sm font-semibold shadow-xl"
                 :class="t.tipo === 'error' ? 'bg-rose-600 text-white' : 'bg-slate-800 text-white'" x-text="t.texto"></div>
        </template>
    </div>
</div>
    {{-- Modal: elegir presentación al seleccionar un producto que se compra de varias formas --}}
    <div x-show="elegir" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-center justify-center p-4" @click.self="elegir = null">
        <div x-show="elegir" x-transition class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">¿Cómo lo compras?</p>
            <p class="text-lg font-extrabold text-slate-800 leading-tight mb-1" x-text="elegir?.nombre"></p>
            <p class="text-xs text-slate-400 mb-3">Elige la presentación (o su número). Esc para cancelar.</p>
            <div class="space-y-2 max-h-[60vh] overflow-y-auto">
                <button type="button" @click="agregar(elegir, null)" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-50 hover:bg-slate-100 text-left">
                    <span class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center font-bold text-indigo-600">1</span>
                    <span class="flex-1"><span class="block font-bold" x-text="elegir?.unidad || 'Unidad'"></span><span class="block text-xs text-slate-500">Unidad base</span></span>
                    <span class="text-sm font-bold text-slate-600" x-text="'Costo S/ ' + num(elegir?.costo)"></span>
                </button>
                <template x-for="(pr, k) in (elegir?.presentaciones || [])" :key="pr.id">
                    <button type="button" @click="agregar(elegir, pr)" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-left">
                        <span class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center font-bold text-indigo-600" x-text="k + 2"></span>
                        <span class="flex-1"><span class="block font-bold text-indigo-900" x-text="pr.nombre"></span>
                            <span class="block text-xs text-indigo-500" x-text="'Trae ' + num(pr.factor) + ' ' + (elegir?.unidad || '') + ' · entran ' + num(pr.factor) + ' al stock por cada una'"></span></span>
                        <span class="text-sm font-bold text-indigo-700" x-text="'S/ ' + num((elegir?.costo || 0) * pr.factor)"></span>
                    </button>
                </template>
            </div>
            <button type="button" @click="elegir = null" class="mt-3 w-full h-11 rounded-xl bg-slate-200 font-bold">Cancelar</button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    window.COMPRA = {
        csrf: @json(csrf_token()),
        compra: @json($compra),
        documentos: @json($documentos),
        tiposIgv: @json($tiposIgv),
        tipIgvPred: @json($tipIgvPred),
        estadopagos: @json($estadopagos),
        almacenes: @json($almacenes),
        documentosIdentidad: @json($documentosIdentidad),
        factorIgv: @json(\App\Support\Comprobante::FACTOR_IGV),
        hoy: @json(now()->toDateString()),
        rutas: {
            guardar: @json($compra ? route('compras.update', $compra['id']) : route('compras.store')),
            metodo: @json($compra ? 'PUT' : 'POST'),
            productos: @json(route('compras.productos')),
            proveedores: @json(route('compras.proveedores')),
            proveedor: @json(url('compras/proveedor')),
        },
    };
</script>
<script src="{{ asset('js/compras.js') }}?v={{ filemtime(public_path('js/compras.js')) }}"></script>
@endpush
