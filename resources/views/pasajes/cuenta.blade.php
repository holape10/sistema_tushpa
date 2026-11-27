@extends('pasajes.layout')
@section('titulo', 'Mi cuenta')
@section('contenido')
    <div x-data="{ nueva: @js((bool) old('nombre')) }" class="max-w-md mx-auto bg-white rounded-2xl shadow-sm p-5 space-y-4">
        <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1">
            <button type="button" @click="nueva = false" :class="!nueva ? 'bg-white shadow' : ''" class="h-10 rounded-lg font-bold text-sm">Ingresar</button>
            <button type="button" @click="nueva = true" :class="nueva ? 'bg-white shadow' : ''" class="h-10 rounded-lg font-bold text-sm">Crear cuenta</button>
        </div>
        @if ($errors->any())
            <p class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ $errors->first() }}</p>
        @endif

        <form x-show="!nueva" method="POST" action="{{ route('pasajes.ingresar', $negocio->id_empresa_negocio) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="volver" value="{{ $volver }}">
            <label class="block text-sm font-semibold text-gray-600">DNI o documento
                <input name="doc" value="{{ old('doc') }}" required maxlength="15" inputmode="numeric" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
            <label class="block text-sm font-semibold text-gray-600">Contraseña
                <input name="password" type="password" required maxlength="60" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
            <button class="w-full h-12 rounded-xl bg-indigo-600 font-bold text-white">Ingresar</button>
        </form>

        <form x-show="nueva" x-cloak method="POST" action="{{ route('pasajes.registro', $negocio->id_empresa_negocio) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="volver" value="{{ $volver }}">
            <label class="block text-sm font-semibold text-gray-600">DNI o documento
                <input name="doc" value="{{ old('doc') }}" required maxlength="15" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
            <label class="block text-sm font-semibold text-gray-600">Nombres y apellidos
                <input name="nombre" value="{{ old('nombre') }}" required maxlength="150" class="mt-1 w-full h-11 rounded-xl border-gray-300 uppercase"></label>
            <label class="block text-sm font-semibold text-gray-600">Celular
                <input name="celular" value="{{ old('celular') }}" required maxlength="20" inputmode="tel" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
            <label class="block text-sm font-semibold text-gray-600">Correo (opcional)
                <input name="email" type="email" value="{{ old('email') }}" maxlength="120" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
            <label class="block text-sm font-semibold text-gray-600">Contraseña
                <input name="password" type="password" required minlength="6" maxlength="60" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
            <label class="block text-sm font-semibold text-gray-600">Repite la contraseña
                <input name="password_confirmation" type="password" required minlength="6" maxlength="60" class="mt-1 w-full h-11 rounded-xl border-gray-300"></label>
            <button class="w-full h-12 rounded-xl bg-indigo-600 font-bold text-white">Crear cuenta</button>
        </form>
    </div>
@endsection
