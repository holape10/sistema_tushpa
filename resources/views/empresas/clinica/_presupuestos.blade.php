{{--
    Presupuestos (plan de tratamiento) del paciente: procedimientos por pieza con su precio, descuento y validez.
    Se imprime, el paciente lo firma y, aceptado, pasa al registro de tratamientos. Necesita $servicios (productos con precio).
--}}
@php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500'; @endphp
@include('empresas.clinica._firma_js')
@once
<script>
    window.presupuestosClinica = function (hisId) {
        const CSRF = '{{ csrf_token() }}';
        const soles = n => 'S/ ' + (Number(n) || 0).toFixed(2);
        const SERVICIOS = @js(($servicios ?? collect())->map(fn ($s) => ['n' => $s->pronom, 'p' => (float) $s->propun])->values());
        return {
            lista: [], modal: null, guardando: false, soles,
            fecha(f) { return f ? new Date(f + 'T00:00').toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : ''; },
            numero(p) { return String(p.numero).padStart(5, '0'); },
            async cargar() {
                const r = await fetch('{{ url('clinica/historia') }}/' + hisId + '/presupuestos', { headers: { Accept: 'application/json' } });
                this.lista = r.ok ? await r.json() : [];
            },
            async post(url, data) {
                this.guardando = true;
                try {
                    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) });
                    const j = await r.json().catch(() => ({}));
                    const res = r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j;
                    tushpaAviso(res.mensaje || (res.ok ? 'Listo.' : 'No se pudo guardar.'), res.ok ? 'ok' : 'error');
                    return res;
                } finally { this.guardando = false; }
            },
            linea() { return { pieza: '', descripcion: '', cantidad: 1, precio: '' }; },
            nuevo() { this.modal = { pre_id: null, items: [this.linea()], descuento: '', validez_dias: 30, observaciones: '' }; },
            editar(p) { this.modal = { pre_id: p.pre_id, items: p.items.map(i => ({ ...i })), descuento: Number(p.descuento) || '', validez_dias: p.validez_dias, observaciones: p.observaciones || '' }; },
            // Al elegir un servicio del catálogo se trae su precio
            servicio(it) { const s = SERVICIOS.find(x => x.n === String(it.descripcion).toUpperCase().trim()); if (s && !Number(it.precio)) it.precio = s.p; },
            get servicios() { return SERVICIOS; },
            subtotal() { return (this.modal?.items || []).reduce((a, i) => a + (Number(i.cantidad) || 0) * (Number(i.precio) || 0), 0); },
            async guardar() {
                const m = this.modal;
                const datos = { items: m.items.filter(i => String(i.descripcion).trim()), descuento: m.descuento || 0, validez_dias: m.validez_dias || 30, observaciones: m.observaciones };
                const r = await this.post(m.pre_id ? '{{ url('clinica/presupuestos') }}/' + m.pre_id : '{{ url('clinica/historia') }}/' + hisId + '/presupuestos', datos);
                if (r.ok) { this.modal = null; this.cargar(); }
            },
            async estado(p, estado) {
                if (estado === 'ACEPTADO' && !confirm('¿El paciente acepta el presupuesto? Pasará al registro de tratamientos por ' + soles(p.total) + '.')) return;
                const r = await this.post('{{ url('clinica/presupuestos') }}/' + p.pre_id + '/estado', { estado });
                if (r.ok) { this.cargar(); if (estado === 'ACEPTADO') window.dispatchEvent(new CustomEvent('tratamientos-recargar')); }
            },
            firmar(p) { tushpaFirmar({ tipo: 'presupuesto', id: p.pre_id, titulo: 'Presupuesto N° ' + this.numero(p) + ' · ' + soles(p.total), alFirmar: () => this.cargar() }); },
        };
    };
</script>
@endonce

<div x-data="presupuestosClinica({{ $h->his_cli_id }})" x-init="cargar()" class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <h2 class="font-bold text-gray-700 flex-1">📋 Presupuestos / plan de tratamiento</h2>
        <button type="button" @click="nuevo()" class="px-3 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-bold">+ Nuevo presupuesto</button>
    </div>
    <div x-show="!lista.length" class="bg-white rounded-2xl shadow-sm p-5 text-center text-sm text-gray-400">
        Sin presupuestos. Con <b>+ Nuevo presupuesto</b> armas el plan con sus precios, lo imprimes y el paciente lo firma.
    </div>
    <template x-for="p in lista" :key="p.pre_id">
        <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-0 basis-full sm:basis-auto">
                <p class="font-bold text-gray-800">N° <span x-text="numero(p)"></span> · <span x-text="soles(p.total)"></span>
                    <span class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-extrabold"
                          :class="{ 'bg-emerald-100 text-emerald-800': p.estado === 'ACEPTADO', 'bg-rose-100 text-rose-700': p.estado === 'RECHAZADO', 'bg-amber-100 text-amber-800': p.estado === 'PENDIENTE' }"
                          x-text="p.estado === 'RECHAZADO' ? 'NO ACEPTADO' : p.estado"></span>
                    <span x-show="p.firma_url" class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-extrabold bg-indigo-100 text-indigo-700">✔ FIRMADO</span></p>
                <p class="text-xs text-gray-500 truncate" x-text="fecha(p.fecha) + ' · ' + p.items.map(i => (i.pieza ? i.pieza + ' ' : '') + i.descripcion).join(', ')"></p>
                <p x-show="p.estado === 'PENDIENTE' && p.vence < new Date().toISOString().slice(0, 10)" class="text-xs font-bold text-rose-600">Venció el <span x-text="fecha(p.vence)"></span></p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <a :href="'{{ url('clinica/presupuestos') }}/' + p.pre_id + '/imprimir'" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-white border text-xs font-semibold"><i class="fas fa-print"></i> Imprimir</a>
                <button type="button" x-show="!p.firma_url" @click="firmar(p)" class="px-2.5 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold">✍️ Firmar</button>
                <button type="button" x-show="p.estado === 'PENDIENTE'" @click="editar(p)" class="px-2.5 py-1.5 rounded-lg bg-white border text-xs">✏️</button>
                <button type="button" x-show="p.estado === 'PENDIENTE'" @click="estado(p, 'ACEPTADO')" class="px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold">✔ Aceptó</button>
                <button type="button" x-show="p.estado === 'PENDIENTE'" @click="estado(p, 'RECHAZADO')" class="px-2.5 py-1.5 rounded-lg bg-white border text-rose-600 text-xs font-bold">No aceptó</button>
                <button type="button" x-show="p.estado === 'RECHAZADO'" @click="estado(p, 'PENDIENTE')" class="px-2.5 py-1.5 rounded-lg bg-white border text-xs">Volver a pendiente</button>
            </div>
        </div>
    </template>

    <datalist id="servicios-clinica"><template x-for="s in servicios" :key="s.n"><option :value="s.n"></option></template></datalist>
    <div x-show="modal" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl p-5 space-y-3">
            <h3 class="font-bold text-gray-800" x-text="modal?.pre_id ? 'Corregir presupuesto' : 'Nuevo presupuesto'"></h3>
            <template x-if="modal">
                <div class="space-y-3">
                    <div class="hidden sm:grid grid-cols-12 gap-2 text-[11px] font-bold text-gray-500 uppercase">
                        <span class="col-span-2">Pieza</span><span class="col-span-5">Procedimiento</span><span class="col-span-1 text-right">Cant.</span><span class="col-span-2 text-right">Precio S/</span><span class="col-span-2 text-right">Importe</span>
                    </div>
                    <template x-for="(it, i) in modal.items" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-center border-b sm:border-0 pb-2 sm:pb-0">
                            <input x-model="it.pieza" maxlength="20" placeholder="Ej. 36" class="{{ $in }} col-span-3 sm:col-span-2">
                            <input x-model="it.descripcion" @change="servicio(it)" list="servicios-clinica" maxlength="200" placeholder="Ej. Resina simple" class="{{ $in }} col-span-9 sm:col-span-5 uppercase">
                            <input type="number" step="1" min="1" x-model="it.cantidad" class="{{ $in }} col-span-3 sm:col-span-1 text-right">
                            <input type="number" step="0.01" min="0" x-model="it.precio" placeholder="0.00" class="{{ $in }} col-span-4 sm:col-span-2 text-right font-bold">
                            <span class="col-span-3 sm:col-span-1 text-right text-sm font-semibold" x-text="((Number(it.cantidad) || 0) * (Number(it.precio) || 0)).toFixed(2)"></span>
                            <button type="button" @click="modal.items.splice(i, 1)" x-show="modal.items.length > 1" class="col-span-2 sm:col-span-1 text-rose-500 text-lg" title="Quitar">×</button>
                        </div>
                    </template>
                    <button type="button" @click="modal.items.push(linea())" class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs font-bold">+ Agregar procedimiento</button>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <label class="block text-xs font-semibold text-gray-500">Descuento S/<input type="number" step="0.01" min="0" x-model="modal.descuento" class="{{ $in }} mt-1 text-right"></label>
                        <label class="block text-xs font-semibold text-gray-500">Válido por (días)<input type="number" min="1" max="365" x-model="modal.validez_dias" class="{{ $in }} mt-1 text-right"></label>
                        <div class="text-right self-end">
                            <p class="text-xs text-gray-500">Subtotal <span x-text="soles(subtotal())"></span></p>
                            <p class="text-xl font-black text-teal-700" x-text="'Total ' + soles(Math.max(0, subtotal() - (Number(modal.descuento) || 0)))"></p>
                        </div>
                    </div>
                    <label class="block text-xs font-semibold text-gray-500">Observaciones (formas de pago, número de sesiones, indicaciones)
                        <textarea x-model="modal.observaciones" rows="2" maxlength="2000" class="{{ $in }} mt-1"></textarea></label>
                    <p x-show="modal.pre_id" class="text-xs text-amber-700">Si ya estaba firmado, al guardar el paciente deberá firmar otra vez.</p>
                </div>
            </template>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modal = null" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                <button type="button" @click="guardar()" :disabled="guardando" class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-bold disabled:opacity-40">Guardar</button>
            </div>
        </div>
    </div>
</div>
