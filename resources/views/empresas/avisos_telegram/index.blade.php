@extends('layouts.app')
@section('title', 'Avisos por Telegram')

@section('content')
{{-- Mantenimiento › Avisos por Telegram: la central de lo que le llega a cada administrador (gratis, el bot del sistema) --}}
<div x-data="avisosTelegram(@js($admins))" class="max-w-5xl mx-auto space-y-4">
    <section class="rounded-3xl bg-gradient-to-br from-sky-500 to-indigo-700 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">✈️ Avisos por Telegram</h1>
        <p class="mt-1 text-white/90">Todo lo que pasa en tu negocio, en tu celular y <b>gratis</b>: el resumen de ventas a la hora que quieras, alertas al momento,
            cambios de precios y más. Aquí eliges qué le llega a cada administrador.</p>
        <p class="mt-2 text-sm text-white/90">💬 Además, cuando quieras, <b>pregúntale al bot</b>: "¿cuánto vendí hoy?", "¿qué se está acabando?", "¿quién me debe más?"</p>
    </section>

    @unless ($configurado)
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 text-amber-900 p-4 text-sm">Telegram aún no está activo en el servidor. Pídele a soporte que lo active.</div>
    @endunless

    <p x-show="aviso" x-text="aviso" x-cloak class="rounded-xl bg-indigo-50 text-indigo-800 px-4 py-2 text-sm"></p>

    <template x-for="a in admins" :key="a.id">
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
                <div class="flex-1 min-w-[12rem]">
                    <p class="font-extrabold text-gray-800" x-text="a.nombre"></p>
                    <p class="text-xs" :class="a.vinculado ? 'text-emerald-700 font-semibold' : 'text-gray-500'"
                       x-text="a.vinculado ? '✔ Telegram vinculado' : 'Sin Telegram: vincúlalo para que le lleguen los avisos'"></p>
                </div>
                <template x-if="!a.vinculado && !a.enlace">
                    <button type="button" @click="vincular(a)" class="h-10 px-4 rounded-xl bg-sky-600 text-white text-sm font-bold">Vincular Telegram</button>
                </template>
                <template x-if="!a.vinculado && a.enlace">
                    <span class="flex flex-wrap items-center gap-2 text-sm">
                        <a :href="a.enlace" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-sky-50 text-sky-700 font-bold">Abrir en Telegram ↗</a>
                        <span class="text-gray-500">esperando que toque <b>Iniciar</b>…</span>
                    </span>
                </template>
                <template x-if="a.vinculado">
                    <span class="flex flex-wrap gap-2">
                        <button type="button" @click="probar(a)" class="h-10 px-3 rounded-xl bg-gray-100 text-sm font-bold">Enviar prueba</button>
                        <button type="button" @click="quitar(a)" class="h-10 px-3 rounded-xl text-rose-600 text-sm font-bold hover:bg-rose-50">Desvincular</button>
                    </span>
                </template>
            </div>

            <div class="p-4 grid sm:grid-cols-2 gap-3" :class="!a.vinculado && 'opacity-60'">
                @foreach ($tipos as $col => [$nombre, $explica])
                    <label class="flex items-start gap-3 rounded-xl p-3 ring-1 cursor-pointer" :class="a.avisos[@js($col)] ? 'ring-emerald-300 bg-emerald-50' : 'ring-gray-200'">
                        <input type="checkbox" x-model="a.avisos[@js($col)]" @change="guardar(a)" class="mt-1 rounded text-emerald-600">
                        <span class="flex-1">
                            <span class="block font-bold text-gray-800">{{ $nombre }}</span>
                            <span class="block text-xs text-gray-500">{{ $explica }}</span>
                            @if ($col === 'telegram_resumen')
                                <span x-show="a.avisos.telegram_resumen" class="mt-2 flex items-center gap-2 text-xs font-semibold text-gray-600" @click.stop>
                                    Que llegue a las
                                    <select x-model="a.hora" @change="guardar(a)" class="h-8 rounded-lg border-gray-300 text-sm">
                                        @foreach (\App\Support\AvisosTelegram::HORAS as $h)<option value="{{ $h }}">{{ $h }}</option>@endforeach
                                    </select>
                                    <span class="font-normal text-gray-400">(en la mañana llega el del día anterior)</span>
                                </span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </section>
    </template>
    <p x-show="!admins.length" class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">No hay administradores activos en tu empresa.</p>
</div>

<script>
    function avisosTelegram(admins) {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const post = async (url, datos = {}) => (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(datos) })).json();
        const ruta = (a, accion) => @js(url('avisos-telegram')) + '/' + a.id + '/' + accion;
        return {
            admins, aviso: '',
            async vincular(a) {
                const r = await post(ruta(a, 'enlace'));
                if (!r.ok) { this.aviso = r.mensaje; return; }
                a.enlace = r.enlace;
                window.open(r.enlace, '_blank');
                this.esperar(45);
            },
            // Cada 4 s pregunta si ya tocó Iniciar; al vincularse queda con el resumen y las alertas prendidos
            async esperar(vueltas) {
                const r = await post(@js(route('avisos_telegram.verificar')));
                this.admins.forEach(a => {
                    if (!a.vinculado && (r.vinculados || []).includes(a.id)) {
                        a.vinculado = true; a.enlace = null;
                        if (!Object.values(a.avisos).some(Boolean)) { a.avisos.telegram_resumen = true; if ('telegram_alertas' in a.avisos) a.avisos.telegram_alertas = true; }
                        this.guardar(a);
                        this.aviso = '✔ ' + a.nombre + ' quedó vinculado.';
                    }
                });
                if (this.admins.some(a => a.enlace && !a.vinculado) && vueltas > 1) setTimeout(() => this.esperar(vueltas - 1), 4000);
            },
            async guardar(a) {
                const r = await post(ruta(a, 'guardar'), { avisos: a.avisos, hora: a.hora });
                this.aviso = r.ok ? '✓ Guardado para ' + a.nombre + '.' : (r.message || 'No se pudo guardar.');
            },
            async probar(a) { this.aviso = 'Enviando…'; const r = await post(ruta(a, 'probar')); this.aviso = r.mensaje; },
            async quitar(a) {
                if (!confirm('¿Desvincular el Telegram de ' + a.nombre + '? Ya no le llegará ningún aviso.')) return;
                await post(ruta(a, 'quitar'));
                a.vinculado = false; a.enlace = null; Object.keys(a.avisos).forEach(k => a.avisos[k] = false);
                this.aviso = 'Listo: ' + a.nombre + ' ya no recibe avisos.';
            },
        };
    }
</script>
@endsection
