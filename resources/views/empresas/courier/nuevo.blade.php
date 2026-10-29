@extends('layouts.app')
@section('title', 'Nuevo envío')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $campo = 'mt-1 w-full h-10 rounded-lg border-gray-300 text-sm';
        $etiqueta = 'text-xs font-semibold text-gray-500';
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-5';
        $numeros = ['base', 'kg_incluidos', 'por_kg', 'sobre', 'domicilio', 'dias'];
        $general = collect($numeros)->mapWithKeys(fn ($k) => [$k => (float) $cfg->$k])->all();
        $rutas = $tarifas->map(fn ($t) => ['o' => (int) $t->origen_id, 'd' => (int) $t->destino_id] + collect($numeros)->mapWithKeys(fn ($k) => [$k => (float) $t->$k])->all())->values();
    @endphp

    <div class="space-y-5" x-data="nuevoEnvio()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('courier.index') }}" class="text-sm text-gray-500">← Envíos</a>
                <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-box text-orange-600"></i> Nuevo envío</h1>
            </div>
        </div>

        @if ($agencias->count() < 2)
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-amber-900">
                <p class="font-bold"><i class="fas fa-circle-info"></i> Primero registra las agencias de destino</p>
                <p class="text-sm mt-1">Las ciudades o agencias a donde envías (propias o de aliados) y, si quieres, la tarifa de cada ruta.</p>
                @if (Auth::user()->esAdmin())<a href="{{ route('courier.tarifas') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-amber-500 text-white font-bold text-sm">Ir a tarifas y agencias →</a>@endif
            </div>
        @endif

        {{-- Registrado: imprimir guía y comprobante --}}
        <div x-show="listo" x-cloak class="rounded-3xl bg-emerald-600 text-white p-6 shadow-lg">
            <p class="text-sm text-emerald-100">Envío registrado</p>
            <p class="text-3xl font-black font-mono tracking-widest" x-text="listo?.codigo"></p>
            <p class="text-sm text-emerald-50 mt-1" x-show="listo?.numero" x-text="'Comprobante ' + listo?.numero"></p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" @click="imprimir(listo.guia + '?imprimir=1')" class="px-4 py-2.5 rounded-xl bg-white text-emerald-800 font-extrabold text-sm"><i class="fas fa-print"></i> Guía y etiqueta</button>
                <button type="button" x-show="listo?.ticket" @click="imprimir(listo.ticket + '&imprimir=1')" class="px-4 py-2.5 rounded-xl bg-white/15 font-bold text-sm"><i class="fas fa-receipt"></i> Comprobante</button>
                <a :href="listo?.url" class="px-4 py-2.5 rounded-xl bg-white/15 font-bold text-sm"><i class="fas fa-eye"></i> Ver envío</a>
                <a href="{{ route('courier.nuevo') }}" class="px-4 py-2.5 rounded-xl bg-white/15 font-bold text-sm"><i class="fas fa-plus"></i> Otro envío</a>
            </div>
        </div>

        <form x-show="!listo" @submit.prevent="registrar()" class="grid lg:grid-cols-3 gap-5 items-start">
            <div class="lg:col-span-2 space-y-5">
                {{-- Pre-registro desde la web: el cliente dicta su código y se llena todo --}}
                <div class="rounded-2xl bg-orange-50 ring-1 ring-orange-200 p-4 flex flex-wrap items-center gap-3">
                    <p class="flex-1 min-w-48 text-sm text-orange-900"><i class="fas fa-globe"></i> <b>¿Se pre-registró en la web?</b> Escribe su código de 6 letras.</p>
                    <input x-model="codigoPre" @keydown.enter.prevent="cargarPre()" maxlength="6" placeholder="ABC234" class="w-32 h-10 rounded-lg border-orange-300 font-mono font-bold uppercase tracking-widest text-center">
                    <button type="button" @click="cargarPre()" class="h-10 px-4 rounded-lg bg-orange-600 text-white text-sm font-bold">Cargar</button>
                    <p x-show="f.prerregistro" class="w-full text-xs font-bold text-emerald-700" x-text="'✔ Datos del pre-registro ' + f.prerregistro + ' cargados: pesa y confirma.'"></p>
                </div>

                {{-- Ruta --}}
                <div class="{{ $tarjeta }}">
                    <h2 class="font-black text-gray-800 mb-3"><i class="fas fa-route text-orange-500"></i> Ruta</h2>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="{{ $etiqueta }}">Sale de
                            <select x-model.number="f.origen_id" class="{{ $campo }}" @if ($propias->count() === 1) disabled @endif>
                                @foreach ($propias as $a)<option value="{{ $a->age_id }}">{{ \App\Support\Courier::etiquetaAgencia($a) }}</option>@endforeach
                            </select></label>
                        <label class="{{ $etiqueta }}">Va a
                            <select x-model.number="f.destino_id" required class="{{ $campo }}">
                                <option value="">Elige la agencia de destino…</option>
                                @foreach ($agencias as $a)
                                    <option value="{{ $a->age_id }}" x-show="f.origen_id !== {{ $a->age_id }}">{{ \App\Support\Courier::etiquetaAgencia($a) }}{{ $a->id_empresa_negocio ? '' : ' (aliado)' }}</option>
                                @endforeach
                            </select></label>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <label class="flex-1 min-w-48 flex items-center gap-2 rounded-xl ring-2 px-3 py-2.5 cursor-pointer" :class="f.entrega === 'AGENCIA' ? 'ring-orange-400 bg-orange-50' : 'ring-gray-200'">
                            <input type="radio" value="AGENCIA" x-model="f.entrega"> <span class="text-sm"><b>Recoge en agencia</b><br><span class="text-xs text-gray-500">con su DNI y la clave</span></span></label>
                        <label class="flex-1 min-w-48 flex items-center gap-2 rounded-xl ring-2 px-3 py-2.5 cursor-pointer" :class="f.entrega === 'DOMICILIO' ? 'ring-orange-400 bg-orange-50' : 'ring-gray-200'">
                            <input type="radio" value="DOMICILIO" x-model="f.entrega"> <span class="text-sm"><b>Reparto a domicilio</b><br><span class="text-xs text-gray-500" x-text="'+ S/ ' + tarifa.domicilio.toFixed(2)"></span></span></label>
                    </div>
                    <label x-show="f.entrega === 'DOMICILIO'" class="block mt-3 {{ $etiqueta }}">Dirección de entrega y referencia
                        <input x-model="f.direccion_entrega" :required="f.entrega === 'DOMICILIO'" maxlength="200" class="{{ $campo }} uppercase"></label>
                </div>

                {{-- Remitente y destinatario --}}
                <div class="grid sm:grid-cols-2 gap-5">
                    @foreach (['rem' => ['Remitente', 'fa-user', 'Quien envía (y paga si es en origen)'], 'des' => ['Destinatario', 'fa-user-check', 'Quien recibe']] as $p => [$titulo, $icono, $ayuda])
                        <div class="{{ $tarjeta }} space-y-3">
                            <div><h2 class="font-black text-gray-800"><i class="fas {{ $icono }} text-orange-500"></i> {{ $titulo }}</h2><p class="text-xs text-gray-400">{{ $ayuda }}</p></div>
                            <label class="block {{ $etiqueta }}">DNI o RUC
                                <div class="flex gap-2"><input x-model="f.{{ $p }}_doc" @change="buscar('{{ $p }}')" maxlength="11" inputmode="numeric" class="{{ $campo }}">
                                    <button type="button" @click="buscar('{{ $p }}')" class="mt-1 h-10 px-3 rounded-lg bg-gray-100 text-gray-600" title="Buscar"><i class="fas" :class="buscando === '{{ $p }}' ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i></button></div></label>
                            <label class="block {{ $etiqueta }}">Nombre completo
                                <input x-model="f.{{ $p }}_nombre" required maxlength="150" class="{{ $campo }} uppercase"></label>
                            <label class="block {{ $etiqueta }}">Celular (WhatsApp)
                                <input x-model="f.{{ $p }}_telefono" maxlength="20" inputmode="tel" class="{{ $campo }}"></label>
                        </div>
                    @endforeach
                </div>

                {{-- Paquetes --}}
                <div class="{{ $tarjeta }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h2 class="font-black text-gray-800"><i class="fas fa-boxes-stacked text-orange-500"></i> Lo que envía</h2>
                        <div class="flex gap-2">
                            <button type="button" @click="agregar('PAQUETE')" class="h-9 px-3 rounded-lg bg-orange-50 text-orange-700 text-sm font-bold"><i class="fas fa-box"></i> + Paquete</button>
                            <button type="button" @click="agregar('SOBRE')" class="h-9 px-3 rounded-lg bg-orange-50 text-orange-700 text-sm font-bold"><i class="fas fa-envelope"></i> + Sobre</button>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <template x-for="(p, i) in f.paquetes" :key="i">
                            <div class="rounded-xl ring-1 ring-gray-200 p-3">
                                <div class="flex flex-wrap items-end gap-2">
                                    <span class="h-10 w-10 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center"><i class="fas" :class="p.tipo === 'SOBRE' ? 'fa-envelope' : 'fa-box'"></i></span>
                                    <label class="{{ $etiqueta }} w-20">Cant.<input type="number" min="1" x-model.number="p.cantidad" class="{{ $campo }} text-right"></label>
                                    <template x-if="p.tipo === 'PAQUETE'">
                                        <div class="flex flex-wrap gap-2">
                                            <label class="{{ $etiqueta }} w-24">Peso kg<input type="number" step="0.01" min="0" x-model.number="p.peso" class="{{ $campo }} text-right"></label>
                                            <label class="{{ $etiqueta }} w-20">Largo cm<input type="number" step="0.1" min="0" x-model.number="p.largo" class="{{ $campo }} text-right"></label>
                                            <label class="{{ $etiqueta }} w-20">Ancho cm<input type="number" step="0.1" min="0" x-model.number="p.ancho" class="{{ $campo }} text-right"></label>
                                            <label class="{{ $etiqueta }} w-20">Alto cm<input type="number" step="0.1" min="0" x-model.number="p.alto" class="{{ $campo }} text-right"></label>
                                        </div>
                                    </template>
                                    <label class="{{ $etiqueta }} flex-1 min-w-36">Descripción<input x-model="p.descripcion" maxlength="100" :placeholder="p.tipo === 'SOBRE' ? 'Documentos' : 'Caja con ropa…'" class="{{ $campo }} uppercase"></label>
                                    <button type="button" x-show="f.paquetes.length > 1" @click="f.paquetes.splice(i, 1)" class="h-10 w-10 rounded-lg text-rose-500 hover:bg-rose-50"><i class="fas fa-trash"></i></button>
                                </div>
                                <p x-show="p.tipo === 'PAQUETE' && volumetrico(p) > (Number(p.peso) || 0)" class="mt-1 text-xs text-amber-700"
                                   x-text="'Por su tamaño se cobra como ' + volumetrico(p).toFixed(2) + ' kg (peso volumétrico)'"></p>
                            </div>
                        </template>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3 mt-4">
                        <label class="{{ $etiqueta }}">Contenido (lo que declara)<input x-model="f.contenido" maxlength="200" class="{{ $campo }} uppercase"></label>
                        <label class="{{ $etiqueta }}">Valor declarado S/ <span class="font-normal">(para el seguro{{ (float) $cfg->seguro_pct > 0 ? ': '.rtrim(rtrim(number_format($cfg->seguro_pct, 2), '0'), '.').'%' : '' }})</span>
                            <input type="number" step="0.01" min="0" x-model.number="f.valor_declarado" class="{{ $campo }} text-right"></label>
                        <label class="sm:col-span-2 {{ $etiqueta }}">Observación<input x-model="f.observacion" maxlength="255" placeholder="Frágil, no voltear…" class="{{ $campo }}"></label>
                    </div>
                </div>
            </div>

            {{-- Resumen y cobro --}}
            <div class="{{ $tarjeta }} space-y-4 lg:sticky lg:top-4">
                <h2 class="font-black text-gray-800"><i class="fas fa-receipt text-orange-500"></i> Precio</h2>
                <div class="rounded-xl bg-gray-50 p-3 text-sm space-y-1">
                    <p class="flex justify-between"><span x-text="precio.piezas + ' pieza(s) · ' + precio.peso_cobrado.toFixed(2) + ' kg'"></span><b x-text="'S/ ' + precio.flete.toFixed(2)"></b></p>
                    <p x-show="precio.domicilio > 0" class="flex justify-between"><span>Reparto a domicilio</span><b x-text="'S/ ' + precio.domicilio.toFixed(2)"></b></p>
                    <p x-show="precio.seguro > 0" class="flex justify-between"><span>Seguro</span><b x-text="'S/ ' + precio.seguro.toFixed(2)"></b></p>
                    <p class="flex justify-between border-t pt-2 text-lg"><b>Total</b><b x-text="'S/ ' + precio.total.toFixed(2)"></b></p>
                    <p class="text-[11px] text-gray-400" x-text="(tarifa.propia ? 'Tarifa de la ruta' : 'Tarifa general') + ' · llega en ' + tarifa.dias + ' día(s) aprox.'"></p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 rounded-xl ring-2 px-3 py-2 text-sm cursor-pointer" :class="f.pago === 'ORIGEN' ? 'ring-orange-400 bg-orange-50' : 'ring-gray-200'">
                        <input type="radio" value="ORIGEN" x-model="f.pago"> <b>Paga ahora</b></label>
                    <label class="flex items-center gap-2 rounded-xl ring-2 px-3 py-2 text-sm cursor-pointer" :class="f.pago === 'DESTINO' ? 'ring-orange-400 bg-orange-50' : 'ring-gray-200'">
                        <input type="radio" value="DESTINO" x-model="f.pago"> <b>Paga al recoger</b></label>
                    <label x-show="credito" class="col-span-2 flex items-center gap-2 rounded-xl ring-2 px-3 py-2 text-sm cursor-pointer" :class="f.pago === 'CREDITO' ? 'ring-violet-400 bg-violet-50' : 'ring-gray-200'">
                        <input type="radio" value="CREDITO" x-model="f.pago"> <span><b>A crédito</b> <span class="text-xs text-gray-500"
                            x-text="credito ? 'debe S/ ' + credito.deuda.toFixed(2) + (credito.limite > 0 ? ' de S/ ' + credito.limite.toFixed(2) : ' (sin límite)') : ''"></span></span></label>
                </div>
                <p x-show="f.pago === 'CREDITO'" class="text-xs text-violet-700">Va a su estado de cuenta: se le factura a fin de mes en Courier → Crédito.</p>
                <div x-show="f.pago === 'ORIGEN'" class="space-y-3">
                    @include('empresas.courier.partials.cobro', ['total' => 'precio.total'])
                </div>
                <p x-show="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700" x-text="error"></p>
                <button :disabled="ocupado || !f.destino_id || precio.total <= 0" class="w-full h-12 rounded-xl bg-orange-600 text-white font-extrabold disabled:opacity-50"
                        x-text="ocupado ? 'Registrando…' : (f.pago === 'ORIGEN' ? '✓ Registrar y cobrar S/ ' + precio.total.toFixed(2) : (f.pago === 'CREDITO' ? '✓ Registrar a crédito' : '✓ Registrar envío'))"></button>
            </div>
        </form>

        <iframe x-ref="imprimir" class="hidden"></iframe>
    </div>

    <script>
        function nuevoEnvio() {
            const GENERAL = @json($general);
            const RUTAS = @json($rutas);
            const CFG = { divisor: {{ (int) $cfg->divisor_vol }}, seguroPct: {{ (float) $cfg->seguro_pct }}, seguroMin: {{ (float) $cfg->seguro_min }} };
            const post = (url, data) => fetch(url, { method: 'POST', body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
            const paquete = tipo => ({ tipo, cantidad: 1, peso: tipo === 'SOBRE' ? 0 : '', largo: '', ancho: '', alto: '', descripcion: '' });
            return {
                ocupado: false, error: '', listo: null, buscando: '', credito: null, codigoPre: '',
                f: { origen_id: {{ (int) $propias->first()->age_id }}, destino_id: '', entrega: 'AGENCIA', direccion_entrega: '',
                     rem_doc: '', rem_nombre: '', rem_telefono: '', des_doc: '', des_nombre: '', des_telefono: '',
                     contenido: '', valor_declarado: '', observacion: '', pago: 'ORIGEN', paquetes: [paquete('PAQUETE')], prerregistro: '' },
                async cargarPre() {
                    const codigo = String(this.codigoPre).trim().toUpperCase();
                    if (codigo.length !== 6) return;
                    const r = await fetch(@json(url('courier/prerregistro')) + '/' + codigo, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    if (!r.ok) return (window.tushpaAviso || alert)(r.mensaje, 'error');
                    const d = r.datos;
                    // La agencia de origen es la tuya: del pre-registro se toma el destino, las personas y los paquetes
                    Object.assign(this.f, { destino_id: Number(d.destino_id) || '', entrega: d.entrega || 'AGENCIA', direccion_entrega: d.direccion_entrega || '',
                        rem_doc: d.rem_doc || '', rem_nombre: d.rem_nombre || '', rem_telefono: d.rem_telefono || '', des_doc: d.des_doc || '', des_nombre: d.des_nombre || '',
                        des_telefono: d.des_telefono || '', contenido: d.contenido || '', valor_declarado: d.valor_declarado || '', prerregistro: codigo,
                        paquetes: (d.paquetes || []).map(p => ({ ...paquete(p.tipo), ...p, descripcion: p.descripcion || '' })) });
                    if (!this.f.paquetes.length) this.f.paquetes = [paquete('PAQUETE')];
                    this.buscar('rem');
                },
                cobro: { tdocod: '03', id_med_pag: @json((string) ($medios->first()->id_med_pag ?? '')), clinum: '', clinom: '', clidir: '', paga: null, imprimir: true },
                agregar(tipo) { this.f.paquetes.push(paquete(tipo)); },
                volumetrico(p) { return CFG.divisor > 0 ? (Number(p.largo) || 0) * (Number(p.ancho) || 0) * (Number(p.alto) || 0) / CFG.divisor : 0; },
                // Igual que el servidor: la tarifa de la ruta (en cualquier sentido) o la general
                get tarifa() {
                    const o = Number(this.f.origen_id), d = Number(this.f.destino_id);
                    const r = RUTAS.find(t => t.o === o && t.d === d) || RUTAS.find(t => t.o === d && t.d === o);
                    return { ...(r || GENERAL), propia: !!r };
                },
                get precio() {
                    const t = this.tarifa;
                    let piezas = 0, sobres = 0, cobrable = 0;
                    for (const p of this.f.paquetes) {
                        const c = Math.max(1, parseInt(p.cantidad) || 1); piezas += c;
                        if (p.tipo === 'SOBRE') { sobres += c; continue; }
                        cobrable += Math.max(Number(p.peso) || 0, this.volumetrico(p)) * c;
                    }
                    cobrable = Math.round(cobrable * 100) / 100;
                    const paquetes = piezas > sobres;
                    const adicionales = paquetes ? Math.max(0, Math.ceil(Math.round((cobrable - t.kg_incluidos) * 100) / 100)) : 0;
                    const flete = Math.round((sobres * t.sobre + (paquetes ? t.base + adicionales * t.por_kg : 0)) * 100) / 100;
                    const valor = Number(this.f.valor_declarado) || 0;
                    const seguro = valor > 0 ? Math.round(Math.max(valor * CFG.seguroPct / 100, CFG.seguroMin) * 100) / 100 : 0;
                    const domicilio = this.f.entrega === 'DOMICILIO' ? t.domicilio : 0;
                    return { piezas, peso_cobrado: cobrable, flete, seguro, domicilio, total: Math.round((flete + seguro + domicilio) * 100) / 100 };
                },
                async buscar(p) {
                    const doc = String(this.f[p + '_doc'] || '').replace(/\D/g, '');
                    if (doc.length !== 8 && doc.length !== 11) return;
                    this.buscando = p;
                    try {
                        const r = await fetch(@json(url('courier/documento')) + '/' + doc, { headers: { Accept: 'application/json' } }).then(r => r.json());
                        if (r.nombre) this.f[p + '_nombre'] = r.nombre;
                        if (p === 'rem') { this.credito = r.credito || null; if (!this.credito && this.f.pago === 'CREDITO') this.f.pago = 'ORIGEN'; }
                        if (r.telefono && !this.f[p + '_telefono']) this.f[p + '_telefono'] = r.telefono;
                        if (p === 'des' && r.direccion && !this.f.direccion_entrega) this.f.direccion_entrega = r.direccion;
                    } catch (e) {} finally { this.buscando = ''; }
                },
                imprimir(url) { this.$refs.imprimir.src = url; },
                async registrar() {
                    this.ocupado = true; this.error = '';
                    try {
                        const d = await post(@json(route('courier.guardar')), { ...this.f, cobro: this.f.pago === 'ORIGEN' ? this.cobro : null,
                            imprimir: this.f.pago === 'ORIGEN' && this.cobro.imprimir });
                        if (d.errors) { this.error = Object.values(d.errors).flat()[0]; return; }
                        if (!d.ok) { this.error = d.mensaje; return; }
                        const c = d.comprobante || {};
                        this.listo = { codigo: d.codigo, url: d.url, guia: d.guia, numero: c.numero || '', ticket: c.ticket && !c.impreso ? c.ticket : '' };
                        // La guía (copia del remitente con su clave y la etiqueta del paquete) sale al toque
                        this.imprimir(d.guia + '?imprimir=1');
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } catch (e) { this.error = 'Error de conexión. Revisa en Envíos si se registró antes de volver a intentar.'; } finally { this.ocupado = false; }
                },
            };
        }
    </script>
@endsection
