@extends('layouts.app')
@section('title', 'Inicio')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .acceso { background: linear-gradient(135deg, var(--c) 0%, color-mix(in srgb, var(--c) 72%, #000) 100%); }
        .acceso:hover { transform: translateY(-6px); box-shadow: 0 18px 30px -10px color-mix(in srgb, var(--c) 70%, transparent); filter: brightness(1.06); }
        .acceso:hover .acceso-fondo { transform: rotate(-8deg) scale(1.15); }
    </style>

    @php
        $hora = (int) now()->format('G');
        $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
        // Sin el RUC/DNI delante (el usuario administrador se llama "RUC RAZÓN SOCIAL")
        $nombre = trim(preg_replace(['/^\d{8,11}\s+/', '/[\s.]+$/'], '', auth()->user()->apeusu ?: auth()->user()->name)) ?: auth()->user()->name;
        $totalAccesos = $grupos->flatten(1)->count();
    @endphp

    <div x-data="{ q: '', todos: @js($grupos->flatten(1)->map(fn ($a) => $a['nombre'].' '.$a['grupo'])->merge($reportes->map(fn ($r) => 'reporte '.$r['nombre']))->values()), ver(t) { const n = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); return !this.q.trim() || n(t).includes(n(this.q.trim())); } }">
        {{-- Bienvenida --}}
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-600 to-violet-600 text-white p-6 md:p-8 mb-6 shadow-lg">
            <i class="fas fa-house absolute -right-6 -bottom-8 text-[11rem] opacity-10"></i>
            <div class="relative flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="text-indigo-200 text-sm font-medium" x-data="{ t: '' }" x-init="const f = () => t = new Date().toLocaleString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }); f(); setInterval(f, 30000)" x-text="t"></p>
                    <h1 class="text-2xl md:text-3xl font-extrabold mt-1">{{ $saludo }}, {{ $nombre }} 👋</h1>
                    <p class="text-indigo-100 mt-1 text-sm">Tienes {{ $totalAccesos }} {{ $totalAccesos === 1 ? 'opción' : 'opciones' }} en tu menú. ¿A dónde vamos hoy?</p>
                </div>
                <label class="relative w-full sm:w-72">
                    <i class="fas fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-indigo-300"></i>
                    <input x-model="q" type="search" placeholder="Buscar opción…"
                           class="w-full rounded-xl border-0 bg-white/15 placeholder-indigo-200 text-white pl-10 py-2.5 focus:ring-2 focus:ring-white/60 focus:bg-white/20">
                </label>
            </div>

            @if ($resumen)
                <div class="relative grid grid-cols-3 gap-2 sm:gap-3 mt-6">
                    <a href="{{ url('/ventas') }}" class="rounded-2xl bg-white/10 hover:bg-white/20 transition p-3 sm:p-4 flex items-center gap-3">
                        <span class="hidden sm:flex w-11 h-11 rounded-xl bg-emerald-400/90 flex items-center justify-center"><i class="fas fa-sack-dollar text-lg"></i></span>
                        <span><span class="block text-[11px] sm:text-xs text-indigo-200 leading-tight">Vendido hoy</span><span class="block text-base sm:text-xl font-extrabold whitespace-nowrap">S/ {{ number_format($resumen['total'], 2) }}</span></span>
                    </a>
                    <a href="{{ url('/ventas') }}" class="rounded-2xl bg-white/10 hover:bg-white/20 transition p-3 sm:p-4 flex items-center gap-3">
                        <span class="hidden sm:flex w-11 h-11 rounded-xl bg-sky-400/90 flex items-center justify-center"><i class="fas fa-receipt text-lg"></i></span>
                        <span><span class="block text-[11px] sm:text-xs text-indigo-200 leading-tight">Comprobantes de hoy</span><span class="block text-base sm:text-xl font-extrabold whitespace-nowrap">{{ $resumen['comprobantes'] }}</span></span>
                    </a>
                    <a href="{{ url('/sunat/envios') }}" class="rounded-2xl bg-white/10 hover:bg-white/20 transition p-3 sm:p-4 flex items-center gap-3">
                        <span class="hidden sm:flex w-11 h-11 rounded-xl {{ $resumen['pendientes'] ? 'bg-amber-400' : 'bg-white/25' }} flex items-center justify-center"><i class="fas fa-cloud-arrow-up text-lg"></i></span>
                        <span><span class="block text-[11px] sm:text-xs text-indigo-200 leading-tight">Pendientes SUNAT</span><span class="block text-base sm:text-xl font-extrabold whitespace-nowrap">{{ $resumen['pendientes'] ?: 'Al día ✔' }}</span></span>
                    </a>
                </div>
            @endif
        </div>

        @forelse ($grupos as $grupo => $accesos)
            @php $principal = $grupo === 'Principal'; @endphp
            <section class="mb-7" x-data="{ lista: @js($accesos->map(fn ($a) => $a['nombre'].' '.$a['grupo'])) }" x-show="lista.some(t => ver(t))">
                <h2 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                    {{ $principal ? 'Accesos principales' : $grupo }}
                    <span class="flex-1 h-px bg-slate-200"></span>
                </h2>
                <div class="grid gap-4 {{ $principal ? 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6' }}">
                    @foreach ($accesos as $a)
                        <a href="{{ $a['url'] }}" x-show="ver(@js($a['nombre'] . ' ' . $a['grupo']))" style="--c: {{ $a['color'] }}"
                           class="acceso group relative overflow-hidden rounded-2xl text-white shadow-md transition duration-300 flex flex-col {{ $principal ? 'h-40' : 'h-32' }}">
                            <i class="acceso-fondo fas {{ $a['icono'] }} absolute -right-3 -bottom-4 transition duration-500 {{ $principal ? 'text-8xl' : 'text-7xl' }}" style="opacity:.14"></i>
                            <div class="relative flex-1 flex flex-col items-center justify-center gap-2 px-3 text-center">
                                <span class="{{ $principal ? 'w-16 h-16 text-3xl' : 'w-12 h-12 text-xl' }} rounded-2xl bg-white/20 ring-1 ring-white/30 flex items-center justify-center shadow-inner">
                                    <i class="fas {{ $a['icono'] }}"></i>
                                </span>
                                <span class="font-extrabold uppercase leading-tight {{ $principal ? 'text-sm md:text-base' : 'text-xs md:text-sm' }}">{{ $a['nombre'] }}</span>
                            </div>
                            <div class="relative bg-black/15 text-[11px] font-medium py-1.5 text-center group-hover:bg-black/25 transition">
                                Click para entrar <i class="fas fa-circle-arrow-right ml-0.5 group-hover:translate-x-1 transition inline-block"></i>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="bg-white rounded-2xl p-10 text-center text-slate-500 shadow-sm">
                <i class="fas fa-lock text-4xl mb-3 text-slate-300"></i>
                <p>Aún no tienes opciones asignadas en tu menú. Pide al administrador que te las active.</p>
            </div>
        @endforelse

        @if ($reportes->isNotEmpty())
            <section class="mb-7" x-data="{ lista: @js($reportes->map(fn ($r) => 'reporte '.$r['nombre'])) }" x-show="lista.some(t => ver(t))">
                <h2 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                    Reportes <span class="flex-1 h-px bg-slate-200"></span>
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($reportes as $r)
                        <a href="{{ $r['url'] }}" x-show="ver(@js('reporte ' . $r['nombre']))"
                           class="inline-flex items-center gap-2 rounded-xl bg-white shadow-sm ring-1 ring-slate-200 px-3.5 py-2 text-sm font-medium text-slate-700 hover:ring-indigo-400 hover:text-indigo-700 hover:-translate-y-0.5 transition">
                            <i class="fas fa-chart-column text-indigo-500"></i> {{ $r['nombre'] }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <p x-show="q.trim() && !todos.some(t => ver(t))" x-cloak class="text-center text-slate-500 py-8">
            No hay opciones con “<span x-text="q"></span>”.
        </p>
    </div>
@endsection
