@extends('layouts.app')
@section('title', $tx['titulo'])

@section('content')
<div class="max-w-7xl mx-auto space-y-4" x-data="cuentasPagina()">

    {{-- Encabezado y pestañas --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('cuentas.index', 'cobrar') }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold {{ $tipo === 'cobrar' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Por cobrar</a>
        <a href="{{ route('cuentas.index', 'pagar') }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold {{ $tipo === 'pagar' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Por pagar</a>
        <a href="{{ route('cuentas.reporte', $tipo) }}" class="ml-auto px-4 h-10 inline-flex items-center rounded-xl bg-white text-sm font-semibold text-slate-600 hover:bg-slate-50">📊 Reportes</a>
    </div>

    {{-- Indicadores --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">Saldo {{ $tipo === 'cobrar' ? 'por cobrar' : 'por pagar' }}</p>
            <p class="text-2xl font-extrabold">{{ number_format($kpi['pendiente'], 2) }}</p>
            <p class="text-xs text-slate-400">{{ $kpi['documentos'] }} documentos pendientes</p>
        </div>
        <a href="?estado=vencidas" class="bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-rose-200">
            <p class="text-xs font-semibold text-slate-500">Vencido</p>
            <p class="text-2xl font-extrabold {{ $kpi['vencido'] > 0 ? 'text-rose-600' : 'text-slate-300' }}">{{ number_format($kpi['vencido'], 2) }}</p>
            <p class="text-xs text-slate-400">Ver solo vencidas →</p>
        </a>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">Vence en 7 días</p>
            <p class="text-2xl font-extrabold {{ $kpi['por_vencer'] > 0 ? 'text-amber-600' : 'text-slate-300' }}">{{ number_format($kpi['por_vencer'], 2) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">{{ $tx['pasado'] }} este mes</p>
            <p class="text-2xl font-extrabold text-emerald-600">{{ number_format($kpi['mes'], 2) }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div class="flex gap-1 p-1 bg-slate-200/60 rounded-xl text-sm font-semibold">
            @foreach (['pendientes' => 'Pendientes', 'vencidas' => 'Vencidas', 'pagadas' => 'Pagadas', 'anuladas' => 'Anuladas', 'todas' => 'Todas'] as $k => $v)
                <button name="estado" value="{{ $k }}" class="px-3 h-8 rounded-lg {{ $estado === $k ? 'bg-white shadow text-indigo-700' : 'text-slate-500' }}">{{ $v }}</button>
            @endforeach
        </div>
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ $tx['persona'] }}, RUC/DNI o serie-número"
               class="flex-1 min-w-[200px] h-10 rounded-xl border-slate-300 text-sm">
        <input type="hidden" name="estado" value="{{ $estado }}">
    </form>

    {{-- Listado --}}
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-3">Documento</th>
                    <th class="text-left px-4 py-3">{{ $tx['persona'] }}</th>
                    <th class="text-left px-4 py-3">Vence</th>
                    <th class="text-right px-4 py-3">Total</th>
                    <th class="text-right px-4 py-3">{{ $tx['pasado'] }}</th>
                    <th class="text-right px-4 py-3">Saldo</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($cuentas as $c)
                    @php
                        $dias = $c->fec_ven ? (int) floor((strtotime($hoy) - strtotime($c->fec_ven)) / 86400) : null;
                        $abierta = in_array($c->estado_cob, ['PENDIENTE', 'PARCIAL']);
                        $moneda = $c->moneda === 'USD' ? '$' : 'S/';
                    @endphp
                    <tr class="{{ $c->estado_cob === 'ANULADO' ? 'text-slate-400' : '' }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-semibold">{{ $c->serie }}-{{ $c->numero }}</span>
                            <span class="block text-[11px] text-slate-400">{{ $tiposDoc[$c->tdocod] ?? $c->tdocod }} · {{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-semibold">{{ $c->persona }}</span>
                            <span class="block text-[11px] text-slate-400">{{ $c->doc_persona }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($c->fec_ven)
                                {{ \Carbon\Carbon::parse($c->fec_ven)->format('d/m/Y') }}
                                @if ($abierta && $dias > 0)
                                    <span class="block text-[11px] font-bold text-rose-600">Vencida hace {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}</span>
                                @elseif ($abierta && $dias !== null && $dias >= -7)
                                    <span class="block text-[11px] font-bold text-amber-600">{{ $dias === 0 ? 'Vence hoy' : 'Vence en ' . abs($dias) . ' días' }}</span>
                                @endif
                            @else — @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $moneda }} {{ number_format($c->total, 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap text-emerald-700">{{ number_format($c->abono, 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap font-bold">
                            {{ number_format($c->saldo, 2) }}
                            @if (!$abierta)
                                <span class="block text-[10px] font-bold {{ $c->estado_cob === 'PAGADO' ? 'text-emerald-600' : 'text-slate-400' }}">{{ $c->estado_cob }}</span>
                            @elseif ($c->estado_cob === 'PARCIAL')
                                <span class="block text-[10px] font-bold text-sky-600">PAGO PARCIAL</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" @click="historial({{ $c->id }})" class="text-xs font-semibold text-slate-500 hover:text-indigo-700 mr-2">Historial</button>
                            @if ($abierta)
                                <button type="button" @click="abrirPago(@js(['id' => $c->id, 'doc' => $c->serie . '-' . $c->numero, 'persona' => $c->persona, 'saldo' => (float) $c->saldo, 'moneda' => $moneda]))"
                                        class="h-8 px-3 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">{{ $tx['accion'] }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">
                        No hay cuentas {{ $estado === 'todas' ? '' : $estado }}. Se crean solas al {{ $tipo === 'cobrar' ? 'vender' : 'comprar' }} con forma de pago <strong>Crédito</strong>.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $cuentas->links() }}</div>

    {{-- ===== Modal: registrar cobro / pago ===== --}}
    <div x-show="pago" x-cloak class="fixed inset-0 z-50 bg-slate-900/50 flex items-end sm:items-center justify-center p-0 sm:p-4" @keydown.escape.window="pago = null">
        <div @click.outside="pago = null" class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
            <template x-if="pago">
                <div class="p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-bold">{{ $tx['accion'] }} <span x-text="pago.doc"></span></h2>
                        <p class="text-sm text-slate-500" x-text="pago.persona"></p>
                        <p class="text-sm mt-1">Saldo pendiente: <strong x-text="pago.moneda + ' ' + num(pago.saldo)"></strong></p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs font-semibold text-slate-500">Fecha
                            <input type="date" x-model="form.fecha" max="{{ $hoy }}" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="text-xs font-semibold text-slate-500">N° operación <span class="font-normal">(opcional)</span>
                            <input x-model="form.num_oper" maxlength="50" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-slate-500">Medio de pago</span>
                            <button type="button" @click="dividir = !dividir; repartir()" class="text-xs font-semibold text-indigo-600" x-text="dividir ? 'Un solo medio' : 'Dividir en varios medios'"></button>
                        </div>
                        <div x-show="!dividir" class="grid grid-cols-[1fr_auto] gap-2">
                            <select x-model.number="form.medio" class="h-11 rounded-xl border-slate-300 text-sm font-semibold">
                                @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                            </select>
                            <input type="number" step="0.01" min="0.01" :max="pago.saldo" x-model.number="form.monto"
                                   class="w-36 h-11 rounded-xl border-2 border-emerald-300 text-right text-lg font-bold">
                        </div>
                        <div x-show="dividir" class="space-y-1.5">
                            @foreach ($medios as $m)
                                <label class="flex items-center gap-2 text-sm">
                                    <span class="flex-1 font-semibold text-slate-600">{{ $m->nom_med_pag }}</span>
                                    <input type="number" step="0.01" min="0" x-model.number="form.montos[{{ $m->id_med_pag }}]" placeholder="0.00"
                                           class="w-32 h-10 rounded-xl border-slate-300 text-right font-semibold">
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs mt-1.5" :class="total() > pago.saldo + 0.001 ? 'text-rose-600' : 'text-slate-500'">
                            Total: <strong x-text="num(total())"></strong> · queda <strong x-text="num(Math.max(0, pago.saldo - total()))"></strong>
                            <span x-show="total() < pago.saldo - 0.001"> (pago parcial)</span>
                        </p>
                    </div>
                    @if ($tipo === 'pagar')
                        <label class="flex items-start gap-2 text-sm text-slate-600">
                            <input type="checkbox" x-model="form.desde_caja" class="mt-0.5 rounded text-indigo-600">
                            <span>El efectivo sale de la caja de mi turno <span class="block text-xs text-slate-400">Desmárcalo si pagaste con dinero de otra fuente (banco, gerencia).</span></span>
                        </label>
                    @else
                        <p class="text-xs text-slate-400">El efectivo cobrado entra a la caja de tu turno como "Cuentas por cobrar".</p>
                    @endif
                    <label class="block text-xs font-semibold text-slate-500">Comentario <span class="font-normal">(opcional)</span>
                        <input x-model="form.comentario" maxlength="200" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm">
                    </label>
                    <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 text-rose-700 text-sm px-3 py-2"></p>
                    <div class="flex gap-2">
                        <button type="button" @click="pago = null" class="flex-1 h-12 rounded-xl bg-slate-100 font-semibold">Cancelar</button>
                        <button type="button" @click="registrar()" :disabled="enviando"
                                class="flex-[2] h-12 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold disabled:opacity-50"
                                x-text="enviando ? 'Registrando…' : '{{ $tx['accion'] }} ' + num(total())"></button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== Modal: historial de pagos ===== --}}
    <div x-show="hist" x-cloak class="fixed inset-0 z-50 bg-slate-900/50 flex items-end sm:items-center justify-center p-0 sm:p-4" @keydown.escape.window="hist = null">
        <div @click.outside="hist = null" class="bg-white w-full sm:max-w-2xl rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
            <template x-if="hist">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-bold">Historial <span x-text="hist.cuenta.serie + '-' + hist.cuenta.numero"></span></h2>
                            <p class="text-sm text-slate-500" x-text="hist.cuenta.persona"></p>
                        </div>
                        <button type="button" @click="hist = null" class="text-2xl leading-none text-slate-400">×</button>
                    </div>
                    <div class="grid grid-cols-3 gap-2 my-4 text-center">
                        <div class="bg-slate-50 rounded-xl py-2"><p class="text-[11px] text-slate-500">Total</p><p class="font-extrabold" x-text="num(hist.cuenta.total)"></p></div>
                        <div class="bg-emerald-50 rounded-xl py-2"><p class="text-[11px] text-slate-500">{{ $tx['pasado'] }}</p><p class="font-extrabold text-emerald-700" x-text="num(hist.cuenta.abono)"></p></div>
                        <div class="bg-amber-50 rounded-xl py-2"><p class="text-[11px] text-slate-500">Saldo</p><p class="font-extrabold" x-text="num(hist.cuenta.saldo)"></p></div>
                    </div>
                    <p x-show="!hist.pagos.length" class="text-center text-slate-400 text-sm py-6">Aún no hay pagos registrados.</p>
                    <ul class="divide-y divide-slate-100">
                        <template x-for="p in hist.pagos" :key="p.id">
                            <li class="py-2.5 flex items-start gap-3" :class="p.estado === 'ANULADO' ? 'opacity-50' : ''">
                                <div class="flex-1 min-w-0 text-sm">
                                    <p><strong x-text="p.recibo"></strong> · <span x-text="p.fecha"></span>
                                        <span x-show="p.caja" class="ml-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 px-1.5 rounded">CAJA</span>
                                        <span x-show="p.estado === 'ANULADO'" class="ml-1 text-[10px] font-bold text-rose-600 bg-rose-50 px-1.5 rounded">ANULADO</span></p>
                                    <p class="text-xs text-slate-500" x-text="p.medios + (p.oper ? ' · Op. ' + p.oper : '') + (p.usuario ? ' · ' + p.usuario : '')"></p>
                                    <p x-show="p.comentario" class="text-xs text-slate-400" x-text="p.comentario"></p>
                                    <p x-show="p.motivo" class="text-xs text-rose-500" x-text="'Motivo: ' + p.motivo"></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-bold" x-text="num(p.monto)"></p>
                                    <p class="text-[11px] text-slate-400" x-text="'saldo ' + num(p.saldo)"></p>
                                    <div class="flex gap-2 justify-end mt-1">
                                        <a :href="p.urlRecibo" target="_blank" class="text-xs font-semibold text-indigo-700">Recibo</a>
                                        <button x-show="p.estado === 'REGISTRADO'" type="button" @click="anular(p)" class="text-xs font-semibold text-rose-600">Anular</button>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function cuentasPagina() {
        const MEDIOS = @json($medios->pluck('id_med_pag'));
        const HOY = @json($hoy);
        const csrf = @json(csrf_token());
        const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
        return {
            pago: null, hist: null, dividir: false, enviando: false, error: '',
            form: {},
            num: n => (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            abrirPago(c) {
                this.pago = c; this.error = ''; this.dividir = false;
                this.form = { fecha: HOY, num_oper: '', comentario: '', medio: MEDIOS[0], monto: c.saldo, montos: {}, desde_caja: true };
            },
            repartir() { this.form.montos = {}; if (this.dividir) this.form.montos[this.form.medio] = this.form.monto; },
            medios() {
                return this.dividir
                    ? Object.entries(this.form.montos).map(([id, monto]) => ({ id: +id, monto: r2(monto) })).filter(m => m.monto > 0)
                    : [{ id: +this.form.medio, monto: r2(this.form.monto) }];
            },
            total() { return r2(this.medios().reduce((s, m) => s + m.monto, 0)); },
            async registrar() {
                this.error = '';
                if (!(this.total() > 0)) { this.error = 'Ingresa el monto.'; return; }
                if (this.total() > this.pago.saldo + 0.001) { this.error = 'El monto es mayor al saldo pendiente.'; return; }
                this.enviando = true;
                try {
                    const r = await fetch(@json(url('cuentas/' . $tipo)) + '/' + this.pago.id + '/pagar', {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ fecha: this.form.fecha, num_oper: this.form.num_oper, comentario: this.form.comentario,
                            desde_caja: !!this.form.desde_caja, medios: this.medios() }),
                    });
                    const d = await r.json();
                    if (r.status === 422) { this.error = Object.values(d.errors)[0][0]; return; }
                    if (d.estado !== 'ok') { this.error = d.mensaje; return; }
                    if (confirm('✔ Registrado. ¿Imprimir el recibo?')) window.open(d.recibo, '_blank');
                    window.location.reload();
                } catch (e) { this.error = 'Error de conexión.'; } finally { this.enviando = false; }
            },
            async historial(id) {
                const r = await fetch(@json(url('cuentas/' . $tipo)) + '/' + id + '/pagos', { headers: { Accept: 'application/json' } });
                this.hist = await r.json();
            },
            async anular(p) {
                const motivo = prompt('Motivo para anular el ' + p.recibo + ' (S/ ' + this.num(p.monto) + '):');
                if (!motivo) return;
                const r = await fetch(@json(url('cuentas/' . $tipo . '/pago')) + '/' + p.id + '/anular', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ motivo }),
                });
                const d = await r.json();
                if (r.status === 422) { alert(Object.values(d.errors)[0][0]); return; }
                if (d.estado !== 'ok') { alert(d.mensaje); return; }
                window.location.reload();
            },
        };
    }
</script>
@endpush
