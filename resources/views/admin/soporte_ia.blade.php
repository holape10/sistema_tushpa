@extends('admin.layout')
@section('title', 'Soporte con IA')
@section('ancho', 'max-w-5xl')

@section('content')
    <h1 class="text-xl font-bold mb-1">🧑‍🔧 Soporte con IA</h1>
    <p class="text-sm text-slate-500 mb-4">Pregúntale por la base de datos de una empresa: revisa sus datos y te explica qué pasa y cómo se arregla.
        <b>Solo consulta</b>: no puede cambiar nada (las consultas corren en modo de solo lectura y las claves salen tapadas). Modelo: <b>{{ $modelo }}</b>.</p>

    @if ($bloqueo)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">{{ $bloqueo }}</div>
    @endif

    {{-- Empresa: se escribe parte de la razón social o del RUC y se elige de la lista (con muchas empresas, buscar es más rápido) --}}
    <div class="bg-white rounded-2xl shadow-sm p-4 mb-4" x-data="{
            texto: @js($cliente ? $cliente->razon_social.' · '.$cliente->ruc : ''), abierto: false, marcado: 0,
            todas: @js($clientes->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->razon_social.' · '.$c->ruc])->values()),
            get lista() {
                const q = this.texto.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                const partes = q.split(/\s+/).filter(Boolean);
                return this.todas.filter(c => { const n = c.nombre.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); return partes.every(p => n.includes(p)); }).slice(0, 30);
            },
            elegir(c) { if (c) { window.location = @js(route('admin.soporte_ia.index')) + '?cliente=' + c.id; } },
        }">
        <label class="block text-sm text-slate-600">Empresa
            <input type="search" x-model="texto" @focus="abierto = true; $el.select()" @input="abierto = true; marcado = 0" @click.outside="abierto = false"
                   @keydown.arrow-down.prevent="marcado = Math.min(marcado + 1, lista.length - 1)" @keydown.arrow-up.prevent="marcado = Math.max(marcado - 1, 0)"
                   @keydown.enter.prevent="elegir(lista[marcado])" @keydown.escape="abierto = false" autocomplete="off"
                   placeholder="Escribe la razón social o el RUC…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm"></label>
        <div x-show="abierto && texto.trim() !== ''" x-cloak class="mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
            <template x-for="(c, i) in lista" :key="c.id">
                <button type="button" @click="elegir(c)" @mouseenter="marcado = i" class="block w-full text-left px-3 py-2 text-sm"
                        :class="i === marcado ? 'bg-indigo-600 text-white' : 'hover:bg-slate-50'" x-text="c.nombre"></button>
            </template>
            <p x-show="!lista.length" class="px-3 py-2 text-sm text-slate-400">Ninguna empresa coincide.</p>
        </div>
    </div>

    @if ($cliente)
        <div class="bg-white rounded-2xl shadow-sm" x-data="soporteIa(@js($historial))">
            <div class="p-4 border-b border-slate-100 flex items-center gap-3">
                <p class="flex-1 font-bold">{{ $cliente->razon_social }} <span class="font-normal text-slate-400">· {{ $cliente->ruc }}</span></p>
                <button type="button" @click="nueva()" x-show="mensajes.length" class="text-sm font-semibold text-slate-500 hover:text-indigo-700">↺ Nueva conversación</button>
            </div>
            <div class="p-4 space-y-4 max-h-[65vh] overflow-y-auto" x-ref="lista">
                <template x-if="!mensajes.length">
                    <div class="text-sm text-slate-500 space-y-2">
                        <p>Ejemplos:</p>
                        <template x-for="ej in ejemplos"><button type="button" @click="texto = ej; enviar()" class="block text-left px-3 py-2 rounded-xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700" x-text="ej"></button></template>
                    </div>
                </template>
                <template x-for="(m, i) in mensajes" :key="i">
                    <div :class="m.role === 'user' ? 'flex justify-end' : ''">
                        <div x-show="m.role === 'user'" class="max-w-[85%] rounded-2xl bg-indigo-600 text-white px-4 py-2 text-sm whitespace-pre-line" x-html="m.html"></div>
                        <div x-show="m.role === 'assistant'" class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-800">
                            <div class="soporte-respuesta" x-html="m.html"></div>
                            <details x-show="m.sql && m.sql.length" class="mt-2 text-xs text-slate-500">
                                <summary class="cursor-pointer">🔎 SQL que consultó (<span x-text="(m.sql || []).length"></span>)</summary>
                                <template x-for="q in (m.sql || [])"><pre class="mt-1 p-2 rounded-lg bg-white border border-slate-200 whitespace-pre-wrap" x-text="q"></pre></template>
                            </details>
                            <p x-show="m.costo" class="mt-1 text-[11px] text-slate-400" x-text="'US$ ' + m.costo + ' · ' + m.consultas + ' consulta(s)'"></p>
                        </div>
                    </div>
                </template>
                <p x-show="trabajando" class="text-sm text-indigo-700 font-semibold">⏳ Revisando la base de datos…</p>
                <p x-show="error" x-text="error" class="rounded-xl bg-rose-50 text-rose-700 text-sm px-3 py-2"></p>
            </div>
            <form @submit.prevent="enviar()" class="p-4 border-t border-slate-100 flex gap-2">
                <textarea x-model="texto" rows="2" maxlength="2000" placeholder="Ej.: ¿por qué no cuadra la caja del turno 15?" @keydown.enter.prevent="if (!$event.shiftKey) enviar()"
                          class="flex-1 rounded-xl border-slate-300 text-sm"></textarea>
                <button :disabled="trabajando || !texto.trim()" class="px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold disabled:opacity-50">Preguntar</button>
            </form>
        </div>

        <style>
            .soporte-respuesta p { margin: 0 0 .6rem; } .soporte-respuesta p:last-child { margin-bottom: 0; }
            .soporte-respuesta ul, .soporte-respuesta ol { margin: 0 0 .6rem 1.2rem; list-style: disc; } .soporte-respuesta ol { list-style: decimal; }
            .soporte-respuesta table { width: 100%; border-collapse: collapse; margin: .4rem 0 .8rem; font-size: .8rem; }
            .soporte-respuesta th, .soporte-respuesta td { border-bottom: 1px solid #e5e7eb; padding: .3rem .4rem; text-align: left; }
            .soporte-respuesta h1, .soporte-respuesta h2, .soporte-respuesta h3 { font-weight: 800; margin: .4rem 0; }
            .soporte-respuesta code { background: #e5e7eb; padding: 0 .2rem; border-radius: .25rem; }
            .soporte-respuesta pre { background: #0f172a; color: #e2e8f0; padding: .6rem; border-radius: .5rem; overflow-x: auto; margin: .4rem 0; }
            .soporte-respuesta pre code { background: none; padding: 0; }
        </style>
        <script>
            function soporteIa(historial) {
                return {
                    mensajes: historial, texto: '', trabajando: false, error: '',
                    ejemplos: ['¿Cuántos comprobantes tiene pendientes o rechazados por SUNAT y por qué?', '¿Hay productos con stock negativo? ¿Cuáles y desde cuándo?',
                        '¿Por qué la rentabilidad de este mes sale tan alta?', '¿Los correlativos de las series coinciden con lo emitido?'],
                    bajar() { this.$nextTick(() => { this.$refs.lista.scrollTop = this.$refs.lista.scrollHeight; }); },
                    async enviar() {
                        const pregunta = this.texto.trim();
                        if (!pregunta || this.trabajando) return;
                        this.error = '';
                        this.mensajes.push({ role: 'user', html: pregunta.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])) });
                        this.texto = '';
                        this.trabajando = true;
                        this.bajar();
                        try {
                            const r = await fetch(@js(route('admin.soporte_ia.preguntar', $cliente->id)), { method: 'POST',
                                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ pregunta }) });
                            const d = await r.json();
                            if (!d.ok) { this.error = d.mensaje || d.message || 'No se pudo responder.'; }
                            else { this.mensajes.push({ role: 'assistant', html: d.html, sql: d.sql, costo: d.costo, consultas: d.consultas }); }
                        } catch (e) { this.error = 'Sin conexión con el servidor.'; }
                        this.trabajando = false;
                        this.bajar();
                    },
                    async nueva() {
                        await fetch(@js(route('admin.soporte_ia.nueva', $cliente->id)), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) } });
                        this.mensajes = []; this.error = '';
                    },
                };
            }
        </script>
    @endif
@endsection
