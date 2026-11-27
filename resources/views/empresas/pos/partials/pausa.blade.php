{{--
    Pausar caja: el cajero elige el mensaje y la caja queda bloqueada a pantalla completa (el turno sigue abierto).
    Para volver, su clave o la de un administrador. Con «pausa por inactividad» en la sucursal, se pausa sola.
    Variables: $pausa (la pausa abierta o null), $inactividad (minutos; 0 = no se pausa sola)
--}}
@php
    $motivosPausa = collect(\App\Support\PausasCaja::MOTIVOS)->except('INACTIVIDAD')->all();
    $pausaJs = $pausa ? ['motivo' => $pausa->motivo, 'minutos' => $pausa->minutos, 'pc' => $pausa->pc,
        'inicio' => \Carbon\Carbon::parse($pausa->inicio)->format('H:i'), 'vuelve' => $pausa->minutos ? \Carbon\Carbon::parse($pausa->inicio)->addMinutes($pausa->minutos)->format('H:i') : null] : null;
@endphp
<div x-data="pausaCaja(@js($pausaJs), @js(\App\Support\PausasCaja::MOTIVOS), {{ (int) $inactividad }})" @pausar-caja.window="elegir = true">
    {{-- Elegir el mensaje --}}
    <div x-show="elegir && !pausa" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="elegir = false">
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl" @click.outside="elegir = false">
            <h3 class="text-lg font-black text-slate-800">⏸ Pausar caja</h3>
            <div class="mt-4 grid gap-2">
                @foreach ($motivosPausa as $clave => [$titulo, $sub])
                    <button type="button" @click="pausar(@js($clave))" :disabled="enviando"
                            class="flex items-center justify-between rounded-2xl border-2 border-slate-200 px-4 py-3 text-left hover:border-indigo-400 hover:bg-indigo-50 disabled:opacity-50">
                        <span><span class="block font-black text-slate-800">{{ $titulo }}</span><span class="text-xs text-slate-500">{{ $sub }}</span></span>
                        @if ($clave === 'DESCANSO')
                            <span class="flex items-center gap-1 text-sm text-slate-600" @click.stop>
                                <input type="number" min="1" max="240" x-model.number="minutos" class="h-9 w-16 rounded-lg border-slate-300 text-center text-sm font-bold"> min
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
            <p x-show="error" x-text="error" class="mt-3 text-sm font-semibold text-rose-600"></p>
            <button type="button" @click="elegir = false" class="mt-4 h-10 w-full rounded-xl bg-slate-100 text-sm font-bold text-slate-700">Volver</button>
        </div>
    </div>

    {{-- Caja en pausa: el mensaje grande para los clientes --}}
    <div x-show="pausa" x-cloak class="fixed inset-0 z-[200] flex flex-col items-center justify-center bg-slate-900 p-6 text-center text-white">
        <p class="text-6xl sm:text-8xl font-black tracking-tight" x-text="pausa ? motivos[pausa.motivo][0] : ''"></p>
        <p class="mt-4 text-2xl sm:text-3xl text-slate-300"
           x-text="pausa ? (pausa.motivo === 'DESCANSO' && pausa.vuelve ? 'Vuelvo a las ' + pausa.vuelve : motivos[pausa.motivo][1]) : ''"></p>
        <p class="mt-6 text-sm text-slate-500" x-text="pausa ? (pausa.pc ? pausa.pc + ' · ' : '') + 'desde las ' + pausa.inicio : ''"></p>

        <form @submit.prevent="reanudar()" class="mt-12 flex flex-wrap items-center justify-center gap-2">
            <input type="password" x-model="clave" x-ref="clave" autocomplete="current-password" placeholder="Clave para volver"
                   @keydown.stop class="h-12 w-56 rounded-xl border-0 bg-white/10 text-center text-lg text-white placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-400">
            <button :disabled="enviando" class="h-12 rounded-xl bg-indigo-600 px-6 font-bold hover:bg-indigo-500 disabled:opacity-50">Volver a la caja</button>
        </form>
        <p x-show="error" x-text="error" class="mt-3 text-sm font-semibold text-rose-300"></p>
    </div>
</div>

<script>
    function pausaCaja(pausa, motivos, inactividad) {
        const csrf = @js(csrf_token());
        const enviar = async (url, datos) => {
            const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(datos) });
            if (r.status === 401 || r.status === 419) { window.location.reload(); return null; }
            return r.json().catch(() => null);
        };
        return {
            pausa, motivos, elegir: false, minutos: 10, clave: '', error: '', enviando: false, ultimoUso: Date.now(),
            init() {
                // Con la caja en pausa, los atajos del punto de venta (F9, F10…) no hacen nada
                this.$watch('pausa', (p) => { window.cajaEnPausa = !!p; if (p) this.$nextTick(() => this.$refs.clave?.focus()); });
                window.cajaEnPausa = !!this.pausa;
                if (this.pausa) this.$nextTick(() => this.$refs.clave?.focus());
                if (inactividad > 0) {
                    ['keydown', 'mousemove', 'mousedown', 'touchstart', 'wheel'].forEach((e) => window.addEventListener(e, () => { this.ultimoUso = Date.now(); }, { passive: true }));
                    setInterval(() => { if (!this.pausa && !this.enviando && Date.now() - this.ultimoUso > inactividad * 60000) this.pausar('INACTIVIDAD'); }, 30000);
                }
            },
            async pausar(motivo) {
                this.enviando = true;
                this.error = '';
                try {
                    const pc = (window.CajaOffline && CajaOffline.pcLocal && CajaOffline.pcLocal()) || '';
                    const d = await enviar(@js(route('pv.pausa.pausar')), { motivo, minutos: motivo === 'DESCANSO' ? this.minutos : null, pc });
                    if (!d) return;
                    if (!d.ok) { this.error = d.mensaje || 'No se pudo pausar la caja.'; return; }
                    this.pausa = d.pausa;
                    this.elegir = false;
                } catch (e) {
                    this.error = 'Sin conexión: la caja no se pausó.';
                } finally {
                    this.enviando = false;
                }
            },
            async reanudar() {
                this.enviando = true;
                this.error = '';
                try {
                    const d = await enviar(@js(route('pv.pausa.reanudar')), { clave: this.clave });
                    if (!d) return;
                    if (!d.ok) { this.error = d.mensaje || 'No se pudo volver a la caja.'; this.clave = ''; return; }
                    // Se recarga: la venta que estaba a medias se recupera sola
                    window.location.reload();
                } catch (e) {
                    this.error = 'Sin conexión. Intenta otra vez.';
                } finally {
                    this.enviando = false;
                }
            },
        };
    }
</script>
