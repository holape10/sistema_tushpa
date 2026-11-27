{{--
    Tabla editable del explorador de bases (con la segunda clave abierta y llave primaria): doble clic en una celda para
    cambiarla, ➖ para marcar una fila para borrar, ➕ para agregar una; nada se guarda hasta «Aplicar». Todo va junto o
    nada (EdicionTabla). Variables: $vista (ExploradorBd::ver), $base, $tabla, $delicada (tabla de plata o stock).
--}}
@php
    $columnasJs = collect($vista['columnas'])->map(fn ($c) => ['nombre' => $c['nombre'], 'tipo' => $c['tipo'], 'nulo' => $c['nulo'], 'llave' => $c['llave'],
        'auto' => $c['auto'], 'editable' => $c['editable']])->values()->all();
@endphp
<div x-data="tablaEditable(@js($columnasJs), @js(array_values($vista['filas'])), @js(array_values($vista['cortados'])), @js($vista['llave']))"
     @beforeunload.window="if (total) { $event.preventDefault(); $event.returnValue = '' }">
    <div class="px-4 py-2 border-b flex flex-wrap items-center gap-2 text-xs bg-rose-50/60">
        <span class="font-semibold text-rose-800">✏️ Edición en la tabla:</span>
        <span class="text-slate-600">doble clic en una celda para cambiarla · ➖ marca la fila para borrar · ➕ agrega una fila · nada se guarda hasta <b>Aplicar</b></span>
    </div>
    @if ($delicada)
        <p class="px-4 py-2 border-b text-xs font-semibold text-amber-800 bg-amber-50">⚠️ Tabla de ventas, caja o stock: si la cambias aquí, el sistema no se entera (kardex, caja, SUNAT). Úsalo solo para corregir un dato suelto.</p>
    @endif

    <div class="overflow-auto max-h-[65vh]">
        <table class="text-xs">
            <thead class="sticky top-0 z-10 bg-slate-100 text-slate-600">
                <tr>
                    <th class="px-2 py-2 w-8"></th>
                    <template x-for="c in columnas" :key="c.nombre">
                        <th class="px-3 py-2 text-left font-mono whitespace-nowrap" :title="c.tipo + (c.nulo ? '' : ' · obligatorio') + (c.editable ? '' : ' · no se edita aquí')">
                            <span x-text="(c.llave ? '🔑 ' : '') + c.nombre"></span><span x-show="!c.editable && !c.llave" class="ml-0.5 text-slate-400">🔒</span>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <template x-for="(fila, i) in filas" :key="i">
                    <tr :class="borrar[i] ? 'bg-rose-50 line-through text-rose-400' : 'hover:bg-amber-50/40'">
                        <td class="px-2 py-1 text-center">
                            <button type="button" @click="alternarBorrar(i)" class="w-6 h-6 rounded-md font-bold"
                                    :class="borrar[i] ? 'bg-slate-200 text-slate-700 no-underline' : 'bg-rose-100 text-rose-700 hover:bg-rose-200'"
                                    :title="borrar[i] ? 'No borrar' : 'Marcar para borrar'" x-text="borrar[i] ? '↺' : '−'"></button>
                        </td>
                        <template x-for="c in columnas" :key="c.nombre">
                            <td class="px-3 py-1 font-mono whitespace-nowrap max-w-[320px]" @dblclick="editar(i, c)"
                                :class="{ 'bg-amber-100': cambiada(i, c.nombre), 'cursor-text': puede(i, c), 'text-slate-300 italic': valor(i, c.nombre) === null && !enEdicion(i, c.nombre) }"
                                :title="puede(i, c) ? 'Doble clic para cambiar' : (c.llave ? 'Llave: no se cambia' : (cortado(i, c.nombre) ? 'Texto largo: cámbialo con una orden SQL' : 'No se edita aquí'))">
                                <template x-if="enEdicion(i, c.nombre)">
                                    <span class="flex items-center gap-1">
                                        <input x-ref="celda" :value="valor(i, c.nombre) ?? ''" @keydown.enter.prevent="guardar(i, c.nombre, $event.target.value)"
                                               @keydown.escape.prevent="editando = null" @blur="guardar(i, c.nombre, $event.target.value)"
                                               class="h-7 min-w-[8rem] rounded border-indigo-400 px-1.5 text-xs font-mono">
                                        <button type="button" x-show="c.nulo" @mousedown.prevent="guardar(i, c.nombre, null)" title="Dejar vacío (NULL)"
                                                class="h-7 px-1.5 rounded bg-slate-200 text-[10px] font-bold">NULL</button>
                                    </span>
                                </template>
                                <template x-if="!enEdicion(i, c.nombre)"><span class="block truncate" x-text="mostrar(valor(i, c.nombre))"></span></template>
                            </td>
                        </template>
                    </tr>
                </template>
                {{-- Filas nuevas --}}
                <template x-for="(nueva, k) in nuevas" :key="'n' + k">
                    <tr class="bg-emerald-50">
                        <td class="px-2 py-1 text-center">
                            <button type="button" @click="nuevas.splice(k, 1)" class="w-6 h-6 rounded-md bg-emerald-200 text-emerald-800 font-bold" title="Quitar esta fila nueva">×</button>
                        </td>
                        <template x-for="c in columnas" :key="c.nombre">
                            <td class="px-2 py-1">
                                <template x-if="c.nombre in nueva">
                                    <input x-model="nueva[c.nombre]" :placeholder="c.llave && c.auto ? '(automático)' : (c.nulo ? '' : 'obligatorio')"
                                           class="h-7 min-w-[7rem] w-full rounded border-emerald-300 px-1.5 text-xs font-mono">
                                </template>
                                <template x-if="!(c.nombre in nueva)"><span class="text-slate-300 italic" x-text="c.llave && c.auto ? '(automático)' : '—'"></span></template>
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
        <p x-show="!filas.length && !nuevas.length" class="p-6 text-center text-sm text-slate-500">Sin filas.</p>
    </div>

    <div class="px-4 py-3 border-t flex flex-wrap items-center gap-2">
        <button type="button" @click="agregarFila()" class="h-9 px-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-bold hover:bg-emerald-100">＋ Agregar fila</button>
        <span class="text-xs text-slate-500" x-show="total" x-text="resumenCorto()"></span>
        <span class="flex-1"></span>
        <button type="button" x-show="total" @click="descartar()" class="h-9 px-3 rounded-lg bg-slate-100 text-sm font-bold">Descartar cambios</button>
        <button type="button" :disabled="!total || enviando" @click="confirmar = true"
                class="h-9 px-5 rounded-lg bg-rose-600 text-white text-sm font-bold disabled:opacity-40"
                x-text="enviando ? 'Aplicando…' : (total ? 'Aplicar ' + total + ' cambio(s)' : 'Aplicar')"></button>
    </div>
    <p x-show="mensaje" x-cloak class="mx-4 mb-3 rounded-xl px-3 py-2 text-sm font-semibold" :class="ok ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-700'" x-text="mensaje"></p>

    {{-- Confirmación con el detalle de lo que se va a hacer --}}
    <div x-show="confirmar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="confirmar = false">
        <div class="w-full max-w-xl rounded-2xl bg-white shadow-xl p-5" @click.outside="confirmar = false">
            <h3 class="text-lg font-bold">¿Aplicar estos cambios en <span class="font-mono">{{ $tabla }}</span>?</h3>
            <p class="text-xs text-slate-500 mb-3">Base {{ $base }} · se aplican todos juntos o ninguno, y quedan registrados con el valor de antes.</p>
            <ul class="max-h-72 overflow-y-auto space-y-1 text-sm font-mono">
                <template x-for="(linea, n) in detalle()" :key="n">
                    <li class="rounded-lg px-2 py-1" :class="linea.tipo === 'borrar' ? 'bg-rose-50 text-rose-800' : (linea.tipo === 'agregar' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900')" x-text="linea.texto"></li>
                </template>
            </ul>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="confirmar = false" class="h-10 px-4 rounded-xl bg-slate-100 text-sm font-bold">Volver</button>
                <button type="button" @click="aplicar()" class="h-10 px-5 rounded-xl bg-rose-600 text-white text-sm font-bold">Sí, aplicar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function tablaEditable(columnas, filas, cortados, llave) {
        return {
            columnas, filas, cortados, llave,
            cambios: {}, borrar: {}, nuevas: [], editando: null, confirmar: false, enviando: false, mensaje: '', ok: false,
            get total() {
                return Object.keys(this.cambios).filter((i) => !this.borrar[i] && Object.keys(this.cambios[i]).length).length
                    + Object.keys(this.borrar).filter((i) => this.borrar[i]).length + this.nuevas.length;
            },
            mostrar(v) { return v === null ? 'NULL' : (typeof v === 'object' ? JSON.stringify(v) : String(v)); },
            cortado(i, col) { return (this.cortados[i] || []).includes(col); },
            puede(i, c) { return c.editable && !this.cortado(i, c.nombre) && !this.borrar[i] && this.filas[i][c.nombre] !== '•••• (oculto)'; },
            enEdicion(i, col) { return this.editando && this.editando.i === i && this.editando.col === col; },
            cambiada(i, col) { return this.cambios[i] && col in this.cambios[i]; },
            valor(i, col) { return this.cambiada(i, col) ? this.cambios[i][col] : this.filas[i][col]; },
            editar(i, c) {
                if (!this.puede(i, c)) return;
                this.editando = { i, col: c.nombre };
                this.$nextTick(() => { const el = this.$refs.celda; if (el) { el.focus(); el.select(); } });
            },
            guardar(i, col, nuevo) {
                if (!this.enEdicion(i, col)) return;
                this.editando = null;
                const original = this.filas[i][col];
                // Una celda NULL que se deja vacía sigue siendo NULL; en columnas numéricas 10 = 10.00; lo demás, texto exacto ("007" no es "7")
                const numerica = /int|decimal|numeric|float|double|real/i.test((this.columnas.find((c) => c.nombre === col) || {}).tipo || '');
                const comoNumero = (v) => (v !== null && String(v).trim() !== '' && !isNaN(Number(v))) ? Number(v) : null;
                const igual = nuevo === null ? original === null
                    : (original === null ? nuevo === ''
                        : (numerica && comoNumero(original) !== null && comoNumero(nuevo) !== null ? comoNumero(original) === comoNumero(nuevo) : String(original) === nuevo));
                const actual = { ...(this.cambios[i] || {}) };
                if (igual) delete actual[col]; else actual[col] = nuevo;
                if (Object.keys(actual).length) this.cambios[i] = actual; else delete this.cambios[i];
                this.cambios = { ...this.cambios };
            },
            alternarBorrar(i) {
                this.borrar[i] = !this.borrar[i];
                this.borrar = { ...this.borrar };
            },
            agregarFila() {
                const nueva = {};
                this.columnas.forEach((c) => { if (c.editable || (c.llave && !c.auto)) nueva[c.nombre] = ''; });
                this.nuevas.push(nueva);
            },
            descartar() { this.cambios = {}; this.borrar = {}; this.nuevas = []; this.mensaje = ''; },
            llaveDe(i) { const l = {}; this.llave.forEach((c) => { l[c] = this.filas[i][c]; }); return l; },
            textoLlave(i) { return this.llave.map((c) => c + '=' + this.filas[i][c]).join(', '); },
            resumenCorto() {
                const e = Object.keys(this.cambios).filter((i) => !this.borrar[i]).length, b = Object.values(this.borrar).filter(Boolean).length, a = this.nuevas.length;
                return [e ? e + ' fila(s) editada(s)' : '', b ? b + ' para borrar' : '', a ? a + ' nueva(s)' : ''].filter(Boolean).join(' · ');
            },
            detalle() {
                const lineas = [];
                Object.keys(this.cambios).forEach((i) => {
                    if (this.borrar[i]) return;
                    Object.entries(this.cambios[i]).forEach(([col, v]) => lineas.push({ tipo: 'editar',
                        texto: '✏️ ' + this.textoLlave(i) + ' · ' + col + ': ' + this.mostrar(this.filas[i][col]) + ' → ' + this.mostrar(v) }));
                });
                Object.keys(this.borrar).filter((i) => this.borrar[i]).forEach((i) => lineas.push({ tipo: 'borrar', texto: '➖ Borrar la fila ' + this.textoLlave(i) }));
                this.nuevas.forEach((n) => lineas.push({ tipo: 'agregar', texto: '➕ Nueva: ' + Object.entries(n).filter(([, v]) => v !== '').map(([c, v]) => c + '=' + v).join(', ') }));
                return lineas;
            },
            async aplicar() {
                this.confirmar = false;
                this.enviando = true;
                this.mensaje = '';
                const editar = Object.keys(this.cambios).filter((i) => !this.borrar[i]).map((i) => {
                    const antes = {};
                    Object.keys(this.cambios[i]).forEach((col) => { antes[col] = this.filas[i][col]; });
                    return { llave: this.llaveDe(i), antes, valores: this.cambios[i] };
                });
                const borrar = Object.keys(this.borrar).filter((i) => this.borrar[i]).map((i) => ({ llave: this.llaveDe(i) }));
                try {
                    const r = await fetch(@js(route('admin.basedatos.tabla')), { method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify({ base: @js($base), tabla: @js($tabla), editar, borrar, agregar: this.nuevas }) });
                    const d = await r.json().catch(() => ({}));
                    this.ok = r.ok && d.ok;
                    this.mensaje = d.mensaje || (d.errors ? Object.values(d.errors).flat()[0] : (d.message || 'No se pudo aplicar.'));
                    if (this.ok) { this.descartar(); this.mensaje = '✅ ' + d.mensaje + ' Recargando…'; setTimeout(() => location.reload(), 900); }
                } catch (e) {
                    this.ok = false;
                    this.mensaje = 'Sin conexión: no se aplicó nada.';
                } finally {
                    this.enviando = false;
                }
            },
        };
    }
</script>
