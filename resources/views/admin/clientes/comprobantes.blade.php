@extends('admin.layout')
@section('title', 'Comprobantes · '.$cliente->razon_social)

@section('content')
    @php
        $color = ['ACEPTADO' => 'bg-emerald-100 text-emerald-700', 'OBSERVADO' => 'bg-amber-100 text-amber-800', 'RECHAZADO' => 'bg-rose-100 text-rose-700',
            'ERROR' => 'bg-orange-100 text-orange-700', 'EN RESUMEN' => 'bg-sky-100 text-sky-700', 'GENERADO' => 'bg-slate-100 text-slate-600'];
        $tipos = ['01' => 'Factura', '03' => 'Boleta', '07' => 'N. crédito', '08' => 'N. débito'];
    @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.clientes.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Clientes</a>
        <div>
            <h1 class="text-xl font-bold">Comprobantes SUNAT</h1>
            <p class="text-sm text-slate-500">{{ $cliente->nombre_comercial ?: $cliente->razon_social }} · RUC {{ $cliente->ruc }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <div class="flex gap-1 p-1 bg-white rounded-xl shadow-sm text-sm">
            @foreach (\App\Http\Controllers\Admin\ComprobanteController::FILTROS as $valor => $texto)
                <a href="{{ route('admin.clientes.comprobantes', [$cliente->id, 'filtro' => $valor]) }}"
                   class="px-3 py-1.5 rounded-lg font-semibold {{ $filtro === $valor ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $texto }}@isset($conteo[$valor]) <span class="opacity-70">({{ $conteo[$valor] }})</span>@endisset</a>
            @endforeach
        </div>
        <form method="GET" class="ml-auto flex gap-2">
            <input type="hidden" name="filtro" value="{{ $filtro }}">
            <select name="anio" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-sm">
                <option value="">Todos los años</option>
                @foreach ($anios as $a)<option value="{{ $a }}" @selected($anio === (int) $a)>{{ $a }}</option>@endforeach
            </select>
            <input name="q" value="{{ $q }}" placeholder="B001-71, cliente o DNI/RUC" class="rounded-xl border-slate-300 text-sm w-56">
        </form>
    </div>

    {{-- Las acciones van de uno en uno (3 a la vez al consultar o enviar) y cada fila muestra cómo quedó, como en el sistema de la empresa --}}
    <div x-data="comprobantesPanel()">
        <div class="bg-white rounded-2xl shadow-sm p-3 mb-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold" x-text="sel.length + ' marcado(s)'"></span>
            @foreach (['consultar' => ['🔎 Consultar en SUNAT', 'bg-sky-50 text-sky-700'], 'enviar' => ['📤 Enviar a SUNAT', 'bg-indigo-50 text-indigo-700'],
                'resumen' => ['🧾 Enviar por resumen diario', 'bg-violet-50 text-violet-700'],
                'fecha' => ['📅 Corregir fecha (vencidos)', 'bg-amber-50 text-amber-800'],
                'aceptado' => ['✔ Marcar aceptado', 'bg-emerald-50 text-emerald-700'], 'descartar' => ['🚫 Descartar (rechazado)', 'bg-slate-100 text-slate-700'],
                'anular' => ['✖ Anular en el sistema', 'bg-rose-50 text-rose-700']] as $valor => [$texto, $clase])
                <button type="button" @click="ejecutar('{{ $valor }}')" :disabled="trabajando" @if ($valor === 'fecha') x-show="hayVencidos" @endif
                        class="px-3 py-1.5 rounded-lg font-semibold disabled:opacity-50 {{ $clase }}">{{ $texto }}</button>
            @endforeach
            <input x-model="motivo" maxlength="70" placeholder="Motivo (para descartar o anular)" class="ml-auto rounded-lg border-slate-300 text-sm w-64">
            <p x-show="progreso" x-text="progreso" class="w-full text-sm font-semibold text-indigo-700"></p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr><th class="px-3 py-2"><input type="checkbox" :checked="sel.length > 0 && sel.length === disponibles()" :disabled="trabajando || disponibles() === 0"
                        @change="sel = $event.target.checked ? [...$root.querySelectorAll('[data-id]:not(:disabled)')].map(x => x.value) : []" class="rounded"></th>
                        <th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-left">Cliente</th>
                        <th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-left">SUNAT</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lista as $c)
                        @php $vencido = in_array($c->est_sunat ?: 'PENDIENTE', \App\Support\Sunat\SunatService::ESTADOS_REENVIABLES, true) && \App\Support\Sunat\CambioFecha::vencido($c); @endphp
                        <tr data-fila="{{ $c->IdCpe_cabecera }}" data-vencido="{{ $vencido ? 1 : 0 }}">
                            <td class="px-3 py-2"><input type="checkbox" value="{{ $c->IdCpe_cabecera }}" data-id x-model="sel" class="rounded"></td>
                            <td class="px-3 py-2 whitespace-nowrap"><span class="js-fecha">{{ \Carbon\Carbon::parse($c->ccafem)->format('d/m/Y') }}</span>
                                <span class="js-vencido block text-[10px] text-rose-600 font-semibold {{ $vencido ? '' : 'hidden' }}">fuera de plazo</span></td>
                            <td class="px-3 py-2 whitespace-nowrap"><span class="text-xs text-slate-400">{{ $tipos[$c->tdocod] ?? $c->tdocod }}</span><br><b>{{ $c->serdoc }}-{{ $c->numdoc }}</b>
                                <a href="{{ route('admin.clientes.comprobantes.pdf', [$cliente->id, $c->IdCpe_cabecera]) }}" target="_blank" title="Ver o descargar el PDF A4"
                                   class="ml-1 px-1.5 py-0.5 rounded-md bg-rose-50 text-rose-700 text-[11px] font-bold hover:bg-rose-100">PDF</a></td>
                            <td class="px-3 py-2">{{ $c->ccanom }}<span class="block text-xs text-slate-400">{{ $c->ccandi }}</span></td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($c->ccaitv, 2) }}</td>
                            <td class="px-3 py-2 max-w-sm"><span class="js-estado px-2 py-0.5 rounded-lg text-xs font-bold {{ $color[$c->est_sunat] ?? 'bg-slate-100 text-slate-600' }}">{{ $c->est_sunat ?: 'PENDIENTE' }}</span>
                                <span class="js-msg block text-[11px] text-slate-500 mt-0.5 line-clamp-2" title="{{ $c->ccadessun }}">{{ $c->ccadessun }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-10 text-center text-slate-400">No hay comprobantes {{ $filtro === 'todos' ? '' : mb_strtolower(\App\Http\Controllers\Admin\ComprobanteController::FILTROS[$filtro]) }} 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <script>
        function comprobantesPanel() {
            const URL_BASE = @js(route('admin.clientes.comprobantes.accion', [$cliente->id, 0])).replace(/0$/, '');
            const COLOR = @js($color);
            const NOMBRE = { consultar: 'Consultando', enviar: 'Enviando', fecha: 'Corrigiendo fecha', aceptado: 'Marcando', descartar: 'Descartando', anular: 'Anulando' };
            return {
                sel: [], motivo: '', trabajando: false, progreso: '',
                get hayVencidos() { return !!document.querySelector('tr[data-vencido="1"]'); },
                disponibles() { return document.querySelectorAll('[data-id]:not(:disabled)').length; },
                async uno(accion, id) {
                    const fila = document.querySelector(`tr[data-fila="${id}"]`);
                    fila.querySelector('.js-estado').textContent = '⏳ ' + NOMBRE[accion] + '…';
                    let r;
                    try {
                        const res = await fetch(URL_BASE + id, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                            body: JSON.stringify({ accion, motivo: this.motivo }) });
                        r = await res.json();
                    } catch (e) { r = { ok: false, mensaje: 'Sin conexión con el servidor.' }; }
                    return this.pintar(id, r);
                },
                /** Cómo quedó un comprobante después de una acción */
                pintar(id, r) {
                    const fila = document.querySelector(`tr[data-fila="${id}"]`);
                    if (!fila) return false;
                    const etiqueta = fila.querySelector('.js-estado');
                    if (r.estado) {
                        etiqueta.className = 'js-estado px-2 py-0.5 rounded-lg text-xs font-bold ' + (COLOR[r.estado] || 'bg-slate-100 text-slate-600');
                        etiqueta.textContent = r.anulado ? 'ANULADO EN EL SISTEMA' : r.estado;
                        fila.querySelector('.js-fecha').textContent = r.fecha;
                        fila.querySelector('.js-vencido').classList.toggle('hidden', !r.vencido);
                        fila.dataset.vencido = r.vencido ? '1' : '0';
                    } else {
                        etiqueta.textContent = 'ERROR';
                    }
                    const msg = fila.querySelector('.js-msg');
                    msg.textContent = r.mensaje || ''; msg.title = r.mensaje || '';
                    msg.className = 'js-msg block text-[11px] mt-0.5 line-clamp-2 ' + (r.ok ? 'text-slate-500' : 'text-rose-600 font-semibold');
                    if (r.anulado) fila.classList.add('opacity-50');
                    // Ya resuelto (aceptado, anulado o descartado): su casillero se apaga para no volver a marcarlo
                    if (r.ok && (r.anulado || ['ACEPTADO', 'DESCARTADO'].includes(r.estado))) {
                        const chk = fila.querySelector('[data-id]');
                        chk.checked = false;
                        chk.disabled = true;
                        this.sel = this.sel.filter(x => x !== String(id));
                    }
                    return r.ok;
                },
                /** Boletas por resumen diario: todo en una sola llamada (se agrupan por fecha y van juntas a SUNAT) */
                async resumenDiario() {
                    const ids = [...this.sel];
                    if (!ids.length) { alert('Marca al menos una boleta.'); return; }
                    if (!confirm(`¿Enviar por resumen diario? Van las boletas (y sus notas) pendientes de los días marcados, junto a las demás pendientes de esos días.`)) return;
                    this.trabajando = true;
                    this.progreso = 'Enviando el resumen diario a SUNAT…';
                    ids.forEach(id => { const e = document.querySelector(`tr[data-fila="${id}"] .js-estado`); if (e) e.textContent = '⏳ En resumen…'; });
                    let lista = [];
                    try {
                        const res = await fetch(@js(route('admin.clientes.comprobantes.resumen', $cliente->id)), { method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ ids }) });
                        lista = await res.json();
                        if (!Array.isArray(lista)) throw new Error();
                    } catch (e) {
                        this.progreso = 'No se pudo enviar el resumen. Intenta de nuevo.';
                        this.trabajando = false;
                        return;
                    }
                    let ok = 0;
                    lista.forEach(r => { if (this.pintar(r.id, r)) ok++; });
                    this.sel = [];
                    this.progreso = `Listo: ${ok} en el resumen o aceptado(s), ${lista.length - ok} con problema (mira el mensaje de cada fila). Si quedan "EN RESUMEN", usa Consultar en unos minutos.`;
                    if (ok > 0 && @js($filtro) !== 'todos') {
                        this.progreso += ' Actualizando la lista…';
                        setTimeout(() => location.reload(), 2500);
                        return;
                    }
                    this.trabajando = false;
                },
                async ejecutar(accion) {
                    if (accion === 'resumen') return this.resumenDiario();
                    let ids = [...this.sel];
                    if (accion === 'fecha') ids = ids.filter(id => document.querySelector(`tr[data-fila="${id}"]`)?.dataset.vencido === '1');
                    if (!ids.length) { alert(accion === 'fecha' ? 'Marca comprobantes que estén fuera de plazo.' : 'Marca al menos un comprobante.'); return; }
                    const avisos = {
                        anular: '¿Anular en el sistema? El stock vuelve al almacén y se anulan sus cobros.',
                        descartar: '¿Descartar? Solo deja de salir en pendientes; no mueve stock.',
                        fecha: `¿Corregir la fecha de ${ids.length} comprobante(s) fuera de plazo? Pasan al último día que SUNAT todavía acepta. Envíalos hoy mismo.`,
                        aceptado: `¿Marcar ${ids.length} comprobante(s) como aceptados? Hazlo solo si ya los viste aceptados en la web de SUNAT.`,
                    };
                    if (avisos[accion] && !confirm(avisos[accion])) return;
                    this.trabajando = true;
                    // Consultar y enviar: de 3 en 3 (SUNAT tarda en responder cada uno); lo demás, uno tras otro
                    const juntos = ['consultar', 'enviar'].includes(accion) ? 3 : 1;
                    let ok = 0, hechos = 0, siguiente = 0;
                    this.progreso = `${NOMBRE[accion]} 0 de ${ids.length}…`;
                    const trabajador = async () => {
                        while (siguiente < ids.length) {
                            const id = ids[siguiente++];
                            if (await this.uno(accion, id)) ok++;
                            this.progreso = `${NOMBRE[accion]} ${++hechos} de ${ids.length}…`;
                        }
                    };
                    await Promise.all(Array.from({ length: Math.min(juntos, ids.length) }, trabajador));
                    this.progreso = `Listo: ${ok} bien, ${ids.length - ok} con problema (mira el mensaje de cada fila).`;
                    this.sel = [];
                    // Algo cambió: la lista y los contadores se actualizan solos (los mensajes de cada comprobante quedan guardados)
                    if (ok > 0 && @js($filtro) !== 'todos') {
                        this.progreso += ' Actualizando la lista…';
                        setTimeout(() => location.reload(), 1500);
                        return;
                    }
                    this.trabajando = false;
                },
            };
        }
    </script>
    <div class="mt-4">{{ $lista->links() }}</div>

    <div class="mt-4 grid md:grid-cols-2 gap-3 text-xs text-slate-600">
        <div class="bg-white rounded-2xl shadow-sm p-4 space-y-1">
            <p class="font-bold text-slate-800 text-sm">¿Qué hago con cada uno?</p>
            <p><b>🔎 Consultar:</b> pregunta a SUNAT si lo tiene. Si SUNAT dice que está aceptado, aquí queda <b>ACEPTADO</b> solo (sirve para facturas y boletas).</p>
            <p><b>📤 Enviar:</b> lo manda ahora a SUNAT (dentro del plazo: facturas 3 días, boletas 7, contados desde el día siguiente a la emisión).</p>
            <p><b>📅 Corregir fecha:</b> sale si hay vencidos. Los pasa al último día que SUNAT todavía acepta; luego envíalos hoy mismo.</p>
            <p><b>✔ Marcar aceptado:</b> cuando ya lo viste aceptado en la web de SUNAT pero aquí quedó pendiente.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4 space-y-1">
            <p><b>🚫 Descartar:</b> para RECHAZADOS que ya no se corregirán (por ejemplo, de años anteriores). Sale de pendientes y no mueve stock.</p>
            <p><b>✖ Anular en el sistema:</b> para lo que nunca llegó a SUNAT y la venta no se hizo. Devuelve el stock y anula sus cobros.</p>
            <p class="text-slate-400">Los comprobantes no se borran: su número ya se usó y debe quedar el rastro. Lo aceptado por SUNAT se anula con nota de crédito o baja desde el sistema de la empresa.</p>
        </div>
    </div>
@endsection
