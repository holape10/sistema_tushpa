@extends('layouts.app')
@section('title', 'Configuración del colegio')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $meses = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => mb_strtoupper(\Carbon\Carbon::create(2000, $m, 1)->locale('es')->monthName)]);
        $filas = $grados->map(fn ($g) => ['grado_id' => $g->grado_id, 'nivel' => $g->nivel, 'grado' => $g->grado, 'seccion' => $g->seccion,
            'pension' => (float) $g->pension, 'matricula' => (float) $g->matricula, 'inscripcion' => (float) $g->inscripcion,
            'activo' => (bool) $g->activo, 'alumnos' => (int) ($usados[$g->grado_id] ?? 0)])->values();
        $valores = collect((array) $cfg)->only(['anio', 'mes_inicio', 'mes_fin', 'dia_vence', 'dscto_2', 'dscto_3', 'adelanto_meses', 'adelanto_pct'])->map(fn ($v) => (float) $v);
    @endphp

    <div class="space-y-5 max-w-5xl" x-data="configColegio()">
        <div>
            <a href="{{ route('colegio.index') }}" class="text-sm text-gray-500">← Alumnos</a>
            <h1 class="text-2xl font-black text-gray-800">Configuración del colegio</h1>
            <p class="text-sm text-gray-500">Los cambios valen para las matrículas que se hagan desde ahora; lo ya generado no cambia.</p>
        </div>

        {{-- Año escolar y descuentos --}}
        <form @submit.prevent="guardar(@js(route('colegio.configuracion.guardar')), cfg)" class="bg-white rounded-2xl shadow-sm p-5 space-y-5">
            <h2 class="font-black text-gray-800"><i class="fas fa-calendar-days text-indigo-500"></i> Año escolar y pensiones</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label class="text-xs font-semibold text-gray-500">Año escolar
                    <input type="number" x-model.number="cfg.anio" min="2020" max="2100" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500">Primera pensión
                    <select x-model.number="cfg.mes_inicio" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                        @foreach ($meses as $n => $m)<option value="{{ $n }}">{{ $m }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-gray-500">Última pensión
                    <select x-model.number="cfg.mes_fin" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                        @foreach ($meses as $n => $m)<option value="{{ $n }}">{{ $m }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-gray-500">Vence el día
                    <input type="number" x-model.number="cfg.dia_vence" min="1" max="31" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
            </div>
            <p class="text-sm text-gray-600" x-text="'Se generan ' + Math.max(0, cfg.mes_fin - cfg.mes_inicio + 1) + ' pensiones por alumno, cada una vence el día ' + cfg.dia_vence + ' de su mes.'"></p>

            <h2 class="font-black text-gray-800 pt-2"><i class="fas fa-people-roof text-violet-500"></i> Descuento por hermanos o referidos</h2>
            <p class="text-sm text-gray-500 -mt-3">Al matricular, si el alumno es hermano o referido de otro, se le aplica este descuento en cada pensión.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label class="text-xs font-semibold text-gray-500">2° alumno del grupo (%)
                    <input type="number" step="any" x-model.number="cfg.dscto_2" min="0" max="100" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500">3° alumno o más (%)
                    <input type="number" step="any" x-model.number="cfg.dscto_3" min="0" max="100" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
            </div>

            <h2 class="font-black text-gray-800 pt-2"><i class="fas fa-forward text-emerald-500"></i> Descuento por pagar varios meses juntos</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label class="text-xs font-semibold text-gray-500">Desde cuántas pensiones
                    <input type="number" x-model.number="cfg.adelanto_meses" min="0" max="12" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                <label class="text-xs font-semibold text-gray-500">Descuento (%)
                    <input type="number" step="any" x-model.number="cfg.adelanto_pct" min="0" max="100" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
            </div>
            <p class="text-sm text-gray-600" x-show="cfg.adelanto_meses > 0 && cfg.adelanto_pct > 0"
               x-text="'Si pagan ' + cfg.adelanto_meses + ' pensiones o más de una vez, tienen ' + cfg.adelanto_pct + '% de descuento en esas pensiones.'"></p>
            <p class="text-sm text-gray-400" x-show="!(cfg.adelanto_meses > 0 && cfg.adelanto_pct > 0)">Sin descuento por adelantado (pon 0 para desactivarlo).</p>
            <div class="flex justify-end"><button class="h-11 px-6 rounded-xl bg-indigo-600 text-white font-bold">Guardar</button></div>
        </form>

        {{-- Grados y precios --}}
        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-5 border-b border-gray-100 flex flex-wrap items-center gap-2">
                <div class="mr-auto"><h2 class="font-black text-gray-800"><i class="fas fa-layer-group text-sky-500"></i> Grados, secciones y precios</h2>
                    <p class="text-sm text-gray-500">La inscripción solo se cobra a los alumnos nuevos (primera vez). Los antiguos pagan matrícula y pensiones.</p></div>
                <button type="button" @click="tipicos()" class="h-10 px-3 rounded-xl bg-sky-50 text-sky-700 text-sm font-bold"><i class="fas fa-wand-magic-sparkles"></i> Cargar grados típicos</button>
                <button type="button" @click="agregar()" class="h-10 px-3 rounded-xl bg-gray-100 text-gray-700 text-sm font-bold"><i class="fas fa-plus"></i> Grado</button>
            </div>
            <div class="p-3 border-b border-gray-100 bg-gray-50 flex flex-wrap items-end gap-2 text-xs">
                <span class="font-semibold text-gray-500 mr-1">Poner a todos los de</span>
                <select x-model="masivo.nivel" class="h-9 rounded-lg border-gray-300 text-xs"><option value="">todos los niveles</option>
                    @foreach (\App\Support\Colegio::NIVELES as $n)<option>{{ $n }}</option>@endforeach</select>
                <input type="number" step="any" x-model="masivo.pension" placeholder="Pensión" class="w-24 h-9 rounded-lg border-gray-300 text-xs text-right">
                <input type="number" step="any" x-model="masivo.matricula" placeholder="Matrícula" class="w-24 h-9 rounded-lg border-gray-300 text-xs text-right">
                <input type="number" step="any" x-model="masivo.inscripcion" placeholder="Inscripción" class="w-24 h-9 rounded-lg border-gray-300 text-xs text-right">
                <button type="button" @click="aplicarMasivo()" class="h-9 px-3 rounded-lg bg-white ring-1 ring-gray-200 font-bold">Aplicar</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[760px]">
                    <thead class="text-xs text-gray-500 text-left"><tr>
                        <th class="px-3 py-2">Nivel</th><th class="px-3 py-2">Grado</th><th class="px-3 py-2">Sección</th>
                        <th class="px-3 py-2 text-right">Pensión</th><th class="px-3 py-2 text-right">Matrícula</th><th class="px-3 py-2 text-right">Inscripción</th>
                        <th class="px-3 py-2 text-center">Activo</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(g, i) in grados" :key="i">
                            <tr :class="g.activo ? '' : 'opacity-50'">
                                <td class="px-2 py-1.5"><select x-model="g.nivel" class="w-32 h-9 rounded-lg border-gray-300 text-sm">
                                    @foreach (\App\Support\Colegio::NIVELES as $n)<option>{{ $n }}</option>@endforeach</select></td>
                                <td class="px-2 py-1.5"><input x-model="g.grado" maxlength="40" class="w-32 h-9 rounded-lg border-gray-300 text-sm uppercase"></td>
                                <td class="px-2 py-1.5"><input x-model="g.seccion" maxlength="20" class="w-20 h-9 rounded-lg border-gray-300 text-sm uppercase"></td>
                                <td class="px-2 py-1.5"><input type="number" step="any" min="0" x-model.number="g.pension" class="w-24 h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                                <td class="px-2 py-1.5"><input type="number" step="any" min="0" x-model.number="g.matricula" class="w-24 h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                                <td class="px-2 py-1.5"><input type="number" step="any" min="0" x-model.number="g.inscripcion" class="w-24 h-9 rounded-lg border-gray-300 text-sm text-right"></td>
                                <td class="px-2 py-1.5 text-center"><input type="checkbox" x-model="g.activo" class="h-5 w-5 rounded text-indigo-600"></td>
                                <td class="px-2 py-1.5 whitespace-nowrap text-right">
                                    <span x-show="g.alumnos" class="text-xs text-gray-400" x-text="g.alumnos + ' matr.'"></span>
                                    <button type="button" @click="seccion(i)" class="px-2 py-1 rounded-lg bg-gray-50 text-gray-600 text-xs font-bold" title="Otra sección del mismo grado">+ Secc.</button>
                                    <button type="button" @click="grados.splice(i, 1)" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600 text-xs font-bold" title="Quitar">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!grados.length" class="p-8 text-center text-sm text-gray-400">Aún no hay grados. Usa “Cargar grados típicos” y luego pon los precios.</p>
            </div>
            <div class="p-4 border-t border-gray-100 flex items-center gap-3">
                <p class="text-xs text-gray-400 mr-auto">Si quitas un grado que ya tiene alumnos, solo se desactiva.</p>
                <button type="button" @click="guardar(@js(route('colegio.configuracion.grados')), { grados })" class="h-11 px-6 rounded-xl bg-indigo-600 text-white font-bold">Guardar grados</button>
            </div>
        </div>
    </div>

    <script>
        function configColegio() {
            const TIPICOS = @json(\App\Support\Colegio::GRADOS_TIPICOS);
            const nuevo = (nivel = 'PRIMARIA', grado = '') => ({ grado_id: null, nivel, grado, seccion: 'ÚNICA', pension: 0, matricula: 0, inscripcion: 0, activo: true, alumnos: 0 });
            return {
                cfg: @json($valores),
                grados: @json($filas),
                masivo: { nivel: '', pension: '', matricula: '', inscripcion: '' },
                agregar() { this.grados.push(nuevo()); },
                seccion(i) { const g = this.grados[i]; this.grados.splice(i + 1, 0, { ...g, grado_id: null, alumnos: 0, seccion: '' }); },
                tipicos() {
                    const hay = new Set(this.grados.map(g => g.nivel + '|' + g.grado));
                    TIPICOS.forEach(([n, g]) => { if (!hay.has(n + '|' + g)) this.grados.push(nuevo(n, g)); });
                },
                aplicarMasivo() {
                    this.grados.filter(g => !this.masivo.nivel || g.nivel === this.masivo.nivel).forEach(g => {
                        ['pension', 'matricula', 'inscripcion'].forEach(k => { if (this.masivo[k] !== '') g[k] = Number(this.masivo[k]); });
                    });
                },
                async guardar(url, datos) {
                    const r = await fetch(url, { method: 'POST', body: JSON.stringify(datos),
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } });
                    const d = await r.json().catch(() => ({ mensaje: 'Error al guardar.' }));
                    if (d.errors) return (window.tushpaAviso || alert)(Object.values(d.errors).flat()[0], 'error');
                    (window.tushpaAviso || alert)(d.mensaje, d.ok ? 'ok' : 'error');
                    if (d.ok && datos.grados) setTimeout(() => location.reload(), 800);
                },
            };
        }
    </script>
@endsection
