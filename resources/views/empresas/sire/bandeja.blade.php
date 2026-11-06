@extends('layouts.app')
@section('title', 'Bandeja SIRE')

@php
    $nombrePeriodo = \Carbon\Carbon::createFromFormat('!Ym', $periodo)->locale('es')->translatedFormat('F Y');
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-4" x-data="bandejaSire(@js($filas))">

    <section class="bg-gradient-to-r from-indigo-700 to-indigo-500 rounded-2xl p-5 text-white flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-[240px]">
            <p class="text-xs uppercase tracking-wider text-indigo-200">Estudio contable</p>
            <h1 class="text-xl font-extrabold">Bandeja SIRE · <span class="capitalize">{{ $nombrePeriodo }}</span></h1>
            <p class="text-sm text-indigo-100 mt-0.5">Trae las propuestas de ventas y compras de todas tus empresas. Si SUNAT demora con una, pasa a la siguiente y vuelve a ella después.</p>
        </div>
        <form method="GET">
            <label class="text-xs font-semibold text-indigo-100">Periodo
                <input type="month" value="{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}" :disabled="trabajando"
                       onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block mt-1 h-10 rounded-xl border-0 text-sm text-slate-800">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
            </label>
        </form>
    </section>

    {{-- Avance de toda la bandeja --}}
    <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="traerTodas()" :class="trabajando && 'hidden'" class="h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                ⬇ Traer todas las propuestas
            </button>
            <button type="button" @click="detener()" x-cloak :class="!trabajando && 'hidden'" class="h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold">
                ■ Detener
            </button>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600" :class="trabajando && 'hidden'">
                <input type="checkbox" x-model="tambienListas" class="rounded text-indigo-600"> Volver a traer también las que ya están listas
            </label>
            <span class="ml-auto text-sm font-semibold text-slate-600" x-text="mensaje"></span>
        </div>
        <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" :style="`width: ${Math.round(conteo('listo') * 100 / Math.max(1, filas.filter(f => f.configurado).length))}%`"></div>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-bold">
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700" x-text="'✔ ' + conteo('listo') + ' listas'"></span>
            <span class="rounded-full bg-indigo-100 px-3 py-1 text-indigo-700" x-text="'⏳ ' + (conteo('proceso') + conteo('pidiendo') + conteo('revisando')) + ' en proceso'"></span>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800" x-show="conteo('demora')" x-text="'🐢 ' + conteo('demora') + ' SUNAT demora (se dejaron para después)'"></span>
            <span class="rounded-full bg-rose-100 px-3 py-1 text-rose-700" x-show="conteo('error')" x-text="'✖ ' + conteo('error') + ' con error'"></span>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600" x-show="filas.filter(f => !f.configurado).length" x-text="filas.filter(f => !f.configurado).length + ' sin credenciales SIRE'"></span>
        </div>
    </section>

    {{-- Cada empresa con sus dos libros --}}
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-3">Empresa</th><th class="text-left px-4 py-3 w-[30%]">Ventas (RVIE)</th><th class="text-left px-4 py-3 w-[30%]">Compras (RCE)</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody>
                    <template x-for="ruc in empresas()" :key="ruc">
                        <tr class="border-t border-slate-100" :class="filas.some(f => f.ruc === ruc && f.activa) ? 'bg-indigo-50/60' : ''">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-800" x-text="(fila(ruc, 'ventas').propia ? '🏛️ ' : '') + fila(ruc, 'ventas').nombre"></p>
                                <p class="text-xs font-mono text-slate-400" x-text="ruc"></p>
                            </td>
                            <template x-for="libro in ['ventas', 'compras']" :key="libro">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2" x-data="{ get f() { return fila(ruc, libro); } }">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold"
                                              :class="{ 'bg-emerald-100 text-emerald-700': f.estado === 'listo', 'bg-indigo-100 text-indigo-700': ['proceso', 'pidiendo', 'revisando'].includes(f.estado),
                                                        'bg-amber-100 text-amber-800': f.estado === 'demora', 'bg-rose-100 text-rose-700': f.estado === 'error', 'bg-slate-100 text-slate-500': f.estado === 'nada' || !f.configurado }">
                                            <span x-show="['pidiendo', 'revisando'].includes(f.estado)" class="h-3 w-3 rounded-full border-2 border-indigo-500 border-t-transparent animate-spin"></span>
                                            <span x-text="etiqueta(f)"></span>
                                        </span>
                                        <button type="button" :class="(!f.configurado || trabajando) && 'hidden'" @click="traerUna(f)" class="text-xs font-bold text-indigo-700 hover:underline"
                                                x-text="f.estado === 'listo' ? 'Volver a traer' : (f.estado === 'proceso' || f.estado === 'demora' ? 'Revisar' : 'Traer')"></button>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500" x-show="fila(ruc, libro).detalle" x-text="fila(ruc, libro).detalle"></p>
                                </td>
                            </template>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('estudio.cambiar') }}">
                                    @csrf
                                    <input type="hidden" name="ruc" :value="ruc">
                                    <input type="hidden" name="ir" value="/sire/dbf?periodo={{ $periodo }}">
                                    <button class="h-9 px-3 rounded-lg bg-white ring-1 ring-indigo-200 hover:bg-indigo-50 text-indigo-700 text-xs font-bold whitespace-nowrap" :disabled="trabajando">Abrir →</button>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function bandejaSire(filas) {
        const URL_BASE = @json(url('/sire/bandeja'));
        const PERIODO = @json($periodo);
        const espera = ms => new Promise(r => setTimeout(r, ms));
        return {
            filas: filas.map(f => ({ ...f, detalle: f.estado === 'error' ? f.mensaje : (f.estado === 'listo' ? f.filas + ' comprobantes' : (f.descripcion || '')), activa: false })),
            trabajando: false, detenido: false, tambienListas: false, mensaje: '',
            empresas() { return [...new Set(this.filas.map(f => f.ruc))]; },
            fila(ruc, libro) { return this.filas.find(f => f.ruc === ruc && f.libro === libro); },
            conteo(estado) { return this.filas.filter(f => f.configurado && f.estado === estado).length; },
            etiqueta(f) {
                if (!f.configurado) return 'Sin credenciales';
                return { nada: 'Sin traer', pidiendo: 'Pidiendo a SUNAT…', proceso: 'SUNAT preparando', revisando: 'Revisando…', listo: '✔ Lista', demora: 'SUNAT demora', error: 'Error' }[f.estado] || f.estado;
            },
            /** Una llamada al servidor que no espera más de 45 s: si SUNAT no contesta, se sigue con la siguiente */
            async llamar(f, accion) {
                const control = new AbortController();
                const reloj = setTimeout(() => control.abort(), 45000);
                try {
                    const r = await fetch(`${URL_BASE}/${f.ruc}/${f.libro}/${accion}?periodo=${PERIODO}`, {
                        method: 'POST', signal: control.signal,
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    });
                    const d = await r.json();
                    if (!r.ok) return { estado: 'error', mensaje: d.message || 'No se pudo.' };
                    return d;
                } catch (e) {
                    return { estado: 'lento' };
                } finally {
                    clearTimeout(reloj);
                }
            },
            aplicar(f, d) {
                if (d.estado === 'lento') { f.estado = 'proceso'; f.detalle = 'SUNAT tardó en responder: se vuelve a revisar luego'; return; }
                f.estado = d.estado === 'ok' ? 'proceso' : d.estado;
                f.detalle = d.estado === 'listo' ? d.filas + ' comprobantes' : (d.mensaje || d.descripcion || (d.estado === 'ok' ? 'Ticket ' + d.ticket : ''));
            },
            /**
             * 1) Pide las que faltan, una por una. 2) Da vueltas revisando los tickets: el que sigue en proceso se deja y se
             * pasa al siguiente. Lo que lleva más de 20 minutos queda "SUNAT demora" (su ticket sigue vivo para después).
             */
            async procesar(lista) {
                this.trabajando = true; this.detenido = false;
                const inicio = Date.now();
                for (const f of lista.filter(f => f.estado !== 'proceso')) {
                    if (this.detenido) break;
                    f.activa = true; f.estado = 'pidiendo'; this.mensaje = `Pidiendo ${f.libro} de ${f.nombre}…`;
                    this.aplicar(f, await this.llamar(f, 'pedir'));
                    f.activa = false;
                    await espera(800);
                }
                let vuelta = 0;
                while (!this.detenido) {
                    const pendientes = lista.filter(f => f.estado === 'proceso');
                    if (!pendientes.length) break;
                    if (Date.now() - inicio > 20 * 60000) {
                        pendientes.forEach(f => { f.estado = 'demora'; f.detalle = 'SUNAT sigue preparándola: presiona "Revisar" más tarde'; });
                        break;
                    }
                    vuelta++;
                    for (const f of pendientes) {
                        if (this.detenido) break;
                        f.activa = true; f.estado = 'revisando';
                        this.mensaje = `Vuelta ${vuelta}: revisando ${f.libro} de ${f.nombre}… (${lista.filter(x => ['proceso', 'revisando'].includes(x.estado)).length} en proceso)`;
                        this.aplicar(f, await this.llamar(f, 'revisar'));
                        f.activa = false;
                        await espera(1200);
                    }
                    // Las vueltas se espacian: SUNAT suele tardar de segundos a minutos
                    if (lista.some(f => f.estado === 'proceso')) await espera(vuelta < 4 ? 8000 : 20000);
                }
                lista.filter(f => ['pidiendo', 'revisando'].includes(f.estado)).forEach(f => { f.estado = 'proceso'; });
                this.trabajando = false;
                this.mensaje = this.detenido ? 'Detenido. Los pedidos hechos siguen en SUNAT: "Traer todas" continúa donde quedó.'
                    : `Listo: ${this.conteo('listo')} propuestas descargadas.` + (this.conteo('error') ? ` ${this.conteo('error')} con error.` : '');
            },
            traerTodas() {
                const lista = this.filas.filter(f => f.configurado && (this.tambienListas || f.estado !== 'listo'));
                if (this.tambienListas) lista.forEach(f => { if (f.estado === 'listo') f.estado = 'nada'; });
                // Las que se quedaron "demora" se revisan sin pedir de nuevo
                lista.forEach(f => { if (f.estado === 'demora') f.estado = 'proceso'; });
                if (!lista.length) { this.mensaje = 'Todas las propuestas del periodo ya están listas.'; return; }
                this.procesar(lista);
            },
            traerUna(f) {
                if (f.estado === 'listo' || f.estado === 'error') f.estado = 'nada';
                if (f.estado === 'demora') f.estado = 'proceso';
                this.procesar([f]);
            },
            detener() { this.detenido = true; this.mensaje = 'Deteniendo…'; },
        };
    }
</script>
@endsection
