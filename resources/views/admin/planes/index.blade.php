@extends('admin.layout')
@section('title', 'Planes')

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Planes</h1>
            <p class="text-sm text-slate-500">Precio mensual y límites de cada plan. Se eligen al crear o editar un cliente.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($planes as $pl)
            @include('admin.planes._tarjeta', ['pl' => $pl])
        @endforeach
        @include('admin.planes._tarjeta', ['pl' => new \App\Models\Central\Plan(['activo' => true])])
    </div>

    <p class="mt-6 text-xs text-slate-500">
        <strong>Máx. usuarios</strong> vacío = ilimitado (al llegar al límite, el cliente no puede crear más usuarios).
        <strong>Tienda virtual</strong> = el cliente puede publicar su tienda en <code>su-subdominio/tiendavirtual</code>.
        Lo demás de "Incluye" es solo texto para mostrar.
    </p>
@endsection
