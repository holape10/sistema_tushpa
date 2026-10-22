@extends('layouts.app')
@section('title', 'Colegio')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-4';
    @endphp

    <div class="space-y-5" x-data="colegioAlumnos()">
        {{-- Cabecera --}}
        <div class="relative overflow-hidden rounded-3xl p-6 sm:p-7 text-white bg-gradient-to-br from-sky-600 via-indigo-700 to-indigo-900 shadow-lg">
            <div class="absolute -right-10 -top-10 w-56 h-56 rounded-full bg-white/10"></div>
            <div class="absolute right-24 -bottom-16 w-40 h-40 rounded-full bg-white/5"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-sky-100/80"><i class="fas fa-school"></i> Colegio · año escolar {{ $anio }}</p>
                    <p class="mt-2 text-sm text-sky-100">Alumnos matriculados</p>
                    <p class="text-4xl sm:text-5xl font-black tracking-tight">{{ $kpi->matriculados }}</p>
                    <p class="mt-1 text-sm text-sky-100">Cobrado este mes <b class="text-white">{{ $soles($kpi->cobradoMes) }}</b> · falta cobrar en el año {{ $soles($kpi->porCobrar) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="abrirNuevo()" class="px-4 py-2.5 rounded-xl bg-white text-indigo-800 font-extrabold text-sm shadow hover:bg-indigo-50"><i class="fas fa-user-plus"></i> Matricular alumno</button>
                    <a href="{{ route('colegio.reportes') }}" class="px-4 py-2.5 rounded-xl bg-white/15 font-bold text-sm hover:bg-white/25"><i class="fas fa-chart-pie"></i> Reportes</a>
                    @if ($esAdmin)
                        <a href="{{ route('colegio.sorteo') }}" class="px-4 py-2.5 rounded-xl bg-amber-400 text-amber-950 font-extrabold text-sm shadow hover:bg-amber-300"><i class="fas fa-gift"></i> Sorteo</a>
                        <a href="{{ route('colegio.configuracion') }}" class="px-3 py-2.5 rounded-xl bg-white/15 text-sm hover:bg-white/25" title="Configuración"><i class="fas fa-gear"></i></a>
                    @endif
                </div>
            </div>
        </div>

        @if ($grados->isEmpty())
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-amber-900">
                <p class="font-bold"><i class="fas fa-circle-info"></i> Primero configura los grados y sus precios</p>
                <p class="text-sm mt-1">Pensión, matrícula e inscripción de cada grado, los meses de pensión y los descuentos por hermanos.</p>
                @if ($esAdmin)<a href="{{ route('colegio.configuracion') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-amber-500 text-white font-bold text-sm">Ir a configuración →</a>@endif
            </div>
        @endif

        {{-- Indicadores: tocar uno filtra la lista --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            @foreach ([
                ['', 'Matriculados', $kpi->matriculados, 'fa-users', 'text-indigo-600', 'en '.$anio],
                ['aldia', 'Al día', $kpi->alDia, 'fa-circle-check', 'text-emerald-600', 'sin nada vencido'],
                ['debe1', 'Deben 1 mes', $kpi->deben1, 'fa-clock', 'text-amber-600', 'pensión vencida'],
                ['debe2', 'Deben 2 o más', $kpi->deben2, 'fa-triangle-exclamation', 'text-rose-600', 'morosos'],
                ['vencido', 'Deuda vencida', $soles($kpi->vencido), 'fa-sack-xmark', 'text-rose-600', 'por cobrar ya'],
            ] as [$filtro, $titulo, $valor, $icono, $color, $sub])
                <button type="button" @click="filtro = '{{ $filtro }}'" class="{{ $tarjeta }} text-left ring-2 transition" :class="filtro === '{{ $filtro }}' ? 'ring-indigo-400' : 'ring-transparent hover:ring-gray-200'">
                    <p class="text-[11px] font-semibold uppercase text-gray-400"><i class="fas {{ $icono }} {{ $color }}"></i> {{ $titulo }}</p>
                    <p class="text-2xl font-black text-gray-800 mt-1">{{ $valor }}</p>
                    <p class="text-xs text-gray-400">{{ $sub }}</p>
                </button>
            @endforeach
        </div>

        {{-- Lista --}}
        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-4 flex flex-wrap gap-3 items-center border-b border-gray-100">
                <input type="search" x-model="q" placeholder="Buscar alumno, DNI o código…" class="flex-1 min-w-48 h-11 rounded-xl border-gray-300 text-sm">
                <select x-model="grado" class="h-11 rounded-xl border-gray-300 text-sm">
                    <option value="">Todos los grados</option>
                    @foreach ($grados as $g)<option value="{{ $g->grado_id }}">{{ \App\Support\Colegio::etiquetaGrado($g) }}</option>@endforeach
                </select>
                <select x-model="filtro" class="h-11 rounded-xl border-gray-300 text-sm">
                    <option value="">Todos</option><option value="aldia">Al día</option><option value="debe1">Deben 1 mes</option>
                    <option value="debe2">Deben 2 o más</option><option value="sin">Sin matrícula {{ $anio }}</option><option value="retirados">Retirados</option>
                </select>
            </div>
            <div class="divide-y divide-gray-100">
                <template x-for="a in lista" :key="a.id">
                    <a :href="a.url" class="flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-indigo-50/50">
                        <span class="h-10 w-10 shrink-0 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-black" x-text="iniciales(a.nombre)"></span>
                        <div class="flex-1 min-w-48">
                            <p class="font-bold text-gray-800" x-text="a.nombre"></p>
                            <p class="text-xs text-gray-400"><span x-text="a.grado"></span> · cód. <span x-text="a.codigo"></span><span x-show="a.dni" x-text="' · DNI ' + a.dni"></span>
                                <span x-show="a.dscto > 0" class="ml-1 rounded bg-violet-50 px-1.5 font-semibold text-violet-700" x-text="'-' + a.dscto + '% pensión'"></span></p>
                            <p class="text-xs text-gray-500" x-show="a.responsable" x-text="a.responsable"></p>
                        </div>
                        <div class="w-28 text-right">
                            <p class="text-[11px] text-gray-400">Vencido</p>
                            <p class="font-bold" :class="a.vencido > 0 ? 'text-rose-600' : 'text-gray-400'" x-text="soles(a.vencido)"></p>
                        </div>
                        <span class="w-32 text-center rounded-full px-2.5 py-1 text-xs font-black" :class="colores[a.color]" x-text="a.etiqueta"></span>
                    </a>
                </template>
                <p x-show="!lista.length" class="p-8 text-center text-gray-400 text-sm">No hay alumnos con ese filtro.</p>
            </div>
        </div>

        {{-- Matricular alumno nuevo --}}
        <div x-show="nuevo" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto" @keydown.escape.window="nuevo = null">
            <form @submit.prevent="guardar()" class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl my-4" @click.outside="nuevo = null">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h2 class="text-lg font-black text-gray-800"><i class="fas fa-user-graduate text-indigo-600"></i> Matricular alumno</h2>
                    <button type="button" @click="nuevo = null" class="text-2xl text-gray-400">×</button>
                </div>
                <template x-if="nuevo">
                <div class="p-6 space-y-5">
                    {{-- Alumno --}}
                    <section>
                        <p class="text-xs font-black uppercase text-indigo-600 mb-2">1. El alumno</p>
                        <div class="grid sm:grid-cols-6 gap-3">
                            <label class="sm:col-span-2 text-xs font-semibold text-gray-500">DNI
                                <div class="flex mt-1"><input x-model="nuevo.dni" maxlength="15" inputmode="numeric" class="flex-1 min-w-0 h-10 rounded-l-lg border-gray-300 text-sm">
                                    <button type="button" @click="consultar(nuevo, 'dni', 'nombres')" class="px-3 rounded-r-lg bg-indigo-600 text-white"><i class="fas fa-magnifying-glass"></i></button></div></label>
                            <label class="sm:col-span-4 text-xs font-semibold text-gray-500">Apellidos y nombres *
                                <input x-model="nuevo.nombres" required maxlength="150" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                            <label class="sm:col-span-2 text-xs font-semibold text-gray-500">Fecha de nacimiento
                                <input type="date" x-model="nuevo.fecha_nac" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                            <label class="sm:col-span-1 text-xs font-semibold text-gray-500">Sexo
                                <select x-model="nuevo.sexo" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"><option value="">—</option><option value="M">M</option><option value="F">F</option></select></label>
                            <label class="sm:col-span-3 text-xs font-semibold text-gray-500">Grado {{ $anio }} *
                                <select x-model="nuevo.grado_id" required class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                                    <option value="">Elige el grado…</option>
                                    @foreach ($grados as $g)<option value="{{ $g->grado_id }}">{{ \App\Support\Colegio::etiquetaGrado($g) }} · pensión {{ $soles($g->pension) }}</option>@endforeach
                                </select></label>
                        </div>
                        <label class="mt-3 flex items-start gap-2 rounded-xl bg-indigo-50 p-3 text-sm cursor-pointer">
                            <input type="checkbox" x-model="nuevo.nuevo" class="mt-0.5 rounded text-indigo-600">
                            <span><b>Es la primera vez en el colegio</b> <span class="text-gray-600">(paga inscripción). Si ya estudió aquí, solo se le cobra matrícula y pensiones.</span></span>
                        </label>
                        <p x-show="resumenCobro" class="mt-2 text-sm text-gray-600" x-text="resumenCobro"></p>
                    </section>

                    {{-- Hermano o referido --}}
                    <section class="relative">
                        <p class="text-xs font-black uppercase text-indigo-600 mb-1">2. ¿Tiene un hermano o referido en el colegio? <span class="font-normal normal-case text-gray-400">(opcional)</span></p>
                        <p class="text-xs text-gray-500 mb-2">Desde el 2° alumno del mismo grupo familiar la pensión tiene descuento: 2° alumno {{ rtrim(rtrim(number_format($cfg->dscto_2, 2), '0'), '.') }}% · 3° y más {{ rtrim(rtrim(number_format($cfg->dscto_3, 2), '0'), '.') }}%.</p>
                        <div x-show="!nuevo.hermano">
                            <input x-model="buscaHermano" @input.debounce.300ms="buscarHermano()" placeholder="Busca al hermano o al alumno que lo refirió…" class="w-full h-10 rounded-lg border-gray-300 text-sm">
                            <div x-show="hermanos.length" class="absolute z-10 left-0 right-0 mt-1 bg-white rounded-xl shadow-xl ring-1 ring-gray-200 max-h-56 overflow-y-auto">
                                <template x-for="h in hermanos" :key="h.alu_id">
                                    <button type="button" @click="nuevo.hermano = h; nuevo.hermano_de = h.alu_id; hermanos = []" class="w-full text-left px-4 py-2 text-sm hover:bg-indigo-50" x-text="h.nombres + (h.dni ? ' · ' + h.dni : '')"></button>
                                </template>
                            </div>
                        </div>
                        <p x-show="nuevo.hermano" class="flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2 text-sm text-violet-800">
                            <i class="fas fa-people-roof"></i> Mismo grupo familiar que <b x-text="nuevo.hermano?.nombres"></b>
                            <button type="button" @click="nuevo.hermano = null; nuevo.hermano_de = null" class="ml-auto text-xs underline">quitar</button></p>
                    </section>

                    {{-- Responsables --}}
                    <section>
                        <p class="text-xs font-black uppercase text-indigo-600 mb-2">3. Padre, madre y apoderado <span class="font-normal normal-case text-gray-400">(llena los que tengas)</span></p>
                        <div class="space-y-3">
                            <template x-for="r in nuevo.responsables" :key="r.tipo">
                                <div class="rounded-2xl ring-1 ring-gray-200 p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="font-bold text-gray-700" x-text="r.tipo"></p>
                                        <label class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 cursor-pointer">
                                            <input type="radio" name="paga" :value="r.tipo" x-model="nuevo.paga" class="text-emerald-600"> Es quien paga normalmente</label>
                                    </div>
                                    <div class="grid sm:grid-cols-6 gap-2">
                                        <div class="sm:col-span-2 flex"><input x-model="r.clinum" placeholder="DNI o RUC" maxlength="15" inputmode="numeric" class="flex-1 min-w-0 h-9 rounded-l-lg border-gray-300 text-sm">
                                            <button type="button" @click="consultar(r, 'clinum', 'clinom', 'clidir')" class="px-3 rounded-r-lg bg-indigo-600 text-white text-sm"><i class="fas fa-magnifying-glass"></i></button></div>
                                        <input x-model="r.clinom" placeholder="Nombre completo o razón social" maxlength="150" class="sm:col-span-4 h-9 rounded-lg border-gray-300 text-sm uppercase">
                                        <input x-model="r.telefono" placeholder="Celular" maxlength="20" inputmode="tel" class="sm:col-span-2 h-9 rounded-lg border-gray-300 text-sm">
                                        <input x-model="r.clicor" type="email" placeholder="Correo" maxlength="120" class="sm:col-span-2 h-9 rounded-lg border-gray-300 text-sm">
                                        <input x-model="r.clidir" placeholder="Dirección" maxlength="200" class="sm:col-span-2 h-9 rounded-lg border-gray-300 text-sm">
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Con su RUC se le puede emitir factura. Al cobrar siempre se puede elegir quién paga.</p>
                    </section>

                    <p x-show="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700" x-text="error"></p>
                </div>
                </template>
                <div class="px-6 py-4 border-t flex gap-3 justify-end">
                    <button type="button" @click="nuevo = null" class="px-5 h-11 rounded-xl bg-gray-100 font-bold text-gray-700">Cancelar</button>
                    <button :disabled="guardando" class="px-6 h-11 rounded-xl bg-indigo-600 text-white font-extrabold disabled:opacity-60" x-text="guardando ? 'Matriculando…' : '✓ Matricular'"></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function colegioAlumnos() {
            const grados = @json($grados->keyBy('grado_id'));
            const soles = n => 'S/ ' + Number(n || 0).toFixed(2);
            return {
                alumnos: @json($alumnos), q: '', grado: '', filtro: '', nuevo: null, guardando: false, error: '', buscaHermano: '', hermanos: [],
                colores: { green: 'bg-emerald-100 text-emerald-700', amber: 'bg-amber-100 text-amber-800', red: 'bg-rose-100 text-rose-700', gray: 'bg-gray-100 text-gray-500' },
                soles,
                norm(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },
                iniciales(n) { return String(n || '').split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]).join(''); },
                get lista() {
                    const q = this.norm(this.q.trim());
                    return this.alumnos.filter(a => (!q || this.norm(a.nombre + ' ' + a.dni + ' ' + a.codigo).includes(q))
                        && (!this.grado || String(a.grado_id) === this.grado)
                        && (this.filtro === 'retirados' ? a.retirado : !a.retirado)
                        && (this.filtro === '' || this.filtro === 'retirados' || (this.filtro === 'aldia' && a.etiqueta === 'AL DÍA')
                            || (this.filtro === 'debe1' && a.meses === 1) || (this.filtro === 'debe2' && a.meses >= 2)
                            || (this.filtro === 'vencido' && a.vencido > 0) || (this.filtro === 'sin' && !a.grado_id)))
                        .sort((x, y) => x.orden - y.orden || x.nombre.localeCompare(y.nombre));
                },
                get resumenCobro() {
                    const g = this.nuevo && grados[this.nuevo.grado_id];
                    if (!g) return '';
                    const partes = [];
                    if (this.nuevo.nuevo && Number(g.inscripcion) > 0) partes.push('inscripción ' + soles(g.inscripcion));
                    if (Number(g.matricula) > 0) partes.push('matrícula ' + soles(g.matricula));
                    partes.push('{{ (int) $cfg->mes_fin - (int) $cfg->mes_inicio + 1 }} pensiones de ' + soles(g.pension));
                    return 'Se le generará: ' + partes.join(' + ') + '.';
                },
                abrirNuevo() {
                    this.error = ''; this.buscaHermano = ''; this.hermanos = [];
                    this.nuevo = { dni: '', nombres: '', fecha_nac: '', sexo: '', grado_id: '', nuevo: true, hermano: null, hermano_de: null, paga: 'MADRE',
                        responsables: ['PADRE', 'MADRE', 'APODERADO'].map(tipo => ({ tipo, clinum: '', clinom: '', telefono: '', clicor: '', clidir: '' })) };
                },
                async consultar(obj, campoDoc, campoNom, campoDir) {
                    const doc = String(obj[campoDoc] || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(doc)) { this.error = 'Escribe un DNI (8 dígitos) o RUC (11 dígitos).'; return; }
                    this.error = '';
                    try {
                        const r = await (await fetch(@json(url('cobros/cliente')) + '/' + doc, { headers: { Accept: 'application/json' } })).json();
                        if (r.nom) { obj[campoNom] = r.nom; if (campoDir && r.dir) obj[campoDir] = r.dir; if (r.tel && 'telefono' in obj) obj.telefono = r.tel; }
                        else this.error = r.error || 'No se encontró: escríbelo a mano.';
                    } catch (e) { this.error = 'No se pudo consultar el documento.'; }
                },
                async buscarHermano() {
                    const q = this.buscaHermano.trim();
                    if (q.length < 2) { this.hermanos = []; return; }
                    this.hermanos = await (await fetch(@json(route('colegio.buscar')) + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })).json();
                },
                async guardar() {
                    this.guardando = true; this.error = '';
                    try {
                        const r = await fetch(@json(route('colegio.guardar')), { method: 'POST', body: JSON.stringify(this.nuevo),
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } });
                        const d = await r.json();
                        if (r.status === 422) { this.error = Object.values(d.errors || {}).flat()[0]; return; }
                        if (!d.ok) { this.error = d.mensaje || 'No se pudo guardar.'; return; }
                        window.location.href = d.url;
                    } catch (e) { this.error = 'Error de conexión. Intenta otra vez.'; } finally { this.guardando = false; }
                },
            };
        }
    </script>
@endsection
