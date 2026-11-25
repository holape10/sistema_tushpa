@extends('layouts.app')
@section('title', 'Punto Venta Avícola')
@section('barra_compacta', '1')
@section('content')
@include('partials.pantalla_completa')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php
        $prods = $productos->map(fn ($p) => ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'precio' => (float) $p->propun, 'stock' => (float) $p->stock, 'ume' => $p->umecod])->values();
        $vendidoHoy = $hoy->whereNull('ccabaj');
    @endphp

    @if ($productos->isEmpty())
        <div class="mb-4 rounded-2xl bg-amber-50 border border-amber-200 p-4 text-amber-900">
            <b>Aún no tienes productos avícolas.</b>
            @if (auth()->user()->esAdmin())
                Entra a <a href="{{ route('avicola.configuracion') }}" class="underline font-semibold">Configuración avícola</a> y toca «Crear productos típicos» (pollo vivo, gallina, partes, huevos).
            @else Pide al administrador que los cree en Configuración avícola. @endif
        </div>
    @endif
    @unless ($turno)
        <div class="mb-4 rounded-2xl bg-rose-50 border border-rose-200 p-4 text-rose-800">⚠️ <b>No tienes tu turno abierto.</b> Para vender, primero
            <a href="{{ route('turnos.index') }}" class="underline font-semibold">abre tu turno de caja</a>.</div>
    @endunless

    {{-- Precio del día por kilo --}}
    @if ($productos->isNotEmpty())
        <details class="mb-4 rounded-2xl bg-white shadow-sm" @if ($productos->every(fn ($p) => (float) $p->propun <= 0)) open @endif>
            <summary class="cursor-pointer px-5 py-3 flex flex-wrap items-center gap-3">
                <span class="font-bold text-gray-800"><i class="fas fa-tags text-orange-500"></i> Precio del día</span>
                <span class="text-sm text-gray-500">
                    @php($conPrecio = $productos->filter(fn ($p) => (float) $p->propun > 0))
                    @forelse ($conPrecio->take(4) as $p){{ $p->pronom }}: <b class="text-gray-800">S/ {{ number_format((float) $p->propun, 2) }}</b>@unless ($loop->last) · @endunless
                    @empty <span class="text-amber-700 font-semibold">Pon el precio del día para empezar a vender.</span> @endforelse
                    @if ($conPrecio->count() > 4) · … @endif
                </span>
                <span class="ml-auto text-xs text-indigo-600 font-semibold">Cambiar ▾</span>
            </summary>
            @if ($puedePrecio)
                <form method="POST" action="{{ route('avicola.precios') }}" class="px-5 pb-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @csrf
                    @foreach ($productos as $p)
                        <label class="text-xs font-semibold text-gray-500">{{ $p->pronom }} <span class="font-normal">(S/ por {{ $p->umecod === 'KGM' ? 'kilo' : 'unidad' }})</span>
                            <input type="number" step="0.01" min="0" name="precios[{{ $p->IdProducto }}]" value="{{ number_format((float) $p->propun, 2, '.', '') }}"
                                   class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right font-bold text-gray-800"></label>
                    @endforeach
                    <div class="sm:col-span-2 lg:col-span-4 flex items-center gap-3">
                        <button class="h-10 px-5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold">Guardar precio del día</button>
                        <span class="text-xs text-gray-500">Queda como el precio de venta: lo usan esta pantalla y el Punto Venta.</span>
                    </div>
                </form>
            @else
                <p class="px-5 pb-4 text-sm text-gray-500">Solo el administrador (o quien tiene permiso para cambiar precios) pone el precio del día.</p>
            @endif
        </details>
    @endif

    <div x-data="ventaPeso()" class="grid lg:grid-cols-12 gap-4">
        {{-- Pesadas --}}
        <section class="lg:col-span-7 bg-white rounded-2xl shadow-sm p-5 space-y-4">
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="sm:col-span-2 text-xs font-semibold text-gray-500">Producto
                    <select x-model.number="producto" @change="usarPrecio()" class="mt-1 block w-full h-11 rounded-xl border-gray-300 font-semibold">
                        <template x-for="p in productos" :key="p.id"><option :value="p.id" x-text="p.nombre + ' · stock ' + num(p.stock) + ' ' + (p.ume === 'KGM' ? 'kg' : 'und')"></option></template>
                    </select></label>
                <label class="text-xs font-semibold text-gray-500">Precio S/ por kilo
                    <input type="number" step="0.01" min="0" x-model.number="precio" :readonly="!puedePrecio"
                           class="mt-1 block w-full h-11 rounded-xl border-orange-300 bg-orange-50 text-right text-lg font-extrabold text-orange-700"></label>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="font-bold text-gray-800"><i class="fas fa-scale-balanced text-orange-500"></i> Pesadas <span class="text-xs font-normal text-gray-400">(jabas con aves en la balanza)</span></p>
                    <button type="button" @click="agregar()" class="h-9 px-3 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold">+ Pesada</button>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase"><tr><th class="text-left py-1">#</th><th>Jabas</th><th>Aves</th><th>Peso bruto (kg)</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(p, i) in pesadas" :key="i">
                            <tr>
                                <td class="py-1 text-gray-400" x-text="i + 1"></td>
                                <td class="px-1"><input type="number" min="0" x-model.number="p.jabas" @input="if (!p.avesTocadas) p.aves = p.jabas * avesJaba" class="w-full h-10 rounded-lg border-gray-300 text-center"></td>
                                <td class="px-1"><input type="number" min="0" x-model.number="p.aves" @input="p.avesTocadas = true" class="w-full h-10 rounded-lg border-gray-300 text-center"></td>
                                <td class="px-1"><input type="number" step="0.001" min="0" x-model.number="p.peso" :id="'peso-' + i" @keydown.enter.prevent="agregar()" class="w-full h-10 rounded-lg border-gray-300 text-right font-bold"></td>
                                <td class="text-right"><button type="button" @click="pesadas.splice(i, 1)" x-show="pesadas.length > 1" class="text-gray-300 hover:text-rose-600 px-2">✕</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p class="mt-1 text-xs text-gray-400">Enter en el peso agrega otra pesada. Tara por jaba: {{ rtrim(rtrim(number_format((float) $cfg->tara_jaba, 3), '0'), '.') }} kg (se cambia en Configuración).</p>
            </div>

            <div class="grid grid-cols-2 gap-3 rounded-xl bg-rose-50/60 border border-rose-100 p-3">
                <label class="text-xs font-semibold text-rose-700">Aves muertas (no se cobran)
                    <input type="number" min="0" x-model.number="avesMuertas" class="mt-1 block w-full h-10 rounded-lg border-rose-200 text-center"></label>
                <label class="text-xs font-semibold text-rose-700">Peso de las muertas (kg)
                    <input type="number" step="0.001" min="0" x-model.number="pesoMuertas" class="mt-1 block w-full h-10 rounded-lg border-rose-200 text-right"></label>
            </div>

            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Jabas · aves</dt><dd class="font-bold" x-text="jabas + ' · ' + aves"></dd></div>
                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">Bruto</dt><dd class="font-bold" x-text="num(bruto) + ' kg'"></dd></div>
                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-[11px] text-gray-500">− Tara<span x-show="pesoMuertas > 0"> y muertas</span></dt><dd class="font-bold" x-text="num(tara + (pesoMuertas || 0)) + ' kg'"></dd></div>
                <div class="rounded-xl bg-orange-100 p-2"><dt class="text-[11px] text-orange-700">Peso neto</dt><dd class="text-lg font-extrabold text-orange-700" x-text="num(neto) + ' kg'"></dd></div>
            </dl>
            <p class="text-sm text-gray-500" x-show="aves > 0 && neto > 0">Peso promedio por ave: <b x-text="num(neto / Math.max(1, aves - (avesMuertas || 0))) + ' kg'"></b></p>
        </section>

        {{-- Cliente y cobro --}}
        <section class="lg:col-span-5 bg-white rounded-2xl shadow-sm p-5 space-y-4">
            <div class="grid grid-cols-3 gap-1.5 p-1 bg-gray-100 rounded-xl">
                <template x-for="c in [['13', 'N. Venta'], ['03', 'Boleta'], ['01', 'Factura']]" :key="c[0]">
                    <button type="button" @click="tdocod = c[0]" class="h-10 rounded-lg text-sm font-bold"
                            :class="tdocod === c[0] ? 'bg-indigo-600 text-white shadow ring-2 ring-indigo-300' : 'text-gray-600'" x-text="(tdocod === c[0] ? '✓ ' : '') + c[1]"></button>
                </template>
            </div>
            <div class="space-y-2">
                <p class="text-xs font-semibold text-gray-500 uppercase">Cliente</p>
                <div class="flex gap-2">
                    <select x-model="cliente.tdicod" class="h-10 w-24 rounded-xl border-gray-300 text-sm"><option value="1">DNI</option><option value="6">RUC</option><option value="0">Otro</option></select>
                    <input x-model="cliente.num" @keydown.enter.prevent="buscar()" @blur="if (/^\d{8}$|^\d{11}$/.test(cliente.num) && cliente.num !== '00000000') buscar()" placeholder="DNI o RUC"
                           class="flex-1 h-10 rounded-xl border-gray-300">
                    <button type="button" @click="buscar()" class="h-10 w-10 rounded-xl bg-indigo-600 text-white"><i class="fas fa-magnifying-glass"></i></button>
                </div>
                <input x-model="cliente.nom" placeholder="Nombre o razón social" class="w-full h-10 rounded-xl border-gray-300 uppercase">
                <input x-model="cliente.dir" placeholder="Dirección (opcional)" class="w-full h-10 rounded-xl border-gray-300">
                <p class="text-xs" :class="msgOk ? 'text-emerald-700' : 'text-rose-600'" x-text="msg"></p>
            </div>

            <div class="flex gap-2">
                <button type="button" @click="credito = false" class="flex-1 h-10 rounded-xl text-sm font-bold border" :class="!credito ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600'">Contado</button>
                <button type="button" @click="credito = true" class="flex-1 h-10 rounded-xl text-sm font-bold border" :class="credito ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-600'">Al crédito (a cuenta)</button>
            </div>
            {{-- Contado: uno o varios medios. El primero se llena solo con lo que falta --}}
            <div x-show="!credito" class="space-y-2">
                <p class="text-xs font-semibold text-gray-500 uppercase">Cómo paga</p>
                <template x-for="(m, i) in pagos" :key="i">
                    <div class="flex gap-2 items-center">
                        <select x-model.number="m.id" class="flex-1 h-10 rounded-xl border-gray-300 text-sm font-semibold">
                            <template x-for="o in medios" :key="o.id"><option :value="o.id" x-text="o.nombre" :selected="o.id === m.id"></option></template>
                        </select>
                        <input type="number" step="0.01" min="0" :value="i === 0 ? montoDe(0).toFixed(2) : m.monto" @input="i === 0 ? null : m.monto = $event.target.value"
                               :readonly="i === 0" class="w-28 h-10 rounded-xl border-gray-300 text-right font-bold" :class="i === 0 && 'bg-gray-50'">
                        <input x-show="!esEfectivo(m.id)" x-model="m.op" maxlength="30" placeholder="N° operación" class="w-28 h-10 rounded-xl border-gray-300 text-sm">
                        <button type="button" x-show="i > 0" @click="pagos.splice(i, 1)" class="text-gray-300 hover:text-rose-600 px-1">✕</button>
                    </div>
                </template>
                <div class="flex items-center gap-3">
                    <button type="button" @click="pagos.push({ id: medios.find(o => !esEfectivo(o.id))?.id ?? medios[0].id, monto: '', op: '' })" x-show="medios.length > 1 && pagos.length < 4"
                            class="text-sm font-semibold text-indigo-600 hover:underline">+ Otro medio (Yape, tarjeta…)</button>
                    <p x-show="montoDe(0) < 0" class="text-xs font-semibold text-rose-600">Los otros medios pasan el total.</p>
                </div>
                <label x-show="esEfectivo(pagos[0].id)" class="flex items-center gap-2 text-xs font-semibold text-gray-500">Paga con (S/)
                    <input type="number" step="0.01" min="0" x-model.number="paga" :placeholder="num(montoDe(0), 2)" class="w-32 h-9 rounded-xl border-gray-300 text-right"></label>
            </div>
            <label x-show="credito" class="block text-xs font-semibold text-gray-500">Paga hasta el
                <input type="date" x-model="fecVen" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>

            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs font-semibold text-gray-500">Jabas que devuelve hoy
                    <input type="number" min="0" x-model.number="devueltas" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-center"></label>
                <label class="text-xs font-semibold text-gray-500">Ruta o chofer (opcional)
                    <input x-model="ruta" maxlength="60" class="mt-1 block w-full h-10 rounded-xl border-gray-300"></label>
            </div>

            <div class="rounded-2xl bg-gray-900 text-white p-4 flex items-end justify-between">
                <div><p class="text-xs text-white/60">Total a cobrar</p><p class="text-3xl font-extrabold" x-text="'S/ ' + num(total, 2)"></p>
                    <p class="text-xs text-white/60" x-text="num(neto) + ' kg × S/ ' + num(precio, 2)"></p></div>
                <div class="text-right" x-show="vuelto > 0"><p class="text-xs text-white/60">Vuelto</p><p class="text-xl font-bold text-emerald-300" x-text="'S/ ' + num(vuelto, 2)"></p></div>
            </div>
            <button type="button" @click="registrar(true)" :disabled="procesando || neto <= 0 || precio <= 0"
                    class="w-full h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-lg font-extrabold disabled:opacity-40"
                    x-text="procesando ? 'Registrando…' : '🖨 Registrar e imprimir'"></button>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="registrar(false)" :disabled="procesando || neto <= 0 || precio <= 0"
                        class="h-11 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-bold disabled:opacity-40">✓ Registrar (sin imprimir)</button>
                <button type="button" @click="proforma()" :disabled="procesando || neto <= 0 || precio <= 0"
                        class="h-11 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold disabled:opacity-40">📝 Proforma</button>
            </div>
            <iframe x-ref="impresion" class="hidden"></iframe>
        </section>
    </div>

    {{-- Ventas de hoy --}}
    <section class="mt-6 bg-white rounded-2xl shadow-sm overflow-x-auto">
        <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap gap-4 items-center">
            <p class="font-bold text-gray-800 flex-1">Ventas de hoy</p>
            <span class="text-sm text-gray-500">{{ $vendidoHoy->count() }} ventas · {{ number_format($vendidoHoy->sum('aves')) }} aves ·
                <b class="text-gray-800">{{ number_format((float) $vendidoHoy->sum('peso_neto'), 2) }} kg</b> · <b class="text-gray-800">S/ {{ number_format((float) $vendidoHoy->sum('total'), 2) }}</b></span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                <th class="px-3 py-2 text-left">Hora</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-left">Producto</th>
                <th class="px-3 py-2 text-right">Jabas</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Neto kg</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($hoy as $v)
                    <tr class="{{ $v->ccabaj ? 'opacity-40 line-through' : '' }}">
                        <td class="px-3 py-2 text-gray-500">{{ \Carbon\Carbon::parse($v->fecha)->format('H:i') }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $v->serdoc }}-{{ $v->numdoc }} @if ($v->credito)<span class="ml-1 px-1.5 rounded bg-amber-100 text-amber-800 font-sans font-bold">CRÉDITO</span>@endif</td>
                        <td class="px-3 py-2">@if ($v->clicod)<a href="{{ route('avicola.cliente', $v->clicod) }}" class="text-indigo-600 hover:underline">{{ $v->clinom }}</a>@else{{ $v->clinom }}@endif</td>
                        <td class="px-3 py-2">{{ $v->pronom }}</td>
                        <td class="px-3 py-2 text-right">{{ $v->jabas }}</td><td class="px-3 py-2 text-right">{{ $v->aves }}@if ($v->aves_muertas) <span class="text-rose-600 text-xs">(−{{ $v->aves_muertas }})</span>@endif</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $v->peso_neto, 2) }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $v->precio, 2) }}</td>
                        <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $v->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">Aún no hay ventas hoy.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="px-5 py-2 text-xs text-gray-400">Para anular o reimprimir una venta usa Ventas › Panel de Ventas (al anularla, deja de contar aquí y en los reportes).</p>
    </section>

    <script src="{{ asset('js/impresion.js') }}?v={{ filemtime(public_path('js/impresion.js')) }}" data-url="{{ url('impresion/comprobante') }}" data-csrf="{{ csrf_token() }}"></script>
    <script>
        function ventaPeso() {
            const productos = @js($prods);
            return {
                productos, puedePrecio: @js($puedePrecio), avesJaba: {{ (int) $cfg->aves_jaba }}, taraJaba: {{ (float) $cfg->tara_jaba }},
                producto: productos[0]?.id ?? null, precio: productos[0]?.precio ?? 0,
                pesadas: [{ jabas: 0, aves: 0, peso: null }], avesMuertas: 0, pesoMuertas: 0,
                tdocod: '13', cliente: { tdicod: '1', num: '00000000', nom: 'VENTA AL PORTADOR', dir: '' }, msg: '', msgOk: true,
                credito: false, fecVen: @js(now()->addDays(7)->toDateString()), paga: null,
                medios: @js($medios->map(fn ($m) => ['id' => (int) $m->id_med_pag, 'nombre' => $m->nom_med_pag])->values()),
                pagos: [{ id: {{ (int) ($medios->first()->id_med_pag ?? 0) }}, monto: '', op: '' }],
                esEfectivo(id) { return String(this.medios.find(o => o.id === Number(id))?.nombre || '').toUpperCase() === 'EFECTIVO'; },
                /** El primer medio se queda con lo que falta; los demás, con lo que se escribió */
                montoDe(i) {
                    if (i > 0) return Number(this.pagos[i].monto) || 0;
                    return Math.round((this.total - this.pagos.slice(1).reduce((s, m) => s + (Number(m.monto) || 0), 0)) * 100) / 100;
                },
                get vuelto() { return !this.credito && this.esEfectivo(this.pagos[0].id) && this.paga > this.montoDe(0) ? this.paga - this.montoDe(0) : 0; },
                devueltas: 0, ruta: '', procesando: false,
                num(n, d = 3) { return (Math.round((Number(n) || 0) * 1000) / 1000).toLocaleString('es-PE', { minimumFractionDigits: d === 2 ? 2 : 0, maximumFractionDigits: d }); },
                get jabas() { return this.pesadas.reduce((s, p) => s + (Number(p.jabas) || 0), 0); },
                get aves() { return this.pesadas.reduce((s, p) => s + (Number(p.aves) || 0), 0); },
                get bruto() { return this.pesadas.reduce((s, p) => s + (Number(p.peso) || 0), 0); },
                get tara() { return this.jabas * this.taraJaba; },
                get neto() { return Math.max(0, Math.round((this.bruto - this.tara - (Number(this.pesoMuertas) || 0)) * 1000) / 1000); },
                get total() { return Math.round(this.neto * (Number(this.precio) || 0) * 100) / 100; },
                usarPrecio() { this.precio = this.productos.find(p => p.id === this.producto)?.precio ?? 0; },
                agregar() {
                    const ultima = this.pesadas[this.pesadas.length - 1];
                    this.pesadas.push({ jabas: ultima?.jabas || 0, aves: ultima?.aves || 0, peso: null });
                    this.$nextTick(() => document.getElementById('peso-' + (this.pesadas.length - 1))?.focus());
                },
                async buscar() {
                    const doc = String(this.cliente.num).trim();
                    if (!doc || doc === '00000000') return;
                    this.msg = 'Buscando…'; this.msgOk = true;
                    try {
                        const d = await (await fetch(@js(url('cobros/cliente')) + '/' + encodeURIComponent(doc), { headers: { Accept: 'application/json' } })).json();
                        if (d.error) { this.msg = d.error; this.msgOk = false; return; }
                        this.cliente = { tdicod: d.tdicod || (doc.length === 11 ? '6' : '1'), num: doc, nom: d.nom || '', dir: d.dir && d.dir !== '--' ? d.dir : '' };
                        if (this.cliente.tdicod === '6') this.tdocod = '01';
                        this.msg = '✔ Cliente encontrado.'; this.msgOk = true;
                    } catch (e) { this.msg = 'No se pudo consultar: escribe los datos.'; this.msgOk = false; }
                },
                datos() {
                    return {
                        IdProducto: this.producto, precio: this.precio, tdocod: this.tdocod, tdicod: this.cliente.tdicod,
                        clinum: this.cliente.num, clinom: this.cliente.nom, clidir: this.cliente.dir,
                        pesadas: this.pesadas.map(p => ({ jabas: p.jabas || 0, aves: p.aves || 0, peso: p.peso || 0 })),
                        aves_muertas: this.avesMuertas || 0, peso_muertas: this.pesoMuertas || 0,
                    };
                },
                async enviar(url, body) {
                    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify(body) });
                    const d = await r.json();
                    if (!d.ok) throw new Error(d.mensaje || Object.values(d.errors || {})[0]?.[0] || 'No se pudo guardar.');
                    return d;
                },
                async registrar(imprimir) {
                    if (this.procesando) return;
                    if (this.tdocod === '01' && this.cliente.tdicod !== '6') { tushpaAviso('La factura necesita el RUC del cliente.', 'error'); return; }
                    if (this.credito && this.cliente.num === '00000000') { tushpaAviso('Para vender al crédito elige al cliente.', 'error'); return; }
                    if (!this.credito && this.montoDe(0) <= 0) { tushpaAviso('Revisa los medios de pago: pasan el total.', 'error'); return; }
                    this.procesando = true;
                    try {
                        const pagos = this.pagos.map((m, i) => ({ id: m.id, monto: this.montoDe(i), op: m.op })).filter(m => m.monto > 0);
                        const d = await this.enviar(@js(route('avicola.vender')), { ...this.datos(), tdocod: this.tdocod, credito: this.credito, fecVen: this.fecVen,
                            id_med_pag: pagos.map(m => m.id), mon_med_pag: pagos.map(m => m.monto), op_med_pag: pagos.map(m => m.op || null),
                            paga: this.paga || this.total, jabas_devueltas: this.devueltas || 0, ruta: this.ruta });
                        tushpaAviso(`✔ ${d.numero} registrada: S/ ${this.num(d.total, 2)}`, 'ok');
                        if (imprimir) {
                            const directo = window.TushpaImpresion ? await TushpaImpresion.comprobante(d.id) : false;
                            if (!directo) this.$refs.impresion.src = d.ticket + '&imprimir=1&t=' + Date.now();
                        }
                        setTimeout(() => location.reload(), imprimir ? 1800 : 900);
                    } catch (e) {
                        tushpaAviso(e.message || 'Sin conexión con el servidor.', 'error');
                    } finally { this.procesando = false; }
                },
                /** Proforma: no es venta (no emite ni descuenta stock); se cobra después desde Ventas › Proformas */
                async proforma() {
                    if (this.procesando) return;
                    this.procesando = true;
                    try {
                        const d = await this.enviar(@js(route('avicola.proforma')), this.datos());
                        tushpaAviso(`📝 Proforma ${d.numero} guardada: S/ ${this.num(d.total, 2)}` + (d.impresora ? ` · impresa en ${d.impresora}` : ''), 'ok');
                        if (!d.impresora) this.$refs.impresion.src = d.imprimir + '?imprimir=1&t=' + Date.now();
                        setTimeout(() => location.reload(), 1800);
                    } catch (e) {
                        tushpaAviso(e.message || 'Sin conexión con el servidor.', 'error');
                    } finally { this.procesando = false; }
                },
            };
        }
    </script>
@endsection
