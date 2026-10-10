@extends('layouts.app')
@section('title', 'Abonados · Estacionamiento')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-cyan-500 focus:ring-cyan-500';
        $btn = 'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition';
    @endphp

    <div x-data="abonados()" class="space-y-5">
        <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-800 via-cyan-700 to-indigo-700 text-white p-5 sm:p-7 shadow-lg">
            <x-kene-adorno patron="cruces" />
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a href="{{ route('estacionamiento.index') }}" class="text-xs text-cyan-100 hover:text-white">← Volver al estacionamiento</a>
                    <h1 class="text-2xl sm:text-3xl font-black mt-1"><i class="fas fa-id-card"></i> Abonados</h1>
                    <p class="text-cyan-50 text-sm mt-1">Pensiones mensuales: mientras están vigentes, la placa entra y sale sin pagar.</p>
                </div>
                <button type="button" @click="nueva()" class="{{ $btn }} bg-white text-cyan-800 hover:bg-cyan-50"><i class="fas fa-plus"></i> Nueva pensión</button>
            </div>
        </section>

        @if (!$turno)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                <i class="fas fa-triangle-exclamation"></i> Para cobrar pensiones <a href="{{ route('turnos.index') }}" class="font-bold underline">apertura tu turno de caja</a>.
            </div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <template x-for="k in tarjetas" :key="k.estado">
                <button type="button" @click="filtro = filtro === k.estado ? '' : k.estado" class="text-left bg-white rounded-2xl shadow-sm p-4 border-2 transition"
                        :class="filtro === k.estado ? k.borde : 'border-transparent hover:border-gray-200'">
                    <p class="text-xs font-semibold text-gray-500" x-text="k.nombre"></p>
                    <p class="text-3xl font-black" :class="k.texto" x-text="ultimos.filter(a => a.situacion === k.estado).length"></p>
                </button>
            </template>
        </div>

        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-3 border-b flex flex-wrap gap-2 items-center">
                <input x-model="q" placeholder="Buscar por placa, nombre, DNI o celular" class="{{ $in }} flex-1 min-w-[220px]">
                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" x-model="historial" class="rounded"> Ver historial completo</label>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 bg-gray-50">
                        <tr><th class="px-3 py-2 text-left">Placa</th><th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-left">Vigencia</th>
                            <th class="px-3 py-2 text-left">Espacio</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y">
                        <template x-for="a in visibles" :key="a.abo_id">
                            <tr class="hover:bg-cyan-50/40" :class="a.situacion === 'ANULADO' ? 'opacity-50 line-through' : ''">
                                <td class="px-3 py-2">
                                    <span class="font-black text-gray-900" style="font-family: ui-monospace, Consolas, monospace" x-text="a.placa"></span>
                                    <span x-show="a.placa2" class="block text-xs text-gray-500" x-text="'+ ' + a.placa2"></span>
                                    <span class="block text-[11px] text-gray-400" x-text="a.tipo"></span>
                                </td>
                                <td class="px-3 py-2"><span class="font-semibold" x-text="a.clinom"></span>
                                    <span class="block text-xs text-gray-500" x-text="[a.clinum, a.telefono].filter(Boolean).join(' · ')"></span></td>
                                <td class="px-3 py-2">
                                    <span x-text="fecha(a.inicio) + ' – ' + fecha(a.fin)"></span>
                                    <span class="block mt-0.5 text-[11px] font-bold px-1.5 rounded w-fit" :class="chip(a.situacion)" x-text="texto(a)"></span>
                                </td>
                                <td class="px-3 py-2" x-text="a.espacio || '—'"></td>
                                <td class="px-3 py-2 text-right font-semibold" x-text="soles(a.precio)"></td>
                                <td class="px-3 py-2"><a x-show="a.IdCpe_cabecera" :href="'{{ url('voucher') }}/' + a.IdCpe_cabecera" target="_blank" class="text-cyan-700 underline" x-text="a.comprobante"></a></td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button type="button" x-show="a.situacion !== 'ANULADO'" @click="editar(a)" class="px-2 py-1 rounded-lg text-gray-600 hover:bg-gray-100" title="Editar datos"><i class="fas fa-pen"></i></button>
                                    <button type="button" x-show="a.situacion !== 'ANULADO'" @click="renovar(a)" class="px-3 py-1.5 rounded-lg bg-cyan-600 text-white text-xs font-bold hover:bg-cyan-700">Renovar</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!visibles.length" class="text-center text-gray-400 py-10">No hay abonados con ese filtro.</p>
            </div>
        </div>

        {{-- Modal: vender / renovar --}}
        <div x-show="modal === 'vender'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-3 overflow-y-auto">
            <form @submit.prevent="vender()" class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-6 text-sm" @click.outside="modal = null">
                <div class="flex items-center justify-between px-5 py-4 border-b">
                    <p class="font-black text-lg text-gray-800" x-text="venta.renovacion ? 'Renovar pensión' : 'Nueva pensión'"></p>
                    <button type="button" @click="modal = null" class="w-9 h-9 rounded-full hover:bg-gray-100" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="p-5 grid sm:grid-cols-2 gap-5">
                    <div class="space-y-3">
                        <p class="font-bold text-gray-700">1. Vehículo y cliente</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label>Placa<input x-model="venta.placa" @input="venta.placa = venta.placa.toUpperCase()" maxlength="8" required class="{{ $in }} mt-1 font-black uppercase"></label>
                            <label>2.ª placa <span class="text-gray-400">(opcional)</span><input x-model="venta.placa2" @input="venta.placa2 = venta.placa2.toUpperCase()" maxlength="8" class="{{ $in }} mt-1 uppercase"></label>
                        </div>
                        <label class="block">Tipo de vehículo
                            <select x-model.number="venta.tar_id" @change="precioSugerido()" required class="{{ $in }} mt-1">
                                <template x-for="t in tarifas" :key="t.tar_id"><option :value="t.tar_id" x-text="t.nombre + (Number(t.pension) ? ' · ' + soles(t.pension) + ' al mes' : '')"></option></template>
                            </select></label>
                        <label class="block">Espacio reservado <span class="text-gray-400">(opcional)</span>
                            <select x-model="venta.esp_id" class="{{ $in }} mt-1">
                                <option value="">Sin espacio fijo</option>
                                @foreach ($espacios as $e)<option value="{{ $e->esp_id }}">{{ $e->codigo }}{{ $e->zona ? ' · '.$e->zona : '' }}</option>@endforeach
                            </select></label>
                        <div class="grid grid-cols-[120px_1fr] gap-2">
                            <label>DNI / RUC<input x-model="venta.clinum" @change="buscarDoc()" maxlength="15" class="{{ $in }} mt-1"></label>
                            <label>Nombre / razón social<input x-model="venta.clinom" maxlength="150" required class="{{ $in }} mt-1"></label>
                        </div>
                        <label class="block">Celular<input x-model="venta.telefono" maxlength="20" class="{{ $in }} mt-1"></label>
                        <label class="block">Nota<input x-model="venta.obs" maxlength="200" placeholder="Horario, autorizado a…" class="{{ $in }} mt-1"></label>
                    </div>
                    <div class="space-y-3">
                        <p class="font-bold text-gray-700">2. Periodo y pago</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label>Empieza el<input type="date" x-model="venta.inicio" required class="{{ $in }} mt-1"></label>
                            <label>Meses
                                <select x-model.number="venta.meses" @change="precioSugerido()" class="{{ $in }} mt-1">
                                    @foreach ([1, 2, 3, 6, 12] as $m)<option value="{{ $m }}">{{ $m }} {{ $m === 1 ? 'mes' : 'meses' }}</option>@endforeach
                                </select></label>
                        </div>
                        <p class="text-xs text-gray-500" x-text="'Vigente del ' + fecha(venta.inicio) + ' al ' + fecha(finDe(venta.inicio, venta.meses))"></p>
                        <label class="block">Precio total S/<input type="number" step="0.10" min="0.1" x-model.number="venta.precio" required class="{{ $in }} mt-1 text-lg font-black"></label>
                        <label class="block">Comprobante
                            <select x-model="venta.tdocod" class="{{ $in }} mt-1"><option value="03">Boleta</option><option value="01">Factura</option><option value="13">Nota de venta</option></select></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label>Medio de pago
                                <select x-model="venta.medio" class="{{ $in }} mt-1">
                                    @foreach ($mediospagos as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                                </select></label>
                            <label>Paga con S/<input type="number" step="0.10" min="0" x-model.number="venta.paga" class="{{ $in }} mt-1"></label>
                        </div>
                        <p x-show="venta.paga > venta.precio" class="text-sm">Vuelto: <b x-text="soles(venta.paga - venta.precio)"></b></p>
                        <label class="flex items-center gap-2"><input type="checkbox" x-model="venta.imprimir" class="rounded"> Imprimir comprobante</label>
                        <button :disabled="ocupado || {{ $turno ? 'false' : 'true' }}" class="w-full py-3 rounded-xl bg-cyan-600 text-white font-black hover:bg-cyan-700 disabled:opacity-50"
                                x-text="ocupado ? 'Registrando…' : 'COBRAR ' + soles(venta.precio)"></button>
                        @if (!$turno)<p class="text-xs text-amber-700">Apertura tu turno de caja para cobrar.</p>@endif
                    </div>
                </div>
            </form>
        </div>

        {{-- Modal: editar datos --}}
        <div x-show="modal === 'editar'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-3">
            <form @submit.prevent="guardar()" class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 space-y-3 text-sm" @click.outside="modal = null">
                <p class="font-black text-lg text-gray-800" x-text="'Abonado ' + (form.placa || '')"></p>
                <label class="block">2.ª placa<input x-model="form.placa2" @input="form.placa2 = (form.placa2 || '').toUpperCase()" maxlength="8" class="{{ $in }} mt-1 uppercase"></label>
                <label class="block">Celular<input x-model="form.telefono" maxlength="20" class="{{ $in }} mt-1"></label>
                <label class="block">Espacio reservado
                    <select x-model="form.esp_id" class="{{ $in }} mt-1">
                        <option value="">Sin espacio fijo</option>
                        @foreach ($espacios as $e)<option value="{{ $e->esp_id }}">{{ $e->codigo }}{{ $e->zona ? ' · '.$e->zona : '' }}</option>@endforeach
                    </select></label>
                <label class="block">Nota<input x-model="form.obs" maxlength="200" class="{{ $in }} mt-1"></label>
                <p class="text-xs text-gray-500">Para anular una pensión, anula su comprobante desde Ventas.</p>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="modal = null" class="{{ $btn }} bg-gray-100 text-gray-700">Cancelar</button>
                    <button :disabled="ocupado" class="{{ $btn }} bg-cyan-600 text-white hover:bg-cyan-700 disabled:opacity-50">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abonados() {
            const RUTA = @json(url('estacionamiento/abonados'));
            const CSRF = @json(csrf_token());
            const hoyIso = @json(now()->toDateString());
            return {
                abonados: @js($abonados), tarifas: @js($tarifas), q: '', filtro: '', historial: false, modal: null, ocupado: false, venta: {}, form: {},
                tarjetas: [
                    { estado: 'VIGENTE', nombre: 'Vigentes', texto: 'text-emerald-600', borde: 'border-emerald-400' },
                    { estado: 'POR_VENCER', nombre: 'Vencen en 5 días', texto: 'text-amber-500', borde: 'border-amber-400' },
                    { estado: 'VENCIDO', nombre: 'Vencidos', texto: 'text-rose-600', borde: 'border-rose-400' },
                    { estado: 'PROXIMO', nombre: 'Empiezan pronto', texto: 'text-sky-600', borde: 'border-sky-400' },
                ],
                // La pensión más reciente de cada placa (las anteriores son historial)
                get ultimos() {
                    const vistos = new Set();
                    return this.abonados.filter(a => a.situacion !== 'ANULADO').filter(a => !vistos.has(a.placa) && vistos.add(a.placa));
                },
                get visibles() {
                    const q = this.q.trim().toLowerCase();
                    return (this.historial ? this.abonados : this.ultimos).filter(a => (!this.filtro || a.situacion === this.filtro)
                        && (!q || [a.placa, a.placa2, a.clinom, a.clinum, a.telefono].some(v => (v || '').toLowerCase().includes(q))));
                },
                soles(n) { return 'S/ ' + Number(n || 0).toFixed(2); },
                fecha(s) { if (!s) return ''; const [a, m, d] = s.slice(0, 10).split('-'); return `${d}/${m}/${a}`; },
                finDe(inicio, meses) {
                    if (!inicio) return '';
                    const d = new Date(inicio + 'T12:00:00'), dia = d.getDate();
                    d.setDate(1); d.setMonth(d.getMonth() + Number(meses || 1));
                    d.setDate(Math.min(dia, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()) - 1);
                    return d.toISOString().slice(0, 10);
                },
                sumarDia(s) { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + 1); return d.toISOString().slice(0, 10); },
                chip(s) { return { VIGENTE: 'bg-emerald-100 text-emerald-700', POR_VENCER: 'bg-amber-100 text-amber-800', VENCIDO: 'bg-rose-100 text-rose-700', PROXIMO: 'bg-sky-100 text-sky-700' }[s] || 'bg-gray-100 text-gray-500'; },
                texto(a) {
                    return { VIGENTE: 'Vigente · ' + a.dias + ' días', POR_VENCER: a.dias ? 'Vence en ' + a.dias + ' día(s)' : 'Vence hoy', VENCIDO: 'Vencido hace ' + Math.abs(a.dias) + ' día(s)',
                        PROXIMO: 'Empieza el ' + this.fecha(a.inicio), ANULADO: 'Anulado' }[a.situacion];
                },
                avisar(texto, ok = true) { if (window.tushpaAviso) return window.tushpaAviso(texto, ok); alert(texto); },
                async post(url, datos = {}) {
                    try {
                        const r = await fetch(url, { method: 'POST', body: JSON.stringify(datos), headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' } });
                        const d = await r.json();
                        if (r.status === 422) return { ok: false, mensaje: Object.values(d.errors || {})[0]?.[0] || d.message };
                        if (!r.ok) return { ok: false, mensaje: d.message || 'No se pudo completar.' };
                        return d;
                    } catch (e) { return { ok: false, mensaje: 'Sin conexión con el servidor.' }; }
                },
                ventaVacia() {
                    return { renovacion: false, placa: '', placa2: '', tar_id: this.tarifas[0]?.tar_id ?? null, esp_id: '', clinum: '', clinom: '', telefono: '', obs: '',
                        inicio: hoyIso, meses: 1, precio: 0, tdocod: '03', medio: @json((string) ($mediospagos->first()->id_med_pag ?? '')), paga: null, imprimir: true };
                },
                nueva() { this.venta = this.ventaVacia(); this.precioSugerido(); this.modal = 'vender'; },
                renovar(a) {
                    this.venta = Object.assign(this.ventaVacia(), { renovacion: true, placa: a.placa, placa2: a.placa2 || '', tar_id: a.tar_id, esp_id: a.esp_id || '',
                        clinum: a.clinum || '', clinom: a.clinom, telefono: a.telefono || '', obs: a.obs || '', inicio: a.fin >= hoyIso ? this.sumarDia(a.fin) : hoyIso });
                    this.precioSugerido();
                    this.modal = 'vender';
                },
                precioSugerido() { const t = this.tarifas.find(t => t.tar_id == this.venta.tar_id); this.venta.precio = Math.round(Number(t?.pension || 0) * this.venta.meses * 100) / 100; },
                async buscarDoc() {
                    const doc = (this.venta.clinum || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(doc)) return;
                    const d = await fetch('{{ url('cobros/cliente') }}/' + doc, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({}));
                    if (d && d.nom) { this.venta.clinom = d.nom; this.venta.clidir = d.dir && d.dir !== '--' ? d.dir : ''; if (doc.length === 11) this.venta.tdocod = '01'; this.venta.telefono ||= d.tel || ''; }
                },
                async vender() {
                    const v = this.venta;
                    if (v.tdocod === '01' && !/^\d{11}$/.test(v.clinum)) return this.avisar('Para factura escribe el RUC.', false);
                    this.ocupado = true;
                    const r = await this.post(RUTA, { ...v, esp_id: v.esp_id || null, id_med_pag: [v.medio], mon_med_pag: [v.precio], paga: v.paga || v.precio, imprimir: v.imprimir ? 1 : 0 });
                    this.ocupado = false;
                    this.avisar(r.ok ? r.mensaje + ' ' + r.numero + (r.vuelto > 0 ? ' · vuelto ' + this.soles(r.vuelto) : '') : r.mensaje, r.ok);
                    if (!r.ok) return;
                    if (v.imprimir && !r.impreso) window.open('{{ url('voucher') }}/' + r.id + '?imprimir=1', '_blank');
                    setTimeout(() => location.reload(), 900);
                },
                editar(a) { this.form = { abo_id: a.abo_id, placa: a.placa, placa2: a.placa2 || '', telefono: a.telefono || '', esp_id: a.esp_id || '', obs: a.obs || '' }; this.modal = 'editar'; },
                async guardar() {
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/' + this.form.abo_id, { ...this.form, esp_id: this.form.esp_id || null });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) setTimeout(() => location.reload(), 600);
                },
            };
        }
    </script>
@endsection
