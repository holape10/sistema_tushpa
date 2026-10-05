@extends('layouts.app')
@section('title', 'Venta Masiva')

@section('content')
<div x-data="ventaMasiva()" x-init="iniciar()" class="max-w-7xl mx-auto pb-28">
    @include('empresas.partials.alert')

    {{-- Datos de emisión --}}
    <section class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <label class="md:col-span-3 text-sm font-medium text-gray-700">Fecha de emisión
                <input type="date" x-model="fecha" @change="cambiarFecha()" :disabled="emitiendo"
                       class="mt-1 w-full h-11 rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </label>
            <div class="md:col-span-9">
                <label class="text-sm font-medium text-gray-700">Descripción del detalle en el comprobante
                    <input type="text" x-model="concepto" maxlength="150" :disabled="emitiendo"
                           class="mt-1 w-full h-11 rounded-xl border-gray-300 text-sm font-semibold uppercase focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <span class="text-xs text-gray-400 self-center">Agregar mes:</span>
                    <template x-for="m in cfg.meses" :key="m">
                        <button type="button" @click="agregarMes(m)" :disabled="emitiendo" class="px-2.5 h-7 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100" x-text="'+ ' + m"></button>
                    </template>
                    <button type="button" @click="concepto = cfg.concepto" :disabled="emitiendo" class="px-2.5 h-7 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold">Restablecer</button>
                </div>
            </div>
            <div class="md:col-span-5">
                <p class="text-sm font-medium text-gray-700 mb-1">Forma de pago</p>
                <div class="flex gap-1 p-1 bg-gray-100 rounded-xl">
                    <template x-for="e in cfg.estadopagos" :key="e.cre_dia_id">
                        <button type="button" @click="elegirPago(e)" :disabled="emitiendo" :class="estadopago == e.cre_dia_id ? 'bg-white shadow text-indigo-700' : 'text-gray-500'"
                                class="flex-1 h-9 rounded-lg text-sm font-bold" x-text="e.cre_dia_nom"></button>
                    </template>
                </div>
            </div>
            <label x-show="!esContado" x-cloak class="md:col-span-3 text-sm font-medium text-gray-700">Vence el
                <input type="date" x-model="fecVen" :min="fecha" class="mt-1 w-full h-11 rounded-xl border-gray-300 text-sm">
            </label>
            <div class="md:col-span-4 flex flex-col justify-end gap-2 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" x-model="guardar" class="rounded text-indigo-600"> Guardar montos y comprobantes cambiados como predeterminados</label>
                <label class="flex items-center gap-2"><input type="checkbox" x-model="enviarSunat" class="rounded text-indigo-600"> Enviar las facturas a SUNAT al terminar</label>
            </div>
        </div>
    </section>

    {{-- Resumen --}}
    <section class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="col-span-2 rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-500 text-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase opacity-80">Total a emitir</p>
            <p class="text-3xl font-black" x-text="soles(totalSeleccion)"></p>
            <p class="text-xs opacity-80" x-text="seleccionados.length + ' de ' + filas.length + ' clientes seleccionados'"></p>
        </div>
        <template x-for="t in tipos" :key="t.cod">
            <button type="button" @click="filtro = filtro === t.cod ? '' : t.cod" :class="filtro === t.cod ? 'ring-2 ring-indigo-500' : ''"
                    class="bg-white rounded-2xl shadow-sm p-4 text-left">
                <p class="text-xs font-semibold uppercase text-gray-500" x-text="t.nombre"></p>
                <p class="text-xl font-extrabold text-gray-800" x-text="resumenTipo(t.cod).n"></p>
                <p class="text-xs text-gray-400" x-text="soles(resumenTipo(t.cod).total)"></p>
            </button>
        </template>
    </section>

    {{-- Lista --}}
    <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-center gap-2 p-3 border-b border-gray-100">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input type="search" x-model="busqueda" placeholder="Buscar por nombre o RUC / DNI…" class="w-full h-10 pl-10 rounded-xl border-gray-200 text-sm">
            </div>
            <button type="button" @click="marcar(true)" :disabled="emitiendo" class="h-10 px-3 rounded-xl bg-gray-100 text-sm font-semibold">Marcar visibles</button>
            <button type="button" @click="marcar(false)" :disabled="emitiendo" class="h-10 px-3 rounded-xl bg-gray-100 text-sm font-semibold">Desmarcar</button>
            <a href="{{ route('contactos.index', 'clientes') }}" class="h-10 px-3 rounded-xl text-sm font-semibold text-indigo-600 inline-flex items-center">Editar clientes →</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr>
                        <th class="px-3 py-2 w-10"><input type="checkbox" :checked="visibles.length && visibles.every(f => f.sel)" @change="marcar($event.target.checked)" :disabled="emitiendo" class="rounded"></th>
                        <th class="px-3 py-2 text-left">Cliente</th>
                        <th class="px-3 py-2 text-left w-40">Comprobante</th>
                        <th class="px-3 py-2 text-right w-32">Monto S/</th>
                        <th class="px-3 py-2 text-left w-56">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="f in visibles" :key="f.id">
                        <tr :class="f.estado === 'ok' ? 'bg-emerald-50' : (f.estado === 'error' ? 'bg-rose-50' : (f.emitido ? 'bg-amber-50/60' : ''))">
                            <td class="px-3 py-2 text-center"><input type="checkbox" x-model="f.sel" :disabled="emitiendo || f.estado === 'ok'" class="rounded"></td>
                            <td class="px-3 py-2">
                                <p class="font-semibold text-gray-800" x-text="f.nombre"></p>
                                <p class="text-xs text-gray-400"><span x-text="(f.tdicod === '6' ? 'RUC ' : 'DOC ') + f.doc"></span><span x-show="f.dir" x-text="' · ' + f.dir"></span></p>
                            </td>
                            <td class="px-3 py-2">
                                <select x-model="f.tdocod" :disabled="emitiendo || f.estado === 'ok'" class="w-full h-9 rounded-lg border-gray-200 text-xs font-bold"
                                        :class="{ '01': 'text-emerald-700', '03': 'text-blue-700', '13': 'text-sky-600' }[f.tdocod]">
                                    <template x-for="t in tipos" :key="t.cod">
                                        <option :value="t.cod" :disabled="t.cod === '01' && f.tdicod !== '6'" :selected="t.cod === f.tdocod" x-text="t.nombre"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0.01" x-model.number="f.monto" :disabled="emitiendo || f.estado === 'ok'"
                                       class="w-full h-9 rounded-lg border-gray-200 text-right font-bold text-emerald-700">
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <template x-if="f.estado === 'ok'">
                                    <span class="flex items-center gap-2">
                                        <span class="font-bold text-emerald-700" x-text="'✔ ' + f.numero"></span>
                                        <a :href="f.pdf" target="_blank" class="text-indigo-600 font-semibold hover:underline">PDF</a>
                                        <span x-show="f.sunat" class="text-gray-500" x-text="f.sunat"></span>
                                    </span>
                                </template>
                                <span x-show="f.estado === 'emitiendo'" class="text-indigo-600 font-semibold">Emitiendo…</span>
                                <span x-show="f.estado === 'error'" class="text-rose-700 font-semibold" x-text="'✘ ' + f.error"></span>
                                <span x-show="!f.estado && f.emitido" class="text-amber-700 font-semibold" x-text="'Ya emitido este mes: ' + f.emitido"></span>
                                <span x-show="!f.estado && !f.emitido" class="text-gray-400">Pendiente</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p x-show="!filas.length" class="py-12 text-center text-gray-400">
                No hay clientes con <strong>facturación mensual</strong>. Márcala al editar un cliente (Contactos &gt; Clientes) o impórtala del sistema antiguo.
            </p>
        </div>
    </section>

    {{-- Barra de acción --}}
    <div class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t border-gray-200" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <template x-if="emitiendo || hechos">
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-gray-600 mb-1">
                            <span x-text="emitiendo ? 'Emitiendo ' + progreso.hecho + ' de ' + progreso.total + '…' : 'Terminado: ' + resultado.ok + ' emitidos' + (resultado.error ? ', ' + resultado.error + ' con error' : '')"></span>
                            <span x-text="Math.round(progreso.total ? progreso.hecho / progreso.total * 100 : 0) + '%'"></span>
                        </div>
                        <div class="h-2.5 rounded-full bg-gray-200 overflow-hidden"><div class="h-full bg-emerald-500 transition-all" :style="`width:${progreso.total ? progreso.hecho / progreso.total * 100 : 0}%`"></div></div>
                    </div>
                </template>
                <p x-show="!emitiendo && !hechos" class="text-sm text-gray-500"><strong x-text="seleccionados.length"></strong> comprobantes por <strong x-text="soles(totalSeleccion)"></strong></p>
            </div>
            <button type="button" x-show="emitiendo" @click="detener = true" class="h-12 px-5 rounded-xl bg-rose-50 text-rose-700 font-bold">Detener</button>
            <a x-show="!emitiendo && idsEmitidos.length" x-cloak :href="cfg.rutas.zip + '?ids=' + idsEmitidos.join(',')"
               class="h-12 px-5 rounded-xl bg-indigo-50 text-indigo-700 font-bold inline-flex items-center">⬇ Descargar ZIP (PDF)</a>
            <button type="button" @click="emitir()" :disabled="emitiendo || !seleccionados.length"
                    class="h-12 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold shadow disabled:opacity-50"
                    x-text="emitiendo ? 'Emitiendo…' : 'Emitir ' + seleccionados.length + ' comprobantes'"></button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function ventaMasiva() {
        const CFG = {
            clientes: @json($clientes), concepto: @json($concepto), meses: @json($meses), estadopagos: @json($estadopagos),
            comprobantes: @json($comprobantes), csrf: @json(csrf_token()),
            rutas: { emitir: @json(route('ventas.masiva.emitir')), zip: @json(route('ventas.masiva.zip')), base: @json(route('ventas.masiva')), sunat: @json(url('sunat/enviar')) },
        };
        const NOMBRES = { '01': 'Factura', '03': 'Boleta', '13': 'Nota de venta' };
        const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
        return {
            cfg: CFG,
            fecha: @json($fecha),
            concepto: CFG.concepto,
            estadopago: null,
            fecVen: '',
            guardar: true,
            enviarSunat: false,
            busqueda: '',
            filtro: '',
            // Los ya emitidos este mes quedan desmarcados para no duplicar
            filas: CFG.clientes.map(c => ({ ...c, sel: !c.emitido && c.monto > 0, estado: '', numero: '', pdf: '', error: '', sunat: '', idCpe: null,
                                           tdocodOriginal: c.tdocod, montoOriginal: c.monto })),
            emitiendo: false,
            detener: false,
            hechos: false,
            progreso: { hecho: 0, total: 0 },
            resultado: { ok: 0, error: 0 },

            iniciar() {
                const contado = CFG.estadopagos.find(e => e.cre_dia_tip === 'CONTADO') || CFG.estadopagos[0];
                this.estadopago = contado?.cre_dia_id ?? null;
            },
            get tipos() {
                return ['01', '03', '13'].filter(c => CFG.comprobantes[c] !== undefined || c === '13').map(c => ({ cod: c, nombre: NOMBRES[c] }));
            },
            get visibles() {
                const q = this.busqueda.trim().toLowerCase();
                return this.filas.filter(f => (!this.filtro || f.tdocod === this.filtro) && (!q || f.nombre.toLowerCase().includes(q) || f.doc.includes(q)));
            },
            get seleccionados() { return this.filas.filter(f => f.sel && f.estado !== 'ok'); },
            get totalSeleccion() { return r2(this.seleccionados.reduce((s, f) => s + (Number(f.monto) || 0), 0)); },
            get idsEmitidos() { return this.filas.filter(f => f.idCpe).map(f => f.idCpe); },
            get esContado() {
                const e = CFG.estadopagos.find(x => x.cre_dia_id == this.estadopago);
                return !e || e.cre_dia_tip === 'CONTADO';
            },
            resumenTipo(cod) {
                const s = this.seleccionados.filter(f => f.tdocod === cod);
                return { n: s.length, total: r2(s.reduce((t, f) => t + (Number(f.monto) || 0), 0)) };
            },
            soles(n) { return 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

            marcar(v) { this.visibles.forEach(f => { if (f.estado !== 'ok') f.sel = v; }); },
            agregarMes(m) {
                const base = this.concepto.replace(/\s+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SETIEMBRE|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+\d{4}$/i, '');
                this.concepto = (base.trim() + ' ' + m).slice(0, 150);
            },
            cambiarFecha() {
                // Recarga para recalcular qué clientes ya tienen comprobante en ese mes
                if (this.fecha) location.href = CFG.rutas.base + '?fecha=' + this.fecha;
            },
            elegirPago(e) {
                this.estadopago = e.cre_dia_id;
                if (e.cre_dia_tip !== 'CONTADO') {
                    const f = new Date(this.fecha + 'T00:00:00');
                    f.setDate(f.getDate() + (parseInt(e.cre_dia_fac) || 30));
                    this.fecVen = f.toISOString().slice(0, 10);
                }
            },

            async emitir() {
                const lista = this.seleccionados;
                if (!lista.length) return;
                if (!this.concepto.trim()) { alert('Escribe la descripción del detalle.'); return; }
                const malos = lista.filter(f => !(f.monto > 0));
                if (malos.length) { alert('Revisa el monto de: ' + malos.map(f => f.nombre).slice(0, 5).join(', ')); return; }
                const dup = lista.filter(f => f.emitido).length;
                const resumen = this.tipos.map(t => this.resumenTipo(t.cod)).map((r, i) => r.n ? `${r.n} ${this.tipos[i].nombre.toLowerCase()}(s)` : '').filter(Boolean).join(', ');
                if (!confirm(`Se emitirán ${lista.length} comprobantes (${resumen}) por ${this.soles(this.totalSeleccion)} con fecha ${this.fecha.split('-').reverse().join('/')}.`
                    + (dup ? `\n\n⚠ ${dup} cliente(s) YA tienen comprobante este mes.` : '') + '\n\n¿Continuar?')) return;

                this.emitiendo = true; this.detener = false; this.hechos = false;
                this.progreso = { hecho: 0, total: lista.length };
                this.resultado = { ok: 0, error: 0 };
                for (const f of lista) {
                    if (this.detener) break;
                    f.estado = 'emitiendo';
                    const cambio = f.tdocod !== f.tdocodOriginal || r2(f.monto) !== r2(f.montoOriginal);
                    try {
                        const r = await fetch(CFG.rutas.emitir, {
                            method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf },
                            body: JSON.stringify({ clicod: f.id, tdocod: f.tdocod, monto: f.monto, fecha: this.fecha, concepto: this.concepto,
                                estadopago: this.estadopago, fecVen: this.esContado ? null : this.fecVen, guardar: this.guardar && cambio }),
                        });
                        if (r.status === 419) { f.estado = 'error'; f.error = 'La sesión expiró. Recarga la página.'; this.detener = true; }
                        else {
                            const d = await r.json();
                            if (r.status === 422) { f.estado = 'error'; f.error = Object.values(d.errors)[0][0]; }
                            else if (d.estado !== 'success') { f.estado = 'error'; f.error = d.mensaje || 'No se pudo emitir.'; }
                            else { f.estado = 'ok'; f.numero = d.numero; f.pdf = d.pdf; f.idCpe = d.id; f.sel = false; }
                        }
                    } catch (e) {
                        f.estado = 'error'; f.error = 'Sin conexión.';
                    }
                    f.estado === 'ok' ? this.resultado.ok++ : this.resultado.error++;
                    this.progreso.hecho++;
                }
                if (this.enviarSunat) await this.enviarFacturas();
                this.emitiendo = false;
                this.hechos = true;
            },

            async enviarFacturas() {
                for (const f of this.filas.filter(x => x.estado === 'ok' && x.tdocod === '01' && !x.sunat)) {
                    f.sunat = 'Enviando a SUNAT…';
                    try {
                        const d = await (await fetch(CFG.rutas.sunat + '/' + f.idCpe, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CFG.csrf } })).json();
                        f.sunat = d.success ? 'SUNAT: ' + (d.estado || 'ACEPTADO') : 'SUNAT: ' + (d.mensaje || 'no enviado');
                    } catch (e) { f.sunat = 'SUNAT: sin conexión'; }
                }
            },
        };
    }
</script>
@endpush
