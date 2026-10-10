@extends('layouts.app')
@section('title', 'Nueva sucursal')
@section('content')
    @include('empresas.partials.alert')
    @php $in = 'block w-full mt-1 rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    <form method="POST" action="{{ route('sucursales.store') }}" class="max-w-4xl space-y-5">
        @csrf
        <div class="rounded-2xl bg-gradient-to-r from-indigo-700 to-violet-600 text-white p-5 relative isolate overflow-hidden">
            <x-kene-adorno patron="cruces" />
            <h1 class="text-xl font-extrabold">Nueva sucursal</h1>
            <p class="text-sm text-indigo-100">Otro local de la misma empresa (otra calle u otra ciudad), con sus propias series y correlativos.</p>
        </div>

        <section class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-2 gap-4 text-sm">
            <h3 class="sm:col-span-2 font-bold text-gray-700">1. Datos del local</h3>
            <label class="sm:col-span-2 font-medium text-gray-600">Nombre de la sucursal
                <input name="nombre_comercial" value="{{ old('nombre_comercial') }}" required maxlength="255" placeholder="Ej. SUCURSAL PUNCHANA" class="{{ $in }} uppercase"></label>
            <label class="sm:col-span-2 font-medium text-gray-600">Dirección
                <input name="direccion" value="{{ old('direccion') }}" required maxlength="255" placeholder="Calle, número" class="{{ $in }} uppercase"></label>
            <div class="font-medium text-gray-600">Ciudad / distrito
                <div class="mt-1">@include('empresas.partials.ubigeo', ['name' => 'ubigeo', 'valor' => old('ubigeo')])</div></div>
            <label class="font-medium text-gray-600">Código de establecimiento SUNAT <span class="font-normal text-gray-400">(anexo, ej. 0001)</span>
                <input name="codigofiscal" value="{{ old('codigofiscal') }}" maxlength="4" inputmode="numeric" class="{{ $in }} font-mono"></label>
            <label class="font-medium text-gray-600">Teléfono
                <input name="telefono" value="{{ old('telefono') }}" maxlength="30" class="{{ $in }}"></label>
            <label class="font-medium text-gray-600">Correo
                <input type="email" name="correo" value="{{ old('correo') }}" maxlength="255" class="{{ $in }}"></label>
        </section>

        <section class="bg-white rounded-2xl shadow-sm p-5 text-sm">
            <h3 class="font-bold text-gray-700">2. Series propias</h3>
            <p class="text-xs text-gray-500 mb-3">Ya te sugerimos las que siguen libres. Cada serie empieza en el número 1. Recuerda registrar las series en SUNAT si te lo piden.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                @foreach ($series as $clave => $c)
                    <label class="font-medium text-gray-600">{{ $c['nombre'] }}
                        <input name="{{ $c['serie'] }}" value="{{ old($c['serie'], $sugeridas[$c['serie']] ?? '') }}" required maxlength="4" class="{{ $in }} font-mono uppercase"></label>
                @endforeach
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm p-5 text-sm">
            <h3 class="font-bold text-gray-700">3. Productos</h3>
            <label class="block mt-2 font-medium text-gray-600">Copiar el catálogo de productos de
                <select name="copiar_de" class="{{ $in }}">
                    <option value="">No copiar (empezar sin productos)</option>
                    @foreach ($sucursales as $s)<option value="{{ $s->id_empresa_negocio }}" @selected((string) old('copiar_de', $sucursales->first()->id_empresa_negocio) === (string) $s->id_empresa_negocio)>{{ $s->nombre_comercial }}</option>@endforeach
                </select></label>
            <p class="text-xs text-gray-500 mt-2">Se copian categorías, productos, presentaciones, precios por horario y combos, <b>sin stock</b>: el stock de la nueva sucursal lo ingresas con una compra, un ingreso o una transferencia. Los medios de pago se copian de esa misma sucursal.</p>
        </section>

        <div class="flex gap-3">
            <button class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700">Crear sucursal</button>
            <a href="{{ route('sucursales.index') }}" class="px-6 py-3 rounded-xl bg-white border font-semibold text-gray-600">Cancelar</a>
        </div>
    </form>
@endsection
