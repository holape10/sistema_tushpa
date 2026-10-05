@extends('layouts.app')
@section('title', 'Configuración de Asistencia')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php $ips = array_filter(array_map('trim', explode(',', (string) $negocio->ip_asistencia))); @endphp

    <div class="grid lg:grid-cols-2 gap-5 items-start">
        {{-- IP permitida --}}
        <section class="bg-white rounded-2xl shadow-sm">
            <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fas fa-wifi"></i></span>
                <div><h3 class="font-bold text-gray-800">Red del local (IP permitida)</h3>
                    <p class="text-xs text-gray-400">Solo se podrá marcar asistencia desde esta conexión a internet.</p></div>
            </header>
            <div class="p-5 space-y-4" x-data="{ ips: @js(implode(', ', $ips)) }">
                <div class="rounded-xl p-4 {{ $ips ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">
                    <p class="font-bold"><i class="fas {{ $ips ? 'fa-lock' : 'fa-lock-open' }}"></i> {{ $ips ? 'Restricción activa' : 'Sin restricción' }}</p>
                    <p class="text-sm">{{ $ips ? 'Se permite marcar desde: ' . implode(', ', $ips) : 'Hoy se puede marcar desde cualquier red, incluso desde la casa del trabajador con un QR reenviado.' }}</p>
                </div>
                <div class="rounded-xl border border-dashed border-gray-300 p-4 flex items-center justify-between gap-3">
                    <div><p class="text-xs uppercase text-gray-400 font-bold">Tu IP pública ahora</p><p class="font-mono text-lg font-bold text-gray-800">{{ $miIp }}</p></div>
                    <button type="button" @click="ips = ips ? (ips.split(',').map(s => s.trim()).includes('{{ $miIp }}') ? ips : ips + ', {{ $miIp }}') : '{{ $miIp }}'"
                            class="px-3 py-2 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-semibold hover:bg-indigo-100">Usar esta IP</button>
                </div>
                <form method="POST" action="{{ route('asistencia.configuracion.ip') }}" class="space-y-2">
                    @csrf
                    <label class="text-sm font-medium text-gray-600">IP permitidas (separa varias con coma)
                        <input name="ip_asistencia" x-model="ips" placeholder="Vacío = sin restricción" class="block w-full mt-1 rounded-xl border-gray-300 font-mono"></label>
                    <p class="text-xs text-gray-400"><i class="fas fa-circle-info"></i> Abre esta página desde una computadora conectada al Wi-Fi del local y pulsa “Usar esta IP”. Si tu internet cambia de IP, actualízala aquí.</p>
                    <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </form>
                <a href="{{ route('asistencia.kiosko') }}" target="_blank" class="inline-block text-sm font-semibold text-indigo-600 hover:underline"><i class="fas fa-up-right-from-square"></i> Abrir kiosko de asistencia ({{ $empleados }} trabajadores)</a>
            </div>
        </section>

        {{-- Feriados --}}
        <section class="bg-white rounded-2xl shadow-sm">
            <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fas fa-calendar-day"></i></span>
                <div class="flex-1"><h3 class="font-bold text-gray-800">Feriados {{ $anio }}</h3><p class="text-xs text-gray-400">Salen como “F” en el tareo cuando no hay marcación.</p></div>
                <div class="flex gap-1">
                    <a href="{{ route('asistencia.configuracion', ['anio' => $anio - 1]) }}" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center"><i class="fas fa-chevron-left text-xs"></i></a>
                    <a href="{{ route('asistencia.configuracion', ['anio' => $anio + 1]) }}" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center"><i class="fas fa-chevron-right text-xs"></i></a>
                </div>
            </header>
            <div class="p-5 space-y-4">
                <form method="POST" action="{{ route('asistencia.feriados.guardar') }}" class="grid sm:grid-cols-[150px_1fr_auto] gap-2">
                    @csrf
                    <input type="date" name="fecha" required class="rounded-lg border-gray-300 text-sm">
                    <input name="descripcion" required maxlength="100" placeholder="Feriado regional / de la empresa" class="rounded-lg border-gray-300 text-sm uppercase">
                    <button class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600">+ Agregar</button>
                </form>
                <div class="max-h-[420px] overflow-y-auto divide-y divide-gray-100 text-sm">
                    @foreach ($propios as $f)
                        <div class="flex items-center gap-3 py-2">
                            <span class="w-24 font-mono text-gray-600">{{ \Carbon\Carbon::parse($f->fecha)->format('d/m/Y') }}</span>
                            <span class="flex-1 font-semibold text-amber-700">{{ $f->descripcion }}</span>
                            <form method="POST" action="{{ route('asistencia.feriados.eliminar', $f->id) }}">@csrf @method('DELETE')<button class="text-rose-500 text-xs hover:underline">Quitar</button></form>
                        </div>
                    @endforeach
                    @foreach ($nacionales as $fecha => $nombre)
                        @continue($propios->contains('fecha', $fecha))
                        <div class="flex items-center gap-3 py-2">
                            <span class="w-24 font-mono text-gray-600">{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</span>
                            <span class="flex-1 text-gray-700">{{ $nombre }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Nacional</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
