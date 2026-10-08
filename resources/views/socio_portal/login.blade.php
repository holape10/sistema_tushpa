@extends('socio_portal.layout')
@section('titulo', 'Ingresar')

@section('contenido')
<div class="max-w-md mx-auto py-6">
    <div class="bg-white rounded-3xl shadow-sm p-6">
        <h1 class="text-2xl font-extrabold">{{ $negocio && \App\Support\Gimnasio::usa((int) $negocio->id_empresa_negocio) ? 'Hola, bienvenido 💪' : 'Hola, socio 👋' }}</h1>
        <p class="text-sm text-slate-500 mt-1">Ingresa con tu DNI. Si es tu primer ingreso, tu contraseña es tu mismo DNI.</p>
        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('socio.portal.entrar') }}" class="mt-5 space-y-4">
            @csrf
            <label class="block text-sm font-semibold text-slate-600">DNI o RUC
                <input name="doc" value="{{ old('doc') }}" inputmode="numeric" maxlength="11" required autofocus autocomplete="username"
                       class="mt-1 w-full h-12 rounded-xl border-slate-300 text-lg font-semibold focus:border-emerald-500 focus:ring-emerald-500">
            </label>
            <label class="block text-sm font-semibold text-slate-600">Contraseña
                <input type="password" name="password" required autocomplete="current-password"
                       class="mt-1 w-full h-12 rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
            </label>
            <button class="w-full h-12 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold">Ingresar</button>
        </form>
        <p class="text-xs text-center text-slate-400 mt-5">Aquí ves tu estado de cuenta, tus pagos y tu carnet digital.</p>
    </div>
</div>
@endsection
