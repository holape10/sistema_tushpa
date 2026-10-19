@extends('layouts.app')
@section('title', 'Configuración de préstamos')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500'; @endphp
    <div x-data="configPrestamos()" class="space-y-5 max-w-5xl">
        <div>
            <a href="{{ route('prestamos.index') }}" class="text-sm text-gray-500">← Préstamos</a>
            <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-gear text-emerald-600"></i> Configuración de préstamos</h1>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="font-bold text-gray-800"><i class="fas fa-tags text-emerald-600"></i> Planes</h2>
            <p class="text-sm text-gray-500">Al prestar se elige un plan y ya se llenan el interés y las cuotas (igual se pueden cambiar en cada préstamo).</p>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-400 bg-gray-50"><tr><th class="text-left px-3 py-2">Plan</th><th class="text-right">Interés</th><th class="text-right">Cuotas</th><th class="text-left px-2">Frecuencia</th>
                    <th class="text-right">Monto</th><th class="text-left px-2">Ejemplo con S/ 100</th><th></th></tr></thead>
                <tbody class="divide-y">
                    <template x-for="p in planes" :key="p.plan_id">
                        <tr :class="Number(p.activo) ? '' : 'opacity-40'">
                            <td class="px-3 py-2 font-semibold"><span x-text="p.nombre"></span> <span x-show="Number(p.primer_prestamo)" class="ml-1 px-1.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold">1RA VEZ</span></td>
                            <td class="text-right" x-text="Number(p.interes) + '%'"></td><td class="text-right" x-text="p.cuotas"></td><td class="px-2" x-text="frec[p.frecuencia]"></td>
                            <td class="text-right text-xs" x-text="(Number(p.monto_min) ? 'desde ' + Number(p.monto_min) : '') + (Number(p.monto_max) ? ' hasta ' + Number(p.monto_max) : '') || '—'"></td>
                            <td class="px-2 text-xs text-gray-500" x-text="ejemplo(p)"></td>
                            <td class="text-right px-2"><button type="button" @click="plan = { ...p, primer_prestamo: !!Number(p.primer_prestamo), activo: !!Number(p.activo) }" class="text-emerald-700 text-xs font-bold">Editar</button></td>
                        </tr>
                    </template>
                </tbody></table></div>
            <div class="rounded-2xl bg-emerald-50/60 p-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <p class="col-span-2 sm:col-span-4 text-xs font-bold uppercase text-emerald-800" x-text="plan.plan_id ? 'Editar plan' : 'Nuevo plan'"></p>
                <label class="block text-xs font-semibold text-gray-500 col-span-2">Nombre<input x-model="plan.nombre" placeholder="Ej. DIARIO 24 DÍAS" class="{{ $in }} mt-1 uppercase"></label>
                <label class="block text-xs font-semibold text-gray-500">Interés %<input type="number" min="0" step="1" x-model.number="plan.interes" class="{{ $in }} mt-1 text-right"></label>
                <label class="block text-xs font-semibold text-gray-500">N° de cuotas<input type="number" min="1" x-model.number="plan.cuotas" class="{{ $in }} mt-1 text-right"></label>
                <label class="block text-xs font-semibold text-gray-500">Frecuencia<select x-model="plan.frecuencia" class="{{ $in }} mt-1">@foreach (\App\Support\Prestamos::FRECUENCIAS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></label>
                <label class="block text-xs font-semibold text-gray-500">Monto mínimo<input type="number" min="0" x-model.number="plan.monto_min" class="{{ $in }} mt-1 text-right"></label>
                <label class="block text-xs font-semibold text-gray-500">Monto máximo <span class="font-normal">(0 = sin tope)</span><input type="number" min="0" x-model.number="plan.monto_max" class="{{ $in }} mt-1 text-right"></label>
                <div class="flex flex-col justify-end gap-1 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="plan.primer_prestamo" class="rounded text-emerald-600"> Para la primera vez</label>
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="plan.activo" class="rounded text-emerald-600"> Activo</label>
                </div>
                <p class="col-span-2 sm:col-span-4 text-sm text-emerald-800" x-text="ejemplo(plan)"></p>
                <div class="col-span-2 sm:col-span-4 flex gap-2 justify-end">
                    <button type="button" x-show="plan.plan_id" @click="plan = vacio()" class="px-4 py-2 rounded-xl bg-white text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="guardarPlan()" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold">Guardar plan</button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-5 grid md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <h2 class="font-bold text-gray-800"><i class="fas fa-calendar-days text-emerald-600"></i> Días de cobro</h2>
                    <p class="text-xs text-gray-500 mb-2">Las cuotas diarias solo caen en estos días (normalmente de lunes a sábado: el domingo se descansa).</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (\App\Support\Prestamos::DIAS as $n => $d)
                            <button type="button" @click="alternar({{ $n }})" class="w-14 py-2 rounded-xl text-sm font-bold border-2" :class="f.dias.includes({{ $n }}) ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-gray-200 text-gray-400'">{{ $d }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-xs font-semibold text-gray-500">Mora por cuota atrasada S/ <span class="font-normal">(0 = sin mora)</span><input type="number" min="0" step="0.5" x-model.number="f.mora_diaria" class="{{ $in }} mt-1 text-right"></label>
                    <label class="block text-xs font-semibold text-gray-500">Cuotas de gracia<input type="number" min="0" x-model.number="f.dias_gracia" class="{{ $in }} mt-1 text-right"></label>
                </div>
                <p class="text-xs text-gray-500">Ej. mora S/ 1 y 2 de gracia: si debe 5 cuotas, al cobrar se sugiere S/ 3 de mora (el cobrador la puede cambiar).</p>
                <label class="block text-xs font-semibold text-gray-500">Capital inicial S/ <span class="font-normal">(con cuánto empezaste; para ver tu rentabilidad)</span><input type="number" min="0" x-model.number="f.capital_inicial" class="{{ $in }} mt-1 text-right"></label>
            </div>
            <div>
                <h2 class="font-bold text-gray-800"><i class="fas fa-umbrella-beach text-emerald-600"></i> Feriados (no se cobra)</h2>
                <p class="text-xs text-gray-500 mb-2">Una fecha por línea (AAAA-MM-DD). Si una cuota cae en feriado, pasa al siguiente día de cobro.</p>
                <textarea x-model="f.feriados" rows="9" class="{{ $in }} font-mono" placeholder="2026-12-25"></textarea>
                <button type="button" @click="cargarFeriados()" class="mt-2 px-3 py-1.5 rounded-lg bg-gray-100 text-xs font-bold">🇵🇪 Agregar feriados nacionales del Perú</button>
            </div>
            <div class="md:col-span-2 flex justify-end"><button type="button" @click="guardar()" class="px-6 py-2.5 rounded-xl bg-emerald-600 text-white font-bold">Guardar configuración</button></div>
        </div>
    </div>

    <script>
        function configPrestamos() {
            const CSRF = '{{ csrf_token() }}';
            const vacio = () => ({ plan_id: null, nombre: '', interes: 20, cuotas: 24, frecuencia: 'DIARIA', monto_min: 0, monto_max: 0, primer_prestamo: false, activo: true });
            const post = (url, data) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) })
                .then(async r => { const j = await r.json().catch(() => ({})); return r.status === 422 ? { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] } : j; });
            return {
                planes: @js($planes), plan: vacio(), vacio, frec: @js(\App\Support\Prestamos::FRECUENCIAS),
                f: { dias: @js($cfg->dias), mora_diaria: {{ (float) $cfg->mora_diaria }}, dias_gracia: {{ (int) $cfg->dias_gracia }}, feriados: @js(implode("\n", $cfg->lista_feriados)), capital_inicial: {{ (float) $cfg->capital_inicial }} },
                ejemplo(p) {
                    const total = 100 * (1 + (Number(p.interes) || 0) / 100), n = Math.max(1, Number(p.cuotas) || 1);
                    return 'S/ 100 → devuelve S/ ' + total.toFixed(2) + ' en ' + n + ' cuotas de S/ ' + (Math.ceil(total / n * 10 - 0.0001) / 10).toFixed(2);
                },
                alternar(n) { this.f.dias = this.f.dias.includes(n) ? this.f.dias.filter(x => x !== n) : [...this.f.dias, n].sort(); },
                cargarFeriados() { const set = new Set(this.f.feriados.split(/\s+/).filter(Boolean)); @js($feriadosPeru).forEach(x => set.add(x)); this.f.feriados = [...set].sort().join('\n'); },
                async guardar() { const r = await post('{{ route('prestamos.configuracion.guardar') }}', this.f); tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error'); },
                async guardarPlan() {
                    const r = await post('{{ route('prestamos.configuracion.plan') }}', this.plan);
                    tushpaAviso(r.mensaje || 'Error', r.ok ? 'ok' : 'error');
                    if (r.ok) { this.planes = r.planes; this.plan = vacio(); }
                },
            };
        }
    </script>
@endsection
