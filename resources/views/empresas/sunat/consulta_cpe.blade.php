@extends('layouts.app')
@section('title', 'Consulta CPE Individual')

@section('content')
<div class="max-w-4xl mx-auto space-y-4" x-data="consultaCpe()">
    <section class="rounded-3xl overflow-hidden shadow-sm bg-gradient-to-br from-indigo-700 to-indigo-900 text-white relative isolate">
        <x-kene-adorno patron="laberinto" />
        <div class="p-6 sm:p-8">
            <h1 class="text-2xl font-extrabold">🔎 Consulta CPE en SUNAT</h1>
            <p class="text-indigo-200 text-sm mt-1">Revisa si un comprobante electrónico (tuyo o de un proveedor) está informado en SUNAT, si fue anulado y el estado del emisor.</p>
        </div>
    </section>

    @unless ($conToken)
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            Falta configurar el token de <b>apiperu.dev</b> (<code class="bg-white px-1 rounded">APIPERU_TOKEN</code> en el archivo .env). Pídelo a soporte.
        </div>
    @endunless

    <form @submit.prevent="consultar()" class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-6 gap-3 text-sm">
        <label class="sm:col-span-3 font-medium text-gray-700">RUC del emisor
            <div class="mt-1 flex gap-1">
                <input x-model="f.ruc" inputmode="numeric" maxlength="11" required class="flex-1 min-w-0 h-11 rounded-xl border-gray-300 font-mono">
                <button type="button" @click="f.ruc = @js($rucPropio)" class="px-3 rounded-xl bg-gray-100 text-xs font-semibold text-gray-600 hover:bg-gray-200">Mi RUC</button>
            </div>
        </label>
        <label class="sm:col-span-3 font-medium text-gray-700">Tipo de comprobante
            <select x-model="f.tipo" class="mt-1 w-full h-11 rounded-xl border-gray-300">
                <option value="01">Factura</option><option value="03">Boleta</option><option value="07">Nota de crédito</option><option value="08">Nota de débito</option>
            </select>
        </label>
        <label class="sm:col-span-2 font-medium text-gray-700">Serie
            <input x-model="f.serie" @input="f.serie = f.serie.toUpperCase()" maxlength="4" required placeholder="F001" class="mt-1 w-full h-11 rounded-xl border-gray-300 font-mono uppercase">
        </label>
        <label class="sm:col-span-2 font-medium text-gray-700">Número
            <input x-model="f.numero" inputmode="numeric" maxlength="8" required placeholder="123" class="mt-1 w-full h-11 rounded-xl border-gray-300 font-mono">
        </label>
        <label class="sm:col-span-2 font-medium text-gray-700">Fecha de emisión
            <input type="date" x-model="f.fecha" required class="mt-1 w-full h-11 rounded-xl border-gray-300">
        </label>
        <label class="sm:col-span-2 font-medium text-gray-700">Importe total
            <input type="number" step="0.01" min="0" x-model="f.total" required placeholder="118.00" class="mt-1 w-full h-11 rounded-xl border-gray-300 text-right">
        </label>
        <div class="sm:col-span-4 flex items-end">
            <button class="w-full h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold disabled:opacity-50" :disabled="cargando"
                    x-text="cargando ? 'Consultando en SUNAT…' : '🔎 Consultar en SUNAT'"></button>
        </div>
        <p x-show="error" x-cloak class="sm:col-span-6 text-rose-600" x-text="error"></p>
    </form>

    {{-- Resultados de esta sesión (el más nuevo arriba) --}}
    <template x-for="r in resultados" :key="r.id">
        <div class="bg-white rounded-2xl shadow-sm p-5 border-l-8" :class="{ emerald: 'border-emerald-500', rose: 'border-rose-500', amber: 'border-amber-500', slate: 'border-slate-300' }[r.color]">
            <div class="flex flex-wrap items-center gap-3">
                <span class="px-3 py-1 rounded-full text-sm font-extrabold"
                      :class="{ emerald: 'bg-emerald-100 text-emerald-800', rose: 'bg-rose-100 text-rose-800', amber: 'bg-amber-100 text-amber-800', slate: 'bg-slate-100 text-slate-700' }[r.color]"
                      x-text="r.estado || 'NO SE PUDO CONSULTAR'"></span>
                <span class="font-mono font-bold text-gray-800" x-text="r.etiqueta"></span>
                <span class="ml-auto text-xs text-gray-400" x-text="r.hora"></span>
            </div>
            <p class="text-sm text-gray-600 mt-2" x-text="r.mensaje"></p>
            <p x-show="r.empresa_estado" class="text-xs text-gray-500 mt-1">Emisor: <b x-text="r.empresa_estado"></b> · <b x-text="r.empresa_condicion"></b></p>
            <template x-for="o in r.observaciones"><p class="text-xs text-amber-700 mt-1" x-text="'• ' + o"></p></template>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
    function consultaCpe() {
        const TIPOS = { '01': 'Factura', '03': 'Boleta', '07': 'Nota de crédito', '08': 'Nota de débito' };
        return {
            f: { ruc: @js($rucPropio), tipo: '01', serie: '', numero: '', fecha: @js(now()->toDateString()), total: '' },
            cargando: false, error: '', resultados: [], n: 0,
            async consultar() {
                this.cargando = true; this.error = '';
                try {
                    const r = await fetch(@js(route('sunat.consulta_cpe.consultar')), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify(this.f),
                    });
                    const d = await r.json();
                    if (r.status === 422) { this.error = Object.values(d.errors)[0][0]; return; }
                    if (r.status === 429) { this.error = 'Muchas consultas seguidas: espera un minuto.'; return; }
                    this.resultados.unshift({ ...d, id: ++this.n, hora: new Date().toLocaleTimeString('es-PE'),
                        etiqueta: `${TIPOS[this.f.tipo]} ${this.f.serie}-${this.f.numero} · RUC ${this.f.ruc} · S/ ${Number(this.f.total).toFixed(2)}` });
                } catch (e) {
                    this.error = 'Sin conexión con el servidor.';
                } finally {
                    this.cargando = false;
                }
            },
        };
    }
</script>
@endpush
