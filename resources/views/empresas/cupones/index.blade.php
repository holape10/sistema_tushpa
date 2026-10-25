@extends('layouts.app')
@section('title', 'Cupones de descuento')
@section('content')
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $valor = fn ($c) => $c->tipo === 'P' ? rtrim(rtrim(number_format((float) $c->valor, 2), '0'), '.').'%' : $s($c->valor);
        $hoy = now()->toDateString();
        $estado = function ($c) use ($hoy) {
            return match (true) {
                ! $c->activo => ['Desactivado', 'bg-gray-100 text-gray-600'],
                $c->hasta && $hoy > $c->hasta => ['Vencido', 'bg-red-100 text-red-700'],
                $c->desde && $hoy < $c->desde => ['Aún no empieza', 'bg-amber-100 text-amber-700'],
                $c->usos_max !== null && $c->usos >= $c->usos_max => ['Agotado', 'bg-red-100 text-red-700'],
                default => ['Vigente', 'bg-green-100 text-green-700'],
            };
        };
        $f = $editar;
    @endphp

    <div class="max-w-6xl space-y-5">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-violet-600 to-fuchsia-600 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <p class="text-indigo-100 text-sm font-semibold tracking-wide uppercase">🎟️ Cupones de descuento</p>
                <h1 class="text-2xl sm:text-3xl font-black mt-1">Promociones con código</h1>
                <p class="text-indigo-50 text-sm mt-1 max-w-2xl">Crea un código (por ejemplo <b>VERANO10</b>) y compártelo en tus redes. Al cobrar, el cajero lo escribe en
                    "Cupón de descuento" y el descuento se aplica solo, sin la clave del administrador. Sale en el reporte "Descuentos y Cortesías".</p>
            </div>
        </section>

        {{-- Formulario: nuevo o editar --}}
        <form method="POST" action="{{ $f ? route('cupones.guardar', $f->id) : route('cupones.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-4"
              x-data="{ tipo: @js(old('tipo', $f->tipo ?? 'P')) }">
            @csrf
            <h2 class="font-bold text-gray-800">{{ $f ? 'Editar cupón '.$f->codigo : 'Nuevo cupón' }}</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <label class="block text-sm font-medium text-gray-700">Código
                    <input name="codigo" value="{{ old('codigo', $f->codigo ?? '') }}" required maxlength="30" placeholder="VERANO10"
                           class="mt-1 w-full rounded-lg border-gray-300 uppercase font-bold tracking-wide">
                </label>
                <label class="block text-sm font-medium text-gray-700 lg:col-span-3">Descripción (opcional)
                    <input name="descripcion" value="{{ old('descripcion', $f->descripcion ?? '') }}" maxlength="120" placeholder="Promo de verano en Instagram"
                           class="mt-1 w-full rounded-lg border-gray-300">
                </label>
                <div class="block text-sm font-medium text-gray-700">Descuento
                    <div class="mt-1 flex">
                        <select name="tipo" x-model="tipo" class="rounded-l-lg border-gray-300 text-sm">
                            <option value="P">%</option>
                            <option value="S">S/</option>
                        </select>
                        <input type="number" name="valor" step="0.01" min="0.01" value="{{ old('valor', isset($f) ? (float) $f->valor : '') }}" required
                               class="w-full rounded-r-lg border-gray-300 border-l-0" :placeholder="tipo === 'P' ? '10' : '5.00'">
                    </div>
                </div>
                <label class="block text-sm font-medium text-gray-700">Consumo mínimo S/
                    <input type="number" name="minimo" step="0.01" min="0" value="{{ old('minimo', isset($f) && $f->minimo > 0 ? (float) $f->minimo : '') }}" placeholder="Sin mínimo"
                           class="mt-1 w-full rounded-lg border-gray-300">
                </label>
                <label class="block text-sm font-medium text-gray-700">Válido desde
                    <input type="date" name="desde" value="{{ old('desde', $f->desde ?? '') }}" class="mt-1 w-full rounded-lg border-gray-300">
                </label>
                <label class="block text-sm font-medium text-gray-700">Hasta
                    <input type="date" name="hasta" value="{{ old('hasta', $f->hasta ?? '') }}" class="mt-1 w-full rounded-lg border-gray-300">
                </label>
                <label class="block text-sm font-medium text-gray-700">¿Cuántas veces se puede usar?
                    <input type="number" name="usos_max" min="1" value="{{ old('usos_max', $f->usos_max ?? '') }}" placeholder="Sin límite"
                           class="mt-1 w-full rounded-lg border-gray-300">
                </label>
            </div>
            @if ($errors->any())
                <div class="rounded-lg bg-red-50 text-red-700 text-sm p-3">{{ $errors->first() }}</div>
            @endif
            <div class="flex gap-3">
                <button class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700">{{ $f ? 'Guardar cambios' : 'Crear cupón' }}</button>
                @if ($f)<a href="{{ route('cupones.index') }}" class="px-5 py-2.5 rounded-xl bg-gray-100 text-gray-700 font-semibold">Cancelar</a>@endif
            </div>
        </form>

        {{-- Lista --}}
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">Tus cupones</h2></div>
            @if ($cupones->isEmpty())
                <p class="p-5 text-sm text-gray-500">Aún no tienes cupones. Crea el primero arriba.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr><th class="px-4 py-2 text-left">Código</th><th class="px-4 py-2 text-left">Descuento</th><th class="px-4 py-2 text-left">Condiciones</th>
                            <th class="px-4 py-2 text-center">Usos</th><th class="px-4 py-2 text-right">Descontado</th><th class="px-4 py-2 text-center">Estado</th><th class="px-4 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y">
                        @foreach ($cupones as $c)
                            @php [$texto, $color] = $estado($c); @endphp
                            <tr>
                                <td class="px-4 py-2"><span class="font-black tracking-wide text-indigo-700">{{ $c->codigo }}</span>
                                    @if ($c->descripcion)<span class="block text-xs text-gray-500">{{ $c->descripcion }}</span>@endif</td>
                                <td class="px-4 py-2 font-bold">{{ $valor($c) }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">
                                    {{ $c->minimo > 0 ? 'Consumo desde '.$s($c->minimo) : 'Sin mínimo' }}<br>
                                    {{ $c->desde || $c->hasta ? trim(($c->desde ? 'Desde '.\Carbon\Carbon::parse($c->desde)->format('d/m/Y') : '').' '.($c->hasta ? 'hasta '.\Carbon\Carbon::parse($c->hasta)->format('d/m/Y') : '')) : 'Sin fecha de vencimiento' }}
                                </td>
                                <td class="px-4 py-2 text-center">{{ $c->usos }}{{ $c->usos_max !== null ? ' / '.$c->usos_max : '' }}</td>
                                <td class="px-4 py-2 text-right">{{ $s($c->descontado) }}<span class="block text-xs text-gray-400">{{ $c->ventas }} {{ $c->ventas == 1 ? 'venta' : 'ventas' }}</span></td>
                                <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $color }}">{{ $texto }}</span></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('cupones.index', ['editar' => $c->id]) }}" class="text-indigo-600 font-semibold text-xs mr-2">Editar</a>
                                    <form method="POST" action="{{ route('cupones.estado', $c->id) }}" class="inline">@csrf
                                        <button class="text-xs font-semibold {{ $c->activo ? 'text-red-600' : 'text-green-600' }}">{{ $c->activo ? 'Desactivar' : 'Activar' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
