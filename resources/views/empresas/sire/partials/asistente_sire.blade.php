{{--
    Asistente IA de las compras del mes: se le pregunta en caliente (por qué no cuadra, cuánto suman, qué hacer) y responde
    con los datos del mes. Variables: $periodo, $asistente (motivo por el que no se puede usar, o null)
--}}
<section class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="asistenteSire()">
    <button type="button" @click="abierto = !abierto" class="w-full flex items-center gap-3 px-4 py-3 text-left">
        <span class="font-bold text-slate-800">🤖 Pregúntale al asistente</span>
        <span class="text-xs text-slate-400 flex-1">«¿Por qué no cuadran las notas de crédito?» · «¿Cuánto suman las facturas de PETROPERÚ?» · «¿Qué me falta revisar?»</span>
        <span x-text="abierto ? '−' : '+'" class="text-slate-400 text-lg w-4 text-center"></span>
    </button>
    <div x-show="abierto" x-cloak class="border-t border-slate-100 p-4 space-y-3">
        @if ($asistente)
            <p class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $asistente }}</p>
        @else
            <template x-for="(m, i) in mensajes" :key="i">
                <div :class="m.role === 'user' ? 'ml-auto max-w-[85%] rounded-2xl bg-indigo-600 px-4 py-2 text-sm text-white' : 'max-w-full rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-800 prose prose-sm max-w-none'">
                    <template x-if="m.role === 'user'"><p x-text="m.content"></p></template>
                    <template x-if="m.role !== 'user'"><div x-html="m.html"></div></template>
                </div>
            </template>
            <p x-show="pensando" class="text-sm text-slate-500">Revisando tus compras del mes…</p>
            <form @submit.prevent="enviar()" class="flex gap-2">
                <input x-model="pregunta" maxlength="2000" required placeholder="Escribe tu pregunta sobre las compras de este mes"
                       class="flex-1 h-10 rounded-xl border-slate-300 text-sm" :disabled="pensando">
                <button class="h-10 px-5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold disabled:opacity-50" :disabled="pensando || !pregunta.trim()">Preguntar</button>
            </form>
            <p class="text-[11px] text-slate-400">Lee los comprobantes del mes tal como están ahora (con tus cambios). No cambia nada.</p>
        @endif
    </div>
</section>
<script>
    function asistenteSire() {
        return {
            abierto: false, pregunta: '', pensando: false, mensajes: [],
            async enviar() {
                const pregunta = this.pregunta.trim();
                if (!pregunta || this.pensando) return;
                const historial = this.mensajes.map(m => ({ role: m.role, content: m.content }));
                this.mensajes.push({ role: 'user', content: pregunta });
                this.pregunta = '';
                this.pensando = true;
                try {
                    const r = await fetch(@json(route('sire.detalle.preguntar')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ periodo: @json($periodo), pregunta, historial }),
                    });
                    const d = await r.json();
                    if (!r.ok) {
                        this.mensajes.pop();
                        this.pregunta = pregunta;
                        tushpaAviso(d.message || 'El asistente no pudo responder.', false);
                        return;
                    }
                    this.mensajes.push({ role: 'assistant', content: d.respuesta, html: d.html });
                } catch (e) {
                    this.mensajes.pop();
                    this.pregunta = pregunta;
                    tushpaAviso('Se cortó la conexión. Intenta de nuevo.', false);
                } finally {
                    this.pensando = false;
                }
            },
        };
    }
</script>
