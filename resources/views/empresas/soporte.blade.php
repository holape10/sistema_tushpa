@extends('layouts.app')
@section('title', 'Soporte')

@php
    $s = config('soporte');
    $u = auth()->user();
    $empresa = \App\Models\Empresa::find($u->IdEmpresa);
    $sucursal = \App\Models\EmpresaNegocio::find($u->id_empresa_negocio);
    $datos = "Empresa: " . ($empresa->NomEmpresa ?? '') . "\nRUC: {$u->IdEmpresa}\nSucursal: " . ($sucursal->nombre_comercial ?? '')
        . "\nUsuario: {$u->name} {$u->apeusu}\nSistema: " . request()->getHost();
    $mensaje = "Hola, necesito ayuda con el sistema TUSHPA.\n\n" . $datos . "\n\nMi consulta: ";
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-4" x-data="{ copiado: false }">

    <section class="rounded-3xl overflow-hidden shadow-sm bg-gradient-to-br from-indigo-700 to-indigo-900 text-white relative isolate">
        <x-kene-adorno patron="laberinto" />
        <div class="p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-5">
            <img src="{{ asset('imagenes/icono.png') }}" alt="" class="w-16 h-16 rounded-2xl bg-white p-2 shrink-0">
            <div class="flex-1">
                <h1 class="text-2xl font-extrabold">¿Necesitas ayuda?</h1>
                <p class="text-indigo-200 text-sm mt-1">Soporte de TUSHPA · {{ $s['nombre'] }}. Escríbenos y te ayudamos con tu sistema, SUNAT, el SIRE o tus reportes.</p>
            </div>
            <a href="https://wa.me/{{ $s['whatsapp'] }}?text={{ rawurlencode($mensaje) }}" target="_blank" rel="noopener"
               class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl bg-emerald-500 hover:bg-emerald-400 font-bold shadow-lg shrink-0">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35zM12.04 21.5h-.01a9.45 9.45 0 01-4.82-1.32l-.35-.21-3.58.94.96-3.49-.23-.36a9.43 9.43 0 01-1.45-5.03c0-5.22 4.25-9.46 9.48-9.46 2.53 0 4.9.99 6.69 2.78a9.4 9.4 0 012.77 6.69c0 5.22-4.25 9.46-9.46 9.46zm8.05-17.51A11.31 11.31 0 0012.04.67C5.77.67.67 5.77.67 12.04c0 2 .52 3.96 1.52 5.68L.57 23.33l5.74-1.5a11.33 11.33 0 005.72 1.46h.01c6.27 0 11.37-5.1 11.37-11.37 0-3.04-1.18-5.89-3.32-8.04z"/></svg>
                Escribir por WhatsApp
            </a>
        </div>
    </section>

    <div class="grid sm:grid-cols-2 gap-4">
        <a href="https://wa.me/{{ $s['whatsapp'] }}?text={{ rawurlencode($mensaje) }}" target="_blank" rel="noopener"
           class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4 hover:ring-2 hover:ring-emerald-300 transition">
            <span class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl shrink-0">💬</span>
            <span><span class="block text-xs font-semibold text-slate-500">WhatsApp</span>
                <span class="block text-lg font-bold">{{ chunk_split($s['telefono'], 3, ' ') }}</span>
                <span class="block text-xs text-emerald-600">Respuesta más rápida</span></span>
        </a>
        <a href="tel:+{{ $s['whatsapp'] }}" class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4 hover:ring-2 hover:ring-indigo-300 transition">
            <span class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-2xl shrink-0">📞</span>
            <span><span class="block text-xs font-semibold text-slate-500">Llamar</span>
                <span class="block text-lg font-bold">{{ chunk_split($s['telefono'], 3, ' ') }}</span>
                <span class="block text-xs text-slate-400">{{ $s['horario'] }}</span></span>
        </a>
        <a href="mailto:{{ $s['correo'] }}?subject={{ rawurlencode('Soporte TUSHPA - RUC ' . $u->IdEmpresa) }}&body={{ rawurlencode($mensaje) }}"
           class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4 hover:ring-2 hover:ring-sky-300 transition">
            <span class="w-12 h-12 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-2xl shrink-0">✉️</span>
            <span class="min-w-0"><span class="block text-xs font-semibold text-slate-500">Correo</span>
                <span class="block text-lg font-bold truncate">{{ $s['correo'] }}</span>
                <span class="block text-xs text-slate-400">Para enviar archivos o capturas</span></span>
        </a>
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <span class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-2xl shrink-0">📍</span>
            <span><span class="block text-xs font-semibold text-slate-500">Dirección</span>
                <span class="block font-bold">{{ $s['direccion'] }}</span></span>
        </div>
    </div>

    {{-- Datos que el soporte siempre pide --}}
    <section class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div>
                <h2 class="font-bold text-slate-700">Datos para soporte</h2>
                <p class="text-xs text-slate-400">Ya van incluidos al escribir por WhatsApp o correo. Puedes copiarlos si nos contactas por otro medio.</p>
            </div>
            <button type="button" @click="navigator.clipboard.writeText(@js($datos)).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })"
                    class="shrink-0 h-9 px-4 rounded-xl bg-slate-800 text-white text-sm font-semibold" x-text="copiado ? '✔ Copiado' : 'Copiar'"></button>
        </div>
        <pre class="bg-slate-50 rounded-xl p-4 text-sm text-slate-700 whitespace-pre-wrap font-mono">{{ $datos }}</pre>
    </section>

    <section class="bg-white rounded-2xl shadow-sm p-5 text-sm text-slate-600">
        <h2 class="font-bold text-slate-700 mb-2">Antes de escribir, ten a la mano</h2>
        <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-1 list-disc pl-5">
            <li>Una captura de pantalla del problema</li>
            <li>El mensaje de error exacto (si aparece)</li>
            <li>La serie y número del comprobante involucrado</li>
            <li>Qué estabas haciendo cuando ocurrió</li>
        </ul>
    </section>
</div>
@endsection
