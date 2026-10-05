@extends('layouts.app')
@section('title', 'Gastos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php
        $soles = fn($n) => 'S/ ' . number_format($n, 2);
        $maxCat = max(1, (float) $porCategoria->max('total'));
    @endphp

    <div x-data="gastos(@js($categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()), @js($medios->first()->id_med_pag ?? null))">
        {{-- Filtros y acciones --}}
        <div class="flex flex-col lg:flex-row gap-3 mb-4">
            <form method="GET" class="bg-white rounded-2xl shadow-sm p-3 flex flex-wrap items-end gap-2 flex-1">
                <label class="text-sm">Mes<input type="month" name="mes" value="{{ $mes }}" onchange="this.form.submit()" class="block rounded-lg border-gray-300 text-sm font-semibold"></label>
                <label class="text-sm">Categoría
                    <select name="categoria" onchange="this.form.submit()" class="block rounded-lg border-gray-300 text-sm">
                        <option value="">Todas</option>
                        @foreach ($categorias as $c)<option value="{{ $c->id }}" @selected(request('categoria') == $c->id)>{{ $c->nombre }}</option>@endforeach
                    </select></label>
                <label class="text-sm flex-1 min-w-[160px]">Buscar<input name="q" value="{{ $q }}" placeholder="Proveedor, RUC, número…" class="block w-full rounded-lg border-gray-300 text-sm"></label>
                <button class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Filtrar</button>
                <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-3 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i></a>
            </form>
            <div class="flex gap-2">
                @if (auth()->user()->esAdmin())
                    <button type="button" @click="abrirSire()" class="px-4 py-3 rounded-2xl bg-white shadow-sm text-sm font-semibold text-indigo-700 hover:bg-indigo-50"><i class="fas fa-cloud-arrow-down"></i> Importar del SIRE</button>
                @endif
                <button type="button" @click="nuevo()" class="px-5 py-3 rounded-2xl bg-rose-600 text-white text-sm font-bold hover:bg-rose-700 shadow-sm"><i class="fas fa-plus"></i> Nuevo gasto</button>
            </div>
        </div>

        {{-- Resumen --}}
        <div class="grid lg:grid-cols-3 gap-4 mb-4">
            <div class="grid grid-cols-2 gap-3 lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm p-4 col-span-2"><p class="text-xs text-gray-500">Gastos del mes</p><p class="text-3xl font-black text-rose-600">{{ $soles($totales['total']) }}</p>
                    <p class="text-xs text-gray-400">{{ $totales['n'] }} comprobante(s)</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">IGV crédito fiscal</p><p class="text-lg font-black text-emerald-600">{{ $soles($totales['igv']) }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Retenciones 4ta</p><p class="text-lg font-black text-amber-600">{{ $soles($totales['retencion']) }}</p></div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4 lg:col-span-2">
                <p class="text-sm font-bold text-gray-700 mb-2">¿En qué se fue el dinero?</p>
                <div class="space-y-1.5 max-h-44 overflow-y-auto">
                    @forelse ($porCategoria as $nombre => $c)
                        <div class="flex items-center gap-2 text-sm">
                            <span class="w-40 truncate text-gray-600">{{ $nombre ?: 'SIN CATEGORÍA' }}</span>
                            <div class="flex-1 h-3 rounded-full bg-gray-100"><div class="h-3 rounded-full" style="width: {{ round($c['total'] / $maxCat * 100) }}%; background: {{ $c['color'] ?? '#9ca3af' }}"></div></div>
                            <span class="w-24 text-right font-semibold">{{ number_format($c['total'], 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 py-6 text-center">Sin gastos registrados en el mes.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Lista --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-left">Proveedor</th>
                        <th class="px-3 py-2 text-left">Categoría</th><th class="px-3 py-2 text-right">Base</th><th class="px-3 py-2 text-right">IGV</th>
                        <th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($gastos as $g)
                        <tr class="hover:bg-gray-50 {{ $g->estado !== 'Registrado' ? 'opacity-50 line-through' : '' }}">
                            <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                            <td class="px-3 py-2"><span class="text-xs text-gray-500">{{ $documentos[$g->tdocod] ?? $g->tdocod }}</span>
                                <span class="block font-semibold">{{ trim(($g->serie ? $g->serie . '-' : '') . $g->numero, '-') ?: '—' }}</span>
                                @if ($g->origen === 'SIRE')<span class="text-[10px] px-1.5 rounded bg-indigo-100 text-indigo-700 font-bold">SIRE</span>@endif</td>
                            <td class="px-3 py-2"><span class="font-semibold text-gray-700">{{ $g->prov_nombre ?: '—' }}</span><span class="block text-xs text-gray-400">{{ $g->prov_doc }} {{ $g->descripcion ? '· ' . $g->descripcion : '' }}</span></td>
                            <td class="px-3 py-2"><span class="text-[11px] px-2 py-0.5 rounded-full font-bold text-white" style="background: {{ $g->color ?? '#9ca3af' }}">{{ $g->categoria ?? '—' }}</span></td>
                            <td class="px-3 py-2 text-right">{{ number_format($g->base + $g->no_gravado, 2) }}</td>
                            <td class="px-3 py-2 text-right {{ $g->credito_fiscal ? 'text-emerald-700' : 'text-gray-400' }}">{{ number_format($g->igv, 2) }}</td>
                            <td class="px-3 py-2 text-right font-bold whitespace-nowrap">{{ $g->moneda === 'USD' ? 'US$' : 'S/' }} {{ $g->tdocod === '07' ? '-' : '' }}{{ number_format($g->total, 2) }}
                                @if ($g->retencion > 0)<span class="block text-[10px] text-amber-600">Ret. 4ta {{ number_format($g->retencion, 2) }}</span>@endif
                                @if ($g->forma_pago === 'CREDITO')<span class="block text-[10px] text-amber-600 font-semibold">CRÉDITO</span>@endif</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @if ($g->estado === 'Registrado')
                                    <button type="button" class="text-indigo-600 text-xs hover:underline" @click="editar({{ $g->id }}, @js(collect((array) $g)->only(['fecha', 'tdocod', 'serie', 'numero', 'prov_doc', 'prov_nombre', 'categoria_id', 'descripcion', 'moneda', 'tipo_cambio', 'base', 'igv', 'no_gravado', 'retencion', 'credito_fiscal', 'forma_pago', 'id_med_pag'])))">Editar</button>
                                    <form method="POST" action="{{ route('gastos.anular', $g->id) }}" class="inline" onsubmit="return confirm('¿Anular este gasto?')">@csrf<button class="text-rose-600 text-xs hover:underline ml-2">Anular</button></form>
                                @else<span class="text-xs text-gray-400 no-underline">Anulado</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No hay gastos en este mes. Registra luz, agua, alquiler, internet, honorarios…</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">La <strong>mercadería</strong> se registra en Compras (mueve stock). Aquí van los gastos del negocio. <a href="{{ route('gastos.reporte') }}" class="text-indigo-600 hover:underline">Ver reporte anual →</a></p>

        {{-- Modal gasto --}}
        <div x-show="modal === 'gasto'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center sm:p-4" @click.self="modal = null">
            <form @submit.prevent="guardar()" class="bg-white w-full sm:max-w-2xl rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[95vh] overflow-y-auto">
                <div class="px-5 py-3 border-b flex justify-between items-center sticky top-0 bg-white">
                    <h3 class="font-bold text-gray-800" x-text="id ? 'Editar gasto' : 'Nuevo gasto'"></h3>
                    <button type="button" @click="modal = null" class="text-gray-400 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-5 space-y-3 text-sm">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <label>Fecha<input type="date" x-model="g.fecha" required class="block w-full mt-1 rounded-lg border-gray-300"></label>
                        <label class="col-span-1 sm:col-span-2">Comprobante
                            <select x-model="g.tdocod" @change="ajustarTipo()" class="block w-full mt-1 rounded-lg border-gray-300">
                                @foreach ($documentos as $c => $n)<option value="{{ $c }}">{{ $n }}</option>@endforeach
                            </select></label>
                        <label>Categoría
                            <select x-model="g.categoria_id" required class="block w-full mt-1 rounded-lg border-gray-300">
                                <template x-for="c in categorias" :key="c.id"><option :value="c.id" x-text="c.nombre"></option></template>
                            </select></label>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                        <label>Serie<input x-model="g.serie" maxlength="6" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                        <label>Número<input x-model="g.numero" maxlength="20" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                        <label class="col-span-1 sm:col-span-1">RUC / DNI<input x-model="g.prov_doc" maxlength="15" inputmode="numeric" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                        <label class="col-span-3 sm:col-span-3">Proveedor<input x-model="g.prov_nombre" maxlength="200" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                    </div>
                    <label class="block">Descripción<input x-model="g.descripcion" maxlength="255" placeholder="Ej. RECIBO DE LUZ DE OCTUBRE" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>

                    <div class="rounded-xl bg-gray-50 p-3 space-y-3">
                        <div class="flex flex-wrap items-end gap-3">
                            <label class="flex-1 min-w-[140px]">Total del comprobante
                                <input type="number" step="0.01" min="0" x-model.number="rapido" @input="repartir()" placeholder="0.00" class="block w-full mt-1 rounded-lg border-gray-300 text-lg font-bold text-right"></label>
                            <label class="flex items-center gap-2 pb-2"><input type="checkbox" x-model="conIgv" @change="repartir()" class="rounded"> Incluye IGV 18 %</label>
                            <label>Moneda<select x-model="g.moneda" class="block mt-1 rounded-lg border-gray-300"><option value="PEN">Soles</option><option value="USD">Dólares</option></select></label>
                            <label x-show="g.moneda === 'USD'">T.C.<input type="number" step="0.001" x-model.number="g.tipo_cambio" class="block w-24 mt-1 rounded-lg border-gray-300"></label>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <label>Base gravada<input type="number" step="0.01" min="0" x-model.number="g.base" class="block w-full mt-1 rounded-lg border-gray-300 text-right"></label>
                            <label>IGV<input type="number" step="0.01" min="0" x-model.number="g.igv" class="block w-full mt-1 rounded-lg border-gray-300 text-right"></label>
                            <label>No gravado<input type="number" step="0.01" min="0" x-model.number="g.no_gravado" class="block w-full mt-1 rounded-lg border-gray-300 text-right"></label>
                            <label>Retención 4ta<input type="number" step="0.01" min="0" x-model.number="g.retencion" class="block w-full mt-1 rounded-lg border-gray-300 text-right"></label>
                        </div>
                        <div class="flex flex-wrap justify-between items-center gap-2">
                            <label class="flex items-center gap-2"><input type="checkbox" x-model="g.credito_fiscal" class="rounded"> Usar el IGV como crédito fiscal <span class="text-xs text-gray-400">(solo facturas)</span></label>
                            <p class="font-bold">Total: <span x-text="n2(total())"></span> <span x-show="g.retencion > 0" class="text-xs text-amber-600" x-text="'· a pagar ' + n2(total() - g.retencion)"></span></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label>Forma de pago<select x-model="g.forma_pago" class="block w-full mt-1 rounded-lg border-gray-300"><option value="CONTADO">Contado</option><option value="CREDITO">Crédito (por pagar)</option></select></label>
                        <label x-show="g.forma_pago === 'CONTADO'">Pagado con
                            <select x-model="g.id_med_pag" class="block w-full mt-1 rounded-lg border-gray-300">
                                @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                            </select></label>
                    </div>
                    <p class="text-rose-600 font-semibold" x-text="error"></p>
                </div>
                <div class="px-5 py-3 border-t flex justify-end gap-2 sticky bottom-0 bg-white">
                    <button type="button" @click="modal = null" class="px-4 py-2 rounded-xl bg-gray-100 font-semibold">Cancelar</button>
                    <button :disabled="enviando" class="px-6 py-2 rounded-xl bg-rose-600 text-white font-semibold disabled:opacity-50" x-text="enviando ? 'Guardando…' : 'Guardar gasto'"></button>
                </div>
            </form>
        </div>

        {{-- Modal SIRE --}}
        <div x-show="modal === 'sire'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center sm:p-4" @click.self="modal = null">
            <div class="bg-white w-full sm:max-w-5xl rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[95vh] flex flex-col">
                <div class="px-5 py-3 border-b flex justify-between items-center">
                    <div><h3 class="font-bold text-gray-800">Importar gastos desde el SIRE (RCE)</h3>
                        <p class="text-xs text-gray-500">Comprobantes que SUNAT tiene a tu nombre y aún no están en Compras ni en Gastos.</p></div>
                    <button type="button" @click="modal = null" class="text-gray-400 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-5 overflow-y-auto space-y-3 text-sm">
                    @if ($solicitudesSire->isEmpty())
                        <p class="rounded-xl bg-amber-50 text-amber-800 p-4">Aún no hay propuestas RCE descargadas. Ve a <a href="{{ route('sire.index', 'compras') }}" class="font-bold underline">SIRE Compras (RCE)</a>, solicita y descarga la propuesta del periodo.</p>
                    @else
                        <div class="flex flex-wrap items-end gap-2">
                            <label>Propuesta
                                <select x-model="sire.solicitud" class="block mt-1 rounded-lg border-gray-300">
                                    @foreach ($solicitudesSire as $s)<option value="{{ $s->id }}">{{ substr($s->periodo, 4, 2) }}/{{ substr($s->periodo, 0, 4) }} ({{ $s->filas }} comprobantes)</option>@endforeach
                                </select></label>
                            <button type="button" @click="cargarSire()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold">Buscar pendientes</button>
                            <label x-show="sire.filas.length" class="ml-auto">Categoría para todos
                                <select @change="sire.filas.forEach(f => f.categoria_id = $event.target.value)" class="block mt-1 rounded-lg border-gray-300">
                                    <option value="">—</option><template x-for="c in categorias" :key="c.id"><option :value="c.id" x-text="c.nombre"></option></template>
                                </select></label>
                        </div>
                        <p x-show="sire.cargando" class="text-gray-400">Leyendo la propuesta…</p>
                        <p x-show="sire.mensaje" class="font-semibold" :class="sire.ok ? 'text-emerald-600' : 'text-rose-600'" x-text="sire.mensaje"></p>
                        <table x-show="sire.filas.length" class="w-full text-sm">
                            <thead class="text-xs uppercase text-gray-500 bg-gray-50"><tr><th class="p-2"><input type="checkbox" @change="sire.filas.forEach(f => f.sel = $event.target.checked)" checked class="rounded"></th>
                                <th class="p-2 text-left">Fecha</th><th class="p-2 text-left">Comprobante</th><th class="p-2 text-left">Proveedor</th><th class="p-2 text-right">Total</th><th class="p-2 text-left">Categoría</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="f in sire.filas" :key="f.clave">
                                    <tr>
                                        <td class="p-2 text-center"><input type="checkbox" x-model="f.sel" class="rounded"></td>
                                        <td class="p-2 whitespace-nowrap" x-text="f.fecha"></td>
                                        <td class="p-2" x-text="f.tipo + ' ' + f.serie + '-' + f.numero"></td>
                                        <td class="p-2"><span x-text="f.prov_nombre"></span><span class="block text-xs text-gray-400" x-text="f.prov_doc"></span></td>
                                        <td class="p-2 text-right font-semibold" x-text="(f.moneda === 'USD' ? 'US$ ' : '') + n2(f.total)"></td>
                                        <td class="p-2"><select x-model="f.categoria_id" class="rounded-lg border-gray-300 text-xs">
                                            <option value="">Elegir…</option><template x-for="c in categorias" :key="c.id"><option :value="c.id" x-text="c.nombre"></option></template></select></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p class="text-xs text-gray-400">Si alguno es compra de <strong>mercadería</strong>, no lo importes aquí: regístralo en Compras para que entre al stock.</p>
                    @endif
                </div>
                <div class="px-5 py-3 border-t flex justify-end gap-2" x-show="sire.filas.length">
                    <button type="button" @click="importarSire()" :disabled="enviando" class="px-6 py-2 rounded-xl bg-indigo-600 text-white font-semibold disabled:opacity-50"
                            x-text="enviando ? 'Importando…' : 'Importar seleccionados (' + sire.filas.filter(f => f.sel).length + ')'"></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function gastos(categorias, medio) {
            const CSRF = @js(csrf_token());
            const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
            const post = (url, data) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) })
                .then(async r => { const d = await r.json().catch(() => ({ message: 'Error del servidor.' })); if (r.status === 422) d.message = Object.values(d.errors)[0][0]; return d; });
            return {
                categorias, modal: null, id: null, g: {}, rapido: null, conIgv: true, error: '', enviando: false,
                sire: { solicitud: @js($solicitudesSire->first()->id ?? null), filas: [], cargando: false, mensaje: '', ok: true },
                n2(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                total() { return r2((Number(this.g.base) || 0) + (Number(this.g.igv) || 0) + (Number(this.g.no_gravado) || 0)); },
                nuevo() {
                    this.id = null; this.error = ''; this.rapido = null; this.conIgv = true;
                    this.g = { fecha: new Date().toISOString().slice(0, 10), tdocod: '01', serie: '', numero: '', prov_doc: '', prov_nombre: '', categoria_id: categorias[0]?.id,
                        descripcion: '', moneda: 'PEN', tipo_cambio: null, base: 0, igv: 0, no_gravado: 0, retencion: 0, credito_fiscal: true, forma_pago: 'CONTADO', id_med_pag: medio };
                    this.modal = 'gasto';
                },
                editar(id, datos) {
                    this.id = id; this.error = ''; this.g = { ...datos, credito_fiscal: !!Number(datos.credito_fiscal) };
                    this.rapido = this.total(); this.conIgv = Number(datos.igv) > 0; this.modal = 'gasto';
                },
                // Escribe el total y el sistema separa base e IGV (o retención 8 % en recibos por honorarios mayores a S/ 1,500)
                repartir() {
                    const t = Number(this.rapido) || 0;
                    if (this.g.tdocod === '02') {
                        this.g.base = 0; this.g.igv = 0; this.g.no_gravado = r2(t); this.g.retencion = t > 1500 ? r2(t * 0.08) : 0; return;
                    }
                    if (this.conIgv) { this.g.base = r2(t / 1.18); this.g.igv = r2(t - this.g.base); this.g.no_gravado = 0; }
                    else { this.g.base = 0; this.g.igv = 0; this.g.no_gravado = r2(t); }
                },
                ajustarTipo() {
                    this.g.credito_fiscal = this.g.tdocod === '01' || this.g.tdocod === '14';
                    if (['02', '12', '00'].includes(this.g.tdocod)) this.conIgv = false;
                    this.repartir();
                },
                async guardar() {
                    this.enviando = true; this.error = '';
                    const d = await post(@js(url('gastos')) + (this.id ? '/' + this.id : ''), this.g);
                    this.enviando = false;
                    if (!d.success) { this.error = d.message; return; }
                    location.href = @js(route('gastos.index')) + '?mes=' + d.mes;
                },
                abrirSire() { this.modal = 'sire'; },
                async cargarSire() {
                    this.sire.cargando = true; this.sire.mensaje = ''; this.sire.filas = [];
                    const d = await fetch(@js(route('gastos.sire')) + '?solicitud=' + this.sire.solicitud, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    this.sire.cargando = false;
                    if (!d.success) { this.sire.ok = false; this.sire.mensaje = d.message; return; }
                    this.sire.filas = d.filas.map(f => ({ ...f, sel: true, categoria_id: '' }));
                    this.sire.ok = true;
                    this.sire.mensaje = d.filas.length ? `${d.filas.length} comprobante(s) de SUNAT no están registrados.` : '✔ Todo lo de SUNAT ya está registrado en el sistema.';
                },
                async importarSire() {
                    const filas = this.sire.filas.filter(f => f.sel);
                    if (filas.some(f => !f.categoria_id)) { this.sire.ok = false; this.sire.mensaje = 'Elige la categoría de cada comprobante seleccionado.'; return; }
                    this.enviando = true;
                    const d = await post(@js(route('gastos.sire.importar')), { solicitud: this.sire.solicitud, filas: filas.map(f => ({ clave: f.clave, categoria_id: f.categoria_id })) });
                    this.enviando = false;
                    alert(d.message);
                    if (d.success) location.reload();
                },
            };
        }
    </script>
@endsection
