@extends('admin.layout')
@section('title', 'Ingresar')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center">
    <form method="POST" action="{{ route('admin.login') }}" class="w-full max-w-sm bg-white rounded-2xl shadow-sm p-6 space-y-4">
        @csrf
        <div class="text-center">
            <img src="{{ asset('imagenes/512.png') }}" alt="" class="w-12 h-12 mx-auto rounded-xl">
            <h1 class="font-bold text-lg mt-2">Panel de clientes</h1>
            <p class="text-sm text-slate-500">Acceso solo para el administrador del sistema</p>
        </div>

        @error('email')
            <p class="rounded-lg bg-rose-50 text-rose-700 text-sm px-3 py-2">{{ $message }}</p>
        @enderror

        <label class="block">
            <span class="text-sm font-semibold text-slate-600">Correo</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 w-full rounded-xl border-slate-300 focus:ring-indigo-400 focus:border-indigo-400">
        </label>
        <label class="block">
            <span class="text-sm font-semibold text-slate-600">Contraseña</span>
            <input type="password" name="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-xl border-slate-300 focus:ring-indigo-400 focus:border-indigo-400">
        </label>
        <button class="w-full h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Ingresar</button>
    </form>
</div>
@endsection
