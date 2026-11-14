{{--
    Agregar a mano un comprobante que SUNAT aún no tiene en la propuesta del mes (el proveedor mandó tarde la factura).
    Se abre con el botón de abajo o, desde un XML, con: window.dispatchEvent(new CustomEvent('sire-agregar', { detail: {...} })).
    Variables: $libro ('compras' | 'ventas'), $periodo
--}}
@php
    $tiposManual = \App\Support\Contabilidad\SireManuales::TIPOS[$libro];
    $montosManual = \App\Support\Contabilidad\SireManuales::MONTOS[$libro];
    $finPeriodo = date('Y-m-t', strtotime(substr($periodo, 0, 4).'-'.substr($periodo, 4, 2).'-01'));
@endphp
<div x-data="sireAgregar(@js($libro), @js($periodo), @js(array_keys($montosManual)), @js($finPeriodo))" @sire-agregar.window="abrir($event.detail)">
    <button type="button" @click="abrir({})"
            class="h-9 px-4 inline-flex items-center gap-1.5 rounded-full bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-sm font-bold">➕ Agregar comprobante</button>

    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/50 sm:p-4" @click.self="abierto = false" @keydown.escape.window="abierto = false">
        <form @submit.prevent="guardar()" class="w-full sm:max-w-2xl max-h-[95vh] overflow-y-auto rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 bg-white border-b px-5 py-3 flex items-center gap-2">
                <h3 class="flex-1 font-extrabold text-slate-800">Agregar {{ $libro === 'compras' ? 'compra' : 'venta' }} a este mes</h3>
                <button type="button" @click="abierto = false" class="text-2xl leading-none text-slate-400" aria-label="Cerrar">&times;</button>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <p class="rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-900">
                    Para comprobantes que SUNAT aún no tiene en la propuesta (llegaron tarde). Queda marcado como <b>MANUAL</b> y va al .dbf, Excel,
                    reporte y CONCAR como los demás. Si después SUNAT lo trae en otro mes, allí sale como «no se lleva» para que no entre dos veces.
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <label class="col-span-2">Tipo
                        <select x-model="f.tipo" class="block w-full mt-1 rounded-lg border-slate-300">
                            @foreach ($tiposManual as $codigo => $nombre)<option value="{{ $codigo }}">{{ $nombre }}</option>@endforeach
                        </select></label>
                    <label>Serie<input x-model="f.serie" maxlength="20" required placeholder="F001" class="block w-full mt-1 rounded-lg border-slate-300 uppercase"></label>
                    <label>Número<input x-model="f.numero" maxlength="20" required inputmode="numeric" class="block w-full mt-1 rounded-lg border-slate-300"></label>
                    <label class="col-span-2 sm:col-span-1">Fecha de emisión<input type="date" x-model="f.fecha" :max="fin" required class="block w-full mt-1 rounded-lg border-slate-300"></label>
                    <label class="col-span-2 sm:col-span-1">Vencimiento <span class="text-slate-400">(opcional)</span><input type="date" x-model="f.vence" class="block w-full mt-1 rounded-lg border-slate-300"></label>
                    <label class="col-span-2">{{ $libro === 'compras' ? 'RUC del proveedor' : 'Documento del cliente' }}
                        <input x-model="f.ruc" maxlength="15" required inputmode="numeric" class="block w-full mt-1 rounded-lg border-slate-300"></label>
                    <label class="col-span-2 sm:col-span-4">{{ $libro === 'compras' ? 'Proveedor' : 'Cliente' }} (nombre o razón social)
                        <input x-model="f.nombre" maxlength="200" required class="block w-full mt-1 rounded-lg border-slate-300 uppercase"></label>
                </div>

                <div x-show="f.tipo === '07' || f.tipo === '08'" class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-xl bg-amber-50 p-3">
                    <p class="col-span-2 sm:col-span-4 text-xs font-bold text-amber-900">Comprobante que modifica</p>
                    <label>Tipo<select x-model="f.ref_tipo" class="block w-full mt-1 rounded-lg border-slate-300"><option value="01">Factura</option><option value="03">Boleta</option></select></label>
                    <label>Serie<input x-model="f.ref_serie" maxlength="20" class="block w-full mt-1 rounded-lg border-slate-300 uppercase"></label>
                    <label>Número<input x-model="f.ref_numero" maxlength="20" class="block w-full mt-1 rounded-lg border-slate-300"></label>
                    <label>Fecha<input type="date" x-model="f.ref_fecha" class="block w-full mt-1 rounded-lg border-slate-300"></label>
                </div>

                <div class="rounded-xl bg-slate-50 p-3 space-y-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <label>Moneda<select x-model="f.moneda" class="block mt-1 rounded-lg border-slate-300"><option value="PEN">Soles</option><option value="USD">Dólares</option></select></label>
                        <label x-show="f.moneda === 'USD'">Tipo de cambio<input type="number" step="0.001" min="0" x-model="f.tc" class="block w-28 mt-1 rounded-lg border-slate-300"></label>
                        <p class="ml-auto text-right"><span class="block text-xs text-slate-500">Total <span x-show="f.tipo === '07'">(se resta)</span></span>
                            <span class="text-2xl font-black text-slate-800" x-text="total().toFixed(2)"></span></p>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach ($montosManual as $k => $titulo)
                            <label>{{ $titulo }}
                                <span class="mt-1 flex gap-1">
                                    <input type="number" step="0.01" min="0" x-model="f.montos.{{ $k }}" class="block w-full rounded-lg border-slate-300 text-right">
                                    @if (str_starts_with($k, 'igv'))
                                        <button type="button" @click="igv('{{ $k }}')" title="18 % de su base" class="shrink-0 px-1.5 rounded-lg bg-white border border-slate-300 text-[11px] font-bold text-slate-600">18%</button>
                                    @endif
                                </span></label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-500">Escribe los montos en positivo. El total se suma solo.@if ($libro === 'compras') El IGV va al lado de su base (IGV 1 con B.I. gravadas).@endif</p>
                </div>
                <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-3 py-2 font-semibold text-rose-700"></p>
            </div>
            <div class="sticky bottom-0 bg-white border-t px-5 py-3 flex justify-end gap-2">
                <button type="button" @click="abierto = false" class="h-11 px-4 rounded-xl bg-slate-100 font-bold text-slate-700">Cancelar</button>
                <button :disabled="guardando" class="h-11 px-5 rounded-xl bg-indigo-600 font-bold text-white disabled:opacity-60" x-text="guardando ? 'Guardando…' : 'Agregar al mes'"></button>
            </div>
        </form>
    </div>
</div>

@once
<script>
    function sireAgregar(libro, periodo, montos, fin) {
        const vacio = () => ({ tipo: '01', serie: '', numero: '', fecha: '', vence: '', ruc: '', nombre: '', moneda: 'PEN', tc: '',
            ref_tipo: '01', ref_serie: '', ref_numero: '', ref_fecha: '', montos: Object.fromEntries(montos.map(k => [k, ''])) });
        const pares = { igv_dg: 'base_dg', igv_dgng: 'base_dgng', igv_dng: 'base_dng', igv: 'gravada' };
        return {
            abierto: false, guardando: false, error: '', fin, f: vacio(),
            /** @param d datos ya llenos (desde un XML) o {} para uno nuevo */
            abrir(d) {
                const f = vacio();
                Object.assign(f, d || {}, { montos: Object.assign(f.montos, (d && d.montos) || {}) });
                Object.keys(f.montos).forEach(k => { f.montos[k] = Number(f.montos[k]) ? String(f.montos[k]) : ''; });
                if (f.fecha && f.fecha > fin) f.fecha = '';
                this.f = f; this.error = ''; this.abierto = true;
            },
            total() { return Math.round(Object.values(this.f.montos).reduce((s, v) => s + (Number(v) || 0), 0) * 100) / 100; },
            igv(k) { const base = Number(this.f.montos[pares[k]]) || 0; this.f.montos[k] = base ? (Math.round(base * 18) / 100).toFixed(2) : ''; },
            async guardar() {
                this.guardando = true; this.error = '';
                try {
                    const r = await fetch(@json(route('sire.dbf.agregar')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ ...this.f, libro, periodo }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok) { this.error = d.errors ? Object.values(d.errors)[0][0] : (d.message || 'No se pudo agregar.'); return; }
                    location.reload();
                } catch (e) {
                    this.error = 'Se cortó la conexión: no se agregó.';
                } finally {
                    this.guardando = false;
                }
            },
        };
    }

    /** Borra un comprobante agregado a mano */
    async function sireQuitarManual(url, numero) {
        if (!confirm('¿Borrar el ' + numero + ' que agregaste a mano? Se borran también sus cuentas.')) return;
        const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } });
        if (r.ok) location.reload(); else tushpaAviso('No se pudo borrar.', false);
    }
</script>
@endonce
