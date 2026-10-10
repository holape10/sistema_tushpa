@php
    $abierto = in_array($t->estado, ['DENTRO', 'SOLICITADO']);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#3730a3">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Mi vehículo · {{ $t->placa }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased"
      x-data="{ estado: @js($t->estado), enviando: false, mensaje: '',
                inicio: new Date(@js(\Carbon\Carbon::parse($t->entrada)->format('Y-m-d\TH:i:s'))).getTime(),
                desfase: new Date(@js(now()->format('Y-m-d\TH:i:s'))).getTime() - Date.now(), ahora: Date.now() }"
      x-init="setInterval(() => ahora = Date.now(), 1000)">

    <header class="relative isolate overflow-hidden bg-gradient-to-br from-indigo-800 via-indigo-700 to-blue-700 text-white rounded-b-[2rem] shadow-lg">
        <x-kene-adorno patron="escalera" />
        <div class="max-w-md mx-auto px-5 pt-8 pb-8 text-center">
            <p class="text-sm text-indigo-200">{{ $negocio->nombre_comercial ?? 'Estacionamiento' }}</p>
            <div class="inline-block mt-3 rounded-xl border-2 border-white/90 overflow-hidden bg-white text-slate-900 shadow-xl">
                <span class="block bg-blue-600 text-[10px] font-black tracking-[.4em] text-white py-0.5">PERÚ</span>
                <span class="block px-6 py-1 text-4xl font-black tracking-widest" style="font-family: ui-monospace, Consolas, monospace">{{ $t->placa }}</span>
            </div>
            <p class="mt-3 text-sm text-indigo-100">Ticket N° {{ $t->numero }} · {{ $t->tipo }}{{ $t->espacio ? ' · Espacio '.$t->espacio : '' }}</p>
        </div>
    </header>

    <main class="max-w-md mx-auto px-5 -mt-5 pb-10 space-y-4">
        @if ($abierto)
            <div class="bg-white rounded-2xl shadow-sm p-5 text-center">
                <p class="text-sm text-slate-500">Tiempo estacionado</p>
                <p class="text-4xl font-black text-slate-900 mt-1"
                   x-text="(() => { const m = Math.max(0, Math.floor((ahora + desfase - inicio) / 60000)); const h = Math.floor(m / 60); return (h ? h + ' h ' : '') + (m % 60) + ' min'; })()"></p>
                <p class="text-xs text-slate-400 mt-1">Entró el {{ \Carbon\Carbon::parse($t->entrada)->format('d/m/Y \a \l\a\s h:i a') }}</p>
                @if ($cobro)
                    <div class="mt-4 rounded-xl bg-indigo-50 px-4 py-3">
                        <p class="text-xs text-indigo-700">{{ $cobro['abonado'] ? 'Eres abonado: no pagas' : 'Hasta este momento' }}</p>
                        @unless ($cobro['abonado'])<p class="text-2xl font-black text-indigo-800">S/ {{ number_format($cobro['importe'], 2) }}</p>@endunless
                        <p class="text-[11px] text-indigo-600 mt-0.5">{{ $cobro['detalle'] }}</p>
                    </div>
                @endif
            </div>

            @if ($t->valet)
                <div class="bg-white rounded-2xl shadow-sm p-5 text-center">
                    <template x-if="estado === 'DENTRO'">
                        <div>
                            <p class="font-bold text-slate-700">¿Ya te vas?</p>
                            <p class="text-sm text-slate-500 mt-1">Pide tu auto y lo llevamos a la salida.</p>
                            <button type="button" :disabled="enviando"
                                    @click="enviando = true; fetch(@js(route('valet.pedir', $t->codigo)), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } })
                                        .then(r => r.json()).then(d => { mensaje = d.mensaje; if (d.ok) estado = 'SOLICITADO'; }).catch(() => mensaje = 'Sin conexión. Intenta de nuevo.').finally(() => enviando = false)"
                                    class="mt-4 w-full py-4 rounded-2xl bg-indigo-600 text-white text-lg font-black shadow-lg shadow-indigo-500/30 active:scale-[.98] disabled:opacity-50">
                                🚗 <span x-text="enviando ? 'Enviando…' : 'TRAER MI AUTO'"></span>
                            </button>
                        </div>
                    </template>
                    <template x-if="estado === 'SOLICITADO'">
                        <div>
                            <p class="text-5xl">🚗💨</p>
                            <p class="mt-2 font-black text-emerald-700 text-lg">Ya estamos trayendo tu auto</p>
                            <p class="text-sm text-slate-500">Acércate a la salida con este ticket.</p>
                        </div>
                    </template>
                    <p x-show="mensaje && estado !== 'SOLICITADO'" x-text="mensaje" class="mt-3 text-sm text-rose-600"></p>
                </div>
            @endif
        @else
            <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
                <p class="text-5xl">{{ $t->estado === 'ANULADO' ? '🚫' : '✅' }}</p>
                <p class="mt-2 font-black text-slate-800 text-lg">{{ $t->estado === 'ANULADO' ? 'Este ticket fue anulado' : 'Tu vehículo ya salió' }}</p>
                @if ($t->salida)<p class="text-sm text-slate-500 mt-1">{{ \Carbon\Carbon::parse($t->salida)->format('d/m/Y h:i a') }}</p>@endif
                <p class="text-sm text-slate-500 mt-3">¡Gracias por tu visita!</p>
            </div>
        @endif

        @if (!empty($negocio->telefono))
            <a href="tel:{{ preg_replace('/\D/', '', $negocio->telefono) }}" class="block text-center text-sm font-semibold text-indigo-700">📞 Llamar al estacionamiento</a>
        @endif
    </main>
</body>
</html>
