@extends('layouts.app')
@section('title', 'Tipo de cambio')
@section('content')
    @include('empresas.partials.alert')
    @php $in = 'block w-full h-11 rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    <div x-data="tipoCambio()" class="max-w-4xl mx-auto space-y-5">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800">Tipo de cambio</h1>
            <p class="text-sm text-slate-500">Tipo de cambio oficial SUNAT (compra y venta) para una fecha. Lo que ya consultaste queda guardado abajo.</p>
        </div>

        @unless ($conToken)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                Falta configurar el token de <b>apiperu.dev</b>: agrega <code class="bg-white px-1 rounded">APIPERU_TOKEN=tu_token</code> en el archivo <code class="bg-white px-1 rounded">.env</code>
                y luego ejecuta <code class="bg-white px-1 rounded">php artisan config:cache</code>.
            </div>
        @endunless

        <div class="bg-white rounded-2xl shadow-sm p-5 grid md:grid-cols-2 gap-5 items-center">
            <form @submit.prevent="consultar()" class="space-y-3">
                <label class="block text-sm font-semibold text-slate-600">Fecha
                    <input type="date" x-model="fecha" max="{{ now()->toDateString() }}" required class="{{ $in }} mt-1"></label>
                <label class="block text-sm font-semibold text-slate-600">Moneda
                    <select x-model="moneda" class="{{ $in }} mt-1"><option value="USD">Dólar (USD)</option><option value="EUR">Euro (EUR)</option></select></label>
                <button :disabled="cargando" class="w-full h-11 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 disabled:opacity-50"
                        x-text="cargando ? 'Consultando…' : 'Consultar'"></button>
                <p x-show="error" x-text="error" class="text-sm text-rose-600"></p>
            </form>

            <div class="rounded-2xl bg-slate-50 p-5 text-center" x-show="tc" x-cloak>
                <p class="text-sm text-slate-500" x-text="(tc?.moneda === 'EUR' ? 'Euro' : 'Dólar') + ' · ' + fechaLarga(tc?.fecha)"></p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="bg-white rounded-xl p-3"><p class="text-xs text-slate-500">Compra</p><p class="text-3xl font-extrabold text-slate-800" x-text="tc?.compra.toFixed(3)"></p></div>
                    <div class="bg-white rounded-xl p-3"><p class="text-xs text-slate-500">Venta</p><p class="text-3xl font-extrabold text-indigo-700" x-text="tc?.venta.toFixed(3)"></p></div>
                </div>
                <p class="text-xs text-slate-400 mt-3" x-show="tc?.fecha_sunat && tc?.fecha_sunat !== tc?.fecha" x-text="'Publicado por SUNAT el ' + fechaLarga(tc?.fecha_sunat) + ' (rige para la fecha consultada)'"></p>
                <p class="text-xs text-slate-400 mt-1">Para compras en dólares se usa el de <b>venta</b>.</p>
            </div>
            <div x-show="!tc" class="text-center text-sm text-slate-400">Elige una fecha y presiona Consultar.</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b font-bold text-slate-700">Consultados</div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>
                    <th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Moneda</th>
                    <th class="px-4 py-2 text-right">Compra</th><th class="px-4 py-2 text-right">Venta</th><th class="px-4 py-2 text-left">Publicado SUNAT</th></tr></thead>
                <tbody class="divide-y">
                    @forelse ($historial as $h)
                        <tr>
                            <td class="px-4 py-2 font-semibold">{{ \Carbon\Carbon::parse($h->fecha)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ $h->moneda }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ number_format($h->compra, 3) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-bold">{{ number_format($h->venta, 3) }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ $h->fecha_sunat ? \Carbon\Carbon::parse($h->fecha_sunat)->format('d/m/Y') : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Aún no hay consultas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function tipoCambio() {
            return {
                fecha: '{{ now()->toDateString() }}', moneda: 'USD', tc: null, error: '', cargando: false,
                fechaLarga(f) { return f ? new Date(f + 'T00:00').toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : ''; },
                async consultar() {
                    this.cargando = true; this.error = '';
                    try {
                        const d = await (await fetch(@js(route('tipo_cambio.consultar')) + '?fecha=' + this.fecha + '&moneda=' + this.moneda, { headers: { Accept: 'application/json' } })).json();
                        if (d.ok) this.tc = d; else { this.tc = null; this.error = d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo consultar.'); }
                    } catch (e) { this.error = 'Sin conexión con el servidor.'; } finally { this.cargando = false; }
                },
            };
        }
    </script>
@endsection
