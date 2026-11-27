@extends('pasajes.layout')
@section('titulo', 'Salidas')
@section('contenido')
    @php
        $rutas = $salidas->groupBy(fn ($s) => $s->origen.' → '.$s->destino);
    @endphp
    <div x-data="{ ruta: '' }" class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <h1 class="text-xl font-black text-gray-800">Compra tu pasaje</h1>
            <select x-model="ruta" class="mt-3 w-full h-11 rounded-xl border-gray-300">
                <option value="">Todas las rutas</option>
                @foreach ($rutas->keys() as $r)<option>{{ $r }}</option>@endforeach
            </select>
        </div>
        @forelse ($salidas as $s)
            <a href="{{ route('pasajes.salida', [$negocio->id_empresa_negocio, $s->id]) }}" x-show="!ruta || ruta === @js($s->origen.' → '.$s->destino)"
               class="flex items-center gap-4 bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-300">
                <div class="text-center w-16">
                    <p class="text-2xl font-black text-gray-800">{{ \Carbon\Carbon::parse($s->salida)->format('H:i') }}</p>
                    <p class="text-xs font-bold text-gray-500">{{ \Carbon\Carbon::parse($s->salida)->isToday() ? 'Hoy' : \Carbon\Carbon::parse($s->salida)->translatedFormat('D d/m') }}</p>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-black text-gray-800">{{ $s->origen }} → {{ $s->destino }}</p>
                    <p class="text-sm text-gray-500"><i class="fas {{ \App\Support\Transportes::TIPOS[$s->tipo][1] ?? 'fa-bus' }}"></i> {{ $s->unidad }}</p>
                </div>
                <p class="font-black text-indigo-700 whitespace-nowrap">S/ {{ number_format($s->precio, 2) }}</p>
            </a>
        @empty
            <p class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">Por ahora no hay salidas a la venta.</p>
        @endforelse
    </div>
@endsection
