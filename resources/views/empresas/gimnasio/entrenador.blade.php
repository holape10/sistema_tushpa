@extends('layouts.app')
@section('title', 'Entrenador')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    <div x-data="entrenador()" class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-person-running text-indigo-600"></i> Panel del entrenador</h1>
                <p class="text-sm text-gray-500">Tus clientes, su historial de medidas y sus planes de nutrición. Lo que registras le aparece al cliente en su portal.</p>
            </div>
            <div class="flex bg-white rounded-xl shadow-sm p-1 text-sm font-semibold">
                <button type="button" @click="vista = 'mios'" class="px-4 py-2 rounded-lg" :class="vista === 'mios' ? 'bg-indigo-600 text-white' : 'text-gray-600'">Mis clientes (<span x-text="clientes.filter(c => c.mio).length"></span>)</button>
                <button type="button" @click="vista = 'todos'" class="px-4 py-2 rounded-lg" :class="vista === 'todos' ? 'bg-indigo-600 text-white' : 'text-gray-600'">Todos</button>
            </div>
        </div>

        <div class="grid lg:grid-cols-[360px_1fr] gap-5 items-start">
            {{-- Lista --}}
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="p-3 border-b"><input x-model="q" placeholder="Buscar cliente" class="{{ $in }}"></div>
                <div class="divide-y max-h-[70vh] overflow-y-auto">
                    <template x-for="c in visibles" :key="c.soc_id">
                        <button type="button" @click="abrir(c.soc_id)" class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-indigo-50" :class="sel?.cliente.soc_id === c.soc_id ? 'bg-indigo-50' : ''">
                            <template x-if="c.foto"><img :src="c.foto" class="w-11 h-11 rounded-xl object-cover" alt=""></template>
                            <template x-if="!c.foto"><span class="w-11 h-11 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-black" x-text="c.nombre.charAt(0)"></span></template>
                            <span class="flex-1 min-w-0">
                                <span class="block font-semibold text-gray-800 truncate text-sm" x-text="c.nombre"></span>
                                <span class="block text-xs text-gray-400" x-text="(c.edad ? c.edad + ' años · ' : '') + (c.ultimo_plan ? 'Último plan ' + fecha(c.ultimo_plan) : 'Sin plan de nutrición')"></span>
                            </span>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full" :class="chip(c.color)" x-text="c.estado"></span>
                        </button>
                    </template>
                    <p x-show="!visibles.length" class="p-8 text-center text-sm text-gray-400" x-text="vista === 'mios' ? 'Aún no tienes clientes. Ve a Todos y toma a tus clientes.' : 'Sin resultados.'"></p>
                </div>
            </div>

            {{-- Cliente --}}
            <div class="space-y-5">
                <div x-show="!sel" class="bg-white rounded-2xl shadow-sm p-12 text-center text-gray-400">
                    <i class="fas fa-hand-pointer text-4xl"></i><p class="mt-3">Elige un cliente para ver su historial y hacerle su plan de nutrición.</p>
                </div>
                <template x-if="sel">
                    <div class="space-y-5">
                        <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-wrap items-center gap-4">
                            <template x-if="sel.cliente.foto"><img :src="sel.cliente.foto" class="w-20 h-20 rounded-2xl object-cover" alt=""></template>
                            <template x-if="!sel.cliente.foto"><span class="w-20 h-20 rounded-2xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-3xl font-black" x-text="sel.cliente.nombre.charAt(0)"></span></template>
                            <div class="flex-1 min-w-0">
                                <p class="text-xl font-extrabold text-gray-800" x-text="sel.cliente.nombre"></p>
                                <p class="text-sm text-gray-500" x-text="(sel.cliente.edad ? sel.cliente.edad + ' años · ' : '') + (sel.cliente.plan ? sel.cliente.plan + ' hasta ' + sel.cliente.vence : 'Sin plan del gimnasio') + ' · ' + sel.asistencias + ' ingresos en 30 días'"></p>
                                <span class="inline-block mt-1 text-[11px] font-black px-2 py-0.5 rounded-full" :class="chip(sel.cliente.color)" x-text="sel.cliente.estado"></span>
                            </div>
                            <button type="button" @click="asignar(!sel.cliente.mio)" class="px-4 py-2 rounded-xl text-sm font-bold"
                                    :class="sel.cliente.mio ? 'bg-gray-100 text-gray-600' : 'bg-indigo-600 text-white'" x-text="sel.cliente.mio ? 'Quitar de mis clientes' : 'Tomar como mi cliente'"></button>
                        </div>

                        {{-- Evolución --}}
                        <div class="bg-white rounded-2xl shadow-sm p-5" x-show="sel.nutricion.some(n => n.peso)">
                            <p class="font-bold text-gray-700 mb-3">Evolución</p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-gray-500 bg-gray-50"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-right">Peso</th><th class="px-3 py-2 text-right">Talla</th><th class="px-3 py-2 text-right">IMC</th><th class="px-3 py-2 text-right">% Grasa</th><th class="px-3 py-2 text-left">Objetivo</th></tr></thead>
                                    <tbody class="divide-y">
                                        <template x-for="(n, i) in sel.nutricion.filter(n => n.peso)" :key="n.nut_id">
                                            <tr>
                                                <td class="px-3 py-2" x-text="fecha(n.fecha)"></td>
                                                <td class="px-3 py-2 text-right font-semibold"><span x-text="n.peso + ' kg'"></span>
                                                    <span x-show="dif(i) !== null" class="text-xs" :class="dif(i) <= 0 ? 'text-emerald-600' : 'text-rose-600'" x-text="' (' + (dif(i) > 0 ? '+' : '') + dif(i) + ')'"></span></td>
                                                <td class="px-3 py-2 text-right" x-text="n.talla ? n.talla + ' m' : '—'"></td>
                                                <td class="px-3 py-2 text-right"><span x-text="n.imc ?? '—'"></span> <span class="text-xs text-gray-400" x-text="n.imc ? '· ' + categoriaImc(n.imc) : ''"></span></td>
                                                <td class="px-3 py-2 text-right" x-text="n.grasa ? n.grasa + ' %' : '—'"></td>
                                                <td class="px-3 py-2 text-xs" x-text="n.objetivo"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Nuevo / editar plan --}}
                        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3 text-sm">
                            <p class="font-bold text-gray-700" x-text="form.nut_id ? 'Editar plan de nutrición' : 'Nuevo plan de nutrición'"></p>
                            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                                <label class="col-span-2">Objetivo
                                    <select x-model="form.objetivo" class="{{ $in }} mt-1">@foreach ($objetivos as $o)<option>{{ $o }}</option>@endforeach</select></label>
                                <label>Fecha<input type="date" x-model="form.fecha" max="{{ now()->toDateString() }}" class="{{ $in }} mt-1"></label>
                                <label>Peso (kg)<input type="number" step="0.1" x-model.number="form.peso" class="{{ $in }} mt-1"></label>
                                <label>Talla (m)<input type="number" step="0.01" placeholder="1.70" x-model.number="form.talla" class="{{ $in }} mt-1"></label>
                                <label>% Grasa<input type="number" step="0.1" x-model.number="form.grasa" class="{{ $in }} mt-1"></label>
                                <label>Calorías/día<input type="number" step="50" x-model.number="form.calorias" class="{{ $in }} mt-1"></label>
                                <p class="col-span-2 sm:col-span-5 self-end text-xs text-gray-500 pb-2" x-show="imcForm" x-text="'IMC: ' + imcForm + ' (' + categoriaImc(imcForm) + ')'"></p>
                            </div>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <template x-for="k in comidas" :key="k[0]">
                                    <label><span x-text="k[1]"></span>
                                        <textarea x-model="form[k[0]]" rows="3" :placeholder="k[2]" class="{{ $in }} mt-1"></textarea></label>
                                </template>
                                <label class="sm:col-span-2 lg:col-span-1">Indicaciones
                                    <textarea x-model="form.indicaciones" rows="3" placeholder="Agua 2.5 L/día, evitar azúcar, suplementos…" class="{{ $in }} mt-1"></textarea></label>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="guardar()" :disabled="ocupado" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold disabled:opacity-50" x-text="ocupado ? 'Guardando…' : 'Guardar plan'"></button>
                                <button type="button" x-show="form.nut_id" @click="limpiar()" class="px-5 py-2.5 rounded-xl bg-gray-100 text-gray-600 font-semibold">Nuevo</button>
                            </div>
                        </div>

                        {{-- Historial --}}
                        <div class="space-y-3">
                            <p class="font-bold text-gray-700">Historial de planes</p>
                            <template x-for="n in sel.nutricion" :key="n.nut_id">
                                <div class="bg-white rounded-2xl shadow-sm p-5 text-sm">
                                    <div class="flex flex-wrap items-start gap-2">
                                        <div class="flex-1">
                                            <p class="font-bold text-gray-800" x-text="fecha(n.fecha) + ' · ' + n.objetivo"></p>
                                            <p class="text-xs text-gray-500" x-text="'Entrenador: ' + (n.entrenador || '—') + (n.calorias ? ' · ' + n.calorias + ' kcal/día' : '')"></p>
                                        </div>
                                        <template x-if="n.IdUsuario === yo || esAdmin">
                                            <div class="flex gap-1.5">
                                                <button type="button" @click="editar(n)" class="px-2.5 py-1 rounded-lg bg-gray-100 text-xs font-semibold">Editar</button>
                                                <button type="button" @click="quitar(n)" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">Quitar</button>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-3 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <template x-for="k in comidas.concat([['indicaciones', 'Indicaciones']])" :key="k[0]">
                                            <div x-show="n[k[0]]" class="rounded-xl bg-indigo-50/60 p-3">
                                                <p class="text-[11px] font-bold uppercase text-indigo-700" x-text="k[1]"></p>
                                                <p class="whitespace-pre-line text-gray-700" x-text="n[k[0]]"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!sel.nutricion.length" class="text-sm text-gray-400">Aún no tiene planes de nutrición.</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div x-show="aviso.visible" x-cloak x-transition class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-2xl text-white text-sm font-semibold"
             :class="aviso.ok ? 'bg-emerald-600' : 'bg-rose-600'" x-text="aviso.texto"></div>
    </div>

    <script>
        function entrenador() {
            const RUTA = @json(url('gimnasio/entrenador'));
            const CSRF = @json(csrf_token());
            const hoy = @json(now()->toDateString());
            const vacio = () => ({ nut_id: null, fecha: hoy, objetivo: @json($objetivos[0]), peso: null, talla: null, grasa: null, calorias: null,
                desayuno: '', media_manana: '', almuerzo: '', media_tarde: '', cena: '', indicaciones: '' });
            return {
                clientes: @js($clientes), yo: {{ (int) $yo }}, esAdmin: @json($esAdmin), q: '', vista: @js($clientes->where('mio', true)->isNotEmpty() ? 'mios' : 'todos'),
                sel: null, form: vacio(), ocupado: false, aviso: { visible: false, ok: true, texto: '' },
                comidas: [['desayuno', 'Desayuno', 'Avena con fruta, 2 huevos…'], ['media_manana', 'Media mañana', 'Yogur, frutos secos…'],
                    ['almuerzo', 'Almuerzo', 'Pechuga a la plancha, arroz integral, ensalada…'], ['media_tarde', 'Media tarde', 'Batido de proteína…'], ['cena', 'Cena', 'Pescado, verduras…']],
                get visibles() {
                    const q = this.q.trim().toLowerCase();
                    return this.clientes.filter(c => (this.vista === 'todos' || c.mio) && (!q || (c.nombre + ' ' + c.doc).toLowerCase().includes(q)));
                },
                get imcForm() { return this.form.peso && this.form.talla ? Math.round(this.form.peso / (this.form.talla * this.form.talla) * 10) / 10 : null; },
                categoriaImc(i) { return i < 18.5 ? 'bajo peso' : i < 25 ? 'normal' : i < 30 ? 'sobrepeso' : 'obesidad'; },
                dif(i) { const l = this.sel.nutricion.filter(n => n.peso); return l[i + 1] ? Math.round((l[i].peso - l[i + 1].peso) * 10) / 10 : null; },
                fecha(s) { if (!s) return ''; const [a, m, d] = s.slice(0, 10).split('-'); return `${d}/${m}/${a}`; },
                chip(c) { return { green: 'bg-emerald-100 text-emerald-700', amber: 'bg-amber-100 text-amber-800', sky: 'bg-sky-100 text-sky-700', red: 'bg-rose-100 text-rose-700' }[c] || 'bg-gray-100 text-gray-600'; },
                avisar(texto, ok = true) { if (window.tushpaAviso) return window.tushpaAviso(texto, ok); this.aviso = { visible: true, ok, texto }; clearTimeout(this._t); this._t = setTimeout(() => this.aviso.visible = false, 3500); },
                async post(url, datos = {}) {
                    try {
                        const r = await fetch(url, { method: 'POST', body: JSON.stringify(datos), headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF } });
                        const d = await r.json();
                        return r.status === 422 ? { ok: false, mensaje: Object.values(d.errors || {})[0]?.[0] || d.message } : d;
                    } catch (e) { return { ok: false, mensaje: 'Sin conexión con el servidor.' }; }
                },
                async abrir(id) {
                    const d = await fetch(RUTA + '/clientes/' + id, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (!d) return this.avisar('No se pudo abrir.', false);
                    this.sel = d;
                    // Se parte de las últimas medidas para no volver a escribir la talla
                    const ult = d.nutricion[0];
                    this.form = Object.assign(vacio(), ult ? { objetivo: ult.objetivo, talla: ult.talla ? Number(ult.talla) : null } : {});
                },
                editar(n) {
                    this.form = { nut_id: n.nut_id, fecha: n.fecha.slice(0, 10), objetivo: n.objetivo, peso: n.peso ? Number(n.peso) : null, talla: n.talla ? Number(n.talla) : null,
                        grasa: n.grasa ? Number(n.grasa) : null, calorias: n.calorias, desayuno: n.desayuno || '', media_manana: n.media_manana || '', almuerzo: n.almuerzo || '',
                        media_tarde: n.media_tarde || '', cena: n.cena || '', indicaciones: n.indicaciones || '' };
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                limpiar() { this.form = vacio(); },
                async guardar() {
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/clientes/' + this.sel.cliente.soc_id + '/nutricion', this.form);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { const c = this.clientes.find(x => x.soc_id === this.sel.cliente.soc_id); if (c) { c.ultimo_plan = this.form.fecha; if (!this.form.nut_id && !c.mio) c.mio = true; } await this.abrir(this.sel.cliente.soc_id); }
                },
                async quitar(n) {
                    if (!confirm('¿Quitar este plan? El cliente dejará de verlo.')) return;
                    const r = await this.post(RUTA + '/nutricion/' + n.nut_id + '/quitar');
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) await this.abrir(this.sel.cliente.soc_id);
                },
                async asignar(tomar) {
                    const r = await this.post(RUTA + '/clientes/' + this.sel.cliente.soc_id + '/asignar', { tomar: tomar ? 1 : 0 });
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.sel.cliente.mio = tomar; const c = this.clientes.find(x => x.soc_id === this.sel.cliente.soc_id); if (c) c.mio = tomar; }
                },
            };
        }
    </script>
@endsection
