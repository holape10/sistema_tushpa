@extends('layouts.app')
@section('title', 'Copia de seguridad')

@php
    $s = config('soporte');
    $ruc = auth()->user()->IdEmpresa;
    $mensaje = "Hola, necesito restaurar la copia de seguridad de mi sistema TUSHPA (RUC {$ruc}). Les envío el archivo.";
@endphp

@section('content')
    @include('empresas.partials.alert')
    <div class="max-w-3xl mx-auto space-y-4" x-data="{ preparando: false }">
        <section class="rounded-3xl overflow-hidden shadow-sm bg-gradient-to-br from-indigo-700 to-indigo-900 text-white relative isolate">
            <x-kene-adorno patron="laberinto" />
            <div class="p-6 sm:p-8">
                <h1 class="text-2xl font-extrabold">💾 Copia de seguridad</h1>
                <p class="text-indigo-200 text-sm mt-1">Descarga toda la información de tu empresa en un solo archivo: ventas, comprobantes, productos, stock, clientes, usuarios y configuración.</p>
            </div>
        </section>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <p class="font-bold text-gray-800">Descargar mi información</p>
            <p class="text-sm text-gray-500 mt-1 mb-4">Se prepara en unos segundos (según el tamaño de tu información) y se descarga como <b>.sql.gz</b>. Guárdala en tu PC, en una USB o en tu nube.</p>
            <form method="POST" action="{{ route('respaldo.descargar') }}" @submit="preparando = true; setTimeout(() => preparando = false, 15000)">
                @csrf
                <button class="px-5 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold disabled:opacity-60" :disabled="preparando"
                        x-text="preparando ? 'Preparando la copia…' : '⬇ Descargar copia de seguridad'"></button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <p class="font-bold text-gray-800">¿Necesitas recuperar tu información?</p>
            <p class="text-sm text-gray-500 mt-1 mb-4">
                Cargar una copia reemplaza todo lo que tienes hoy, así que lo hace nuestro equipo de soporte, de forma segura
                y guardando antes cómo está tu sistema. Envíanos el archivo y te avisamos cuando esté listo.
            </p>
            <a href="https://wa.me/{{ $s['whatsapp'] }}?text={{ rawurlencode($mensaje) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-5 h-11 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">Pedir restauración por WhatsApp</a>
        </div>
    </div>
@endsection
