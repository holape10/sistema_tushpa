@extends('layouts.app')
@section('title', 'Asistente IA')
@section('content')
@php
    $ejemplos = ['¿Cómo van mis ventas este mes comparado con el mes pasado?', 'Muéstrame un gráfico de mis ventas por día de este mes',
        '¿Qué insumos se están por acabar y cuánto debo comprar?', '¿Quiénes me deben y cuánto está vencido?',
        '¿Hay algo raro en mi caja, descuentos o anulaciones de esta semana?', '¿Cómo envío productos del almacén a otra sede?'];
@endphp
<div class="max-w-3xl mx-auto flex flex-col" style="min-height: calc(100vh - 9rem)" x-data="asistente(@js($historial), @js($cupo))">
    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-56">
            <h1 class="text-xl font-extrabold text-gray-800">✨ Asistente IA</h1>
            <p class="text-sm text-gray-500">Pregúntale por tus ventas, utilidad, stock, cuentas y contabilidad, escribiendo o hablando 🎤. Si le pides un gráfico, te lo muestra. Solo consulta: no cambia nada.</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-500" x-show="cupo" x-cloak>Preguntas este mes: <b x-text="cupo ? cupo.usadas + ' de ' + cupo.limite : ''"></b></p>
            <button type="button" @click="nueva()" x-show="mensajes.length" x-cloak class="mt-1 text-sm font-semibold text-indigo-700 hover:underline">+ Nueva conversación</button>
        </div>
    </div>

    @if ($bloqueo)
        <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">{{ $bloqueo }}</div>
    @endif

    <div class="flex-1 mt-4 space-y-3" x-ref="lista">
        <template x-if="!mensajes.length">
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ($ejemplos as $ejemplo)
                    <button type="button" @click="texto = @js($ejemplo); enviar()" @disabled($bloqueo)
                            class="text-left rounded-xl bg-white p-3 text-sm text-gray-700 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-300 disabled:opacity-50">{{ $ejemplo }}</button>
                @endforeach
            </div>
        </template>
        <template x-for="(m, i) in mensajes" :key="i">
            <div :class="m.role === 'user' ? 'flex justify-end' : ''">
                <div x-show="m.role === 'user'" class="max-w-[85%] rounded-2xl rounded-br-md bg-indigo-600 text-white px-4 py-2.5 text-sm whitespace-pre-line" x-html="m.html"></div>
                <div x-show="m.role !== 'user'" class="rounded-2xl rounded-bl-md bg-white shadow-sm ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-800">
                    <div class="asistente-respuesta" x-html="m.html"></div>
                    {{-- Los gráficos que pidió el usuario, debajo de la respuesta --}}
                    <template x-for="(g, j) in (m.graficos || [])" :key="j">
                        <div class="mt-3 rounded-xl ring-1 ring-gray-100 p-3">
                            <p class="text-xs font-bold text-gray-600 mb-2" x-text="g.titulo"></p>
                            <div class="relative h-64"><canvas x-init="$nextTick(() => dibujar($el, g))"></canvas></div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <div x-show="pensando" x-cloak class="flex items-center gap-2 rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-500">
            <span class="h-4 w-4 rounded-full border-2 border-indigo-200 border-t-indigo-600 animate-spin"></span> Revisando tus datos… (puede tomar hasta un minuto)
        </div>
        <p x-show="error" x-cloak class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm" x-text="error"></p>
    </div>

    <form @submit.prevent="enviar()" class="sticky bottom-3 mt-4 flex items-end gap-2 rounded-2xl bg-white p-2 shadow-lg ring-1 ring-gray-200">
        {{-- Hablarle: el navegador escribe lo que dices (gratis); luego revisas y envías --}}
        <button type="button" x-show="vozDisponible" x-cloak @click="escuchar()" @disabled($bloqueo)
                :title="escuchando ? 'Detener' : 'Hablar'" :class="escuchando ? 'bg-rose-600 text-white animate-pulse' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'"
                class="h-11 w-11 shrink-0 rounded-xl text-lg disabled:opacity-40">🎤</button>
        <textarea x-ref="caja" x-model="texto" @keydown.enter.prevent="if (!$event.shiftKey) enviar(); else texto += '\n'" rows="1" maxlength="1500" @disabled($bloqueo)
                  :placeholder="escuchando ? 'Te escucho… habla y luego toca Preguntar' : 'Escribe o toca 🎤 para hablar…'" class="flex-1 resize-none border-0 focus:ring-0 text-sm"></textarea>
        <button :disabled="pensando || !texto.trim()" @disabled($bloqueo) class="h-11 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-40">Preguntar</button>
    </form>
    <p class="mt-2 text-center text-xs text-gray-400">El asistente puede equivocarse al interpretar: revisa los números importantes en los reportes antes de decidir.</p>
</div>

<style>
    .asistente-respuesta p { margin: 0 0 .6rem; } .asistente-respuesta p:last-child { margin-bottom: 0; }
    .asistente-respuesta ul, .asistente-respuesta ol { margin: 0 0 .6rem 1.2rem; list-style: disc; } .asistente-respuesta ol { list-style: decimal; }
    .asistente-respuesta table { width: 100%; border-collapse: collapse; margin: .4rem 0 .8rem; font-size: .8rem; }
    .asistente-respuesta th, .asistente-respuesta td { border-bottom: 1px solid #e5e7eb; padding: .3rem .4rem; text-align: left; }
    .asistente-respuesta h1, .asistente-respuesta h2, .asistente-respuesta h3 { font-weight: 800; margin: .4rem 0; }
    .asistente-respuesta code { background: #f3f4f6; padding: 0 .2rem; border-radius: .25rem; }
</style>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        const COLORES = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#84cc16'];
        const Reconocedor = window.SpeechRecognition || window.webkitSpeechRecognition;

        Alpine.data('asistente', (historial, cupo) => ({
            mensajes: historial, cupo, texto: '', pensando: false, error: '',
            vozDisponible: !!Reconocedor, escuchando: false, _voz: null,
            escapar(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; },
            bajar() { this.$nextTick(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })); },

            // ---------- Voz: lo que dices se escribe en la caja (no se envía solo: lo revisas y tocas Preguntar) ----------
            escuchar() {
                if (this.escuchando) { this._voz?.stop(); return; }
                const voz = new Reconocedor();
                voz.lang = 'es-PE'; voz.interimResults = true; voz.continuous = false;
                const antes = this.texto ? this.texto.trim() + ' ' : '';
                voz.onresult = (e) => { this.texto = antes + Array.from(e.results).map(r => r[0].transcript).join(''); };
                voz.onerror = (e) => {
                    this.error = e.error === 'not-allowed' || e.error === 'service-not-allowed'
                        ? 'Permite el uso del micrófono en tu navegador para poder hablarle al asistente.'
                        : (e.error === 'no-speech' ? 'No te escuché. Toca 🎤 e intenta de nuevo.' : 'No se pudo usar el micrófono. Puedes escribir tu pregunta.');
                };
                voz.onend = () => { this.escuchando = false; this.$refs.caja.focus(); };
                this.error = ''; this.escuchando = true; this._voz = voz;
                voz.start();
            },

            // ---------- Gráficos (Chart.js, como el dashboard) ----------
            valor(v, formato) {
                if (formato === 'soles') return 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (formato === 'porcentaje') return Number(v).toLocaleString('es-PE', { maximumFractionDigits: 1 }) + '%';
                return Number(v).toLocaleString('es-PE', { maximumFractionDigits: 2 });
            },
            dibujar(canvas, g) {
                if (!window.Chart) { setTimeout(() => this.dibujar(canvas, g), 300); return; }
                const torta = g.tipo === 'torta';
                const datasets = g.series.map((s, k) => ({
                    label: s.nombre, data: s.valores, borderRadius: g.tipo === 'barras' ? 6 : 0, tension: .3, fill: false,
                    backgroundColor: torta ? s.valores.map((_, n) => COLORES[n % COLORES.length]) : COLORES[k % COLORES.length],
                    borderColor: torta ? '#fff' : COLORES[k % COLORES.length], pointRadius: g.etiquetas.length > 20 ? 0 : 3,
                }));
                new Chart(canvas, {
                    type: { barras: 'bar', lineas: 'line', torta: 'doughnut' }[g.tipo] || 'bar',
                    data: { labels: g.etiquetas, datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: torta || g.series.length > 1, position: 'bottom' },
                            tooltip: { callbacks: { label: (c) => (c.dataset.label && !torta ? c.dataset.label + ': ' : (torta ? c.label + ': ' : '')) + this.valor(c.parsed?.y ?? c.parsed, g.formato) } },
                        },
                        scales: torta ? {} : { y: { beginAtZero: true, ticks: { callback: (v) => this.valor(v, g.formato) } } },
                    },
                });
            },

            async enviar() {
                const pregunta = this.texto.trim();
                if (!pregunta || this.pensando) return;
                this._voz?.stop();
                this.error = ''; this.texto = ''; this.pensando = true;
                this.mensajes.push({ role: 'user', html: this.escapar(pregunta), graficos: [] });
                this.bajar();
                try {
                    const r = await fetch(@json(route('asistente.preguntar')), { method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ pregunta }) });
                    const d = await r.json().catch(() => ({ ok: false, mensaje: 'Error del servidor (' + r.status + ').' }));
                    if (d.ok) { this.mensajes.push({ role: 'assistant', html: d.html, graficos: d.graficos || [] }); this.cupo = d.cupo; }
                    else { this.error = d.mensaje || d.message || 'No se pudo responder.'; this.mensajes.pop(); this.texto = pregunta; }
                } catch (e) { this.error = 'Sin conexión. Intenta de nuevo.'; this.mensajes.pop(); this.texto = pregunta; }
                this.pensando = false; this.bajar();
            },
            async nueva() {
                await fetch(@json(route('asistente.nueva')), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } });
                this.mensajes = []; this.error = '';
            },
        }));
    });
</script>
@endpush
