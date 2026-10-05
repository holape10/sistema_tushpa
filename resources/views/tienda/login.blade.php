@extends('tienda.layout')
@section('titulo', 'Ingresar')

@section('contenido')
<div class="max-w-md mx-auto px-4 py-10">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
        <h1 class="text-2xl font-extrabold">Ingresar</h1>
        <p class="text-sm text-slate-500 mt-1">Usa tu DNI o RUC. Si ya compraste antes en la tienda y es tu primer ingreso, tu contraseña es tu mismo DNI o RUC.</p>
        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('tienda.entrar') }}" class="mt-5 space-y-4">
            @csrf
            <label class="block text-sm font-semibold text-slate-600">DNI o RUC
                <input name="doc" value="{{ old('doc') }}" inputmode="numeric" maxlength="11" required autofocus class="mt-1 w-full h-12 rounded-xl border-slate-300 text-lg font-semibold">
            </label>
            <label class="block text-sm font-semibold text-slate-600">Contraseña
                <input type="password" name="password" required class="mt-1 w-full h-12 rounded-xl border-slate-300">
            </label>
            <button class="w-full h-12 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Ingresar</button>
        </form>
        <p class="text-sm text-center text-slate-500 mt-5">¿Primera vez? <a href="{{ route('tienda.registro') }}" class="font-bold text-indigo-600">Crea tu cuenta</a></p>
    </div>
    <p class="text-center mt-4"><a href="{{ route('tienda.index') }}" class="text-sm text-slate-500">← Volver a la tienda</a></p>
</div>
@endsection
