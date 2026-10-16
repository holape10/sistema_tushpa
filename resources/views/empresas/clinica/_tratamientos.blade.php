{{--
    Registro de tratamientos de un paciente: cada tratamiento con su costo y sus sesiones (fecha y hora, lo realizado o las
    indicaciones, adelanto, saldo, cancelación y la firma del paciente en la tablet). Lo usan la historia y el Registro de tratamientos.
    El componente padre llama a cargar(his_cli_id).
--}}
@php
    $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500';
    $especialidadesTrat = $especialidadesTrat ?? collect();
@endphp
@once
<script>
    window.registroTratamientos = function () {
        const CSRF = '{{ csrf_token() }}';
        const soles = n => 'S/ ' + (Number(n) || 0).toFixed(2);
        return {
            hisId: null, lista: [], cargando: false, modalTrat: null, modalSesion: null, modalFirma: null, guardando: false,
            soles,
            fecha(f) { return f ? new Date(String(f).replace(' ', 'T')).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; },
            async cargar(hisId) {
                this.hisId = hisId;
                this.cargando = true;
                try {
                    const r = await fetch('{{ url('clinica/historia') }}/' + hisId + '/tratamientos', { headers: { Accept: 'application/json' } });
                    const d = await r.json().catch(() => null);
                    this.lista = Array.isArray(d) ? d : [];
                    if (!r.ok) tushpaAviso(r.status === 401 ? 'La sesión expiró: vuelve a ingresar.' : 'No se pudo cargar el registro de tratamientos.', 'error');
                } catch (e) {
                    tushpaAviso('Sin conexión: no se pudo cargar el registro de tratamientos.', 'error');
                } finally { this.cargando = false; }
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
            // ---- Tratamiento ----
            nuevoTratamiento() {
                const mes = new Date(); mes.setMonth(mes.getMonth() + 1);
                this.modalTrat = { tra_id: null, descripcion: '', esp_id: '', costo: '', detalle: '', adelanto: '', conSesion: true,
                    modalidad: 'UNICO', cuota_inicial: '', cuotas: 12, cuota: '', primer_vencimiento: mes.toISOString().slice(0, 10) };
                this.$nextTick(() => this.prepararFirma('firma_trat'));
            },
            editarTratamiento(t) {
                this.modalTrat = { tra_id: t.tra_id, descripcion: t.descripcion, esp_id: t.esp_id ? String(t.esp_id) : '', costo: t.costo, pagado: t.pagado,
                    modalidad: t.modalidad || 'UNICO', cuota_inicial: t.cuota_inicial, cuotas: t.cuotas, cuota: t.cuota, primer_vencimiento: t.primer_vencimiento };
            },
            // Plan mensual: el costo total es la inicial más las cuotas
            totalPlan(m) { return m.modalidad === 'MENSUAL' ? (Number(m.cuota_inicial) || 0) + (Number(m.cuotas) || 0) * (Number(m.cuota) || 0) : (Number(m.costo) || 0); },
            mesCorto(f) { return f ? new Date(f + 'T00:00').toLocaleDateString('es-PE', { day: '2-digit', month: 'short' }) : ''; },
            async guardarTratamiento() {
                const m = this.modalTrat;
                const datos = { descripcion: m.descripcion, esp_id: m.esp_id || null, costo: m.costo || 0, modalidad: m.modalidad,
                    ...(m.modalidad === 'MENSUAL' ? { cuota_inicial: m.cuota_inicial || 0, cuotas: m.cuotas, cuota: m.cuota, primer_vencimiento: m.primer_vencimiento } : {}) };
                const r = m.tra_id
                    ? await this.post('{{ url('clinica/tratamientos') }}/' + m.tra_id, datos)
                    : await this.post('{{ url('clinica/historia') }}/' + this.hisId + '/tratamientos',
                        { ...datos, detalle: m.detalle, adelanto: m.adelanto || 0, firma: this.firmaDe('firma_trat') });
                if (r.ok) { this.modalTrat = null; this.cargar(this.hisId); }
            },
            // ---- Sesión ----
            nuevaSesion(t) {
                this.modalSesion = { tra: t, detalle: '', adelanto: '' };
                this.$nextTick(() => this.prepararFirma('firma_sesion'));
            },
            async guardarSesion() {
                const m = this.modalSesion;
                const r = await this.post('{{ url('clinica/tratamientos') }}/' + m.tra.tra_id + '/sesion',
                    { detalle: m.detalle, adelanto: m.adelanto || 0, firma: this.firmaDe('firma_sesion') });
                if (r.ok) { this.modalSesion = null; this.cargar(this.hisId); }
            },
            // ---- Firma en la tablet (dedo, lápiz o mouse) ----
            firmar(reg) { this.modalFirma = reg; this.$nextTick(() => this.prepararFirma('firma_sola')); },
            async guardarFirma() {
                const firma = this.firmaDe('firma_sola');
                if (!firma) { tushpaAviso('El paciente debe firmar en el recuadro.', 'error'); return; }
                const r = await this.post('{{ url('clinica/tratamientos/sesion') }}/' + this.modalFirma.reg_id + '/firma', { firma });
                if (r.ok) { this.modalFirma = null; this.cargar(this.hisId); }
            },
            prepararFirma(ref, intento = 0) {
                const c = this.$refs[ref];
                // El modal recién se está mostrando: se espera a que el recuadro tenga tamaño
                if (!c || !c.offsetWidth) { if (intento < 40) setTimeout(() => this.prepararFirma(ref, intento + 1), 50); return; }
                const ratio = window.devicePixelRatio || 1;
                c.width = c.offsetWidth * ratio; c.height = c.offsetHeight * ratio;
                const ctx = c.getContext('2d');
                ctx.scale(ratio, ratio); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
                c._firmado = false;
                let dibujando = false;
                const punto = e => { const b = c.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; };
                c.onpointerdown = e => { dibujando = true; c.setPointerCapture(e.pointerId); const [x, y] = punto(e); ctx.beginPath(); ctx.moveTo(x, y); };
                c.onpointermove = e => { if (!dibujando) return; const [x, y] = punto(e); ctx.lineTo(x, y); ctx.stroke(); c._firmado = true; };
                c.onpointerup = c.onpointercancel = () => { dibujando = false; };
            },
            borrarFirma(ref) { const c = this.$refs[ref]; if (c) { c.getContext('2d').clearRect(0, 0, c.width, c.height); c._firmado = false; } },
            firmaDe(ref) { const c = this.$refs[ref]; return c && c._firmado ? c.toDataURL('image/png') : null; },
        };
    };
</script>
@endonce

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <h2 class="font-bold text-gray-700 flex-1">💳 Registro de tratamientos</h2>
        <a :href="'{{ url('clinica/historia') }}/' + hisId + '/tratamientos/imprimir'" target="_blank" x-show="lista.length"
           class="px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-xs font-semibold"><i class="fas fa-print"></i> Imprimir</a>
        <button type="button" @click="nuevoTratamiento()" class="px-3 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-bold">+ Nuevo tratamiento</button>
    </div>

    <p x-show="cargando" class="text-sm text-gray-400">Cargando…</p>
    <div x-show="!cargando && !lista.length" class="bg-white rounded-2xl shadow-sm p-6 text-center text-sm text-gray-400">
        Sin tratamientos. Con <b>+ Nuevo tratamiento</b> registras el tratamiento, su costo y el primer adelanto.
    </div>

    <template x-for="t in lista" :key="t.tra_id">
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-wrap items-center gap-2 border-b" :class="t.estado === 'CANCELADO' ? 'bg-emerald-50' : 'bg-amber-50'">
                <div class="flex-1 min-w-[200px]">
                    <p class="font-bold text-gray-800" x-text="t.descripcion"></p>
                    <p class="text-xs text-gray-500"><span x-text="t.esp_des || 'Sin especialidad'"></span> · desde <span x-text="fecha(t.creado).split(',')[0]"></span>
                        <span x-show="t.modalidad === 'MENSUAL'" class="ml-1 px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-bold"
                              x-text="'MENSUAL · ' + (Number(t.cuota_inicial) > 0 ? 'inicial ' + soles(t.cuota_inicial) + ' + ' : '') + t.cuotas + ' x ' + soles(t.cuota)"></span></p>
                    <p x-show="t.estado !== 'CANCELADO' && Number(t.vencido) > 0" class="mt-1 text-xs font-extrabold text-rose-600" x-text="'⚠ Debe ' + soles(t.vencido) + ' de cuotas vencidas'"></p>
                    <p x-show="t.estado !== 'CANCELADO' && t.proxima && !(Number(t.vencido) > 0)" class="mt-1 text-xs font-semibold text-indigo-700"
                       x-text="t.proxima ? 'Próxima: ' + t.proxima.etiqueta + ' · ' + soles(t.proxima.falta) + ' (vence ' + mesCorto(t.proxima.vence) + ')' : ''"></p>
                </div>
                <div class="text-right text-sm">
                    <p>Costo <b x-text="soles(t.costo)"></b> · Pagado <b class="text-emerald-700" x-text="soles(t.pagado)"></b></p>
                    <p x-show="t.estado !== 'CANCELADO'" class="font-extrabold text-rose-600" x-text="'Saldo ' + soles(t.saldo)"></p>
                    <p x-show="t.estado === 'CANCELADO'" class="font-extrabold text-emerald-700" x-text="'✔ CANCELADO ' + fecha(t.cancelado).split(',')[0]"></p>
                </div>
                <div class="flex gap-1">
                    <button type="button" @click="editarTratamiento(t)" class="px-2 py-1 rounded-lg bg-white border text-xs" title="Corregir nombre o costo">✏️</button>
                    <button type="button" @click="nuevaSesion(t)" class="px-3 py-1 rounded-lg bg-teal-600 text-white text-xs font-bold">+ Sesión / pago</button>
                </div>
            </div>
            {{-- Calendario de cuotas (plan mensual) --}}
            <div x-show="t.modalidad === 'MENSUAL'" class="px-4 py-2 border-b flex flex-wrap gap-1.5">
                <template x-for="c in (t.cuotas_plan || [])" :key="c.numero">
                    <span class="px-2 py-1 rounded-lg text-[11px] font-semibold border"
                          :class="{ 'bg-emerald-50 border-emerald-200 text-emerald-800': c.estado === 'PAGADA', 'bg-rose-50 border-rose-300 text-rose-700': c.estado === 'VENCIDA',
                                    'bg-amber-50 border-amber-200 text-amber-800': c.estado === 'PARCIAL', 'bg-white border-gray-200 text-gray-500': c.estado === 'PENDIENTE' }"
                          :title="c.etiqueta + ' · vence ' + mesCorto(c.vence) + ' · pagado ' + soles(c.pagado) + ' de ' + soles(c.monto)"
                          x-text="(c.estado === 'PAGADA' ? '✔ ' : c.estado === 'VENCIDA' ? '⚠ ' : '') + c.etiqueta.replace('Cuota ', 'C') + (c.estado === 'PARCIAL' ? ' · falta ' + soles(c.monto - c.pagado) : '')"></span>
                </template>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase text-gray-500 bg-gray-50">
                        <tr><th class="px-3 py-1.5 text-left">Fecha y hora</th><th class="px-3 py-1.5 text-left">Tratamiento realizado / indicaciones</th>
                            <th class="px-3 py-1.5 text-right">Adelanto</th><th class="px-3 py-1.5 text-right">Saldo</th><th class="px-3 py-1.5 text-center">Cancelación</th>
                            <th class="px-3 py-1.5 text-center">Firma</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        <template x-for="r in t.registros" :key="r.reg_id">
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-xs" x-text="fecha(r.fecha_hora)"></td>
                                <td class="px-3 py-2"><span class="whitespace-pre-line" x-text="r.detalle || '—'"></span>
                                    <span class="block text-[11px] text-gray-400" x-text="r.usuario || ''"></span></td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <span x-text="Number(r.adelanto) > 0 ? soles(r.adelanto) : '—'"></span>
                                    <span x-show="r.cobrado === true" class="block text-[10px] font-bold text-emerald-600">cobrado en caja</span>
                                    <span x-show="r.cobrado === false" class="block text-[10px] font-bold text-amber-600">por cobrar en caja</span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap font-semibold" x-text="soles(r.saldo)"></td>
                                <td class="px-3 py-2 text-center"><span x-show="Number(r.adelanto) > 0 && Number(r.saldo) <= 0" class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-extrabold">CANCELADO</span></td>
                                <td class="px-3 py-2 text-center">
                                    <img x-show="r.firma_url" :src="r.firma_url" alt="Firma" class="h-10 mx-auto">
                                    <button type="button" x-show="!r.firma_url" @click="firmar(r)" class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold">✍️ Firmar</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!t.registros.length"><td colspan="6" class="px-3 py-3 text-center text-xs text-gray-400">Sin sesiones todavía.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    {{-- Nuevo tratamiento / corregir --}}
    <div x-show="modalTrat" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-start sm:items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-3" @click.outside="modalTrat = null">
            <h3 class="font-bold text-gray-800" x-text="modalTrat?.tra_id ? 'Corregir tratamiento' : 'Nuevo tratamiento'"></h3>
            <template x-if="modalTrat">
                <div class="space-y-3">
                    <label class="block text-xs font-semibold text-gray-500">Tratamiento
                        <input x-model="modalTrat.descripcion" maxlength="200" placeholder="Ej. ENDODONCIA PIEZA 1.4 + CORONA" class="{{ $in }} mt-1 uppercase"></label>
                    <label class="block text-xs font-semibold text-gray-500">Especialidad
                        <select x-model="modalTrat.esp_id" class="{{ $in }} mt-1"><option value="">—</option>
                            @foreach ($especialidadesTrat as $e)<option value="{{ $e->esp_id }}">{{ $e->esp_des }}</option>@endforeach</select></label>
                    {{-- Cómo paga: un costo con adelantos libres, o un plan mensual (ortodoncia) --}}
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="modalTrat.modalidad = 'UNICO'" class="rounded-xl border-2 px-3 py-2 text-left"
                                :class="modalTrat.modalidad === 'UNICO' ? 'border-teal-600 bg-teal-50' : 'border-gray-200'">
                            <span class="block text-sm font-bold">💵 Costo total</span><span class="block text-[11px] text-gray-500">Paga con adelantos cuando puede</span></button>
                        <button type="button" @click="modalTrat.modalidad = 'MENSUAL'" class="rounded-xl border-2 px-3 py-2 text-left"
                                :class="modalTrat.modalidad === 'MENSUAL' ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200'">
                            <span class="block text-sm font-bold">📅 Mensual</span><span class="block text-[11px] text-gray-500">Ortodoncia: inicial + cuotas por mes</span></button>
                    </div>
                    <label x-show="modalTrat.modalidad !== 'MENSUAL'" class="block text-xs font-semibold text-gray-500">Costo total S/
                        <input type="number" step="0.01" min="0" x-model="modalTrat.costo" class="{{ $in }} mt-1 text-right font-bold"></label>
                    <div x-show="modalTrat.modalidad === 'MENSUAL'" class="grid grid-cols-2 gap-2 rounded-xl bg-indigo-50/60 p-3">
                        <label class="block text-xs font-semibold text-gray-500">Cuota inicial S/
                            <input type="number" step="0.01" min="0" x-model="modalTrat.cuota_inicial" placeholder="0.00" class="{{ $in }} mt-1 text-right"></label>
                        <label class="block text-xs font-semibold text-gray-500">Cuota mensual S/
                            <input type="number" step="0.01" min="0" x-model="modalTrat.cuota" class="{{ $in }} mt-1 text-right font-bold"></label>
                        <label class="block text-xs font-semibold text-gray-500">N° de meses
                            <input type="number" step="1" min="1" max="120" x-model="modalTrat.cuotas" class="{{ $in }} mt-1 text-right"></label>
                        <label class="block text-xs font-semibold text-gray-500">Primera cuota vence
                            <input type="date" x-model="modalTrat.primer_vencimiento" class="{{ $in }} mt-1"></label>
                        <p class="col-span-2 text-xs text-indigo-800">Total del tratamiento: <b x-text="soles(totalPlan(modalTrat))"></b>.
                            Si se alarga, aquí mismo se suben los meses.</p>
                    </div>
                    <p x-show="modalTrat.tra_id" class="text-xs text-gray-500">Ya pagado: <b x-text="soles(modalTrat.pagado)"></b> (el costo no puede quedar por debajo).</p>
                    <template x-if="!modalTrat.tra_id">
                        <div class="space-y-3 border-t pt-3">
                            <p class="text-xs font-bold text-gray-500 uppercase">Primera sesión (opcional)</p>
                            <label class="block text-xs font-semibold text-gray-500">Tratamiento realizado o indicaciones
                                <textarea x-model="modalTrat.detalle" rows="2" class="{{ $in }} mt-1"></textarea></label>
                            <label class="block text-xs font-semibold text-gray-500">Adelanto que paga hoy S/
                                <input type="number" step="0.01" min="0" x-model="modalTrat.adelanto" class="{{ $in }} mt-1 text-right font-bold"></label>
                            <button type="button" x-show="modalTrat.modalidad === 'MENSUAL' && Number(modalTrat.cuota_inicial) > 0" @click="modalTrat.adelanto = modalTrat.cuota_inicial"
                                    class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold" x-text="'Paga la inicial (' + soles(modalTrat.cuota_inicial) + ')'"></button>
                            <p class="text-xs text-gray-500" x-show="totalPlan(modalTrat) > 0">Saldo después del adelanto: <b x-text="soles(totalPlan(modalTrat) - (Number(modalTrat.adelanto) || 0))"></b></p>
                            <div>
                                <div class="flex justify-between items-center"><span class="text-xs font-semibold text-gray-500">Firma del paciente</span>
                                    <button type="button" @click="borrarFirma('firma_trat')" class="text-xs text-gray-500 underline">Borrar</button></div>
                                <canvas x-ref="firma_trat" class="mt-1 w-full h-32 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 touch-none"></canvas>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modalTrat = null" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                <button type="button" @click="guardarTratamiento()" :disabled="guardando" class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-bold disabled:opacity-40">Guardar</button>
            </div>
        </div>
    </div>

    {{-- Nueva sesión / pago --}}
    <div x-show="modalSesion" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-start sm:items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-3" @click.outside="modalSesion = null">
            <template x-if="modalSesion">
                <div class="space-y-3">
                    <div>
                        <h3 class="font-bold text-gray-800">Nueva sesión</h3>
                        <p class="text-sm text-gray-500"><span x-text="modalSesion.tra.descripcion"></span> · saldo <b class="text-rose-600" x-text="soles(modalSesion.tra.saldo)"></b></p>
                    </div>
                    <label class="block text-xs font-semibold text-gray-500">Tratamiento realizado o indicaciones
                        <textarea x-model="modalSesion.detalle" rows="3" class="{{ $in }} mt-1"></textarea></label>
                    <div class="flex items-end gap-2">
                        <label class="block text-xs font-semibold text-gray-500 flex-1">Adelanto / pago S/
                            <input type="number" step="0.01" min="0" :max="modalSesion.tra.saldo" x-model="modalSesion.adelanto" class="{{ $in }} mt-1 text-right font-bold"></label>
                        <button type="button" x-show="Number(modalSesion.tra.saldo) > 0" @click="modalSesion.adelanto = modalSesion.tra.saldo"
                                class="px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">Cancelar todo el saldo</button>
                    </div>
                    <div x-show="modalSesion.tra.modalidad === 'MENSUAL' && modalSesion.tra.estado !== 'CANCELADO'" class="flex flex-wrap gap-2">
                        <button type="button" x-show="modalSesion.tra.proxima" @click="modalSesion.adelanto = modalSesion.tra.proxima.falta; modalSesion.detalle ||= 'Pago ' + modalSesion.tra.proxima.etiqueta"
                                class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold"
                                x-text="modalSesion.tra.proxima ? 'Pagar ' + modalSesion.tra.proxima.etiqueta + ' (' + soles(modalSesion.tra.proxima.falta) + ')' : ''"></button>
                        <button type="button" x-show="Number(modalSesion.tra.vencido) > 0" @click="modalSesion.adelanto = modalSesion.tra.vencido; modalSesion.detalle ||= 'Pago de cuotas vencidas'"
                                class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-bold" x-text="'Pagar lo vencido (' + soles(modalSesion.tra.vencido) + ')'"></button>
                    </div>
                    <p class="text-xs text-gray-500">Saldo después: <b x-text="soles(Math.max(0, (Number(modalSesion.tra.saldo) || 0) - (Number(modalSesion.adelanto) || 0)))"></b>.
                        El pago lo cobra recepción en caja (le aparece en "Por cobrar").</p>
                    <div>
                        <div class="flex justify-between items-center"><span class="text-xs font-semibold text-gray-500">Firma del paciente</span>
                            <button type="button" @click="borrarFirma('firma_sesion')" class="text-xs text-gray-500 underline">Borrar</button></div>
                        <canvas x-ref="firma_sesion" class="mt-1 w-full h-32 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 touch-none"></canvas>
                    </div>
                </div>
            </template>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modalSesion = null" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                <button type="button" @click="guardarSesion()" :disabled="guardando" class="px-4 py-2 rounded-xl bg-teal-600 text-white text-sm font-bold disabled:opacity-40">Guardar sesión</button>
            </div>
        </div>
    </div>

    {{-- Firmar una sesión que quedó sin firma --}}
    <div x-show="modalFirma" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-3" @click.outside="modalFirma = null">
            <h3 class="font-bold text-gray-800">✍️ Firma del paciente</h3>
            <p class="text-sm text-gray-500" x-text="modalFirma ? fecha(modalFirma.fecha_hora) + ' · ' + (modalFirma.detalle || '') : ''"></p>
            <canvas x-ref="firma_sola" class="w-full h-40 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 touch-none"></canvas>
            <div class="flex justify-between gap-2">
                <button type="button" @click="borrarFirma('firma_sola')" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Borrar</button>
                <div class="flex gap-2">
                    <button type="button" @click="modalFirma = null" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="guardarFirma()" :disabled="guardando" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-40">Guardar firma</button>
                </div>
            </div>
        </div>
    </div>
</div>
