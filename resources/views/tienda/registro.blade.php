@extends('tienda.layout')
@section('titulo', 'Crear cuenta')

@section('contenido')
<div class="max-w-md mx-auto px-4 py-10">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
        <h1 class="text-2xl font-extrabold">Crear cuenta</h1>
        <p class="text-sm text-slate-500 mt-1">Tu usuario será tu DNI o RUC.</p>
        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('tienda.registrar') }}" class="mt-5 space-y-4">
            @csrf
            <label class="block text-sm font-semibold text-slate-600">DNI o RUC
                <input name="doc" value="{{ old('doc') }}" inputmode="numeric" maxlength="11" required class="mt-1 w-full h-12 rounded-xl border-slate-300 text-lg font-semibold">
            </label>
            <label class="block text-sm font-semibold text-slate-600">Nombre completo o razón social
                <input name="nombre" value="{{ old('nombre') }}" required maxlength="150" class="mt-1 w-full h-11 rounded-xl border-slate-300 uppercase">
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm font-semibold text-slate-600">Celular
                    <input name="telefono" value="{{ old('telefono') }}" inputmode="tel" maxlength="20" class="mt-1 w-full h-11 rounded-xl border-slate-300">
                </label>
                <label class="block text-sm font-semibold text-slate-600">Correo
                    <input type="email" name="correo" value="{{ old('correo') }}" maxlength="50" class="mt-1 w-full h-11 rounded-xl border-slate-300">
                </label>
            </div>
            <label class="block text-sm font-semibold text-slate-600">Dirección (para delivery)
                <input name="direccion" value="{{ old('direccion') }}" maxlength="200" class="mt-1 w-full h-11 rounded-xl border-slate-300 uppercase">
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm font-semibold text-slate-600">Contraseña
                    <input type="password" name="password" required minlength="6" class="mt-1 w-full h-11 rounded-xl border-slate-300">
                </label>
                <label class="block text-sm font-semibold text-slate-600">Repetir
                    <input type="password" name="password_confirmation" required minlength="6" class="mt-1 w-full h-11 rounded-xl border-slate-300">
                </label>
            </div>
            <button class="w-full h-12 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Crear cuenta</button>
        </form>
        <p class="text-sm text-center text-slate-500 mt-5">¿Ya tienes cuenta? <a href="{{ route('tienda.login') }}" class="font-bold text-indigo-600">Ingresa</a></p>
    </div>
</div>
@endsection
