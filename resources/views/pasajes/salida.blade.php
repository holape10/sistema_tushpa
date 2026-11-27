@extends('pasajes.layout')
@section('titulo', $salida->origen.' → '.$salida->destino)
@section('contenido')
    <div x-data="reservaWeb(@js([
        'plano' => $plano, 'tomados' => $tomados, 'precios' => $precios, 'paradas' => $paradas, 'medios' => $medios,
        'yo' => $pasajero ? ['doc' => $pasajero->doc, 'nombre' => $pasajero->nombre] : null,
    ]))" class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xl font-black text-gray-800">{{ $salida->origen }} → {{ $salida->destino }}</p>
            <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($salida->salida)->translatedFormat('l d/m/Y · H:i') }} · {{ $salida->unidad }}</p>
            <div x-show="paradas.length > 2" class="mt-3 grid grid-cols-2 gap-2">
                <label class="text-xs font-semibold text-gray-500">Subo en
                    <select x-model.number="desde" @change="cambioTramo()" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                        <template x-for="(p, i) in paradas.slice(0, -1)" :key="i"><option :value="i" x-text="p" :selected="i === desde"></option></template>
                    </select></label>
                <label class="text-xs font-semibold text-gray-500">Bajo en
                    <select x-model.number="hasta" @change="cambioTramo()" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
                        <template x-for="(p, i) in paradas" :key="i"><option :value="i" x-text="p" x-show="i > desde" :selected="i === hasta"></option></template>
                    </select></label>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-xs font-semibold text-gray-600">
                <span><span class="inline-block w-3 h-3 rounded bg-emerald-100 ring-1 ring-emerald-400 align-middle"></span> Libre</span>
                <span><span class="inline-block w-3 h-3 rounded bg-indigo-600 align-middle"></span> Tu elección</span>
                <span><span class="inline-block w-3 h-3 rounded bg-slate-300 align-middle"></span> Ocupado</span>
                <template x-for="(precio, cat) in precios" :key="cat">
                    <span class="px-2 rounded bg-slate-100" x-text="(categorias[cat] || cat) + ': S/ ' + precio.toFixed(2)"></span>
                </template>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-4 overflow-x-auto">
            <div class="flex flex-wrap gap-6 justify-center">
                <template x-for="(piso, pi) in plano.pisos" :key="pi">
                    <div>
                        <p class="text-center text-xs font-black text-gray-500 uppercase mb-2" x-text="piso.nombre"></p>
                        <div class="inline-grid gap-1.5 rounded-3xl border-2 border-slate-200 bg-slate-50 p-3" :style="`grid-template-columns: repeat(${piso.columnas}, 2.5rem)`">
                            <template x-for="(celda, k) in piso.celdas.flat()" :key="pi + '-' + k">
                                <button type="button" @click="tocar(celda)" :disabled="!libre(celda)" class="h-10 rounded-xl flex items-center justify-center text-xs transition" :class="claseDe(celda)">
                                    @include('empresas.transportes.partials.asiento')
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div x-show="seleccion.length" x-cloak class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
            <h2 class="font-black text-gray-800">Pasajeros</h2>
            <template x-for="(p, i) in seleccion" :key="p.asiento">
                <div class="grid sm:grid-cols-12 gap-2 items-center rounded-xl bg-slate-50 p-2">
                    <span class="sm:col-span-2 text-sm font-black text-indigo-700" x-text="'Asiento ' + p.asiento"></span>
                    <input x-model="p.doc" placeholder="DNI / documento" maxlength="15" class="sm:col-span-3 h-10 rounded-lg border-gray-300 text-sm">
                    <input x-model="p.nombre" placeholder="Nombres y apellidos" maxlength="150" class="sm:col-span-5 h-10 rounded-lg border-gray-300 text-sm uppercase">
                    <span class="sm:col-span-2 text-right text-sm font-bold" x-text="'S/ ' + p.precio.toFixed(2)"></span>
                </div>
            </template>

            <div class="pt-2 border-t space-y-2">
                <p class="text-sm font-black text-gray-700">¿Con qué pagas?</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="m in medios" :key="m.id">
                        <button type="button" @click="medio = m.id" class="h-10 px-4 rounded-xl text-sm font-bold ring-1"
                                :class="medio === m.id ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-300'" x-text="m.nombre"></button>
                    </template>
                </div>
                <template x-if="elegido && elegido.qr">
                    <img :src="elegido.qr" alt="QR de pago" class="w-48 h-48 object-contain rounded-xl bg-slate-50">
                </template>
                @if ($config->web_texto)<p class="rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-900">{{ $config->web_texto }}</p>@endif
                <label class="block text-xs font-semibold text-gray-500">N° de operación (si ya pagaste)
                    <input x-model="operacion" maxlength="30" class="mt-1 w-full sm:w-64 h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
            </div>
            <p class="text-xs text-gray-500">Tu asiento queda reservado {{ $config->web_reserva_min }} minutos. La agencia te llamará para confirmar tu pago.</p>
            <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700"></p>
            <div class="flex items-center justify-end gap-3">
                <p class="text-2xl font-black text-gray-800" x-text="'S/ ' + total.toFixed(2)"></p>
                @if ($pasajero)
                    <button type="button" @click="reservar()" :disabled="enviando" class="h-12 px-6 rounded-xl bg-emerald-600 font-bold text-white disabled:opacity-50"
                            x-text="enviando ? 'Reservando…' : 'RESERVAR'">RESERVAR</button>
                @else
                    <a href="{{ route('pasajes.cuenta', [$negocio->id_empresa_negocio, 'volver' => url()->current()]) }}" class="h-12 px-6 inline-flex items-center rounded-xl bg-indigo-600 font-bold text-white">Ingresa para reservar</a>
                @endif
            </div>
        </div>
    </div>

    <script>
        function reservaWeb(d) {
            const ultima = Math.max(1, d.paradas.length - 1);
            return {
                plano: d.plano, tomados: d.tomados, precios: d.precios, paradas: d.paradas, medios: d.medios, categorias: @js(\App\Support\Transportes::CATEGORIAS),
                seleccion: [], desde: 0, hasta: ultima, medio: d.medios.length ? d.medios[0].id : null, operacion: '', error: '', enviando: false,
                get hastaReal() { return this.hasta >= ultima ? 99 : this.hasta; },
                get elegido() { return this.medios.find((m) => m.id === this.medio); },
                get total() { return Math.round(this.seleccion.reduce((s, p) => s + p.precio, 0) * 100) / 100; },
                esAsiento(c) { return c && (c.t === 'A' || c.t === 'H'); },
                ocupado(c) { return this.tomados.some((p) => p.asiento === c.n && p.desde < this.hastaReal && this.desde < p.hasta); },
                libre(c) { return this.esAsiento(c) && !this.ocupado(c); },
                claseDe(c) {
                    if (!c) return 'invisible';
                    if (!this.esAsiento(c)) return 'bg-slate-200 cursor-default';
                    if (this.ocupado(c)) return 'bg-slate-300 text-slate-500 cursor-not-allowed';
                    if (this.seleccion.some((p) => p.asiento === c.n)) return 'bg-indigo-600 text-white shadow-lg scale-105';
                    return 'bg-emerald-50 ring-2 ring-emerald-400 text-gray-800';
                },
                cambioTramo() {
                    if (this.hasta <= this.desde) this.hasta = this.desde + 1;
                    this.seleccion = this.seleccion.filter((p) => !this.ocupado({ n: p.asiento }));
                },
                tocar(c) {
                    if (!this.libre(c)) return;
                    const i = this.seleccion.findIndex((p) => p.asiento === c.n);
                    if (i >= 0) { this.seleccion.splice(i, 1); return; }
                    if (this.seleccion.length >= 6) { this.error = 'Máximo 6 asientos por reserva.'; return; }
                    const primero = !this.seleccion.length && d.yo;
                    this.seleccion.push({ asiento: c.n, precio: this.precios[c.cat] || 0, doc: primero ? d.yo.doc : '', nombre: primero ? d.yo.nombre : '' });
                },
                async reservar() {
                    this.error = '';
                    if (this.seleccion.some((p) => !String(p.doc).trim() || !String(p.nombre).trim())) { this.error = 'Completa el documento y el nombre de cada pasajero.'; return; }
                    if (!this.medio) { this.error = 'Elige con qué vas a pagar.'; return; }
                    this.enviando = true;
                    try {
                        const r = await fetch(@js(route('pasajes.reservar', [$negocio->id_empresa_negocio, $salida->id])), { method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ pasajeros: this.seleccion.map((p) => ({ asiento: p.asiento, doc: String(p.doc).trim(), nombre: String(p.nombre).trim() })),
                                desde: this.desde, hasta: this.hastaReal, medio: this.medio, operacion: this.operacion || null }) });
                        if (r.status === 419) { location.reload(); return; }
                        if (r.status === 429) { this.error = 'Demasiados intentos: espera unos minutos.'; return; }
                        const j = await r.json().catch(() => null);
                        if (r.status === 422 && j?.errors) { this.error = Object.values(j.errors)[0][0]; return; }
                        if (!j || !j.ok) { this.error = j?.mensaje || 'No se pudo reservar.'; return; }
                        location.href = j.ir;
                    } catch (e) {
                        this.error = 'Sin conexión: intenta otra vez.';
                    } finally {
                        this.enviando = false;
                    }
                },
            };
        }
    </script>
@endsection
