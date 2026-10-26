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
                <button type="button" @click="vista = 'vip'" class="px-4 py-2 rounded-lg" :class="vista === 'vip' ? 'bg-amber-500 text-white' : 'text-gray-600'">⭐ VIP (<span x-text="clientes.filter(c => c.vip).length"></span>)</button>
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
                                <span class="block text-xs text-gray-400"><span x-show="c.vip" class="font-black text-amber-600">⭐ VIP · </span><span :class="c.rutina_mes ? 'text-emerald-600' : (c.vip ? 'text-rose-600 font-semibold' : '')" x-text="c.rutina_mes ? 'Rutina del mes ✓' : 'Sin rutina este mes'"></span><span x-text="c.edad ? ' · ' + c.edad + ' años' : ''"></span></span>
                            </span>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full" :class="chip(c.color)" x-text="c.estado"></span>
                        </button>
                    </template>
                    <p x-show="!visibles.length" class="p-8 text-center text-sm text-gray-400" x-text="vista === 'mios' ? 'Aún no tienes clientes. Ve a Todos y toma a tus clientes.' : 'Sin resultados.'"></p>
                </div>
            </div>

            {{-- Cliente --}}
            <div class="space-y-5 min-w-0">
                <div x-show="!sel" class="bg-white rounded-2xl shadow-sm p-12 text-center text-gray-400">
                    <i class="fas fa-hand-pointer text-4xl"></i><p class="mt-3">Elige un cliente para armarle su rutina del mes, tomarle sus medidas o hacerle su plan de nutrición.</p>
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
                            <div class="flex flex-col gap-2">
                                <button type="button" @click="asignar(!sel.cliente.mio)" class="px-4 py-2 rounded-xl text-sm font-bold"
                                        :class="sel.cliente.mio ? 'bg-gray-100 text-gray-600' : 'bg-indigo-600 text-white'" x-text="sel.cliente.mio ? 'Quitar de mis clientes' : 'Tomar como mi cliente'"></button>
                                <a :href="sel.hoja" target="_blank" class="px-4 py-2 rounded-xl text-sm font-bold bg-gray-900 text-white text-center"><i class="fas fa-print"></i> Imprimir hoja</a>
                            </div>
                        </div>

                        <div class="flex bg-white rounded-xl shadow-sm p-1 text-sm font-semibold w-fit">
                            <template x-for="t in [['rutina', '💪 Rutina del mes'], ['medidas', '📏 Ficha y medidas'], ['nutricion', '🥗 Nutrición']]">
                                <button type="button" @click="tabC = t[0]" class="px-4 py-2 rounded-lg" :class="tabC === t[0] ? 'bg-indigo-600 text-white' : 'text-gray-600'" x-text="t[1]"></button>
                            </template>
                        </div>

                        {{-- ================= Rutina del mes ================= --}}
                        <div x-show="tabC === 'rutina'" class="space-y-5">
                            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-bold text-gray-700 flex-1" x-text="rut.rut_id ? 'Editar rutina' : 'Nueva rutina del mes'"></p>
                                    <button type="button" x-show="sel.rutinas.length && !rut.rut_id" @click="copiarUltima()" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 font-semibold"><i class="fas fa-copy"></i> Copiar la última rutina</button>
                                    <button type="button" x-show="rut.rut_id" @click="rut = rutinaVacia()" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 font-semibold">Nueva</button>
                                </div>
                                <div class="grid sm:grid-cols-3 gap-3">
                                    <label>Mes<input type="month" x-model="rut.mes" class="{{ $in }} mt-1"></label>
                                    <label class="sm:col-span-2">Objetivo
                                        <select x-model="rut.objetivo" class="{{ $in }} mt-1"><option value="">—</option>@foreach ($objetivos as $o)<option>{{ $o }}</option>@endforeach</select></label>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-600 mb-1">Calentamiento (minutos)</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        @foreach ($calentamiento as $k => $nom)
                                            <label class="rounded-xl bg-gray-50 p-2 text-xs font-semibold text-gray-600">{{ $nom }}
                                                <input type="number" min="0" max="120" x-model.number="rut.calentamiento['{{ $k }}']" placeholder="0" class="{{ $in }} mt-1 text-center"></label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="(d, i) in rut.dias" :key="i">
                                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3 space-y-2">
                                            <div class="flex flex-wrap gap-2 items-center">
                                                <select x-model="d.dia" class="rounded-lg border-gray-300 text-sm font-bold">@foreach ($diasSemana as $dia)<option>{{ $dia }}</option>@endforeach</select>
                                                <input x-model="d.grupo" maxlength="60" placeholder="Grupo: PECHO Y TRÍCEPS, PIERNAS…" class="flex-1 min-w-40 rounded-lg border-gray-300 text-sm uppercase">
                                                <button type="button" @click="rut.dias.splice(i, 1)" class="px-2 py-1 rounded-lg text-rose-600 text-xs font-semibold">Quitar día</button>
                                            </div>
                                            <div class="space-y-1.5">
                                                <template x-for="(e, j) in d.ejercicios" :key="j">
                                                    <div class="grid grid-cols-[1fr_60px_80px_1fr_auto] gap-1.5">
                                                        <input x-model="e.nombre" list="ejercicios_comunes" maxlength="80" placeholder="Ejercicio" class="rounded-lg border-gray-300 text-sm uppercase">
                                                        <input x-model="e.series" maxlength="10" placeholder="Series" class="rounded-lg border-gray-300 text-sm text-center">
                                                        <input x-model="e.reps" maxlength="20" placeholder="Reps" class="rounded-lg border-gray-300 text-sm text-center">
                                                        <input x-model="e.nota" maxlength="80" placeholder="Peso / nota" class="rounded-lg border-gray-300 text-sm">
                                                        <button type="button" @click="d.ejercicios.splice(j, 1)" class="px-2 text-gray-400 hover:text-rose-600">&times;</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <button type="button" @click="d.ejercicios.push(ejercicioVacio())" class="text-xs font-semibold text-indigo-700">+ Agregar ejercicio</button>
                                        </div>
                                    </template>
                                    <button type="button" x-show="rut.dias.length < 7" @click="agregarDia()" class="w-full py-2 rounded-xl border-2 border-dashed border-indigo-200 text-indigo-700 font-semibold">+ Agregar día</button>
                                </div>
                                <label class="block">Indicaciones
                                    <textarea x-model="rut.indicaciones" rows="2" placeholder="Descanso 60 s entre series, hidratarse, estirar al final…" class="{{ $in }} mt-1"></textarea></label>
                                <button type="button" @click="guardarRutina()" :disabled="ocupado" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold disabled:opacity-50" x-text="ocupado ? 'Guardando…' : 'Guardar rutina'"></button>
                            </div>
                            <div class="space-y-3">
                                <p class="font-bold text-gray-700">Rutinas</p>
                                <template x-for="r in sel.rutinas" :key="r.rut_id">
                                    <div class="bg-white rounded-2xl shadow-sm p-4 text-sm">
                                        <div class="flex flex-wrap items-start gap-2">
                                            <div class="flex-1">
                                                <p class="font-bold text-gray-800"><span x-text="mesTexto(r.desde)"></span>
                                                    <span x-show="r.vigente" class="ml-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black">ESTE MES</span></p>
                                                <p class="text-xs text-gray-500" x-text="(r.objetivo || '') + ' · ' + r.dias.length + ' días · Entrenador: ' + (r.entrenador || '—')"></p>
                                            </div>
                                            <template x-if="r.IdUsuario === yo || esAdmin">
                                                <div class="flex gap-1.5">
                                                    <button type="button" @click="editarRutina(r)" class="px-2.5 py-1 rounded-lg bg-gray-100 text-xs font-semibold">Editar</button>
                                                    <button type="button" @click="quitarRutina(r)" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">Quitar</button>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="mt-2 grid sm:grid-cols-2 gap-2">
                                            <template x-for="d in r.dias">
                                                <div class="rounded-xl bg-indigo-50/60 p-2.5">
                                                    <p class="text-[11px] font-black text-indigo-700" x-text="d.dia + (d.grupo ? ' · ' + d.grupo : '')"></p>
                                                    <template x-for="e in d.ejercicios"><p class="text-xs text-gray-700" x-text="e.nombre + (e.series ? ' · ' + e.series + 'x' + (e.reps || '') : '') + (e.nota ? ' · ' + e.nota : '')"></p></template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="!sel.rutinas.length" class="text-sm text-gray-400">Aún no tiene rutinas.</p>
                            </div>
                        </div>

                        {{-- ================= Ficha y medidas ================= --}}
                        <div x-show="tabC === 'medidas'" class="space-y-5 text-sm">
                            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                                <p class="font-bold text-gray-700">Ficha de evaluación</p>
                                <label class="block sm:w-1/2">Objetivo
                                    <select x-model="fic.objetivo" class="{{ $in }} mt-1"><option value="">—</option>@foreach ($objetivos as $o)<option>{{ $o }}</option>@endforeach</select></label>
                                <p class="font-semibold text-gray-600">Antecedentes médicos</p>
                                <div class="grid sm:grid-cols-2 gap-1.5">
                                    @foreach ($antecedentes as $k => $nom)
                                        <label class="flex items-center gap-2"><input type="checkbox" value="{{ $k }}" x-model="fic.antecedentes" class="rounded text-rose-600"> {{ $nom }}</label>
                                    @endforeach
                                </div>
                                <label class="block">Otros<input x-model="fic.otros" maxlength="150" class="{{ $in }} mt-1 uppercase"></label>
                                <label class="block">Observaciones importantes
                                    <textarea x-model="fic.observaciones" rows="2" maxlength="500" class="{{ $in }} mt-1"></textarea></label>
                                <button type="button" @click="guardarFicha()" :disabled="ocupado" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold disabled:opacity-50">Guardar ficha</button>
                            </div>

                            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                                <p class="font-bold text-gray-700" x-text="med.med_id ? 'Editar medidas' : 'Nuevas medidas'"></p>
                                <div class="grid grid-cols-2 sm:grid-cols-6 gap-2">
                                    <label>Fecha<input type="date" x-model="med.fecha" max="{{ now()->toDateString() }}" class="{{ $in }} mt-1"></label>
                                    <label>Edad<input type="number" x-model.number="med.edad" class="{{ $in }} mt-1"></label>
                                    <label>Peso (kg)<input type="number" step="0.1" x-model.number="med.peso" class="{{ $in }} mt-1"></label>
                                    <label>Estatura (m)<input type="number" step="0.01" placeholder="1.70" x-model.number="med.estatura" class="{{ $in }} mt-1"></label>
                                    <label>% Grasa<input type="number" step="0.1" x-model.number="med.grasa" class="{{ $in }} mt-1"></label>
                                    <p class="self-end pb-2 text-xs font-bold text-indigo-700" x-text="imcMed ? 'IMC ' + imcMed + ' · ' + categoriaImc(imcMed) : ''"></p>
                                    @foreach ($perimetros as $k => $nom)
                                        <label>{{ $nom }} (cm)<input type="number" step="0.1" x-model.number="med.{{ $k }}" class="{{ $in }} mt-1"></label>
                                    @endforeach
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="guardarMedida()" :disabled="ocupado" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold disabled:opacity-50">Guardar medidas</button>
                                    <button type="button" x-show="med.med_id" @click="med = medidaVacia()" class="px-5 py-2.5 rounded-xl bg-gray-100 text-gray-600 font-semibold">Nueva</button>
                                </div>
                            </div>

                            <div class="bg-white rounded-2xl shadow-sm p-5" x-show="sel.medidas.length">
                                <p class="font-bold text-gray-700 mb-3">Evolución de medidas</p>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead class="text-gray-500 bg-gray-50"><tr>
                                            <th class="px-2 py-2 text-left">Fecha</th><th class="px-2 py-2">Edad</th><th class="px-2 py-2">Peso</th><th class="px-2 py-2">Estatura</th>
                                            @foreach ($perimetros as $nom)<th class="px-2 py-2">{{ $nom }}</th>@endforeach
                                            <th class="px-2 py-2">% Grasa</th><th class="px-2 py-2">IMC</th><th></th></tr></thead>
                                        <tbody class="divide-y">
                                            <template x-for="m in sel.medidas" :key="m.med_id">
                                                <tr class="text-center">
                                                    <td class="px-2 py-2 text-left font-semibold" x-text="fecha(m.fecha)"></td>
                                                    <td class="px-2 py-2" x-text="m.edad ?? '—'"></td>
                                                    <td class="px-2 py-2" x-text="m.peso ? m.peso + ' kg' : '—'"></td>
                                                    <td class="px-2 py-2" x-text="m.estatura ? m.estatura + ' m' : '—'"></td>
                                                    @foreach (array_keys($perimetros) as $k)<td class="px-2 py-2" x-text="m.{{ $k }} ?? '—'"></td>@endforeach
                                                    <td class="px-2 py-2" x-text="m.grasa ? m.grasa + '%' : '—'"></td>
                                                    <td class="px-2 py-2 font-bold" x-text="m.imc ?? '—'"></td>
                                                    <td class="px-2 py-2 whitespace-nowrap">
                                                        <button type="button" @click="editarMedida(m)" class="text-indigo-600 font-semibold">Editar</button>
                                                        <button type="button" @click="quitarMedida(m)" class="text-rose-600 font-semibold ml-1">Quitar</button></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ================= Nutrición ================= --}}
                        <div x-show="tabC === 'nutricion'" class="space-y-5">
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
                    </div>
                </template>
            </div>
        </div>

        <datalist id="ejercicios_comunes">
            @foreach (['PRESS DE BANCA', 'PRESS INCLINADO CON MANCUERNAS', 'APERTURAS', 'FONDOS', 'DOMINADAS', 'JALÓN AL PECHO', 'REMO CON BARRA', 'REMO EN POLEA', 'PESO MUERTO',
                'SENTADILLA', 'PRENSA DE PIERNAS', 'ZANCADAS', 'EXTENSIÓN DE CUÁDRICEPS', 'CURL FEMORAL', 'ELEVACIÓN DE PANTORRILLAS', 'HIP THRUST',
                'PRESS MILITAR', 'ELEVACIONES LATERALES', 'FACE PULL', 'CURL CON BARRA', 'CURL MARTILLO', 'EXTENSIÓN DE TRÍCEPS EN POLEA', 'PRESS FRANCÉS',
                'ABDOMINALES CRUNCH', 'PLANCHA', 'ELEVACIÓN DE PIERNAS', 'BURPEES', 'SALTO CON CUERDA'] as $e)
                <option value="{{ $e }}">
            @endforeach
        </datalist>

        <div x-show="aviso.visible" x-cloak x-transition class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-2xl text-white text-sm font-semibold"
             :class="aviso.ok ? 'bg-emerald-600' : 'bg-rose-600'" x-text="aviso.texto"></div>
    </div>

    <script>
        function entrenador() {
            const RUTA = @json(url('gimnasio/entrenador'));
            const CSRF = @json(csrf_token());
            const hoy = @json(now()->toDateString());
            const DIAS = @json($diasSemana);
            const vacio = () => ({ nut_id: null, fecha: hoy, objetivo: @json($objetivos[0]), peso: null, talla: null, grasa: null, calorias: null,
                desayuno: '', media_manana: '', almuerzo: '', media_tarde: '', cena: '', indicaciones: '' });
            return {
                clientes: @js($clientes), yo: {{ (int) $yo }}, esAdmin: @json($esAdmin), q: '', vista: @js($clientes->where('mio', true)->isNotEmpty() ? 'mios' : 'todos'),
                sel: null, form: vacio(), ocupado: false, aviso: { visible: false, ok: true, texto: '' },
                comidas: [['desayuno', 'Desayuno', 'Avena con fruta, 2 huevos…'], ['media_manana', 'Media mañana', 'Yogur, frutos secos…'],
                    ['almuerzo', 'Almuerzo', 'Pechuga a la plancha, arroz integral, ensalada…'], ['media_tarde', 'Media tarde', 'Batido de proteína…'], ['cena', 'Cena', 'Pescado, verduras…']],
                get visibles() {
                    const q = this.q.trim().toLowerCase();
                    return this.clientes.filter(c => (this.vista === 'todos' || (this.vista === 'vip' ? c.vip : c.mio)) && (!q || (c.nombre + ' ' + c.doc).toLowerCase().includes(q)));
                },
                // ---- rutina del mes
                tabC: 'rutina', rut: null, fic: null, med: null,
                rutinaVacia() { return { rut_id: null, mes: hoy.slice(0, 7), objetivo: '', calentamiento: {}, dias: [{ dia: 'LUNES', grupo: '', ejercicios: [this.ejercicioVacio()] }], indicaciones: '' }; },
                ejercicioVacio() { return { nombre: '', series: '4', reps: '12', nota: '' }; },
                agregarDia() {
                    const usados = this.rut.dias.map(d => d.dia);
                    this.rut.dias.push({ dia: DIAS.find(d => !usados.includes(d)) || 'LUNES', grupo: '', ejercicios: [this.ejercicioVacio()] });
                },
                mesTexto(s) { const m = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']; return m[+s.slice(5, 7) - 1] + ' ' + s.slice(0, 4); },
                copia(r) { return JSON.parse(JSON.stringify({ objetivo: r.objetivo || '', calentamiento: Array.isArray(r.calentamiento) ? {} : r.calentamiento, dias: r.dias, indicaciones: r.indicaciones || '' })); },
                copiarUltima() { this.rut = Object.assign(this.rutinaVacia(), this.copia(this.sel.rutinas[0])); this.avisar('Se copió la última rutina: cámbiala y guárdala para este mes.'); },
                editarRutina(r) { this.rut = Object.assign(this.rutinaVacia(), this.copia(r), { rut_id: r.rut_id, mes: r.desde.slice(0, 7) }); window.scrollTo({ top: 0, behavior: 'smooth' }); },
                async guardarRutina() {
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/clientes/' + this.sel.cliente.soc_id + '/rutina', this.rut);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { const c = this.clientes.find(x => x.soc_id === this.sel.cliente.soc_id); if (c && this.rut.mes === hoy.slice(0, 7)) c.rutina_mes = true; await this.abrir(this.sel.cliente.soc_id, 'rutina'); }
                },
                async quitarRutina(r) {
                    if (!confirm('¿Quitar esta rutina? El cliente dejará de verla.')) return;
                    const res = await this.post(RUTA + '/rutinas/' + r.rut_id + '/quitar');
                    this.avisar(res.mensaje, res.ok);
                    if (res.ok) await this.abrir(this.sel.cliente.soc_id, 'rutina');
                },
                // ---- ficha y medidas
                medidaVacia(d = this.sel) { return { med_id: null, fecha: hoy, edad: d?.cliente.edad ?? null, peso: null, estatura: d?.medidas[0]?.estatura ? Number(d.medidas[0].estatura) : null,
                    torax: null, cintura: null, cadera: null, muslo: null, pantorrilla: null, brazo: null, grasa: null }; },
                get imcMed() { return this.med && this.med.peso && this.med.estatura ? Math.round(this.med.peso / (this.med.estatura * this.med.estatura) * 10) / 10 : null; },
                editarMedida(m) { this.med = Object.fromEntries(Object.keys(this.medidaVacia()).map(k => [k, k === 'fecha' ? m.fecha.slice(0, 10) : (m[k] === null ? null : (k === 'med_id' ? m[k] : Number(m[k])))])); },
                async guardarMedida() {
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/clientes/' + this.sel.cliente.soc_id + '/medidas', this.med);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) await this.abrir(this.sel.cliente.soc_id, 'medidas');
                },
                async quitarMedida(m) {
                    if (!confirm('¿Quitar las medidas del ' + this.fecha(m.fecha) + '?')) return;
                    const r = await this.post(RUTA + '/medidas/' + m.med_id + '/quitar');
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) await this.abrir(this.sel.cliente.soc_id, 'medidas');
                },
                async guardarFicha() {
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/clientes/' + this.sel.cliente.soc_id + '/ficha', this.fic);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
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
                async abrir(id, tab = null) {
                    const d = await fetch(RUTA + '/clientes/' + id, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (!d) return this.avisar('No se pudo abrir.', false);
                    this.tabC = tab || this.tabC;
                    // Antes de mostrar al cliente: su rutina de este mes para editarla (o una nueva), su ficha y medidas nuevas
                    const actual = d.rutinas.find(r => r.vigente);
                    this.rut = actual ? Object.assign(this.rutinaVacia(), this.copia(actual), { rut_id: actual.rut_id, mes: actual.desde.slice(0, 7) }) : this.rutinaVacia();
                    this.fic = { objetivo: d.ficha?.objetivo || '', antecedentes: d.ficha?.antecedentes || [], otros: d.ficha?.otros || '', observaciones: d.ficha?.observaciones || '' };
                    this.med = this.medidaVacia(d);
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
