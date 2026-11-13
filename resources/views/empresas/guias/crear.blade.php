@extends('layouts.app')
@section('title', 'Nueva guía de remisión')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $caja = 'bg-white rounded-2xl shadow-sm p-5';
        $tit = 'text-sm font-bold uppercase tracking-wide mb-4 flex items-center gap-2';
    @endphp

    <div x-data="guia()" class="max-w-6xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-truck-fast text-indigo-600"></i> Guía de remisión electrónica</h1>
                <p class="text-sm text-gray-500">Número que tendrá: <b>{{ $serie }}-{{ $siguiente }}</b>
                    @if ($venta) · de la venta <b>{{ $guia['doc_numero'] ?: 'N° interno '.$venta }}</b> (cliente y productos ya cargados)@endif
                    @if ($proforma ?? null) · de la proforma <b>{{ $proforma }}</b> (cliente y productos ya cargados; revisa las cantidades)@endif</p>
            </div>
            <a href="{{ route('guias.index') }}" class="px-4 py-2 rounded-xl bg-white border text-sm font-semibold text-gray-600 hover:bg-gray-50"><i class="fas fa-list"></i> Ver guías</a>
        </div>

        @if ($yaTieneGuias->isNotEmpty())
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                <i class="fas fa-triangle-exclamation"></i> Esta venta ya tiene guía:
                {{ $yaTieneGuias->map(fn ($x) => $x->serie.'-'.$x->numero.' ('.$x->est_sunat.')')->implode(', ') }}. Revisa que no la dupliques.
            </div>
        @endif

        {{-- 1. Traslado --}}
        <div class="{{ $caja }}">
            <h3 class="{{ $tit }} text-indigo-700"><span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs">1</span> Datos del traslado</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <label>Motivo del traslado
                    <select x-model="g.motivo" class="{{ $in }} mt-1">
                        @foreach ($motivos as $cod => $nom)<option value="{{ $cod }}">{{ $cod }} · {{ $nom }}</option>@endforeach
                    </select></label>
                <label x-show="g.motivo === '13'">Describe el motivo
                    <input x-model="g.motivo_desc" maxlength="100" class="{{ $in }} mt-1"></label>
                <label>Fecha de traslado
                    <input type="date" x-model="g.fecha_traslado" min="{{ now()->toDateString() }}" class="{{ $in }} mt-1"></label>
                <label>Peso total
                    <div class="flex gap-1 mt-1">
                        <input type="number" step="0.001" min="0" x-model.number="g.peso" placeholder="0.000" class="{{ $in }}">
                        <select x-model="g.unidad_peso" class="rounded-lg border-gray-300 text-sm"><option value="KGM">kg</option><option value="TNE">t</option></select>
                    </div></label>
                <label x-show="['08', '09'].includes(g.motivo)">N° de bultos
                    <input type="number" min="1" x-model.number="g.bultos" class="{{ $in }} mt-1"></label>
                <label class="lg:col-span-2">Comprobante relacionado <span class="text-gray-400">(opcional)</span>
                    <div class="flex gap-1 mt-1">
                        <select x-model="g.doc_tdocod" class="rounded-lg border-gray-300 text-sm"><option value="">—</option><option value="01">Factura</option><option value="03">Boleta</option><option value="12">Ticket</option></select>
                        <input x-model="g.doc_numero" placeholder="F001-123" maxlength="15" class="{{ $in }} uppercase">
                    </div></label>
            </div>
        </div>

        {{-- 2. Destinatario --}}
        <div class="{{ $caja }}">
            <h3 class="{{ $tit }} text-emerald-700"><span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs">2</span> Destinatario</h3>
            <div class="grid sm:grid-cols-[140px_200px_1fr] gap-4 text-sm">
                <label>Tipo
                    <select x-model="g.dest_tdicod" class="{{ $in }} mt-1"><option value="6">RUC</option><option value="1">DNI</option><option value="4">Carné ext.</option><option value="7">Pasaporte</option></select></label>
                <label>N° documento
                    <input x-model="g.dest_num" @change="buscarDestinatario()" @keydown.enter.prevent="buscarDestinatario()" maxlength="15" class="{{ $in }} mt-1"></label>
                <label>Nombre o razón social
                    <input x-model="g.dest_nom" maxlength="150" class="{{ $in }} mt-1 uppercase"></label>
            </div>
            <p x-show="buscando" class="text-xs text-gray-400 mt-2">Buscando…</p>
        </div>

        {{-- 3. Partida y llegada --}}
        <div class="{{ $caja }}">
            <h3 class="{{ $tit }} text-sky-700"><span class="w-6 h-6 rounded-full bg-sky-600 text-white flex items-center justify-center text-xs">3</span> Punto de partida y de llegada</h3>
            <div class="grid lg:grid-cols-2 gap-6 text-sm">
                <div class="space-y-3">
                    <p class="font-semibold text-gray-600"><i class="fas fa-warehouse text-sky-500"></i> Partida (de dónde sale)</p>
                    @if ($almacenes->isNotEmpty())
                        <select @change="usarAlmacen($event.target.value)" class="{{ $in }}">
                            <option value="">Usar la dirección de un almacén…</option>
                            @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
                        </select>
                    @endif
                    <div class="block" data-campo="partida_ubigeo">Ciudad / distrito
                        <div class="mt-1">@include('empresas.partials.ubigeo', ['model' => 'g.partida_ubigeo'])</div></div>
                    <label class="block">Dirección<input x-model="g.partida_direccion" maxlength="200" class="{{ $in }} mt-1 uppercase"></label>
                    <label x-show="g.motivo === '04'" class="block">Código de establecimiento (SUNAT)<input x-model="g.partida_codlocal" maxlength="4" placeholder="0000" class="{{ $in }} mt-1"></label>
                </div>
                <div class="space-y-3">
                    <p class="font-semibold text-gray-600"><i class="fas fa-location-dot text-rose-500"></i> Llegada (a dónde va)</p>
                    <div class="block" data-campo="llegada_ubigeo">Ciudad / distrito
                        <div class="mt-1">@include('empresas.partials.ubigeo', ['model' => 'g.llegada_ubigeo'])</div></div>
                    <label class="block">Dirección<input x-model="g.llegada_direccion" maxlength="200" placeholder="Calle, número, referencia" class="{{ $in }} mt-1 uppercase"></label>
                    <label x-show="g.motivo === '04'" class="block">Código de establecimiento (SUNAT)<input x-model="g.llegada_codlocal" maxlength="4" placeholder="0001" class="{{ $in }} mt-1"></label>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3"><i class="fas fa-circle-info"></i> Escribe la ciudad o el distrito y elígelo de la lista. Con RUC sale solo del padrón de SUNAT, y la llegada de un cliente se recuerda de su guía anterior.</p>
        </div>

        {{-- 4. Transporte --}}
        <div class="{{ $caja }}">
            <h3 class="{{ $tit }} text-amber-700"><span class="w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center text-xs">4</span> Transporte</h3>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($modalidades as $cod => $nom)
                    <button type="button" @click="g.modalidad = '{{ $cod }}'" class="px-4 py-2 rounded-xl border-2 text-sm font-semibold"
                            :class="g.modalidad === '{{ $cod }}' ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-600'">{{ $nom }}</button>
                @endforeach
            </div>
            <div x-show="g.modalidad === '01'" class="grid sm:grid-cols-[200px_1fr_200px] gap-4 text-sm">
                <label>RUC del transportista<input x-model="g.transp_ruc" @change="buscarTransportista()" maxlength="11" class="{{ $in }} mt-1"></label>
                <label>Razón social<input x-model="g.transp_nom" maxlength="150" class="{{ $in }} mt-1 uppercase"></label>
                <label>N° registro MTC <span class="text-gray-400">(opcional)</span><input x-model="g.transp_mtc" maxlength="20" class="{{ $in }} mt-1"></label>
            </div>
            <div x-show="g.modalidad === '02'" class="space-y-4 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" x-model="g.vehiculo_m1l" class="rounded"> Vehículo de categoría M1 o L (auto, moto, mototaxi): no requiere datos del conductor ni placa</label>
                <div x-show="!g.vehiculo_m1l" class="grid sm:grid-cols-2 lg:grid-cols-6 gap-4">
                    <label>Doc.<select x-model="g.cond_tdicod" class="{{ $in }} mt-1"><option value="1">DNI</option><option value="4">Carné ext.</option><option value="7">Pasaporte</option></select></label>
                    <label>N° documento<input x-model="g.cond_num" @change="buscarConductor()" maxlength="15" class="{{ $in }} mt-1"></label>
                    {{-- Un solo campo; al guardar se separa en nombres y apellidos (los dos últimos nombres = apellidos) como pide SUNAT --}}
                    <label class="lg:col-span-2">Nombre completo<input x-model="g.cond_completo" maxlength="200" placeholder="NOMBRES Y APELLIDOS" class="{{ $in }} mt-1 uppercase"></label>
                    <label>Licencia<input x-model="g.cond_licencia" maxlength="10" placeholder="Q12345678" class="{{ $in }} mt-1 uppercase"></label>
                    <label>Placa<input x-model="g.placa" maxlength="8" placeholder="ABC123" class="{{ $in }} mt-1 uppercase"></label>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3"><i class="fas fa-rotate"></i> Se propone el transporte de tu última guía: si es el mismo carro y chofer, no escribas nada.</p>
        </div>

        {{-- 5. Bienes --}}
        <div class="{{ $caja }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="{{ $tit }} text-violet-700"><span class="w-6 h-6 rounded-full bg-violet-600 text-white flex items-center justify-center text-xs">5</span> Bienes que se trasladan</h3>
                {{-- Bien que no está en Productos: se escribe a mano (descripción, unidad y cantidad) --}}
                <button type="button" @click="lineaLibre()" class="mb-4 px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold">+ Línea libre</button>
            </div>
            <div class="relative mb-3">
                <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                {{-- Con el teclado, como en el Punto de venta: ↑ ↓ para moverse, Enter agrega el marcado, Esc cierra la lista --}}
                <input x-model="busca" x-ref="buscaGuia" @input.debounce.300ms="buscarProducto()" @keydown.enter.prevent="agregarLibre()"
                       @keydown.arrow-down.prevent="moverSel(1)" @keydown.arrow-up.prevent="moverSel(-1)" @keydown.escape="resultados = []"
                       placeholder="Buscar producto por nombre o código (↑ ↓ para elegir, Enter agrega)" class="{{ $in }} pl-9">
                <div x-show="resultados.length" @click.outside="resultados = []" x-ref="listaGuia" class="absolute z-20 mt-1 w-full bg-white rounded-xl shadow-xl border max-h-72 overflow-y-auto">
                    <template x-for="(p, idx) in resultados" :key="p.IdProducto">
                        <button type="button" @click="agregar(p)" @mouseenter="sel = idx" :class="idx === sel ? 'bg-violet-100' : ''" class="w-full text-left px-3 py-2 hover:bg-violet-50 text-sm">
                            <span class="font-semibold" x-text="p.pronom"></span> <span class="text-xs text-gray-400" x-text="p.procod + ' · ' + p.umecod"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500"><tr><th class="px-2 py-2 text-left w-28">Código</th><th class="px-2 py-2 text-left">Descripción</th><th class="px-2 py-2 w-36">Unidad</th><th class="px-2 py-2 w-28">Cantidad</th><th class="w-8"></th></tr></thead>
                    <tbody class="divide-y">
                        <template x-for="(it, i) in items" :key="i">
                            <tr>
                                <td class="px-2 py-1.5"><input x-model="it.codigo" maxlength="30" class="{{ $in }}"></td>
                                <td class="px-2 py-1.5"><input x-model="it.descripcion" :id="'guia-des-' + i" maxlength="250" :placeholder="it.IdProducto ? '' : 'Descripción (línea libre)'" class="{{ $in }} uppercase"></td>
                                <td class="px-2 py-1.5"><select x-model="it.umecod" class="{{ $in }}">@foreach ($unidades as $u)<option value="{{ $u->umecod }}">{{ $u->umenom }}</option>@endforeach</select></td>
                                <td class="px-2 py-1.5"><input type="number" step="0.001" min="0" x-model.number="it.cantidad" class="{{ $in }} text-right"></td>
                                <td class="px-2"><button type="button" @click="items.splice(i, 1)" class="text-rose-500 hover:text-rose-700"><i class="fas fa-trash"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!items.length" class="py-6 text-center text-gray-400 text-sm">Busca productos arriba, o toca <b>+ Línea libre</b> para escribir uno a mano.</p>
            </div>
            <label class="block text-sm mt-4">Observación <span class="text-gray-400">(opcional)</span>
                <input x-model="g.observacion" maxlength="250" class="{{ $in }} mt-1"></label>
        </div>

        {{-- Datos que faltan --}}
        <div x-show="faltan.length" x-cloak class="flex items-start gap-3 rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            <i class="fas fa-circle-info text-amber-500 text-lg mt-0.5"></i>
            <div>
                <p class="font-semibold" x-text="faltan.length === 1 ? 'Falta un dato para emitir la guía:' : 'Faltan ' + faltan.length + ' datos para emitir la guía:'"></p>
                <ul class="mt-1 space-y-0.5"><template x-for="f in faltan"><li x-text="'• ' + f"></li></template></ul>
            </div>
        </div>

        {{-- Emitir --}}
        <div class="sticky bottom-0 bg-gray-50/95 backdrop-blur py-3 flex flex-wrap items-center gap-3 border-t">
            <button type="button" @click="guardar(true)" :disabled="ocupado" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-black hover:bg-indigo-700 disabled:opacity-50">
                <i class="fas fa-paper-plane"></i> <span x-text="ocupado ? 'Emitiendo…' : 'EMITIR GUÍA'"></span></button>
            <button type="button" @click="guardar(false)" :disabled="ocupado" class="px-5 py-3 rounded-xl bg-white border font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">Guardar sin enviar</button>
            <p x-show="resultado.mensaje" class="text-sm px-3 py-1.5 rounded-lg" :class="resultado.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'" x-text="resultado.mensaje"></p>
        </div>
    </div>

    <script>
        function guia() {
            const CSRF = @json(csrf_token());
            const ALMACENES = @js($almacenes->keyBy('id_almacen'));
            return {
                g: @js($guia), items: @js($items), faltan: [], busca: '', resultados: [], sel: 0, buscando: false, ocupado: false, resultado: { ok: true, mensaje: '' },
                init() {
                    // El conductor se escribe en un solo campo; se guarda separado: los dos últimos nombres son los apellidos
                    this.g.cond_completo = [this.g.cond_nombres, this.g.cond_apellidos].filter(Boolean).join(' ');
                    this.$watch('g.cond_completo', v => {
                        const p = String(v || '').trim().toUpperCase().split(/\s+/).filter(Boolean);
                        const corte = p.length >= 3 ? p.length - 2 : (p.length === 2 ? 1 : p.length);
                        this.g.cond_nombres = p.slice(0, corte).join(' ');
                        this.g.cond_apellidos = p.slice(corte).join(' ');
                    });
                },
                async json(url) { return fetch(url, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({})); },
                async buscarDestinatario() {
                    const doc = (this.g.dest_num || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(doc)) return;
                    this.g.dest_tdicod = doc.length === 11 ? '6' : '1';
                    this.buscando = true;
                    const d = await this.json(@json(url('guias/documento')) + '/' + doc);
                    this.buscando = false;
                    if (d.nom) this.g.dest_nom = d.nom;
                    if (d.dir && !this.g.llegada_direccion) this.g.llegada_direccion = d.dir;
                    if (d.ubigeo && !this.g.llegada_ubigeo) this.g.llegada_ubigeo = d.ubigeo;
                },
                async buscarTransportista() {
                    if (!/^\d{11}$/.test(this.g.transp_ruc)) return;
                    const d = await this.json(@json(url('guias/documento')) + '/' + this.g.transp_ruc);
                    if (d.nom) this.g.transp_nom = d.nom;
                },
                async buscarConductor() {
                    if (!/^\d{8}$/.test(this.g.cond_num)) return;
                    const d = await this.json(@json(url('guias/documento')) + '/' + this.g.cond_num);
                    if (d.conductor) {
                        Object.assign(this.g, { cond_licencia: d.conductor.licencia });
                        this.g.cond_completo = [d.conductor.nombres, d.conductor.apellidos].filter(Boolean).join(' ');
                    } else if (d.nom && !this.g.cond_completo) { this.g.cond_completo = d.nom; }
                },
                usarAlmacen(id) {
                    const a = ALMACENES[id];
                    if (a) { this.g.partida_ubigeo = a.ubigeo; this.g.partida_direccion = a.direccion || this.g.partida_direccion; if (a.codigo) this.g.partida_codlocal = a.codigo; }
                },
                async buscarProducto() {
                    if (this.busca.trim().length < 2) { this.resultados = []; return; }
                    this.resultados = await this.json(@json(route('guias.productos')) + '?q=' + encodeURIComponent(this.busca.trim()));
                    if (!Array.isArray(this.resultados)) this.resultados = [];
                    this.sel = 0;
                },
                agregar(p) {
                    this.items.push({ IdProducto: p.IdProducto, codigo: p.procod, descripcion: p.pronom, umecod: p.umecod || 'NIU', cantidad: 1 });
                    this.busca = ''; this.resultados = []; this.sel = 0;
                    this.$nextTick(() => this.$refs.buscaGuia?.focus());   // sigue con el teclado: buscar el siguiente
                },
                // ↑ ↓ en la lista de productos (se queda a la vista al bajar)
                moverSel(paso) {
                    if (!this.resultados.length) return;
                    this.sel = (this.sel + paso + this.resultados.length) % this.resultados.length;
                    this.$nextTick(() => this.$refs.listaGuia?.children[this.sel + 1]?.scrollIntoView({ block: 'nearest' }));
                },
                // Línea a mano, vacía: se escribe la descripción y se elige la unidad y la cantidad
                lineaLibre() {
                    this.items.push({ IdProducto: null, codigo: '', descripcion: '', umecod: 'NIU', cantidad: 1 });
                    const i = this.items.length - 1;
                    this.$nextTick(() => document.getElementById('guia-des-' + i)?.focus());
                },
                agregarLibre() {
                    if (this.resultados.length) return this.agregar(this.resultados[this.sel] || this.resultados[0]);
                    if (this.busca.trim().length < 2) return;
                    this.items.push({ IdProducto: null, codigo: '', descripcion: this.busca.trim().toUpperCase(), umecod: 'NIU', cantidad: 1 }); this.busca = '';
                },
                // Resalta en ámbar cada campo que falta y lleva al primero
                marcarFaltantes(errores) {
                    this.faltan = [...new Set(Object.values(errores).map(e => e[0]))];
                    let primero = null;
                    Object.keys(errores).forEach(campo => {
                        const c = campo.startsWith('items.') ? null : campo;
                        const el = c && (document.querySelector(`[data-campo="${c}"]`) || document.querySelector(`[x-model="g.${c}"], [x-model\\.number="g.${c}"]`));
                        if (el) { el.classList.add('ring-2', 'ring-amber-400', 'rounded-lg', 'campo-falta'); primero ??= el; }
                    });
                    (primero || document.querySelector('[x-show="faltan.length"]'))?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                },
                limpiarMarcas() {
                    this.faltan = [];
                    document.querySelectorAll('.campo-falta').forEach(el => el.classList.remove('ring-2', 'ring-amber-400', 'campo-falta'));
                },
                async guardar(enviar) {
                    this.ocupado = true; this.resultado = { ok: true, mensaje: '' }; this.limpiarMarcas();
                    try {
                        const r = await fetch(@json(route('guias.guardar')), { method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                            body: JSON.stringify({ ...this.g, vehiculo_m1l: this.g.vehiculo_m1l ? 1 : 0, items: this.items, enviar: enviar ? 1 : 0 }) });
                        const d = await r.json();
                        if (r.status === 422) { this.marcarFaltantes(d.errors || {}); return; }
                        this.resultado = { ok: d.ok && (d.enviada !== false), mensaje: d.mensaje };
                        if (d.ok) {
                            alert(d.mensaje);
                            location.href = @json(route('guias.index')) + '?resaltar=' + d.id;
                        }
                    } catch (e) { this.resultado = { ok: false, mensaje: 'Sin conexión con el servidor.' }; }
                    finally { this.ocupado = false; }
                },
            };
        }
    </script>
@endsection
