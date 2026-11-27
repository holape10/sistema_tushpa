@extends('layouts.app')
@section('title', 'Venta de pasajes')
@section('content')
    @php
        $categorias = \App\Support\Transportes::CATEGORIAS;
    @endphp
    <div class="space-y-4" x-data="ventaPasajes(@js([
        'plano' => $datos['plano'] ?? null, 'activos' => $datos['activos'] ?? [], 'precios' => $datos['precios'] ?? [],
        'paradas' => $datos['paradas'] ?? [], 'otras' => $datos['otras'] ?? [], 'web' => $web, 'salida' => $elegida->id ?? 0,
    ]))" x-init="iniciar()">
        @include('empresas.transportes.partials.nav')
        @include('empresas.partials.alert')

        <div class="grid lg:grid-cols-12 gap-4 items-start">
            <div class="lg:col-span-4 space-y-4">
                {{-- Reservas por internet: la caja llama al cliente, confirma su pago y la cobra --}}
                <section x-show="web.length" x-cloak class="bg-amber-50 rounded-2xl shadow-sm ring-2 ring-amber-300 overflow-hidden">
                    <h2 class="px-4 py-3 border-b border-amber-200 font-black text-amber-900"><i class="fas fa-globe"></i> Reservas por internet
                        <span class="ml-1 px-2 rounded-full bg-amber-500 text-white text-xs" x-text="web.length"></span></h2>
                    <div class="divide-y divide-amber-200 max-h-80 overflow-y-auto">
                        <template x-for="r in web" :key="r.grupo">
                            <div class="px-4 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <b class="flex-1 text-gray-800" x-text="r.nombre"></b>
                                    <span class="font-black" x-text="'S/ ' + r.total.toFixed(2)"></span>
                                </div>
                                <p class="text-xs text-gray-600" x-text="r.ruta + ' · ' + r.salida + ' · ' + r.pasajes + ' asiento(s)'"></p>
                                <p class="text-xs text-gray-600"><span x-text="r.medio"></span><span x-show="r.operacion" x-text="' · Op. ' + r.operacion"></span>
                                    · vence <span x-text="r.vence"></span></p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <a :href="'tel:' + r.telefono" class="h-8 px-3 inline-flex items-center rounded-lg bg-white text-xs font-bold text-emerald-700 ring-1 ring-emerald-300"><i class="fas fa-phone mr-1"></i> <span x-text="r.telefono"></span></a>
                                    <button type="button" @click="abrirCobro(r)" class="h-8 px-3 rounded-lg bg-emerald-600 text-xs font-bold text-white">Pago confirmado: cobrar</button>
                                    <button type="button" @click="liberar(r.grupo)" class="h-8 px-3 rounded-lg bg-white text-xs font-bold text-rose-600 ring-1 ring-rose-200">Liberar</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>

                {{-- Salidas próximas --}}
                <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <h2 class="px-4 py-3 border-b font-black text-gray-800"><i class="fas fa-calendar-days text-indigo-600"></i> Salidas</h2>
                    <div class="max-h-[70vh] overflow-y-auto divide-y divide-gray-100">
                        @forelse ($proximas as $s)
                            @php $lleno = (int) $s->vendidos >= (int) $s->asientos; @endphp
                            <a href="{{ route('transportes.index', ['salida' => $s->id]) }}"
                               class="block px-4 py-3 {{ $elegida && $elegida->id === $s->id ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : 'hover:bg-gray-50' }}">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg font-black text-gray-800">{{ \Carbon\Carbon::parse($s->salida)->format('H:i') }}</span>
                                    <span class="text-xs font-semibold text-gray-500">{{ \Carbon\Carbon::parse($s->salida)->isToday() ? 'Hoy' : \Carbon\Carbon::parse($s->salida)->translatedFormat('D d/m') }}</span>
                                    <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-bold {{ $lleno ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $s->vendidos }}/{{ $s->asientos }}</span>
                                </div>
                                <p class="font-bold text-gray-700">{{ $s->origen }} → {{ $s->destino }}</p>
                                <p class="text-xs text-gray-500"><i class="fas {{ \App\Support\Transportes::TIPOS[$s->tipo][1] ?? 'fa-bus' }}"></i> {{ $s->unidad }}{{ $s->placa ? ' · '.$s->placa : '' }} · desde S/ {{ number_format($s->precio, 2) }}</p>
                            </a>
                        @empty
                            <p class="p-6 text-center text-sm text-gray-400">No hay salidas programadas en los próximos días.
                                @if (Auth::user()->esAdmin())<a href="{{ route('transportes.salidas') }}" class="block mt-2 font-bold text-indigo-600">Programar una salida →</a>@endif</p>
                        @endforelse
                    </div>
                </section>
            </div>

            {{-- Plano y venta --}}
            <section class="lg:col-span-8">
                @if (! $elegida)
                    <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">Elige una salida para ver sus asientos.</div>
                @else
                    <div class="space-y-4">
                        <div class="bg-white rounded-2xl shadow-sm p-4">
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <div class="flex-1 min-w-[12rem]">
                                    <p class="text-lg font-black text-gray-800">{{ $elegida->origen }} → {{ $elegida->destino }}</p>
                                    <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($elegida->salida)->format('d/m/Y H:i') }} · {{ $elegida->unidad }}{{ $elegida->conductor ? ' · '.$elegida->conductor : '' }}</p>
                                </div>
                                <a href="{{ route('transportes.manifiesto', $elegida->id) }}" target="_blank" class="h-9 px-3 inline-flex items-center rounded-xl bg-slate-100 text-sm font-bold text-slate-700"><i class="fas fa-clipboard-list mr-1"></i> Manifiesto</a>
                            </div>
                            {{-- Tramo: de qué parada a qué parada (solo si la ruta tiene paradas) --}}
                            <div x-show="paradas.length > 2" class="flex flex-wrap items-end gap-2 mb-3">
                                <label class="text-xs font-semibold text-gray-500">Sube en
                                    <select x-model.number="desde" @change="cambioTramo()" class="mt-1 h-10 rounded-lg border-gray-300 text-sm">
                                        <template x-for="(p, i) in paradas.slice(0, -1)" :key="i"><option :value="i" x-text="p" :selected="i === desde"></option></template>
                                    </select></label>
                                <label class="text-xs font-semibold text-gray-500">Baja en
                                    <select x-model.number="hasta" @change="cambioTramo()" class="mt-1 h-10 rounded-lg border-gray-300 text-sm">
                                        <template x-for="(p, i) in paradas" :key="i"><option :value="i" x-text="p" x-show="i > desde" :selected="i === hasta"></option></template>
                                    </select></label>
                                <span x-show="!completo" class="pb-2 text-xs font-bold text-indigo-700">Tramo: escribe el precio de cada pasaje</span>
                            </div>
                            <div class="flex flex-wrap gap-3 text-xs font-semibold text-gray-600 mb-3">
                                <span><span class="inline-block w-3 h-3 rounded bg-emerald-100 ring-1 ring-emerald-400 align-middle"></span> Libre</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-indigo-600 align-middle"></span> Elegido</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-amber-300 align-middle"></span> Reservado</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-rose-200 align-middle"></span> Vendido</span>
                                <template x-for="(precio, cat) in precios" :key="cat">
                                    <span class="px-2 rounded bg-slate-100" x-text="(categorias[cat] || cat) + ': S/ ' + precio.toFixed(2)"></span>
                                </template>
                            </div>
                            <p x-show="cambiando" x-cloak class="mb-3 rounded-xl bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-800">
                                Toca el asiento nuevo para <span x-text="cambiando?.nombre"></span>
                                <button type="button" @click="cambiando = null" class="ml-2 underline">cancelar</button></p>
                            {{-- Pisos o cubiertas, lado a lado --}}
                            <div class="flex flex-wrap gap-6 justify-center">
                                <template x-for="(piso, pi) in plano.pisos" :key="pi">
                                    <div>
                                        <p class="text-center text-xs font-black text-gray-500 uppercase mb-2" x-text="piso.nombre"></p>
                                        <div class="inline-grid gap-1.5 rounded-3xl border-2 border-slate-200 bg-slate-50 p-3" :style="`grid-template-columns: repeat(${piso.columnas}, 2.75rem)`">
                                            <template x-for="(celda, k) in piso.celdas.flat()" :key="pi + '-' + k">
                                                <button type="button" @click="tocar(celda)" :disabled="!esAsiento(celda)" :title="titulo(celda)"
                                                        class="h-11 rounded-xl flex items-center justify-center text-xs transition"
                                                        :class="claseDe(celda)">
                                                    @include('empresas.transportes.partials.asiento')
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- El pasaje tocado: reserva (cobrar o liberar) o vendido (cambiar o boleto) --}}
                        <div x-show="detalle" x-cloak class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="flex-1">
                                    <p class="font-black text-gray-800" x-text="detalle ? 'Asiento ' + detalle.asiento + ' · ' + detalle.nombre : ''"></p>
                                    <p class="text-sm text-gray-500" x-text="detalle ? detalle.doc + (detalle.telefono ? ' · ' + detalle.telefono : '') + ' · ' + tramoDe(detalle) : ''"></p>
                                    <p class="text-sm font-bold" :class="detalle?.estado === 'RESERVADO' ? 'text-amber-700' : 'text-rose-700'"
                                       x-text="detalle ? (detalle.estado === 'RESERVADO' ? 'Reservado' + (detalle.origen === 'WEB' ? ' por internet' : '') + ' hasta ' + detalle.vence : 'Vendido · ' + (detalle.comprobante || '') + (detalle.subio ? ' · ya subió' : '')) : ''"></p>
                                </div>
                                <button type="button" @click="detalle = null; mover = false" class="h-8 w-8 rounded-lg bg-slate-100 text-slate-500"><i class="fas fa-xmark"></i></button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template x-if="detalle && detalle.estado === 'RESERVADO'">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="abrirCobro(detalle)" class="h-10 px-4 rounded-xl bg-emerald-600 text-sm font-bold text-white">Cobrar reserva</button>
                                        <button type="button" @click="liberar(detalle.grupo)" class="h-10 px-4 rounded-xl bg-rose-50 text-sm font-bold text-rose-700">Liberar</button>
                                    </div>
                                </template>
                                <template x-if="detalle && !detalle.subio">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="cambiando = detalle; detalle = null" class="h-10 px-4 rounded-xl bg-indigo-50 text-sm font-bold text-indigo-700">Cambiar de asiento</button>
                                        <button type="button" @click="mover = !mover" class="h-10 px-4 rounded-xl bg-indigo-50 text-sm font-bold text-indigo-700">Cambiar de fecha</button>
                                    </div>
                                </template>
                                <a x-show="detalle" :href="detalle ? boletoUrl + '/' + detalle.grupo : '#'" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-slate-100 text-sm font-bold text-slate-700"><i class="fas fa-qrcode mr-1"></i> Boleto</a>
                            </div>
                            <div x-show="mover" class="flex flex-wrap items-end gap-2 rounded-xl bg-slate-50 p-3">
                                <label class="text-xs font-semibold text-gray-500">Salida
                                    <select x-model.number="moverSalida" class="mt-1 h-10 rounded-lg border-gray-300 text-sm">
                                        <template x-for="o in otras" :key="o.id"><option :value="o.id" x-text="o.texto" :selected="o.id === moverSalida"></option></template>
                                    </select></label>
                                <label class="text-xs font-semibold text-gray-500">Asiento
                                    <input x-model="moverAsiento" maxlength="8" class="mt-1 w-24 h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                                <button type="button" @click="cambiar(detalle, moverSalida, moverAsiento)" class="h-10 px-4 rounded-xl bg-indigo-600 text-sm font-bold text-white">Guardar cambio</button>
                            </div>
                        </div>

                        {{-- Pasajeros y cobro --}}
                        <div x-show="seleccion.length" x-cloak class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                            <h3 class="font-black text-gray-800">Pasajeros <span class="text-sm font-semibold text-gray-500" x-text="tramoTexto"></span></h3>
                            <template x-for="(p, i) in seleccion" :key="p.asiento">
                                <div class="grid sm:grid-cols-12 gap-2 items-center rounded-xl bg-slate-50 p-2">
                                    <span class="sm:col-span-2 text-sm font-black text-indigo-700" x-text="'Asiento ' + p.asiento + ' · ' + (p.pos === 'V' ? 'ventana' : 'pasillo')"></span>
                                    <input x-model="p.doc" @keydown.enter.prevent="buscar(p)" @blur="buscar(p)" placeholder="DNI / CE / Pasaporte" maxlength="15" class="sm:col-span-3 h-10 rounded-lg border-gray-300 text-sm">
                                    <input x-model="p.nombre" placeholder="Nombres y apellidos" maxlength="150" class="sm:col-span-4 h-10 rounded-lg border-gray-300 text-sm uppercase">
                                    <input x-model="p.telefono" placeholder="Celular" maxlength="20" class="sm:col-span-2 h-10 rounded-lg border-gray-300 text-sm">
                                    <span x-show="completo" class="sm:col-span-1 text-right text-sm font-bold" x-text="'S/ ' + p.precio.toFixed(2)"></span>
                                    <input x-show="!completo" type="number" step="any" min="0" x-model.number="p.precio" class="sm:col-span-1 h-10 rounded-lg border-gray-300 text-sm text-right">
                                </div>
                            </template>

                            <div class="grid sm:grid-cols-4 gap-2 pt-2 border-t">
                                <label class="text-xs font-semibold text-gray-500">Comprobante
                                    <select x-model="tdocod" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                        <option value="03">Boleta</option><option value="01">Factura</option><option value="13">Nota de venta</option>
                                    </select></label>
                                <label class="text-xs font-semibold text-gray-500" x-show="tdocod === '01'">RUC
                                    <input x-model="clinum" @blur="buscarRuc()" maxlength="11" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                                <label class="text-xs font-semibold text-gray-500 sm:col-span-2" x-show="tdocod === '01'">Razón social
                                    <input x-model="clinom" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                                <label class="text-xs font-semibold text-gray-500">Medio de pago
                                    <select x-model="medio" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                        @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                                    </select></label>
                                <label class="text-xs font-semibold text-gray-500">Paga con
                                    <input type="number" step="any" min="0" x-model.number="paga" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                                <div class="sm:col-span-2 flex items-end justify-end gap-4">
                                    <p class="text-sm text-gray-500">Vuelto <b class="text-emerald-600" x-text="paga > total ? (paga - total).toFixed(2) : '0.00'"></b></p>
                                    <p class="text-2xl font-black text-gray-800" x-text="'S/ ' + total.toFixed(2)"></p>
                                </div>
                            </div>
                            <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700"></p>
                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button" @click="seleccion = []" class="h-12 px-4 rounded-xl bg-slate-100 font-bold text-slate-700">Cancelar</button>
                                <button type="button" @click="reservar()" :disabled="enviando" class="h-12 px-4 rounded-xl bg-amber-500 font-bold text-white disabled:opacity-50">RESERVAR {{ $reservaMin }} MIN</button>
                                <button type="button" @click="vender(false)" :disabled="enviando" class="h-12 px-5 rounded-xl bg-sky-600 font-bold text-white disabled:opacity-50">REGISTRAR</button>
                                <button type="button" @click="vender(true)" :disabled="enviando" class="h-12 px-6 rounded-xl bg-emerald-600 font-bold text-white disabled:opacity-50"
                                        x-text="enviando ? 'Vendiendo…' : 'VENDER E IMPRIMIR'">VENDER E IMPRIMIR</button>
                            </div>
                        </div>
                    </div>
                @endif

                <div x-show="hecho" x-cloak class="mt-4 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 p-4 flex flex-wrap items-center gap-3">
                    <p class="flex-1 font-bold text-emerald-800" x-text="hecho ? hecho.texto : ''"></p>
                    <a x-show="hecho?.ticket" :href="hecho?.ticket || '#'" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-white text-sm font-bold text-emerald-700 ring-1 ring-emerald-300">Ver ticket</a>
                    <a x-show="hecho?.grupo" :href="hecho?.grupo ? boletoUrl + '/' + hecho.grupo : '#'" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-white text-sm font-bold text-emerald-700 ring-1 ring-emerald-300">Boleto</a>
                    <button type="button" @click="location.reload()" class="h-10 px-4 rounded-xl bg-emerald-600 text-sm font-bold text-white">Seguir vendiendo</button>
                </div>
            </section>
        </div>

        {{-- Cobrar una reserva (de caja o de internet) --}}
        <div x-show="cobro" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="cobro = null">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-5 space-y-3" @click.outside="cobro = null">
                <h3 class="text-lg font-black text-gray-800">Cobrar reserva</h3>
                <p class="text-sm text-gray-600" x-text="cobro ? cobro.nombre + (cobro.total ? ' · S/ ' + cobro.total.toFixed(2) : '') : ''"></p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs font-semibold text-gray-500">Comprobante
                        <select x-model="tdocod" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                            <option value="03">Boleta</option><option value="01">Factura</option><option value="13">Nota de venta</option>
                        </select></label>
                    <label class="text-xs font-semibold text-gray-500">Medio de pago
                        <select x-model="medio" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                            @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                        </select></label>
                    <label class="text-xs font-semibold text-gray-500" x-show="tdocod === '01'">RUC
                        <input x-model="clinum" @blur="buscarRuc()" maxlength="11" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                    <label class="text-xs font-semibold text-gray-500" x-show="tdocod === '01'">Razón social
                        <input x-model="clinom" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                    <label class="col-span-2 text-xs font-semibold text-gray-500">N° de operación
                        <input x-model="operacion" maxlength="30" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                </div>
                <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="cobro = null" class="h-11 px-4 rounded-xl bg-slate-100 font-bold text-slate-700">Cancelar</button>
                    <button type="button" @click="cobrar(false)" :disabled="enviando" class="h-11 px-4 rounded-xl bg-sky-600 font-bold text-white disabled:opacity-50">COBRAR</button>
                    <button type="button" @click="cobrar(true)" :disabled="enviando" class="h-11 px-4 rounded-xl bg-emerald-600 font-bold text-white disabled:opacity-50">COBRAR E IMPRIMIR</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ventaPasajes(d) {
            const csrf = @js(csrf_token());
            const medios = @js($medios->map(fn ($m) => ['id' => (string) $m->id_med_pag, 'nombre' => $m->nom_med_pag])->values());
            const ultima = Math.max(1, d.paradas.length - 1);
            return {
                plano: d.plano || { pisos: [] }, activos: d.activos, precios: d.precios, paradas: d.paradas, otras: d.otras, web: d.web,
                categorias: @js($categorias), seleccion: [], desde: 0, hasta: ultima, tdocod: '03', clinum: '', clinom: '', operacion: '',
                medio: medios.length ? medios[0].id : '', paga: 0, error: '', enviando: false, hecho: null, detalle: null, cambiando: null,
                mover: false, moverSalida: d.salida, moverAsiento: '', cobro: null,
                boletoUrl: @js(url('transportes/boleto')),
                iniciar() { setInterval(() => this.refrescar(), 20000); },
                get completo() { return this.desde === 0 && this.hasta >= ultima; },
                get hastaReal() { return this.hasta >= ultima ? 99 : this.hasta; },
                get tramoTexto() { return this.paradas.length ? this.paradas[this.desde] + ' - ' + this.paradas[Math.min(this.hasta, ultima)] : ''; },
                get total() { return Math.round(this.seleccion.reduce((s, p) => s + (Number(p.precio) || 0), 0) * 100) / 100; },
                tramoDe(p) { return this.paradas.length ? this.paradas[p.desde] + ' - ' + this.paradas[p.hasta >= 99 ? ultima : p.hasta] : ''; },
                esAsiento(c) { return c && (c.t === 'A' || c.t === 'H'); },
                // El pasaje que ocupa ese asiento en el tramo elegido
                tomado(c) { return this.activos.find((p) => p.asiento === c.n && p.desde < this.hastaReal && this.desde < p.hasta); },
                elegido(c) { return c && this.seleccion.some((p) => p.asiento === c.n); },
                titulo(c) {
                    if (!this.esAsiento(c)) return '';
                    const t = this.tomado(c);
                    if (t) return 'Asiento ' + c.n + ' · ' + (t.estado === 'RESERVADO' ? 'reservado' : 'vendido') + ' a ' + t.nombre;
                    return 'Asiento ' + c.n + ' · ' + (this.categorias[c.cat] || c.cat) + ' · ' + (c.pos === 'V' ? 'ventana' : 'pasillo') + ' · S/ ' + (this.precios[c.cat] || 0).toFixed(2);
                },
                claseDe(c) {
                    if (!c) return 'invisible';
                    if (!this.esAsiento(c)) return 'bg-slate-200 cursor-default';
                    const t = this.tomado(c);
                    if (t) return t.estado === 'RESERVADO' ? 'bg-amber-300 text-amber-900' : (t.subio ? 'bg-rose-300 text-rose-900' : 'bg-rose-200 text-rose-800');
                    if (this.elegido(c)) return 'bg-indigo-600 text-white shadow-lg scale-105';
                    return (c.cat === 'CAMA' || c.cat === 'CAMA 180' ? 'bg-amber-50 ring-amber-400' : 'bg-emerald-50 ring-emerald-400') + ' ring-2 text-gray-800 hover:bg-indigo-100';
                },
                cambioTramo() {
                    if (this.hasta <= this.desde) this.hasta = this.desde + 1;
                    this.seleccion = this.seleccion.filter((p) => !this.tomado({ n: p.asiento }));
                    this.detalle = null;
                },
                tocar(c) {
                    if (!this.esAsiento(c)) return;
                    const t = this.tomado(c);
                    if (this.cambiando) {
                        if (t) { this.error = 'Ese asiento está ocupado: elige uno libre.'; return; }
                        this.cambiar(this.cambiando, d.salida, c.n);
                        return;
                    }
                    if (t) { this.detalle = t; this.mover = false; this.moverSalida = d.salida; this.moverAsiento = t.asiento; return; }
                    const i = this.seleccion.findIndex((p) => p.asiento === c.n);
                    if (i >= 0) { this.seleccion.splice(i, 1); return; }
                    this.seleccion.push({ asiento: c.n, pos: c.pos, cat: c.cat, precio: this.precios[c.cat] || 0, tdicod: '1', doc: '', nombre: '', telefono: '' });
                    this.hecho = null;
                },
                async pedir(url, cuerpo) {
                    const r = await fetch(url, { method: cuerpo === undefined ? 'GET' : 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo) });
                    if (r.status === 419 || r.status === 401) { location.reload(); return null; }
                    const j = await r.json().catch(() => null);
                    if (r.status === 422 && j?.errors) return { ok: false, mensaje: Object.values(j.errors)[0][0] };
                    return j || { ok: false, mensaje: 'No se pudo completar.' };
                },
                async refrescar() {
                    try {
                        const j = await this.pedir(@js(url('transportes/salidas')) + '/' + d.salida + '/estado');
                        if (j && j.web) { this.web = j.web; if (d.salida) this.activos = j.activos; }
                    } catch (e) {}
                },
                async buscar(p) {
                    const doc = String(p.doc || '').trim();
                    if (!/^\d{8}$/.test(doc) || p.nombre) return;
                    try {
                        const j = await (await fetch(@js(url('cobros/cliente')) + '/' + doc, { headers: { Accept: 'application/json' } })).json();
                        if (j.nom) p.nombre = j.nom;
                    } catch (e) {}
                },
                async buscarRuc() {
                    if (!/^\d{11}$/.test(this.clinum) || this.clinom) return;
                    try {
                        const j = await (await fetch(@js(url('cobros/cliente')) + '/' + this.clinum, { headers: { Accept: 'application/json' } })).json();
                        if (j.nom) this.clinom = j.nom;
                    } catch (e) {}
                },
                pasajeros() {
                    return this.seleccion.map((p) => ({ asiento: p.asiento, tdicod: String(p.doc).trim().length === 8 ? '1' : (p.tdicod || '4'),
                        doc: String(p.doc).trim(), nombre: String(p.nombre).trim(), telefono: p.telefono || null, precio: this.completo ? null : Number(p.precio) || 0 }));
                },
                listos() {
                    this.error = '';
                    if (this.seleccion.some((p) => !String(p.doc).trim() || !String(p.nombre).trim())) { this.error = 'Completa el documento y el nombre de cada pasajero.'; return false; }
                    return true;
                },
                async vender(imprimir) {
                    if (!this.listos()) return;
                    this.enviando = true;
                    try {
                        const j = await this.pedir(@js(url('transportes/salidas')) + '/' + d.salida + '/vender', { pasajeros: this.pasajeros(), desde: this.desde, hasta: this.hastaReal,
                            tdocod: this.tdocod, clinum: this.tdocod === '01' ? this.clinum : null, clinom: this.tdocod === '01' ? this.clinom : null,
                            id_med_pag: this.medio ? Number(this.medio) : null, paga: this.paga || 0, imprimir });
                        if (!j) return;
                        if (!j.ok) { this.error = j.mensaje || 'No se pudo vender.'; return; }
                        this.seleccion = [];
                        this.hecho = { texto: '✔ Vendido: ' + j.comprobante.numero + ' · S/ ' + j.comprobante.total.toFixed(2) + (j.comprobante.impreso ? ' · impreso' : ''), ticket: j.comprobante.ticket };
                        if (imprimir && !j.comprobante.impreso) window.open(j.comprobante.ticket, '_blank');
                        this.refrescar();
                    } catch (e) {
                        this.error = 'Sin conexión: no se vendió. Intenta otra vez.';
                    } finally {
                        this.enviando = false;
                    }
                },
                async reservar() {
                    if (!this.listos()) return;
                    this.enviando = true;
                    try {
                        const j = await this.pedir(@js(url('transportes/salidas')) + '/' + d.salida + '/reservar', { pasajeros: this.pasajeros(), desde: this.desde, hasta: this.hastaReal });
                        if (!j) return;
                        if (!j.ok) { this.error = j.mensaje || 'No se pudo reservar.'; return; }
                        this.seleccion = [];
                        this.hecho = { texto: '✔ Reservado hasta ' + j.vence + '. Si no se cobra, el asiento se libera solo.', grupo: j.grupo };
                        this.refrescar();
                    } catch (e) {
                        this.error = 'Sin conexión: no se reservó. Intenta otra vez.';
                    } finally {
                        this.enviando = false;
                    }
                },
                abrirCobro(r) {
                    this.error = '';
                    this.cobro = { grupo: r.grupo, nombre: r.nombre, total: r.total || 0 };
                    const m = medios.find((x) => r.medio && x.nombre.toUpperCase() === String(r.medio).toUpperCase());
                    if (m) this.medio = m.id;
                    this.operacion = r.operacion || '';
                },
                async cobrar(imprimir) {
                    this.enviando = true;
                    this.error = '';
                    try {
                        const j = await this.pedir(@js(url('transportes/reservas')) + '/' + this.cobro.grupo + '/cobrar', { tdocod: this.tdocod,
                            clinum: this.tdocod === '01' ? this.clinum : null, clinom: this.tdocod === '01' ? this.clinom : null,
                            id_med_pag: this.medio ? Number(this.medio) : null, operacion: this.operacion || null, imprimir });
                        if (!j) return;
                        if (!j.ok) { this.error = j.mensaje || 'No se pudo cobrar.'; return; }
                        this.hecho = { texto: '✔ Cobrado: ' + j.comprobante.numero + ' · S/ ' + j.comprobante.total.toFixed(2) + (j.comprobante.impreso ? ' · impreso' : ''), ticket: j.comprobante.ticket, grupo: this.cobro.grupo };
                        if (imprimir && !j.comprobante.impreso) window.open(j.comprobante.ticket, '_blank');
                        this.cobro = null;
                        this.detalle = null;
                        this.refrescar();
                    } catch (e) {
                        this.error = 'Sin conexión: no se cobró. Intenta otra vez.';
                    } finally {
                        this.enviando = false;
                    }
                },
                async liberar(grupo) {
                    if (!confirm('¿Liberar esta reserva? Los asientos quedan libres.')) return;
                    const j = await this.pedir(@js(url('transportes/reservas')) + '/' + grupo + '/liberar', {});
                    if (j && !j.ok) { alert(j.mensaje); }
                    this.detalle = null;
                    this.refrescar();
                },
                async cambiar(p, salida, asiento) {
                    this.error = '';
                    const j = await this.pedir(@js(url('transportes/pasajes')) + '/' + p.id + '/cambiar', { salida_id: salida, asiento: String(asiento || '').trim() });
                    if (!j) return;
                    if (!j.ok) { this.error = j.mensaje; alert(j.mensaje); return; }
                    this.cambiando = null;
                    this.detalle = null;
                    this.mover = false;
                    this.hecho = { texto: '✔ ' + j.mensaje, grupo: p.grupo };
                    this.refrescar();
                },
            };
        }
    </script>
@endsection
