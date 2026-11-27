@extends('layouts.app')
@section('title', 'Configuración de transportes')
@section('content')
    <div class="space-y-4">
        @include('empresas.transportes.partials.nav')
        @include('empresas.partials.alert')

        <form method="POST" action="{{ route('transportes.configuracion.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-4 max-w-2xl">
            @csrf
            <h2 class="font-black text-gray-800">Reservas en caja</h2>
            <label class="block text-sm font-semibold text-gray-600">Minutos que dura una reserva
                <input type="number" name="reserva_min" min="5" max="10080" value="{{ old('reserva_min', $config->reserva_min) }}" class="mt-1 block w-40 h-10 rounded-lg border-gray-300 text-sm"></label>

            <h2 class="pt-3 border-t font-black text-gray-800">Pasajes por internet</h2>
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                <input type="checkbox" name="web_activo" value="1" @checked(old('web_activo', $config->web_activo)) class="rounded border-gray-300"> Vender por internet</label>
            <label class="block text-sm font-semibold text-gray-600">Minutos para confirmar el pago
                <input type="number" name="web_reserva_min" min="10" max="1440" value="{{ old('web_reserva_min', $config->web_reserva_min) }}" class="mt-1 block w-40 h-10 rounded-lg border-gray-300 text-sm"></label>
            <label class="block text-sm font-semibold text-gray-600">Mensaje para el pasajero (ej. Yape al 999 999 999 a nombre de…)
                <input name="web_texto" maxlength="255" value="{{ old('web_texto', $config->web_texto) }}" class="mt-1 block w-full h-10 rounded-lg border-gray-300 text-sm"></label>
            @if ($config->web_activo)
                <div class="flex flex-wrap items-center gap-4 rounded-xl bg-slate-50 p-3">
                    <div class="w-28 h-28 bg-white rounded-lg p-1">{!! \App\Support\Carta::qr($enlace, 104) !!}</div>
                    <div class="text-sm"><p class="font-semibold text-gray-600">Enlace para tus clientes</p>
                        <a href="{{ $enlace }}" target="_blank" class="font-bold text-indigo-600 break-all">{{ $enlace }}</a></div>
                </div>
            @endif
            <p class="text-xs text-gray-500">Los medios de pago (Yape, Plin…) y su QR se configuran en Mantenimiento › Medios de pago.</p>
            <button class="h-11 px-5 rounded-xl bg-indigo-600 font-bold text-white">Guardar</button>
        </form>
    </div>
@endsection
