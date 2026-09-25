@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <p class="text-xs text-gray-500">Empresa</p>
            <p class="font-semibold text-gray-800 mt-1">{{ $usuario->IdEmpresa }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <p class="text-xs text-gray-500">Sucursal</p>
            <p class="font-semibold text-gray-800 mt-1">{{ $usuario->sucursal->nombre_comercial ?? '-' }}</p>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-2xl shadow-sm p-6">
        <p class="text-gray-500 text-sm">Bienvenido, aquí va a ir el resumen general del sistema (ventas del día, etc.) más adelante.</p>
    </div>
@endsection