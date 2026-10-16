@extends('layouts.app')
@section('title', $t === 'VENTA' ? 'Registro de Ventas' : 'Registro de Compras')
@section('content')
    @include('empresas.contabilidad._nav')
    @use('App\Support\Contabilidad\Contabilidad')
    @use('App\Support\Contabilidad\Registro')

    @php
        $mes = substr($periodo, 0, 4).'-'.substr($periodo, 4, 2);
        $venta = $t === 'VENTA';
        $docs = Registro::DOCUMENTOS[$t];
        $in = 'mt-1 w-full h-10 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $cuentaDefecto = $venta ? $cfg->cta_ventas : $cfg->cta_compras;
        $config = [
            'por_cobrar' => $cfg->cta_por_cobrar, 'por_pagar' => $cfg->cta_por_pagar, 'igv' => $cfg->cta_igv, 'igv_compras' => $cfg->cta_igv_compras ?? $cfg->cta_igv,
            'isc' => $cfg->cta_isc ?? null, 'icbper' => $cfg->cta_icbper ?? null, 'renta4' => $cfg->cta_renta4_pagar,
            'ventas' => $cfg->cta_ventas, 'ventas_exo' => $cfg->cta_ventas_exo,
            'mercaderias' => $cfg->cta_mercaderias, 'variacion' => $cfg->cta_variacion, 'destino' => (bool) $cfg->asiento_destino,
            'gastos' => isset($imputables['94']) && isset($imputables['79']),
        ];
    @endphp

    <div x-data="registroContable(@js([
        'tipo' => $t, 'cfg' => $config, 'cuentaDefecto' => $cuentaDefecto, 'nombres' => $imputables, 'tasa' => $tasa, 'exonerada' => $exonerada,
        'caja' => $cfg->cta_caja, 'guardar' => route('contabilidad.registro.guardar', $tipo), 'documento' => url('/contabilidad/registro/'.$tipo.'/documento'),
        'registros' => $registros->keyBy('id'),
    ]))">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="periodo" value="{{ $periodo }}">
            <div class="mr-auto">
                <h1 class="text-lg font-black text-gray-800">{{ $venta ? 'Registro de Ventas' : 'Registro de Compras' }}</h1>
                <p class="text-xs text-gray-500">Solo montos, como en el CONCAR: cada comprobante genera su asiento en el Libro Diario.</p>
            </div>
            <label class="text-sm">Periodo
                <input type="month" value="{{ $mes }}" onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block rounded-lg border-gray-300 text-sm font-semibold"></label>
            <label class="text-sm min-w-[200px]">Buscar
                <input name="q" value="{{ $q }}" placeholder="{{ $venta ? 'Cliente' : 'Proveedor' }}, RUC o número" class="block w-full rounded-lg border-gray-300 text-sm"></label>
            <button class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Filtrar</button>
            <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
            @unless ($cerrado)
                <button type="button" @click="nuevo()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-bold"><i class="fas fa-plus"></i> {{ $venta ? 'Registrar venta' : 'Registrar compra' }}</button>
            @endunless
        </form>

        @if ($cerrado)
            <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ Contabilidad::nombrePeriodo($periodo) }} está CERRADO. Ábrelo en el Libro Diario para registrar o cambiar.</div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Comprobantes</p><p class="text-2xl font-black">{{ $registros->count() }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Base imponible</p><p class="text-xl font-black">{{ number_format($totales['base'], 2) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">IGV {{ $venta ? 'de ventas' : 'crédito fiscal' }}</p><p class="text-xl font-black text-indigo-700">{{ number_format($totales['igv'], 2) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Exonerado / otros</p><p class="text-xl font-black">{{ number_format($totales['no_gravado'], 2) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Total (S/)</p><p class="text-xl font-black text-emerald-700">{{ number_format($totales['total'], 2) }}</p></div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Documento</th><th class="px-3 py-2 text-left">{{ $venta ? 'Cliente' : 'Proveedor' }}</th>
                        <th class="px-3 py-2 text-right">Base</th><th class="px-3 py-2 text-right">IGV</th><th class="px-3 py-2 text-right">Exo/otros</th><th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-left">Cuenta</th><th class="px-3 py-2 text-left">Asiento</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($registros as $r)
                        @php $nc = $r->tdocod === '07'; @endphp
                        <tr class="{{ $nc ? 'text-rose-700' : '' }}">
                            <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap"><span class="text-xs text-gray-400">{{ $docs[$r->tdocod] ?? $r->tdocod }}</span><br>{{ $r->serie ? $r->serie.'-' : '' }}{{ $r->numero }}</td>
                            <td class="px-3 py-2"><span class="font-semibold">{{ $r->nombre }}</span><br><span class="text-xs text-gray-400">{{ $r->doc_num }} · {{ $r->condicion === 'CONTADO' ? 'Contado' : 'Crédito' }}{{ $r->moneda === 'USD' ? ' · US$ T.C. '.(float) $r->tipo_cambio : '' }}</span></td>
                            <td class="px-3 py-2 text-right">{{ $nc ? '-' : '' }}{{ number_format($r->base, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ $nc ? '-' : '' }}{{ number_format($r->igv, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ $nc ? '-' : '' }}{{ number_format($r->no_gravado + $r->otros, 2) }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ $nc ? '-' : '' }}{{ $r->moneda === 'USD' ? 'US$ ' : '' }}{{ number_format($r->total, 2) }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $r->cuenta }}</td>
                            <td class="px-3 py-2 text-xs whitespace-nowrap">
                                @foreach ($asientos[$r->id] ?? [] as $a)
                                    <a href="{{ route('contabilidad.diario', ['periodo' => $r->periodo, 'q' => trim(($r->serie ? $r->serie.'-' : '').$r->numero)]) }}" class="text-indigo-600 hover:underline">{{ $a->subdiario }}-{{ str_pad($a->numero, 4, '0', STR_PAD_LEFT) }}</a>
                                @endforeach
                                @if ($r->asiento_editado)<span class="text-amber-600" title="Asiento cambiado a mano">✎</span>@endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right">
                                @unless ($cerrado)
                                    <button type="button" @click="editar({{ $r->id }})" class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold">Editar</button>
                                    <form method="POST" action="{{ route('contabilidad.registro.eliminar', [$tipo, $r->id]) }}" class="inline" onsubmit="return confirm('¿Eliminar este comprobante y su asiento?')">
                                        @csrf @method('DELETE')<button class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">Eliminar</button></form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-3 py-10 text-center text-gray-400">No hay {{ $venta ? 'ventas' : 'compras' }} registradas en {{ Contabilidad::nombrePeriodo($periodo) }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Formulario: comprobante y su asiento. Enter pasa al siguiente campo; en el último, guarda --}}
        <div x-show="abierto" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-3 overflow-y-auto" @keydown.escape.window="abierto = false">
            <form x-ref="form" @submit.prevent="enviar()" @keydown.enter="siguiente($event)" class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl my-6">
                <div class="flex flex-wrap items-center gap-2 px-5 py-4 border-b">
                    <h2 class="text-lg font-black" x-text="(f.id ? 'Editar ' : 'Registrar ') + '{{ $venta ? 'venta' : 'compra' }}'"></h2>
                    <span class="px-2 py-0.5 rounded-lg text-xs font-bold {{ $exonerada ? 'bg-amber-100 text-amber-800' : 'bg-indigo-50 text-indigo-700' }}">
                        {{ $exonerada ? 'Sucursal exonerada: sin IGV' : 'IGV '.rtrim(rtrim(number_format($tasa, 2), '0'), '.').'%' }}</span>
                    <a href="{{ route('contabilidad.plan', ['tab' => 'config']) }}" class="text-xs text-indigo-600 hover:underline"><i class="fas fa-gear"></i> Cuentas e impuestos</a>
                    <span class="ml-auto text-xs text-gray-400 hidden sm:inline"><kbd class="px-1.5 rounded bg-gray-100 border">Enter</kbd> siguiente campo · <kbd class="px-1.5 rounded bg-gray-100 border">Esc</kbd> cerrar</span>
                    <button type="button" @click="abierto = false" class="w-9 h-9 rounded-xl hover:bg-gray-100 text-2xl leading-none">×</button>
                </div>
                <div class="p-5 grid lg:grid-cols-2 gap-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 content-start">
                        <label class="text-sm">Fecha emisión<input type="date" x-model="f.fecha" required class="{{ $in }}"></label>
                        <label class="text-sm">Documento
                            <select x-model="f.tdocod" @change="cambioDocumento()" class="{{ $in }}">@foreach ($docs as $c => $n)<option value="{{ $c }}">{{ $c }} - {{ $n }}</option>@endforeach</select></label>
                        <label class="text-sm">Serie<input x-model="f.serie" maxlength="20" placeholder="F001" class="{{ $in }} uppercase"></label>
                        <label class="text-sm">Número *<input x-ref="numero" x-model="f.numero" required maxlength="20" class="{{ $in }}"></label>
                        <label class="text-sm col-span-2">{{ $venta ? 'RUC / DNI del cliente' : 'RUC del proveedor' }} <span class="text-xs text-gray-400">(Enter busca el nombre)</span>
                            <span class="flex gap-1"><input x-model="f.doc_num" data-buscar maxlength="15" inputmode="numeric" class="{{ $in }}">
                                <button type="button" tabindex="-1" @click="buscarDoc()" class="mt-1 px-3 rounded-lg bg-indigo-600 text-white"><i class="fas" :class="buscando ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i></button></span></label>
                        <label class="text-sm col-span-2 sm:col-span-3">{{ $venta ? 'Cliente' : 'Proveedor' }} *<input x-ref="nombre" x-model="f.nombre" required maxlength="200" class="{{ $in }} uppercase"></label>

                        {{-- Montos: se escribe la base o el total; el IGV sale solo --}}
                        <label class="text-sm" x-show="!exonerada && gravado()"><span x-text="'Base imponible'"></span>
                            <input type="number" step="0.01" min="0" x-model.number="f.base" @input="desdeBase()" class="{{ $in }} text-right font-semibold"></label>
                        <label class="text-sm" x-show="!exonerada && gravado()">IGV <span class="text-xs text-gray-400" x-text="tasa + '%'"></span>
                            <input type="number" step="0.01" min="0" x-model.number="f.igv" tabindex="-1" class="{{ $in }} text-right bg-gray-50"></label>
                        <label class="text-sm" x-show="exonerada || !gravado()">Importe <span class="text-xs text-gray-400" x-text="f.tdocod === '02' ? '(honorarios)' : '(exonerado / inafecto)'"></span>
                            <input type="number" step="0.01" min="0" x-model.number="f.no_gravado" @input="desdeBase()" class="{{ $in }} text-right font-semibold"></label>
                        <label class="text-sm">Total <span class="text-xs text-gray-400">(o escríbelo aquí)</span>
                            <input type="number" step="0.01" min="0" :value="total().toFixed(2)" @input="desdeTotal(+$event.target.value)" @focus="$event.target.select()"
                                   class="{{ $in }} text-right font-black text-emerald-700"></label>

                        <button type="button" tabindex="-1" @click="masTributos = !masTributos" class="col-span-2 sm:col-span-3 text-left text-xs font-semibold text-indigo-600">
                            <span x-text="masTributos ? '▾' : '▸'"></span> Más tributos: ISC, ICBPER, exonerado, otros{{ $venta ? '' : ' y retención de 4ta' }}
                            <span x-show="f.isc || f.icbper || (f.no_gravado && gravado() && !exonerada) || f.otros || f.retencion" class="ml-1 px-1.5 rounded bg-indigo-100">con montos</span></button>
                        <template x-if="masTributos">
                            <div class="col-span-2 sm:col-span-3 grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-xl bg-gray-50 p-3">
                                <label class="text-xs">ISC<input type="number" step="0.01" min="0" x-model.number="f.isc" class="{{ $in }} text-right"></label>
                                <label class="text-xs">ICBPER<input type="number" step="0.01" min="0" x-model.number="f.icbper" class="{{ $in }} text-right"></label>
                                <label class="text-xs" x-show="gravado() && !exonerada">Exonerado / inafecto<input type="number" step="0.01" min="0" x-model.number="f.no_gravado" class="{{ $in }} text-right"></label>
                                <label class="text-xs">Otros cargos<input type="number" step="0.01" x-model.number="f.otros" class="{{ $in }} text-right"></label>
                                @unless ($venta)
                                    <label class="text-xs" x-show="f.tdocod === '02'">Retención 4ta (8%)<input type="number" step="0.01" min="0" x-model.number="f.retencion" class="{{ $in }} text-right"></label>
                                @endunless
                                <label class="text-xs">Moneda<select x-model="f.moneda" class="{{ $in }}"><option value="PEN">Soles</option><option value="USD">Dólares</option></select></label>
                                <label class="text-xs" x-show="f.moneda === 'USD'">Tipo de cambio<input type="number" step="0.001" x-model.number="f.tipo_cambio" class="{{ $in }}"></label>
                                <label class="text-xs">Vence<input type="date" x-model="f.fecha_vence" class="{{ $in }}"></label>
                                <label class="text-xs">Tipo doc.<select x-model="f.doc_tipo" class="{{ $in }}"><option value="6">RUC</option><option value="1">DNI</option><option value="4">C.E.</option><option value="0">Sin doc.</option></select></label>
                            </div>
                        </template>

                        <label class="text-sm col-span-2 sm:col-span-3">Cuenta de {{ $venta ? 'ingreso' : 'compra o gasto' }} *
                            <input x-model="f.cuenta" list="cuentasRegistro" required maxlength="12" @focus="$event.target.select()" class="{{ $in }} font-mono">
                            <span class="text-xs text-gray-500" x-text="nombres[f.cuenta] || 'Escribe o elige una cuenta del plan contable'"></span></label>
                        <datalist id="cuentasRegistro">@foreach ($cuentas as $c => $n)<option value="{{ $c }}">{{ $c }} {{ $n }}</option>@endforeach</datalist>

                        <label class="text-sm">Condición<select x-model="f.condicion" class="{{ $in }}"><option value="CREDITO">Crédito</option><option value="CONTADO">Contado</option></select></label>
                        <label class="text-sm col-span-2" x-show="f.condicion === 'CONTADO'">{{ $venta ? 'Se cobró en' : 'Se pagó con' }}
                            <select x-model="f.cuenta_pago" class="{{ $in }}">@foreach ($cajas as $c => $n)<option value="{{ $c }}">{{ $c }} {{ $n }}</option>@endforeach</select></label>
                        <label class="text-sm col-span-2 sm:col-span-3">Glosa <span class="text-gray-400">(opcional)</span><input x-model="f.glosa" data-ultimo maxlength="200" class="{{ $in }} uppercase"></label>
                    </div>

                    {{-- Asiento que se genera (se puede cambiar a mano) --}}
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <p class="font-bold text-gray-800">Asiento {{ $venta ? '05 - Ventas' : '11 - Compras' }}</p>
                            <label class="ml-auto flex items-center gap-1.5 text-xs font-semibold text-amber-700">
                                <input type="checkbox" tabindex="-1" x-model="manual" @change="if (manual) lineas = propuesta().map(l => ({ ...l }))" class="rounded"> Cambiar el asiento a mano</label>
                        </div>
                        <table class="w-full text-sm border rounded-xl overflow-hidden">
                            <thead class="bg-gray-50 text-xs text-gray-500"><tr><th class="px-2 py-1.5 text-left">Cuenta</th><th class="px-2 py-1.5 text-right w-28">Debe</th><th class="px-2 py-1.5 text-right w-28">Haber</th><th class="w-8" x-show="manual"></th></tr></thead>
                            <tbody>
                                <template x-for="(l, i) in (manual ? lineas : propuesta())" :key="i">
                                    <tr class="border-t">
                                        <td class="px-2 py-1">
                                            <template x-if="!manual"><span><b class="font-mono" x-text="l.cuenta"></b> <span class="text-xs text-gray-500" x-text="nombres[l.cuenta] || '⚠ no está en el plan'"></span></span></template>
                                            <template x-if="manual"><span><input x-model="l.cuenta" list="cuentasTodas" class="w-28 h-8 rounded border-gray-300 text-sm font-mono"> <span class="text-xs text-gray-500" x-text="nombres[l.cuenta] || ''"></span></span></template>
                                        </td>
                                        <td class="px-2 py-1 text-right"><template x-if="!manual"><span x-text="l.debe ? l.debe.toFixed(2) : ''"></span></template>
                                            <template x-if="manual"><input type="number" step="0.01" min="0" x-model.number="l.debe" class="w-24 h-8 rounded border-gray-300 text-sm text-right"></template></td>
                                        <td class="px-2 py-1 text-right"><template x-if="!manual"><span x-text="l.haber ? l.haber.toFixed(2) : ''"></span></template>
                                            <template x-if="manual"><input type="number" step="0.01" min="0" x-model.number="l.haber" class="w-24 h-8 rounded border-gray-300 text-sm text-right"></template></td>
                                        <td x-show="manual" class="text-center"><button type="button" tabindex="-1" @click="lineas.splice(i, 1)" class="text-rose-500">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="!(manual ? lineas : propuesta()).length"><td colspan="3" class="px-2 py-4 text-center text-xs text-gray-400">Escribe el monto para ver el asiento.</td></tr>
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold">
                                <tr class="border-t"><td class="px-2 py-1.5">Totales
                                        <button type="button" tabindex="-1" x-show="manual" @click="lineas.push({ cuenta: '', debe: 0, haber: 0 })" class="ml-2 text-xs text-indigo-600 font-semibold">+ línea</button></td>
                                    <td class="px-2 py-1.5 text-right" x-text="suma('debe').toFixed(2)"></td><td class="px-2 py-1.5 text-right" x-text="suma('haber').toFixed(2)"></td><td x-show="manual"></td></tr>
                            </tfoot>
                        </table>
                        <datalist id="cuentasTodas">@foreach ($imputables as $c => $n)<option value="{{ $c }}">{{ $c }} {{ $n }}</option>@endforeach</datalist>
                        <p class="mt-2 text-sm font-bold" :class="cuadra() ? 'text-emerald-600' : 'text-gray-400'" x-text="cuadra() ? '✔ El asiento cuadra' : (total() > 0 ? '✖ Debe y Haber no cuadran' : '')"></p>
                        <p x-show="f.condicion === 'CONTADO'" class="mt-2 text-xs text-gray-500">Además se registra el asiento 01 de {{ $venta ? 'cobro' : 'pago' }} en <b x-text="f.cuenta_pago"></b>.</p>
                        <p x-show="f.moneda === 'USD'" class="mt-1 text-xs text-gray-500">El asiento va en soles (tipo de cambio <span x-text="f.tipo_cambio"></span>).</p>
                    </div>
                </div>
                <div class="px-5 py-4 border-t flex flex-wrap items-center gap-3">
                    <p x-show="error" class="text-sm text-rose-600 font-semibold" x-text="error"></p>
                    <label x-show="!f.id" class="ml-auto flex items-center gap-1.5 text-sm text-gray-600"><input type="checkbox" tabindex="-1" x-model="seguir" class="rounded"> Al guardar, abrir el siguiente</label>
                    <button type="button" tabindex="-1" @click="abierto = false" class="px-4 h-11 rounded-xl bg-gray-100 font-semibold" :class="f.id ? 'ml-auto' : ''">Cancelar</button>
                    <button x-ref="guardar" :disabled="enviando || !cuadra()" class="px-6 h-11 rounded-xl bg-indigo-600 text-white font-bold disabled:opacity-50" x-text="enviando ? 'Guardando…' : 'Guardar (Enter)'"></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function registroContable(cfg) {
            const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
            const CLAVE = 'registro_contable_' + cfg.tipo;
            const vacio = () => ({ id: null, fecha: new Date().toISOString().slice(0, 10), fecha_vence: '', tdocod: '01', serie: '', numero: '', doc_tipo: '6', doc_num: '',
                nombre: '', moneda: 'PEN', tipo_cambio: 1, base: 0, igv: 0, isc: 0, icbper: 0, no_gravado: 0, otros: 0, retencion: 0,
                cuenta: cfg.cuentaDefecto, condicion: 'CREDITO', cuenta_pago: cfg.caja, glosa: '' });
            return {
                ...cfg, r2, abierto: false, enviando: false, buscando: false, error: '', manual: false, masTributos: false, seguir: true, lineas: [], f: vacio(),
                init() {
                    // Después de guardar se abre el siguiente con la misma fecha, documento, serie y cuenta
                    try {
                        const siguiente = JSON.parse(sessionStorage.getItem(CLAVE) || 'null');
                        if (siguiente) { sessionStorage.removeItem(CLAVE); this.nuevo(siguiente); }
                    } catch (e) {}
                },
                nuevo(base = {}) {
                    this.f = { ...vacio(), ...base }; this.manual = false; this.masTributos = false; this.error = ''; this.abierto = true;
                    this.$nextTick(() => (base.serie ? this.$refs.numero : this.$refs.form.querySelector('input')).focus());
                },
                editar(id) {
                    const r = this.registros[id];
                    this.f = { ...vacio(), ...r, fecha_vence: r.fecha_vence || '', serie: r.serie || '', doc_num: r.doc_num || '', glosa: r.glosa || '',
                        base: +r.base, igv: +r.igv, isc: +r.isc, icbper: +r.icbper, no_gravado: +r.no_gravado, otros: +r.otros, retencion: +r.retencion,
                        tipo_cambio: +r.tipo_cambio, cuenta_pago: r.cuenta_pago || cfg.caja };
                    this.masTributos = !!(this.f.isc || this.f.icbper || this.f.otros || this.f.retencion || this.f.moneda === 'USD' || (this.gravado() && this.f.no_gravado));
                    this.manual = false; this.error = ''; this.abierto = true;
                },
                // Recibo por honorarios: sin IGV
                gravado() { return this.f.tdocod !== '02'; },
                cambioDocumento() { if (!this.gravado() || this.exonerada) { this.f.no_gravado = r2(this.f.base + this.f.no_gravado); this.f.base = 0; this.f.igv = 0; } this.retencionSugerida(); },
                desdeBase() { this.f.igv = !this.exonerada && this.gravado() ? r2(this.f.base * this.tasa / 100) : 0; this.retencionSugerida(); },
                // Se escribió el total: se separa la base y el IGV con la tasa
                desdeTotal(total) {
                    const extra = r2(this.f.isc + this.f.icbper + this.f.otros + (this.exonerada || !this.gravado() ? 0 : this.f.no_gravado));
                    const neto = r2(Math.max(0, total - extra));
                    if (this.exonerada || !this.gravado()) { this.f.no_gravado = neto; this.f.base = 0; this.f.igv = 0; }
                    else { this.f.base = r2(neto / (1 + this.tasa / 100)); this.f.igv = r2(neto - this.f.base); }
                    this.retencionSugerida();
                },
                // Retención de 4ta: 8% del recibo mayor a S/ 1,500
                retencionSugerida() { if (this.tipo === 'COMPRA' && this.f.tdocod === '02') this.f.retencion = this.f.no_gravado > 1500 ? r2(this.f.no_gravado * 0.08) : 0; else this.f.retencion = 0; },
                total() { return r2(this.f.base + this.f.igv + this.f.isc + this.f.icbper + this.f.no_gravado + this.f.otros); },
                // Mismo cálculo que el servidor (Registro::propuesta)
                propuesta() {
                    const f = this.f, c = this.cfg, tc = f.moneda === 'USD' ? (Number(f.tipo_cambio) || 0) : 1, nota = f.tdocod === '07';
                    const s = v => r2((Number(v) || 0) * tc), total = r2(this.total() * tc), igv = s(f.igv), isc = s(f.isc), icb = s(f.icbper),
                        exo = s(f.no_gravado), ret = s(f.retencion), neto = r2(total - ret);
                    const l = (cuenta, m, alDebe) => m && cuenta ? { cuenta, debe: (alDebe !== nota) ? m : 0, haber: (alDebe !== nota) ? 0 : m } : null;
                    let out;
                    if (this.tipo === 'VENTA') {
                        const aparte = exo > 0 && f.cuenta === c.ventas && c.ventas_exo !== c.ventas;
                        out = [l(c.por_cobrar, total, true), l(c.igv, igv, false), l(c.isc, isc, false), l(c.icbper, icb, false),
                            l(f.cuenta, r2(total - igv - isc - icb - (aparte ? exo : 0)), false), aparte ? l(c.ventas_exo, exo, false) : null];
                    } else {
                        const costo = r2(total - igv);
                        out = [l(f.cuenta, costo, true), l(c.igv_compras, igv, true), l(c.por_pagar, neto, false), l(c.renta4, ret, false)];
                        if (String(f.cuenta).startsWith('60') && c.destino) out.push(l(c.mercaderias, costo, true), l(c.variacion, costo, false));
                        else if (/^6[2-8]/.test(f.cuenta) && c.gastos) out.push(l('94', costo, true), l('79', costo, false));
                    }
                    return out.filter(Boolean);
                },
                suma(lado) { return r2((this.manual ? this.lineas : this.propuesta()).reduce((s, x) => s + (Number(x[lado]) || 0), 0)); },
                cuadra() { return this.suma('debe') > 0 && Math.abs(this.suma('debe') - this.suma('haber')) < 0.005; },
                // Enter: al siguiente campo visible; en el RUC primero busca el nombre; en el último, guarda
                async siguiente(e) {
                    const el = e.target;
                    if (el.tagName === 'BUTTON' || el.tagName === 'TEXTAREA') return;
                    e.preventDefault();
                    if (el.hasAttribute('data-buscar') && /^(\d{8}|\d{11})$/.test(String(this.f.doc_num).trim()) && !this.f.nombre) await this.buscarDoc();
                    const campos = [...this.$refs.form.querySelectorAll('input, select')].filter(x => x.offsetParent !== null && !x.disabled && x.tabIndex !== -1 && x.type !== 'checkbox');
                    const i = campos.indexOf(el);
                    if (el.hasAttribute('data-ultimo') || i === campos.length - 1) { this.cuadra() ? this.enviar() : (this.error = 'El asiento no cuadra: revisa los montos.'); return; }
                    const prox = campos[i + 1];
                    prox.focus(); if (prox.select && prox.type !== 'date') prox.select();
                },
                async buscarDoc() {
                    const doc = String(this.f.doc_num || '').trim();
                    if (!/^(\d{8}|\d{11})$/.test(doc)) { this.error = 'Escribe un RUC (11) o DNI (8).'; return; }
                    this.buscando = true; this.error = '';
                    try {
                        const r = await (await fetch(this.documento + '/' + doc, { headers: { Accept: 'application/json' } })).json();
                        if (r.ok) { this.f.nombre = r.nombre; this.f.doc_tipo = doc.length === 11 ? '6' : '1'; } else { this.error = r.mensaje; }
                    } catch (e) { this.error = 'No se pudo consultar.'; }
                    this.buscando = false;
                },
                async enviar() {
                    if (this.enviando) return;
                    this.enviando = true; this.error = '';
                    const datos = { ...this.f };
                    if (this.manual) datos.lineas = this.lineas.filter(x => x.cuenta && (x.debe || x.haber));
                    try {
                        const resp = await fetch(this.guardar + (this.f.id ? '/' + this.f.id : ''), {
                            method: 'POST', body: JSON.stringify(datos),
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        });
                        const r = await resp.json();
                        if (resp.status === 422) { this.error = Object.values(r.errors)[0][0]; return; }
                        if (!r.success) { this.error = r.message; return; }
                        if (!this.f.id && this.seguir) {
                            const sig = /^\d+$/.test(this.f.numero) ? String(+this.f.numero + 1) : '';
                            sessionStorage.setItem(CLAVE, JSON.stringify({ fecha: this.f.fecha, tdocod: this.f.tdocod, serie: this.f.serie, numero: sig,
                                cuenta: this.f.cuenta, condicion: this.f.condicion, cuenta_pago: this.f.cuenta_pago, moneda: this.f.moneda, tipo_cambio: this.f.tipo_cambio }));
                        }
                        location.href = location.pathname + '?periodo=' + r.periodo;
                    } catch (e) { this.error = 'Sin conexión. Intenta otra vez.'; }
                    finally { this.enviando = false; }
                },
            };
        }
    </script>
@endsection
