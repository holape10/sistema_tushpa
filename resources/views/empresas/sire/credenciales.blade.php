@extends('layouts.app')
@section('title', 'Credenciales SIRE')

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    @if (session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold">Credenciales del API SIRE</h1>
                <p class="text-sm text-slate-500">RUC {{ $empresa->IdEmpresa }} · {{ $empresa->NomEmpresa }}</p>
            </div>
            @if (\App\Support\Sunat\Sire::configurado($empresa))
                <span class="shrink-0 text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">Configurado</span>
            @else
                <span class="shrink-0 text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">Sin configurar</span>
            @endif
        </div>

        <form method="POST" action="{{ route('sire.credenciales.guardar') }}" class="mt-5 space-y-4" x-data="{ guardando: false }" @submit="guardando = true">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">ID <span class="font-normal text-slate-400">(client_id)</span></span>
                    <input name="client_id" value="{{ old('client_id', $empresa->client_id) }}" required autocomplete="off"
                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="mt-1 w-full rounded-xl border-slate-300 font-mono text-sm">
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">CLAVE <span class="font-normal text-slate-400">(client_secret)</span></span>
                    <input type="password" name="client_secret" autocomplete="new-password"
                           placeholder="{{ $empresa->client_secret ? '•••••••• guardada — déjalo vacío para no cambiarla' : 'Pega aquí la CLAVE' }}"
                           class="mt-1 w-full rounded-xl border-slate-300 font-mono text-sm">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Usuario SOL</span>
                    <input name="sire_usuario" value="{{ old('sire_usuario', $empresa->sire_usuario) }}" maxlength="30" autocomplete="off"
                           placeholder="{{ $empresa->wsusuario ?: 'USUARIO' }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm uppercase">
                    <span class="text-xs text-slate-400">Vacío = se usa el de facturación electrónica{{ $empresa->wsusuario ? " ({$empresa->wsusuario})" : '' }}.</span>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Clave SOL</span>
                    <input type="password" name="sire_clave" autocomplete="new-password"
                           placeholder="{{ $empresa->sire_clave ? '•••••••• guardada' : 'Vacío = la de facturación' }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>
            <div class="flex justify-end">
                <button :disabled="guardando" class="h-11 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-60"
                        x-text="guardando ? 'Probando conexión con SUNAT…' : 'Guardar y probar conexión'"></button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5 text-sm text-slate-600 space-y-2">
        <h2 class="font-bold text-slate-700">¿Dónde obtengo el ID y la CLAVE?</h2>
        <ol class="list-decimal pl-5 space-y-1">
            <li>Entra a <strong>SUNAT Operaciones en Línea (SOL)</strong> con la clave SOL del RUC.</li>
            <li>Ve a <strong>Empresas → Credenciales de API SUNAT → Gestión Credenciales de API SUNAT</strong>.</li>
            <li>Registra tu aplicación (nombre y URL) y marca los permisos del <strong>SIRE</strong> (ventas y compras).</li>
            <li>Copia el <strong>ID</strong> y la <strong>CLAVE</strong> que te muestra y pégalos aquí.</li>
        </ol>
        <p class="text-xs text-slate-400">El usuario SOL que uses debe tener acceso al SIRE. La CLAVE y la clave SOL se guardan cifradas y nunca se vuelven a mostrar.</p>
    </div>
</div>
@endsection
