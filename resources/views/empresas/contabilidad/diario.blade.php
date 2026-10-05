@extends('layouts.app')
@section('title', 'Libro Diario')
@section('content')
    @include('empresas.contabilidad._nav')
    @use('App\Support\Contabilidad\Contabilidad')

    @php
        $mes = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2);
        $origenColor = ['VENTAS' => 'bg-emerald-100 text-emerald-700', 'COBRANZAS' => 'bg-sky-100 text-sky-700', 'COMPRAS' => 'bg-amber-100 text-amber-800',
                        'PAGOS' => 'bg-rose-100 text-rose-700', 'MANUAL' => 'bg-indigo-100 text-indigo-700', 'APERTURA' => 'bg-violet-100 text-violet-700'];
        $cuadra = abs((float) $totales->debe - (float) $totales->haber) < 0.01;
    @endphp

    <div x-data="diario(@js($imputables), @js(route('contabilidad.asiento.guardar')))">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="periodo" value="{{ $periodo }}">
            <label class="text-sm">Periodo
                <input type="month" value="{{ $mes }}" onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block rounded-lg border-gray-300 text-sm font-semibold"></label>
            <label class="text-sm">Origen
                <select name="origen" onchange="this.form.submit()" class="block rounded-lg border-gray-300 text-sm">
                    <option value="">Todos</option>
                    @foreach (Contabilidad::ORIGENES as $o)<option @selected(request('origen') === $o)>{{ $o }}</option>@endforeach
                </select></label>
            <label class="text-sm">Subdiario
                <select name="subdiario" onchange="this.form.submit()" class="block rounded-lg border-gray-300 text-sm">
                    <option value="">Todos</option>
                    @foreach (Contabilidad::SUBDIARIOS as $c => $n)<option value="{{ $c }}" @selected(request('subdiario') === $c)>{{ $c }} - {{ $n }}</option>@endforeach
                </select></label>
            <label class="text-sm flex-1 min-w-[180px]">Buscar
                <input name="q" value="{{ $q }}" placeholder="Glosa, documento o cuenta" class="block w-full rounded-lg border-gray-300 text-sm"></label>
            <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Filtrar</button>
            <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
        </form>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Asientos del periodo</p><p class="text-2xl font-black">{{ (int) $totales->n }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Total Debe</p><p class="text-2xl font-black text-gray-800">{{ number_format((float) $totales->debe, 2) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Total Haber</p><p class="text-2xl font-black text-gray-800">{{ number_format((float) $totales->haber, 2) }}</p>
                <p class="text-xs font-bold {{ $cuadra ? 'text-emerald-600' : 'text-rose-600' }}">{{ $cuadra ? '✔ Cuadrado' : '✖ Descuadrado' }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-col gap-2">
                <p class="text-xs text-gray-500">{{ Contabilidad::nombrePeriodo($periodo) }}: <strong class="{{ $cerrado ? 'text-rose-600' : 'text-emerald-600' }}">{{ $cerrado ? 'CERRADO' : 'ABIERTO' }}</strong></p>
                <div class="flex gap-2 mt-auto">
                    @unless ($cerrado)
                        <button type="button" @click="nuevo()" class="flex-1 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold"><i class="fas fa-plus"></i> Asiento</button>
                    @endunless
                    <form method="POST" action="{{ route('contabilidad.periodo') }}" onsubmit="return confirm('{{ $cerrado ? '¿Abrir el periodo para modificarlo?' : 'Al cerrar el periodo ya no se podrá modificar ni centralizar. ¿Cerrar?' }}')">
                        @csrf <input type="hidden" name="periodo" value="{{ $periodo }}"><input type="hidden" name="accion" value="{{ $cerrado ? 'abrir' : 'cerrar' }}">
                        <button class="py-2 px-3 rounded-xl text-sm font-semibold {{ $cerrado ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700' }}">
                            <i class="fas {{ $cerrado ? 'fa-lock-open' : 'fa-lock' }}"></i> {{ $cerrado ? 'Abrir' : 'Cerrar' }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left w-24">Asiento</th><th class="px-3 py-2 text-left w-24">Fecha</th><th class="px-3 py-2 text-left w-24">Cuenta</th>
                        <th class="px-3 py-2 text-left">Denominación / Glosa</th><th class="px-3 py-2 text-right w-28">Debe</th><th class="px-3 py-2 text-right w-28">Haber</th></tr>
                </thead>
                @forelse ($asientos as $a)
                    @php $det = $detalle[$a->id] ?? collect(); $manual = in_array($a->origen, ['MANUAL', 'APERTURA'], true); @endphp
                    <tbody class="border-b-4 border-slate-100">
                        <tr class="bg-slate-50">
                            <td class="px-3 py-2 font-mono font-bold">{{ $a->subdiario }}-{{ str_pad($a->numero, 4, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-3 py-2">{{ \Carbon\Carbon::parse($a->fecha)->format('d/m/Y') }}</td>
                            <td class="px-3 py-2"><span class="text-[10px] px-2 py-0.5 rounded-full font-bold {{ $origenColor[$a->origen] ?? '' }}">{{ $a->origen }}</span></td>
                            <td class="px-3 py-2 font-semibold text-gray-700" colspan="3">
                                <div class="flex items-center gap-3">
                                    <span class="flex-1">{{ $a->glosa }}</span>
                                    @if ($manual && !$cerrado)
                                        <button type="button" class="text-indigo-600 text-xs font-normal hover:underline"
                                                @click="editar({{ $a->id }}, @js(['fecha' => $a->fecha, 'glosa' => $a->glosa, 'subdiario' => $a->subdiario,
                                                    'lineas' => $det->map(fn($d) => ['cuenta' => $d->cuenta, 'debe' => (float) $d->debe ?: '', 'haber' => (float) $d->haber ?: '', 'glosa' => $d->glosa ?? '', 'documento' => $d->documento ?? ''])->values()]))">Editar</button>
                                        <form method="POST" action="{{ route('contabilidad.asiento.eliminar', $a->id) }}" onsubmit="return confirm('¿Eliminar este asiento?')">
                                            @csrf @method('DELETE')<button class="text-rose-600 text-xs font-normal hover:underline">Eliminar</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @foreach ($det as $d)
                            <tr>
                                <td></td><td></td>
                                <td class="px-3 py-1 font-mono {{ $d->haber > 0 ? 'pl-8' : '' }}">{{ $d->cuenta }}</td>
                                <td class="px-3 py-1 text-gray-600 {{ $d->haber > 0 ? 'pl-8' : '' }}">{{ $nombres[$d->cuenta] ?? '' }}
                                    @if ($d->anexo_nombre || $d->documento || $d->glosa)<span class="block text-xs text-gray-400">{{ trim(($d->anexo_doc ? $d->anexo_doc . ' ' : '') . ($d->anexo_nombre ?? '') . ($d->documento ? ' · ' . $d->documento : '') . ($d->glosa ? ' · ' . $d->glosa : ''), ' ·') }}</span>@endif</td>
                                <td class="px-3 py-1 text-right">{{ $d->debe > 0 ? number_format($d->debe, 2) : '' }}</td>
                                <td class="px-3 py-1 text-right">{{ $d->haber > 0 ? number_format($d->haber, 2) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody><tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay asientos en este periodo. Centraliza ventas y compras o registra un asiento manual.</td></tr></tbody>
                @endforelse
            </table>
        </div>
        <div class="mt-4">{{ $asientos->links() }}</div>

        {{-- Modal asiento manual --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center sm:p-4" @click.self="modal = false">
            <div class="bg-white w-full sm:max-w-4xl rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[95vh] flex flex-col">
                <datalist id="ctas"><template x-for="(nom, cta) in cuentas" :key="cta"><option :value="cta" x-text="nom"></option></template></datalist>
                <div class="px-5 py-3 border-b flex items-center justify-between">
                    <h3 class="font-bold text-gray-800" x-text="id ? 'Editar asiento' : 'Nuevo asiento'"></h3>
                    <button type="button" @click="modal = false" class="text-gray-400 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-5 overflow-y-auto space-y-3 text-sm">
                    <div class="grid sm:grid-cols-4 gap-3">
                        <label>Fecha<input type="date" x-model="a.fecha" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                        <label>Subdiario
                            <select x-model="a.subdiario" class="block w-full mt-1 rounded-lg border-gray-300">
                                <option value="35">35 - Diario</option><option value="01">01 - Caja y bancos</option><option value="00">00 - Apertura</option>
                            </select></label>
                        <label class="sm:col-span-2">Glosa<input x-model="a.glosa" maxlength="200" placeholder="Ej. PAGO DE ALQUILER DE OCTUBRE" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                    </div>
                    <table class="w-full">
                        <thead class="text-xs uppercase text-gray-500"><tr><th class="text-left py-1 w-36">Cuenta</th><th class="text-left">Denominación</th><th class="text-left w-32">Documento</th><th class="w-28">Debe</th><th class="w-28">Haber</th><th class="w-6"></th></tr></thead>
                        <tbody>
                            <template x-for="(l, i) in a.lineas" :key="i">
                                <tr>
                                    <td class="py-1 pr-1"><input list="ctas" x-model="l.cuenta" class="w-full rounded-lg border-gray-300 font-mono text-sm" :class="l.cuenta && !cuentas[l.cuenta] ? 'border-rose-400 bg-rose-50' : ''"></td>
                                    <td class="py-1 pr-1 text-xs text-gray-500 truncate max-w-[200px]" x-text="cuentas[l.cuenta] || (l.cuenta ? '⚠ no imputable' : '')"></td>
                                    <td class="py-1 pr-1"><input x-model="l.documento" maxlength="30" class="w-full rounded-lg border-gray-300 text-sm"></td>
                                    <td class="py-1 pr-1"><input type="number" step="0.01" min="0" x-model="l.debe" @input="if (l.debe) l.haber = ''" class="w-full rounded-lg border-gray-300 text-right text-sm"></td>
                                    <td class="py-1 pr-1"><input type="number" step="0.01" min="0" x-model="l.haber" @input="if (l.haber) l.debe = ''" class="w-full rounded-lg border-gray-300 text-right text-sm"></td>
                                    <td class="text-center"><button type="button" @click="a.lineas.splice(i, 1)" x-show="a.lineas.length > 2" class="text-rose-500 font-bold">✕</button></td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="font-bold border-t">
                                <td colspan="2" class="py-2"><button type="button" @click="a.lineas.push({ cuenta: '', debe: '', haber: '', documento: '' })" class="text-indigo-600 text-xs font-semibold">+ Agregar línea</button></td>
                                <td class="text-right pr-2">Totales</td>
                                <td class="text-right pr-2" x-text="n2(suma('debe'))"></td><td class="text-right pr-2" x-text="n2(suma('haber'))"></td><td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <p class="text-sm font-semibold" :class="cuadra() ? 'text-emerald-600' : 'text-rose-600'"
                       x-text="cuadra() ? '✔ El asiento cuadra' : 'Diferencia: ' + n2(Math.abs(suma('debe') - suma('haber')))"></p>
                    <p class="text-rose-600 font-semibold" x-text="error"></p>
                </div>
                <div class="px-5 py-3 border-t flex justify-end gap-2">
                    <button type="button" @click="modal = false" class="px-4 py-2 rounded-xl bg-gray-100 font-semibold">Cancelar</button>
                    <button type="button" @click="guardar()" :disabled="enviando || !cuadra()" class="px-6 py-2 rounded-xl bg-indigo-600 text-white font-semibold disabled:opacity-50" x-text="enviando ? 'Guardando…' : 'Guardar asiento'"></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function diario(cuentas, url) {
            const vacio = () => ({ cuenta: '', debe: '', haber: '', documento: '' });
            return {
                cuentas, modal: false, id: null, enviando: false, error: '',
                a: { fecha: '', glosa: '', subdiario: '35', lineas: [] },
                n2(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                suma(lado) { return Math.round(this.a.lineas.reduce((s, l) => s + (parseFloat(l[lado]) || 0), 0) * 100) / 100; },
                cuadra() { return this.suma('debe') > 0 && Math.abs(this.suma('debe') - this.suma('haber')) < 0.005; },
                nuevo() {
                    const p = @js($periodo);
                    const hoy = new Date().toISOString().slice(0, 10);
                    this.id = null; this.error = '';
                    this.a = { fecha: hoy.slice(0, 7).replace('-', '') === p ? hoy : p.slice(0, 4) + '-' + p.slice(4) + '-01', glosa: '', subdiario: '35', lineas: [vacio(), vacio()] };
                    this.modal = true;
                },
                editar(id, datos) { this.id = id; this.error = ''; this.a = JSON.parse(JSON.stringify(datos)); this.modal = true; },
                async guardar() {
                    if (!this.a.glosa.trim()) { this.error = 'Escribe la glosa del asiento.'; return; }
                    this.enviando = true; this.error = '';
                    const r = await fetch(url + (this.id ? '/' + this.id : ''), {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify({ ...this.a, lineas: this.a.lineas.filter(l => l.cuenta) }),
                    });
                    const d = await r.json().catch(() => ({ message: 'Error del servidor.' }));
                    this.enviando = false;
                    if (r.status === 422) { this.error = Object.values(d.errors)[0][0]; return; }
                    if (!d.success) { this.error = d.message; return; }
                    location.href = @js(route('contabilidad.diario')) + '?periodo=' + d.periodo;
                },
            };
        }
    </script>
@endsection
