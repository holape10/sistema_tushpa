@extends('layouts.app')
@section('title', 'Unidades y planos')
@section('content')
    @php
        $T = \App\Support\Transportes::class;
        $lista = $unidades->map(fn ($u) => ['id' => $u->id, 'tipo' => $u->tipo, 'nombre' => $u->nombre, 'placa' => $u->placa, 'activo' => (bool) $u->activo,
            'asientos' => $u->asientos, 'plano' => json_decode($u->plano, true)])->values();
    @endphp
    <div class="space-y-4" x-data="editorUnidades(@js($lista))">
        @include('empresas.transportes.partials.nav')

        <div class="flex flex-wrap items-center gap-2">
            <h1 class="flex-1 text-2xl font-black text-gray-800"><i class="fas fa-bus text-indigo-600"></i> Unidades y planos</h1>
            <button type="button" @click="nueva()" class="h-10 px-4 rounded-xl bg-indigo-600 text-white text-sm font-bold"><i class="fas fa-plus"></i> Unidad</button>
        </div>

        {{-- Lista --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3" x-show="!u">
            <template x-for="x in unidades" :key="x.id">
                <button type="button" @click="editar(x)" class="text-left bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-300" :class="!x.activo && 'opacity-50'">
                    <p class="font-black text-gray-800" x-text="x.nombre"></p>
                    <p class="text-sm text-gray-500" x-text="tipos[x.tipo][0] + (x.placa ? ' · ' + x.placa : '') + ' · ' + x.asientos + ' asientos' + (x.activo ? '' : ' · inactiva')"></p>
                </button>
            </template>
            <p x-show="!unidades.length" class="sm:col-span-2 lg:col-span-3 bg-white rounded-2xl shadow-sm p-8 text-center text-gray-400">Aún no tienes unidades: crea la primera con «Unidad».</p>
        </div>

        {{-- Editor --}}
        <template x-if="u">
            <div class="space-y-4">
                <div class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-5 gap-3 items-end">
                    <label class="text-xs font-semibold text-gray-500">Tipo
                        <select x-model="u.tipo" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                            @foreach ($T::TIPOS as $clave => [$nombre])<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                        </select></label>
                    <label class="text-xs font-semibold text-gray-500 sm:col-span-2">Nombre
                        <input x-model="u.nombre" placeholder="BUS 01, LANCHA SAN PEDRO…" maxlength="80" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                    <label class="text-xs font-semibold text-gray-500">Placa / matrícula
                        <input x-model="u.placa" maxlength="20" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 h-10"><input type="checkbox" x-model="u.activo" class="rounded"> Activa</label>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-black text-gray-700">Empezar con:</span>
                        @foreach ($T::PLANTILLAS as $clave => $nombre)
                            <button type="button" @click="plantilla(@js($clave))" class="h-8 px-3 rounded-lg bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200">{{ $nombre }}</button>
                        @endforeach
                    </div>
                    {{-- Herramientas: lo que se pone al tocar una casilla --}}
                    <div class="flex flex-wrap items-center gap-1.5 border-t pt-3">
                        <span class="text-sm font-black text-gray-700 mr-1">Al tocar una casilla:</span>
                        <template x-for="[clave, texto] in herramientas" :key="clave">
                            <button type="button" @click="herramienta = clave" class="h-8 px-3 rounded-lg text-xs font-bold"
                                    :class="herramienta === clave ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700'" x-text="texto"></button>
                        </template>
                        <select x-show="herramienta === 'A' || herramienta === 'H' || herramienta === 'CAT'" x-model="categoria" class="h-8 rounded-lg border-gray-300 text-xs">
                            @foreach ($T::CATEGORIAS as $clave => $nombre)<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-6 justify-center pt-2">
                        <template x-for="(piso, pi) in u.plano.pisos" :key="pi">
                            <div class="space-y-2">
                                <div class="flex items-center gap-1">
                                    <input x-model="piso.nombre" class="h-8 w-28 rounded-lg border-gray-300 text-xs font-bold text-center">
                                    <button type="button" @click="cambiar(piso, 'filas', 1)" class="h-8 px-2 rounded-lg bg-slate-100 text-xs font-bold" title="Agregar fila">+fila</button>
                                    <button type="button" @click="cambiar(piso, 'filas', -1)" class="h-8 px-2 rounded-lg bg-slate-100 text-xs font-bold" title="Quitar la última fila">−fila</button>
                                    <button type="button" @click="cambiar(piso, 'columnas', 1)" class="h-8 px-2 rounded-lg bg-slate-100 text-xs font-bold" title="Agregar columna">+col</button>
                                    <button type="button" @click="cambiar(piso, 'columnas', -1)" class="h-8 px-2 rounded-lg bg-slate-100 text-xs font-bold" title="Quitar la última columna">−col</button>
                                    <button type="button" x-show="u.plano.pisos.length > 1" @click="u.plano.pisos.splice(pi, 1)" class="h-8 px-2 rounded-lg text-xs font-bold text-rose-600" title="Quitar este piso">✕</button>
                                </div>
                                <div class="inline-grid gap-1.5 rounded-3xl border-2 border-slate-200 bg-slate-50 p-3" :style="`grid-template-columns: repeat(${piso.columnas}, 2.75rem)`">
                                    <template x-for="(celda, k) in piso.celdas.flat()" :key="pi + '-' + k">
                                        <button type="button" @click="tocar(piso, Math.floor(k / piso.columnas), k % piso.columnas)"
                                                class="h-11 rounded-xl flex items-center justify-center text-xs border-2 border-dashed"
                                                :class="!celda ? 'border-slate-200 bg-white' : (celda.t === 'A' || celda.t === 'H' ? (celda.cat === 'CAMA' || celda.cat === 'CAMA 180' ? 'border-amber-400 bg-amber-50' : 'border-emerald-400 bg-emerald-50') : 'border-slate-300 bg-slate-200')">
                                            @include('empresas.transportes.partials.asiento')
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <button type="button" x-show="u.plano.pisos.length < 4" @click="u.plano.pisos.push(pisoVacio('Piso ' + (u.plano.pisos.length + 1)))"
                                class="self-center h-10 px-4 rounded-xl bg-slate-100 text-sm font-bold text-slate-700">+ Piso / cubierta</button>
                    </div>
                    <p class="text-xs text-gray-500 text-center">Los asientos se numeran solos al guardar (de adelante hacia atrás); los de las orillas quedan como <b>ventana</b> y los del medio como <b>pasillo</b>, salvo que lo cambies.</p>
                </div>

                <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="u = null; error = ''" class="h-11 px-4 rounded-xl bg-slate-100 font-bold text-slate-700">Volver</button>
                    <button type="button" @click="guardar()" :disabled="enviando" class="h-11 px-6 rounded-xl bg-indigo-600 font-bold text-white disabled:opacity-50"
                            x-text="enviando ? 'Guardando…' : 'Guardar unidad'">Guardar unidad</button>
                </div>
            </div>
        </template>
    </div>

    <script>
        function editorUnidades(unidades) {
            const csrf = @js(csrf_token());
            const copia = (o) => JSON.parse(JSON.stringify(o));
            return {
                unidades, u: null, herramienta: 'A', categoria: 'SEMICAMA', error: '', enviando: false, tipos: @js($T::TIPOS),
                herramientas: [['A', 'Asiento'], ['H', 'Hamaca'], ['CHOFER', 'Chofer'], ['BANO', 'Baño'], ['ESCALERA', 'Escalera'], ['PUERTA', 'Puerta'],
                    ['POS', 'Ventana / pasillo'], ['NUM', 'Número'], ['CAT', 'Categoría'], ['BORRAR', 'Borrar']],
                pisoVacio(nombre) { return { nombre, filas: 10, columnas: 5, celdas: Array.from({ length: 10 }, () => Array(5).fill(null)) }; },
                nueva() { this.u = { id: null, tipo: 'TERRESTRE', nombre: '', placa: '', activo: true, plano: { pisos: [this.pisoVacio('Piso 1')] } }; this.error = ''; },
                editar(x) { this.u = copia(x); this.error = ''; },
                async plantilla(clave) {
                    if (this.u.plano.pisos.some((p) => p.celdas.flat().some(Boolean)) && !confirm('¿Reemplazar el plano por esta plantilla?')) return;
                    this.u.plano = await (await fetch(@js(url('transportes/unidades/plantilla')) + '/' + clave, { headers: { Accept: 'application/json' } })).json();
                },
                cambiar(piso, que, paso) {
                    if (que === 'filas') {
                        if (paso > 0 && piso.filas < 30) { piso.celdas.push(Array(piso.columnas).fill(null)); piso.filas++; }
                        if (paso < 0 && piso.filas > 1) { piso.celdas.pop(); piso.filas--; }
                    } else {
                        if (paso > 0 && piso.columnas < 8) { piso.celdas.forEach((f) => f.push(null)); piso.columnas++; }
                        if (paso < 0 && piso.columnas > 1) { piso.celdas.forEach((f) => f.pop()); piso.columnas--; }
                    }
                },
                tocar(piso, f, c) {
                    const actual = piso.celdas[f][c];
                    const esAsiento = actual && (actual.t === 'A' || actual.t === 'H');
                    let nueva = actual;
                    if (this.herramienta === 'BORRAR') nueva = null;
                    else if (this.herramienta === 'POS') { if (!esAsiento) return; nueva = { ...actual, pos: actual.pos === 'V' ? 'P' : 'V', manual: true }; }
                    else if (this.herramienta === 'NUM') {
                        if (!esAsiento) return;
                        const n = prompt('Número del asiento', actual.n || '');
                        if (n === null) return;
                        nueva = { ...actual, n: n.trim().toUpperCase() };
                    } else if (this.herramienta === 'CAT') { if (!esAsiento) return; nueva = { ...actual, cat: this.categoria }; }
                    else if (this.herramienta === 'A' || this.herramienta === 'H') {
                        nueva = esAsiento && actual.t === this.herramienta ? null : { t: this.herramienta, cat: this.herramienta === 'H' ? 'HAMACA' : this.categoria };
                    } else nueva = actual && actual.t === this.herramienta ? null : { t: this.herramienta };
                    piso.celdas[f][c] = nueva;
                    piso.celdas = [...piso.celdas];
                },
                async guardar() {
                    this.error = '';
                    if (!this.u.nombre.trim()) { this.error = 'Ponle un nombre a la unidad.'; return; }
                    this.enviando = true;
                    try {
                        const r = await fetch(@js(route('transportes.unidades.guardar')), { method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ id: this.u.id, tipo: this.u.tipo, nombre: this.u.nombre, placa: this.u.placa, activo: this.u.activo, plano: this.u.plano }) });
                        const d = await r.json().catch(() => null);
                        if (r.status === 422 && d?.errors) { this.error = Object.values(d.errors)[0][0]; return; }
                        if (!d || !d.ok) { this.error = d?.mensaje || 'No se pudo guardar.'; return; }
                        const guardada = { ...this.u, id: d.id, plano: d.plano, asientos: d.asientos, nombre: this.u.nombre.toUpperCase(), placa: (this.u.placa || '').toUpperCase() };
                        const i = this.unidades.findIndex((x) => x.id === d.id);
                        if (i >= 0) this.unidades[i] = guardada; else this.unidades.push(guardada);
                        this.u = null;
                    } catch (e) {
                        this.error = 'Sin conexión: no se guardó.';
                    } finally {
                        this.enviando = false;
                    }
                },
            };
        }
    </script>
@endsection
