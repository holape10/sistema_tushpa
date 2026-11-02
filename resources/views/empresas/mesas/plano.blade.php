@extends('layouts.app')
@section('title', 'Plano del local')
@section('content')
@use('App\Support\PlanoMesas')
    {{-- En el celular el dedo arrastra la mesa, no la página --}}
    <style>.plano-item { touch-action: none; -webkit-user-select: none; user-select: none; }</style>
    <div class="space-y-4" x-data="planoEditor({ mesas: @js($mesas), elementos: @js($elementos), usaPlano: @js($usaPlano), alto: @js($alto), ancho: @js($ancho),
            formas: @js(PlanoMesas::FORMAS), tipos: @js(PlanoMesas::ELEMENTOS), url: @js($piso ? route('mesas.plano.guardar', $piso->pis_id) : null) })"
         @keydown.window.delete="if (!['INPUT','TEXTAREA'].includes($event.target.tagName)) quitar()">
        <section class="rounded-3xl bg-gradient-to-br from-slate-800 to-indigo-900 text-white p-5 sm:p-6 shadow-lg">
            <p class="text-indigo-200 text-sm font-semibold uppercase tracking-wide">🗺️ Plano del local</p>
            <h1 class="text-2xl font-black mt-1">Dibuja tu local como es de verdad</h1>
            <p class="text-indigo-100 text-sm mt-1 max-w-3xl">Arrastra las mesas a su lugar, agrándalas desde la esquina <b>◢</b>, crea boxes VIP y pon la barra, paredes, el DJ y la entrada.
                Así el mozo nuevo ubica las mesas al toque. Los boxes y mesas que crees aquí se atienden igual que las demás.</p>
        </section>

        @if (! $piso)
            <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">Primero crea un piso o salón en <a href="{{ route('pisos.index') }}" class="text-indigo-600 font-semibold underline">Pisos</a>.</p>
        @else
            {{-- Pisos y guardar --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($pisos as $p)
                    <a href="{{ route('mesas.plano', ['piso' => $p->pis_id]) }}" @click="if (cambios && !confirm('Tienes cambios sin guardar en este piso. ¿Salir igual?')) $event.preventDefault()"
                       class="h-10 px-4 inline-flex items-center rounded-full text-sm font-bold {{ $p->pis_id === $piso->pis_id ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 shadow-sm' }}">{{ $p->pis_nom }}</a>
                @endforeach
                <label class="ml-auto flex items-center gap-2 text-sm font-semibold text-gray-700 bg-white rounded-full px-4 h-10 shadow-sm cursor-pointer">
                    <input type="checkbox" x-model="usaPlano" @change="cambios = true" class="rounded text-indigo-600"> Ver las mesas en el plano en Comandas
                </label>
                <button type="button" @click="guardar()" :disabled="guardando" class="h-10 px-5 rounded-full bg-emerald-600 text-white text-sm font-black shadow-sm disabled:opacity-60"
                        x-text="guardando ? 'Guardando…' : (cambios ? '💾 Guardar plano *' : '💾 Guardar plano')"></button>
            </div>
            <p x-show="mensaje" x-text="mensaje" class="rounded-xl px-4 py-2 text-sm font-semibold" :class="error ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"></p>

            <div class="grid xl:grid-cols-[1fr_280px] gap-4 items-start">
                {{-- Lienzo --}}
                <div class="bg-white rounded-2xl shadow-sm p-3 overflow-x-auto">
                    <div x-ref="lienzo" class="relative w-full rounded-xl bg-slate-50 ring-1 ring-slate-200 select-none overflow-hidden"
                         :style="'aspect-ratio: ' + ancho + ' / ' + alto + '; width: ' + (100 * ancho / Math.max(ancho, 1000)) + '%; min-width: ' + Math.round(560 * ancho / Math.max(ancho, 1000)) + 'px; background-image: radial-gradient(#94a3b8 1.2px, transparent 1.2px); background-size: ' + (5000 / ancho) + '% ' + (5000 / alto) + '%;'"
                         @pointerdown.self="sel = null">
                        {{-- Barra, paredes, DJ... (debajo de las mesas) --}}
                        <template x-for="(e, i) in elementos" :key="'e' + i">
                            <div class="plano-item absolute flex items-center justify-center text-center font-bold leading-tight cursor-move"
                                 :style="pos(e) + ';' + estiloElemento(e)" :class="esSel('elem', i) ? 'ring-4 ring-indigo-400 z-30' : 'z-10'"
                                 @pointerdown.stop="iniciar($event, 'elem', i, 'mover')">
                                <span class="px-1 pointer-events-none" style="font-size: clamp(9px, 1.1vw, 15px)" x-text="(tipos[e.tipo][0] ? tipos[e.tipo][0] + ' ' : '') + (e.texto ?? '')"></span>
                                <span class="plano-item absolute -right-2 -bottom-2 w-6 h-6 sm:w-4 sm:h-4 sm:-right-1 sm:-bottom-1 bg-indigo-600 rounded-md cursor-se-resize" x-show="esSel('elem', i)" @pointerdown.stop="iniciar($event, 'elem', i, 'tamano')"></span>
                            </div>
                        </template>
                        {{-- Mesas y boxes --}}
                        <template x-for="(m, i) in mesas" :key="'m' + i">
                            <div x-show="m.ubicada" class="plano-item absolute flex flex-col items-center justify-center text-white font-black shadow-md cursor-move"
                                 :style="pos(m)" :class="[claseMesa(m), esSel('mesa', i) ? 'ring-4 ring-indigo-400 z-40' : 'z-20']"
                                 @pointerdown.stop="iniciar($event, 'mesa', i, 'mover')">
                                <span x-show="m.forma === 'BOX'" class="pointer-events-none text-[10px] font-black text-amber-200 tracking-widest">VIP</span>
                                <span class="pointer-events-none px-1 text-center leading-tight" style="font-size: clamp(9px, 1.1vw, 15px)" x-text="m.nombre"></span>
                                <span class="plano-item absolute -right-2 -bottom-2 w-6 h-6 sm:w-4 sm:h-4 sm:-right-1 sm:-bottom-1 bg-indigo-600 rounded-md cursor-se-resize" x-show="esSel('mesa', i)" @pointerdown.stop="iniciar($event, 'mesa', i, 'tamano')"></span>
                            </div>
                        </template>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Toca algo para elegirlo · arrástralo para moverlo · la esquina ◢ cambia el tamaño · en el celular también puedes usar los botones de "Mover".</p>
                </div>

                {{-- Herramientas --}}
                <div class="space-y-4">
                    <div class="bg-white rounded-2xl shadow-sm p-4" x-show="sel" x-cloak>
                        <template x-if="sel && sel.kind === 'mesa'">
                            <div class="space-y-3">
                                <p class="font-bold text-gray-800">Mesa elegida <span class="text-xs font-normal text-gray-500" x-text="metros(actual().w) + ' × ' + metros(actual().h) + ' m'"></span></p>
                                <label class="block text-sm text-gray-600">Nombre
                                    <input x-model="actual().nombre" @input="cambios = true" maxlength="40" class="mt-1 w-full h-10 rounded-lg border-gray-300 uppercase font-bold"></label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <template x-for="(f, clave) in formas" :key="clave">
                                        <button type="button" @click="cambiarForma(clave)" class="h-9 rounded-lg text-xs font-bold"
                                                :class="actual().forma === clave ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" x-text="f[0]"></button>
                                    </template>
                                </div>
                                @include('empresas.mesas.partials.plano_mover')
                                <div class="flex gap-2">
                                    <button type="button" @click="duplicar()" class="flex-1 h-9 rounded-lg bg-gray-100 text-xs font-bold text-gray-700">⧉ Otra igual</button>
                                    <button type="button" @click="quitar()" x-show="actual().id" class="flex-1 h-9 rounded-lg bg-amber-50 text-xs font-bold text-amber-800">Sacar del plano</button>
                                </div>
                                <button type="button" @click="eliminarMesa()" class="w-full h-9 rounded-lg bg-rose-600 text-xs font-bold text-white">🗑️ Eliminar mesa</button>
                                <p class="text-[11px] text-gray-400" x-show="actual().id">"Sacar del plano" la deja en "Sin ubicar" (sigue existiendo). "Eliminar" la borra al guardar (si está ocupada, no se puede).</p>
                            </div>
                        </template>
                        <template x-if="sel && sel.kind === 'elem'">
                            <div class="space-y-3">
                                <p class="font-bold text-gray-800" x-text="tipos[actual().tipo][1]"></p>
                                <label class="block text-sm text-gray-600">Texto
                                    <input x-model="actual().texto" @input="cambios = true" maxlength="40" class="mt-1 w-full h-10 rounded-lg border-gray-300"></label>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="c in colores" :key="c">
                                        <button type="button" @click="actual().color = c; cambios = true" class="w-7 h-7 rounded-full ring-1 ring-gray-300"
                                                :class="actual().color === c ? 'ring-4 ring-indigo-400' : ''" :style="'background:' + c"></button>
                                    </template>
                                </div>
                                @include('empresas.mesas.partials.plano_mover')
                                <div class="flex gap-2">
                                    <button type="button" @click="duplicar()" class="flex-1 h-9 rounded-lg bg-gray-100 text-xs font-bold text-gray-700">⧉ Otro igual</button>
                                    <button type="button" @click="quitar()" class="flex-1 h-9 rounded-lg bg-rose-600 text-xs font-bold text-white">🗑️ Eliminar</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
                        <p class="font-bold text-gray-800">📏 Medidas del local</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs font-semibold text-gray-600">↔ Ancho
                                <span class="mt-1 flex items-center gap-1"><input type="number" min="2" max="60" step="0.5" :value="metros(ancho)" @change="cambiarMedidas(Number($event.target.value), metros(alto))"
                                    class="w-full h-10 rounded-lg border-gray-300 text-center font-bold"> m</span></label>
                            <label class="text-xs font-semibold text-gray-600">↕ Largo
                                <span class="mt-1 flex items-center gap-1"><input type="number" min="2" max="60" step="0.5" :value="metros(alto)" @change="cambiarMedidas(metros(ancho), Number($event.target.value))"
                                    class="w-full h-10 rounded-lg border-gray-300 text-center font-bold"> m</span></label>
                        </div>
                        <p class="text-[11px] text-gray-500">Las medidas reales del ambiente. Las mesas conservan su tamaño: si achicas el local, queda menos espacio alrededor. Cada punto de la cuadrícula es 1 metro.</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
                        <p class="font-bold text-gray-800">➕ Mesas nuevas</p>
                        <div class="grid grid-cols-2 gap-1.5">
                            <template x-for="(f, clave) in formas" :key="clave">
                                <button type="button" @click="agregarMesa(clave)" class="h-10 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-bold hover:bg-emerald-100" x-text="'+ ' + f[0]"></button>
                            </template>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-4 space-y-2">
                        <p class="font-bold text-gray-800">🧱 Partes del local</p>
                        <div class="grid grid-cols-2 gap-1.5">
                            <template x-for="(t, clave) in tipos" :key="clave">
                                <button type="button" @click="agregarElemento(clave)" class="h-10 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200" x-text="(t[0] ? t[0] + ' ' : '') + t[1]"></button>
                            </template>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-4 space-y-2" x-show="sinUbicar().length">
                        <p class="font-bold text-gray-800">📍 Sin ubicar <span class="text-xs font-normal text-gray-500">(toca para ponerla en el plano)</span></p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="m in sinUbicar()" :key="m.id">
                                <span class="inline-flex rounded-lg bg-amber-50 overflow-hidden">
                                    <button type="button" @click="ubicar(m)" class="h-9 px-3 text-amber-800 text-xs font-bold hover:bg-amber-100" x-text="m.nombre"></button>
                                    <button type="button" @click="eliminarMesa(m)" title="Eliminar esta mesa" class="h-9 px-2 text-rose-600 text-xs hover:bg-rose-100">🗑️</button>
                                </span>
                            </template>
                        </div>
                        <button type="button" @click="sinUbicar().forEach(m => ubicar(m))" class="text-xs font-semibold text-indigo-600">Ubicar todas</button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        function planoEditor(cfg) {
            const POR_METRO = {{ PlanoMesas::POR_METRO }}, MEDIDA_MIN = {{ PlanoMesas::MEDIDA_MIN }}, MEDIDA_MAX = {{ PlanoMesas::MEDIDA_MAX }};
            const paso = v => Math.round(v / 5) * 5;
            const entre = (v, min, max) => Math.max(min, Math.min(max, v));
            return {
                ...cfg, sel: null, cambios: false, guardando: false, mensaje: '', error: false, eliminar: [],
                colores: ['#111827', '#475569', '#7c2d12', '#b91c1c', '#c2410c', '#ca8a04', '#15803d', '#0369a1', '#1d4ed8', '#7e22ce', '#db2777'],
                init() {
                    window.addEventListener('beforeunload', e => { if (this.cambios) { e.preventDefault(); e.returnValue = ''; } });
                },
                pos(o) { return `left:${o.x / this.ancho * 100}%;top:${o.y / this.alto * 100}%;width:${o.w / this.ancho * 100}%;height:${o.h / this.alto * 100}%`; },
                claseMesa(m) {
                    return { REDONDA: 'rounded-full bg-emerald-600', CUADRADA: 'rounded-xl bg-emerald-600', RECTANGULAR: 'rounded-xl bg-emerald-600',
                        BOX: 'rounded-2xl bg-violet-700 border-2 border-amber-300' }[m.forma] || 'rounded-xl bg-emerald-600';
                },
                estiloElemento(e) {
                    const c = e.color || this.tipos[e.tipo][2];
                    if (e.tipo === 'PARED') return `background:${c};border-radius:3px`;
                    if (e.tipo === 'TEXTO') return `color:${c}`;
                    if (e.tipo === 'BARRA') return `background:${c};color:#fff;border-radius:10px`;
                    return `background:${c}22;border:2px solid ${c};color:${c};border-radius:12px`;
                },
                esSel(kind, i) { return this.sel && this.sel.kind === kind && this.sel.idx === i; },
                actual() { return this.sel ? (this.sel.kind === 'mesa' ? this.mesas[this.sel.idx] : this.elementos[this.sel.idx]) : {}; },
                sinUbicar() { return this.mesas.filter(m => !m.ubicada && m.id); },
                /** Que nada quede fuera del lienzo (al moverlo o al achicar el ambiente) */
                acomodar(o) {
                    o.w = entre(o.w, 10, this.ancho); o.h = entre(o.h, 10, this.alto);
                    o.x = entre(o.x, 0, this.ancho - o.w); o.y = entre(o.y, 0, this.alto - o.h);
                },
                /** Unidades del plano a metros (1 unidad = 2 cm) */
                metros(u) { return Math.round((Number(u) || 0) / POR_METRO * 10) / 10; },
                /** Medidas reales del local: las mesas conservan su tamaño; lo que queda fuera se acomoda adentro */
                cambiarMedidas(anchoM, largoM) {
                    if (!(anchoM > 0) || !(largoM > 0)) return;
                    this.ancho = entre(paso(anchoM * POR_METRO), MEDIDA_MIN, MEDIDA_MAX);
                    this.alto = entre(paso(largoM * POR_METRO), MEDIDA_MIN, MEDIDA_MAX);
                    [...this.mesas.filter(m => m.ubicada), ...this.elementos].forEach(o => this.acomodar(o));
                    this.cambios = true;
                },
                /** Mover o agrandar con el dedo o el mouse (en pasos de 5, sin salir del lienzo) */
                iniciar(e, kind, idx, modo) {
                    e.preventDefault();
                    this.sel = { kind, idx };
                    const o = kind === 'mesa' ? this.mesas[idx] : this.elementos[idx];
                    // En el celular el dedo queda "pegado" a la mesa aunque salga de ella
                    try { e.currentTarget.setPointerCapture(e.pointerId); } catch (err) { /* sin captura: igual funciona con el mouse */ }
                    const escala = this.ancho / this.$refs.lienzo.getBoundingClientRect().width;
                    const ini = { px: e.clientX, py: e.clientY, x: o.x, y: o.y, w: o.w, h: o.h };
                    const minimo = kind === 'mesa' ? 20 : 10;
                    const mover = ev => {
                        const dx = (ev.clientX - ini.px) * escala, dy = (ev.clientY - ini.py) * escala;
                        if (Math.abs(dx) + Math.abs(dy) < 2) return;
                        ev.preventDefault();
                        if (modo === 'mover') {
                            o.x = entre(paso(ini.x + dx), 0, this.ancho - o.w);
                            o.y = entre(paso(ini.y + dy), 0, this.alto - o.h);
                        } else {
                            o.w = entre(paso(ini.w + dx), minimo, this.ancho - o.x);
                            o.h = entre(paso(ini.h + dy), minimo, this.alto - o.y);
                            if (o.forma === 'REDONDA') o.h = o.w = Math.min(o.w, this.alto - o.y);
                        }
                        this.cambios = true;
                    };
                    const soltar = () => {
                        window.removeEventListener('pointermove', mover);
                        window.removeEventListener('pointerup', soltar);
                        window.removeEventListener('pointercancel', soltar);
                    };
                    window.addEventListener('pointermove', mover, { passive: false });
                    window.addEventListener('pointerup', soltar);
                    window.addEventListener('pointercancel', soltar);
                },
                /** Botones de mover y de tamaño (fácil en el celular) */
                mover(dx, dy) {
                    const o = this.actual();
                    o.x = entre(o.x + dx, 0, this.ancho - o.w); o.y = entre(o.y + dy, 0, this.alto - o.h);
                    this.cambios = true;
                },
                crecer(d) {
                    const o = this.actual();
                    const minimo = this.sel.kind === 'mesa' ? 20 : 10;
                    o.w = entre(o.w + d, minimo, this.ancho - o.x);
                    o.h = o.forma === 'REDONDA' ? o.w : entre(o.h + d, minimo, this.alto - o.y);
                    this.acomodar(o);
                    this.cambios = true;
                },
                /** Un lugar libre para algo nuevo: recorre el lienzo hasta que no choque con nada */
                lugarLibre(w, h) {
                    const ocupados = [...this.mesas.filter(m => m.ubicada), ...this.elementos];
                    for (let y = 20; y + h <= this.alto; y += 25) {
                        for (let x = 20; x + w <= this.ancho; x += 25) {
                            if (!ocupados.some(o => x < o.x + o.w + 10 && x + w + 10 > o.x && y < o.y + o.h + 10 && y + h + 10 > o.y)) return { x, y };
                        }
                    }
                    return { x: paso((this.ancho - w) / 2), y: paso(Math.max(0, this.alto - h) / 2) };
                },
                siguienteNombre(prefijo) {
                    const usados = this.mesas.map(m => (m.nombre || '').trim().toUpperCase());
                    for (let n = 1; n < 1000; n++) {
                        const nombre = `${prefijo} ${String(n).padStart(2, '0')}`;
                        if (!usados.includes(nombre)) return nombre;
                    }
                    return prefijo;
                },
                agregarMesa(forma) {
                    const [, w, h] = this.formas[forma];
                    const nueva = { id: null, nombre: this.siguienteNombre(forma === 'BOX' ? 'BOX' : 'MESA'), forma, w, h, ubicada: true, ...this.lugarLibre(w, h) };
                    this.mesas.push(nueva);
                    this.sel = { kind: 'mesa', idx: this.mesas.length - 1 };
                    this.cambios = true;
                },
                agregarElemento(tipo) {
                    const [, nombre, color, w, h] = this.tipos[tipo];
                    this.elementos.push({ tipo, texto: tipo === 'PARED' ? '' : nombre, color, w, h, ...this.lugarLibre(w, h) });
                    this.sel = { kind: 'elem', idx: this.elementos.length - 1 };
                    this.cambios = true;
                },
                ubicar(m) {
                    const [, w, h] = this.formas[m.forma] || this.formas.CUADRADA;
                    Object.assign(m, { w: m.w || w, h: m.h || h, ubicada: true });
                    Object.assign(m, this.lugarLibre(m.w, m.h));
                    this.acomodar(m);
                    this.sel = { kind: 'mesa', idx: this.mesas.indexOf(m) };
                    this.cambios = true;
                },
                cambiarForma(forma) {
                    const m = this.actual();
                    const [, w, h] = this.formas[forma];
                    Object.assign(m, { forma, w, h });
                    this.acomodar(m);
                    this.cambios = true;
                },
                duplicar() {
                    const o = this.actual();
                    if (this.sel.kind === 'mesa') {
                        this.mesas.push({ id: null, nombre: this.siguienteNombre(o.forma === 'BOX' ? 'BOX' : 'MESA'), forma: o.forma, w: o.w, h: o.h, ubicada: true, ...this.lugarLibre(o.w, o.h) });
                        this.sel = { kind: 'mesa', idx: this.mesas.length - 1 };
                    } else {
                        this.elementos.push({ ...o, ...this.lugarLibre(o.w, o.h) });
                        this.sel = { kind: 'elem', idx: this.elementos.length - 1 };
                    }
                    this.cambios = true;
                },
                /** Mesa: sale del plano (queda "Sin ubicar"); una nueva sin guardar, o un elemento, se borra */
                quitar() {
                    if (!this.sel) return;
                    if (this.sel.kind === 'mesa') {
                        const m = this.mesas[this.sel.idx];
                        if (m.id) m.ubicada = false; else this.mesas.splice(this.sel.idx, 1);
                    } else {
                        this.elementos.splice(this.sel.idx, 1);
                    }
                    this.sel = null;
                    this.cambios = true;
                },
                /** Eliminar la mesa de verdad (se borra al guardar el plano) */
                eliminarMesa(m = null) {
                    m = m || this.actual();
                    if (!m || !confirm(`¿Eliminar la ${m.nombre}? Se borra al guardar el plano.`)) return;
                    if (m.id) this.eliminar.push(m.id);
                    this.mesas.splice(this.mesas.indexOf(m), 1);
                    this.sel = null;
                    this.cambios = true;
                },
                /** Antes de guardar: no dos mesas con el mismo nombre (contando las que están "Sin ubicar") */
                repetida() {
                    const vistos = {};
                    for (const [i, m] of this.mesas.entries()) {
                        const n = (m.nombre || '').trim().toUpperCase();
                        if (vistos[n] !== undefined) return [n, m.ubicada ? i : vistos[n]];
                        vistos[n] = i;
                    }
                    return null;
                },
                async guardar() {
                    const rep = this.repetida();
                    if (rep) {
                        this.error = true;
                        this.mensaje = `Hay dos mesas llamadas ${rep[0]} en este piso (una puede estar en "Sin ubicar"): cambia el nombre o elimina una.`;
                        if (this.mesas[rep[1]].ubicada) this.sel = { kind: 'mesa', idx: rep[1] };
                        return;
                    }
                    this.guardando = true; this.mensaje = '';
                    const cuerpo = {
                        mesas: this.mesas.filter(m => m.ubicada).map(({ id, nombre, forma, x, y, w, h }) => ({ id, nombre, forma, x, y, w, h })),
                        elementos: this.elementos, usar_plano: this.usaPlano, alto: this.alto, ancho: this.ancho, eliminar: this.eliminar,
                    };
                    const r = await fetch(this.url, { method: 'POST', body: JSON.stringify(cuerpo),
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } }).catch(() => null);
                    const d = r ? await r.json().catch(() => ({})) : {};
                    this.guardando = false;
                    this.error = !(r && r.ok && d.ok);
                    this.mensaje = d.mensaje || (d.errors ? Object.values(d.errors).flat()[0] : 'No se pudo guardar. Revisa tu conexión.');
                    if (!this.error) { this.cambios = false; setTimeout(() => location.reload(), 900); }
                },
            };
        }
    </script>
@endsection
