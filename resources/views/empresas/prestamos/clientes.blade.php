@extends('layouts.app')
@section('title', 'Clientes de préstamos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500'; @endphp

    <div x-data="clientesPrestamos()" class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="mr-auto">
                <a href="{{ route('prestamos.index') }}" class="text-sm text-gray-500">← Préstamos</a>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-users text-emerald-600"></i> Clientes</h1>
            </div>
            @if ($gestiona)
                <button type="button" @click="nuevo()" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-sm hover:bg-emerald-700"><i class="fas fa-user-plus"></i> Nuevo cliente</button>
            @endif
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <template x-for="k in tarjetas" :key="k.e">
                <button type="button" @click="estado = estado === k.e ? '' : k.e" class="text-left bg-white rounded-2xl shadow-sm p-3 border-2"
                        :class="estado === k.e ? k.borde : 'border-transparent'">
                    <p class="text-[11px] font-semibold text-gray-500" x-text="k.n"></p>
                    <p class="text-2xl font-black" :class="k.t" x-text="lista.filter(c => c.estado === k.e).length"></p>
                </button>
            </template>
        </div>

        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-3 border-b flex flex-wrap gap-2">
                <input x-model="q" placeholder="Buscar por nombre, DNI, celular o negocio" class="{{ $in }} flex-1 min-w-[200px]">
                <select x-model="zona" class="rounded-lg border-gray-300 text-sm"><option value="">Todas las zonas</option>
                    @foreach ($zonas as $z)<option>{{ $z }}</option>@endforeach</select>
            </div>
            <div class="divide-y">
                <template x-for="c in visibles" :key="c.pcl_id">
                    <div class="p-3 flex flex-wrap items-center gap-3 hover:bg-gray-50">
                        <button type="button" @click="abrir(c.pcl_id)" class="w-11 h-11 rounded-full text-white font-black text-lg" :class="avatar[c.estado] || 'bg-gray-400'" x-text="c.nombre.slice(0, 1)"></button>
                        <button type="button" @click="abrir(c.pcl_id)" class="flex-1 min-w-[180px] text-left">
                            <p class="font-bold text-gray-800" x-text="c.nombre"></p>
                            <p class="text-xs text-gray-500" x-text="[c.dni, c.telefono, c.zona, c.negocio, c.cobrador ? 'cobra ' + c.cobrador : null].filter(Boolean).join(' · ')"></p>
                        </button>
                        <div class="text-right">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold" :class="chip[c.estado]" x-text="nombre[c.estado] + (c.vencidas ? ' · ' + c.vencidas + ' atr.' : '')"></span>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="c.vigentes ? 'debe ' + soles(c.saldo) : (c.pagados ? c.pagados + ' pagado(s)' : 'sin préstamos')"></p>
                        </div>
                        @if ($gestiona)
                            <a :href="'{{ route('prestamos.nuevo') }}?cliente=' + c.pcl_id" x-show="c.estado_cliente === 'ACTIVO'" class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold"><i class="fas fa-hand-holding-dollar"></i> Prestar</a>
                        @endif
                    </div>
                </template>
                <p x-show="!visibles.length" class="p-10 text-center text-gray-400">Sin clientes. @if ($gestiona)<button type="button" @click="nuevo()" class="text-emerald-700 font-bold underline">Registra el primero</button>.@endif</p>
            </div>
        </div>

        {{-- Registrar / editar --}}
        <div x-show="form" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-5 space-y-3" @click.outside="form = null">
                <h3 class="font-bold text-lg text-gray-800" x-text="form?.pcl_id ? 'Editar cliente' : 'Nuevo cliente'"></h3>
                <template x-if="form">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block text-xs font-semibold text-gray-500">DNI *
                            <span class="mt-1 flex gap-2">
                                <input x-model="form.clinum" @input="if (String(form.clinum).replace(/\D/g, '').length === 8) buscarDni()" @keydown.enter.prevent="buscarDni(true)"
                                       inputmode="numeric" maxlength="11" class="{{ $in }} text-lg font-bold">
                                <button type="button" @click="buscarDni(true)" class="shrink-0 px-4 rounded-lg bg-emerald-600 text-white font-bold" title="Buscar DNI"><i class="fas fa-magnifying-glass"></i></button>
                            </span>
                            <span class="text-[11px] font-normal" :class="dniMsg.startsWith('✔') ? 'text-emerald-600' : 'text-gray-400'" x-text="dniMsg || 'Escribe el DNI y el nombre sale solo'"></span></label>
                        <label class="block text-xs font-semibold text-gray-500">Nombre completo *<input x-model="form.clinom" class="{{ $in }} mt-1 uppercase"></label>
                        <label class="block text-xs font-semibold text-gray-500">Celular / WhatsApp<input x-model="form.telefono" inputmode="tel" maxlength="20" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500">Negocio o trabajo<input x-model="form.negocio" placeholder="Ej. Bodega, puesto de mercado, mototaxi" class="{{ $in }} mt-1 uppercase"></label>
                        <label class="block text-xs font-semibold text-gray-500 sm:col-span-2">Dirección de cobro<input x-model="form.direccion" placeholder="Donde se le cobra todos los días" class="{{ $in }} mt-1 uppercase"></label>
                        <label class="block text-xs font-semibold text-gray-500">Referencia<input x-model="form.referencia" placeholder="Ej. frente al parque, puerta verde" class="{{ $in }} mt-1 uppercase"></label>
                        <label class="block text-xs font-semibold text-gray-500">Zona / ruta<input x-model="form.zona" list="zonas" placeholder="Ej. MERCADO CENTRAL" class="{{ $in }} mt-1 uppercase">
                            <datalist id="zonas">@foreach ($zonas as $z)<option value="{{ $z }}"></option>@endforeach</datalist></label>
                        <div class="sm:col-span-2 flex flex-wrap items-center gap-2">
                            <button type="button" @click="ubicar()" class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs font-bold"><i class="fas fa-location-dot text-rose-500"></i> Guardar ubicación actual (GPS)</button>
                            <span class="text-xs text-gray-500" x-text="form.lat ? '✔ Ubicación guardada' : 'Opcional: estando en el negocio del cliente'"></span>
                        </div>
                        <div class="sm:col-span-2 rounded-xl bg-gray-50 p-3 grid sm:grid-cols-3 gap-2">
                            <p class="sm:col-span-3 text-xs font-bold text-gray-500 uppercase">Aval / garante (opcional)</p>
                            <input x-model="form.aval_nombre" placeholder="Nombre" class="{{ $in }} uppercase">
                            <input x-model="form.aval_dni" placeholder="DNI" inputmode="numeric" maxlength="15" class="{{ $in }}">
                            <input x-model="form.aval_telefono" placeholder="Celular" inputmode="tel" maxlength="20" class="{{ $in }}">
                        </div>
                        <label class="block text-xs font-semibold text-gray-500">Cobrador
                            <select x-model="form.cobrador_id" class="{{ $in }} mt-1"><option value="">— Sin asignar —</option>
                                @foreach ($cobradores as $u)<option value="{{ $u->IdUsuario }}">{{ $u->nombre }}</option>@endforeach</select></label>
                        <label class="block text-xs font-semibold text-gray-500">Límite de préstamo S/ <span class="font-normal">(vacío = sin límite)</span><input type="number" min="0" step="10" x-model="form.limite" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500 sm:col-span-2">Observaciones<textarea x-model="form.obs" rows="2" class="{{ $in }} mt-1"></textarea></label>
                        <label x-show="form.pcl_id" class="flex items-center gap-2 text-sm text-rose-600 sm:col-span-2"><input type="checkbox" :checked="form.estado === 'BLOQUEADO'" @change="form.estado = $event.target.checked ? 'BLOQUEADO' : 'ACTIVO'" class="rounded"> Bloqueado (no se le presta más)</label>
                    </div>
                </template>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="form = null" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="guardar(false)" :disabled="ocupado" class="px-4 py-2 rounded-xl bg-gray-800 text-white text-sm font-bold disabled:opacity-40">Guardar</button>
                    <button type="button" x-show="!form?.pcl_id" @click="guardar(true)" :disabled="ocupado" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold disabled:opacity-40">Guardar y prestar <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>

        {{-- Ficha --}}
        <div x-show="ficha" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden" @click.outside="ficha = null">
                <template x-if="ficha">
                    <div>
                        <div class="p-5 bg-gradient-to-r from-emerald-600 to-teal-700 text-white flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center text-2xl font-black" x-text="ficha.cliente.clinom.slice(0, 1)"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xl font-extrabold" x-text="ficha.cliente.clinom"></p>
                                <p class="text-sm text-emerald-50" x-text="['DNI ' + ficha.cliente.clinum, ficha.cliente.telefono, ficha.cliente.negocio].filter(Boolean).join(' · ')"></p>
                                <p class="text-xs text-emerald-100" x-text="[ficha.cliente.direccion, ficha.cliente.referencia, ficha.cliente.zona].filter(Boolean).join(' · ')"></p>
                            </div>
                            <button type="button" @click="ficha = null" class="text-white/80 text-xl">✕</button>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="rounded-2xl p-4 flex items-center gap-4" :class="cal[ficha.calificacion.color][0]">
                                <div class="text-center">
                                    <p class="text-[10px] uppercase font-bold text-gray-500">Calificación</p>
                                    <p class="text-2xl font-black" :class="cal[ficha.calificacion.color][1]" x-text="ficha.calificacion.texto"></p>
                                </div>
                                <div class="flex-1 text-sm text-gray-600">
                                    <p x-text="ficha.calificacion.detalle"></p>
                                    <p x-show="ficha.calificacion.sugerido" class="font-bold text-emerald-700" x-text="'Se le puede prestar hasta ' + soles(ficha.calificacion.sugerido)"></p>
                                    <p x-show="ficha.cliente.aval_nombre" class="text-xs mt-1" x-text="'Aval: ' + [ficha.cliente.aval_nombre, ficha.cliente.aval_dni, ficha.cliente.aval_telefono].filter(Boolean).join(' · ')"></p>
                                </div>
                            </div>
                            <div>
                                <p class="font-bold text-gray-700 mb-1">Préstamos vigentes</p>
                                <template x-for="p in ficha.vigentes" :key="p.pre_id">
                                    <a :href="'{{ url('prestamos') }}/' + p.pre_id" class="flex items-center gap-3 rounded-xl border p-3 mb-2 hover:bg-gray-50">
                                        <span class="flex-1"><b x-text="'N° ' + String(p.numero).padStart(5, '0') + ' · ' + soles(p.monto)"></b>
                                            <span class="block h-1.5 mt-1 rounded-full bg-gray-100"><span class="block h-1.5 rounded-full bg-emerald-500" :style="'width:' + p.avance + '%'"></span></span></span>
                                        <span class="text-right text-sm"><span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold" :class="chip[p.estado]" x-text="nombre[p.estado]"></span>
                                            <span class="block text-xs text-gray-500" x-text="'saldo ' + soles(p.saldo)"></span></span>
                                    </a>
                                </template>
                                <p x-show="!ficha.vigentes.length" class="text-sm text-gray-400">No tiene préstamos vigentes.</p>
                            </div>
                            <div x-show="ficha.historial.length">
                                <p class="font-bold text-gray-700 mb-1">Historial</p>
                                <template x-for="h in ficha.historial" :key="h.pre_id">
                                    <a :href="'{{ url('prestamos') }}/' + h.pre_id" class="flex justify-between text-sm border-b py-1.5 hover:bg-gray-50">
                                        <span x-text="'N° ' + String(h.numero).padStart(5, '0') + ' · ' + soles(h.monto) + ' · ' + fecha(h.fecha)"></span>
                                        <span :class="h.estado === 'PAGADO' ? 'text-emerald-600' : 'text-gray-400'" x-text="h.estado === 'PAGADO' ? '✔ pagado ' + fecha(h.cancelado) : 'anulado'"></span>
                                    </a>
                                </template>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <a x-show="wa(ficha.cliente.telefono)" :href="wa(ficha.cliente.telefono)" target="_blank" class="px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-bold"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                                @if ($gestiona)
                                    <button type="button" @click="editar(ficha.cliente)" class="px-3 py-2 rounded-lg bg-gray-100 text-sm font-bold"><i class="fas fa-pen"></i> Editar</button>
                                    <a x-show="ficha.cliente.estado === 'ACTIVO'" :href="'{{ route('prestamos.nuevo') }}?cliente=' + ficha.cliente.pcl_id" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold"><i class="fas fa-hand-holding-dollar"></i> Nuevo préstamo</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function clientesPrestamos() {
            const CSRF = '{{ csrf_token() }}';
            const vacio = () => ({ pcl_id: null, clinum: '', clinom: '', telefono: '', direccion: '', referencia: '', zona: '', negocio: '', aval_nombre: '', aval_dni: '',
                aval_telefono: '', cobrador_id: '', limite: '', estado: 'ACTIVO', obs: '', lat: null, lng: null });
            return {
                lista: @js($clientes), q: '', zona: '', estado: '', form: null, ficha: null, ocupado: false, dniMsg: '', nombreDeDni: '',
                tarjetas: [{ e: 'AL_DIA', n: 'Al día', t: 'text-emerald-600', borde: 'border-emerald-400' }, { e: 'ATRASADO', n: 'Atrasados', t: 'text-amber-500', borde: 'border-amber-400' },
                    { e: 'MOROSO', n: 'Morosos', t: 'text-rose-600', borde: 'border-rose-400' }, { e: 'SIN_DEUDA', n: 'Sin deuda', t: 'text-sky-600', borde: 'border-sky-400' },
                    { e: 'BLOQUEADO', n: 'Bloqueados', t: 'text-gray-500', borde: 'border-gray-400' }],
                nombre: { AL_DIA: 'Al día', ATRASADO: 'Atrasado', MOROSO: 'Moroso', SIN_DEUDA: 'Sin deuda', BLOQUEADO: 'Bloqueado', PAGADO: 'Pagado' },
                chip: { AL_DIA: 'bg-emerald-100 text-emerald-700', ATRASADO: 'bg-amber-100 text-amber-800', MOROSO: 'bg-rose-100 text-rose-700', SIN_DEUDA: 'bg-sky-100 text-sky-700', BLOQUEADO: 'bg-gray-200 text-gray-600', PAGADO: 'bg-sky-100 text-sky-700' },
                cal: { emerald: ['bg-emerald-50', 'text-emerald-600'], sky: ['bg-sky-50', 'text-sky-600'], amber: ['bg-amber-50', 'text-amber-600'], rose: ['bg-rose-50', 'text-rose-600'], slate: ['bg-slate-50', 'text-slate-600'] },
                avatar: { AL_DIA: 'bg-emerald-500', ATRASADO: 'bg-amber-400', MOROSO: 'bg-rose-500', SIN_DEUDA: 'bg-sky-500', BLOQUEADO: 'bg-gray-400' },
                soles: n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                fecha: f => f ? String(f).slice(8, 10) + '/' + String(f).slice(5, 7) + '/' + String(f).slice(0, 4) : '',
                wa(t) { let n = String(t || '').replace(/\D/g, ''); if (n.length === 9) n = '51' + n; return n.length >= 10 ? 'https://wa.me/' + n : null; },
                get visibles() {
                    const q = this.q.trim().toLowerCase();
                    return this.lista.filter(c => (!this.estado || c.estado === this.estado) && (!this.zona || c.zona === this.zona)
                        && (!q || [c.nombre, c.dni, c.telefono, c.negocio, c.zona].join(' ').toLowerCase().includes(q)));
                },
                nuevo() { this.form = vacio(); this.dniMsg = ''; this.nombreDeDni = ''; },
                editar(c) {
                    this.ficha = null;
                    this.form = Object.assign(vacio(), Object.fromEntries(Object.keys(vacio()).map(k => [k, c[k] ?? vacio()[k]])), { cobrador_id: c.cobrador_id ? String(c.cobrador_id) : '' });
                },
                // Sola al completar los 8 números (sin pisar un nombre escrito a mano) o con el botón 🔍 (en el celular no hay Enter)
                async buscarDni(forzar = false) {
                    const d = String(this.form.clinum || '').trim();
                    if (!/^\d{8}$/.test(d)) { if (forzar) this.dniMsg = 'El DNI tiene 8 números.'; return; }
                    if (!forzar && this.form.clinom && this.form.clinom !== this.nombreDeDni) return;
                    this.dniMsg = 'Buscando…';
                    const r = await fetch('{{ url('cobros/cliente') }}/' + d, { headers: { Accept: 'application/json' } }).then(x => x.json()).catch(() => ({}));
                    if (r && r.nom) { this.form.clinom = this.nombreDeDni = r.nom; this.form.telefono ||= r.tel || ''; this.dniMsg = '✔ Encontrado'; } else this.dniMsg = 'No se encontró: escribe el nombre.';
                },
                ubicar() {
                    if (!navigator.geolocation) return tushpaAviso('Este equipo no tiene GPS.', 'error');
                    navigator.geolocation.getCurrentPosition(p => { this.form.lat = p.coords.latitude; this.form.lng = p.coords.longitude; tushpaAviso('Ubicación guardada.', 'ok'); },
                        () => tushpaAviso('No se pudo leer la ubicación (da permiso al navegador).', 'error'), { enableHighAccuracy: true, timeout: 10000 });
                },
                async guardar(prestar) {
                    this.ocupado = true;
                    const r = await fetch('{{ route('prestamos.clientes.guardar') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify(Object.fromEntries(Object.entries(this.form).map(([k, v]) => [k, v === '' ? null : v]))) });
                    const j = await r.json().catch(() => ({}));
                    this.ocupado = false;
                    const ok = r.ok && j.ok;
                    tushpaAviso(j.mensaje || Object.values(j.errors || {}).flat()[0] || 'No se pudo guardar.', ok ? 'ok' : 'error');
                    if (!ok) return;
                    if (prestar) { location.href = '{{ route('prestamos.nuevo') }}?cliente=' + j.pcl_id; return; }
                    location.reload();
                },
                async abrir(id) {
                    this.ficha = await fetch('{{ url('prestamos/clientes') }}/' + id, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
                },
            };
        }
    </script>
@endsection
