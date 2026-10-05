@extends('tienda.layout')
@section('titulo', 'Mi cuenta')

@section('contenido')
<div class="max-w-4xl mx-auto px-4 py-6 space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Hola, {{ $cliente->clinom }}</h1>
            <p class="text-sm text-slate-500">{{ strlen($cliente->clinum) === 11 ? 'RUC' : 'DNI' }} {{ $cliente->clinum }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tienda.index') }}" class="h-10 px-4 rounded-xl bg-indigo-600 text-white text-sm font-bold flex items-center">Ir a la tienda</a>
            <form method="POST" action="{{ route('tienda.salir') }}">@csrf<button class="h-10 px-4 rounded-xl bg-slate-100 text-sm font-semibold">Salir</button></form>
        </div>
    </div>

    <section class="bg-white rounded-2xl shadow-sm border p-5 {{ session('tienda_cambiar_clave') ? 'border-amber-300 ring-2 ring-amber-200' : 'border-slate-100' }}">
        <h2 class="font-bold">Cambiar contraseña</h2>
        @if (session('tienda_cambiar_clave'))
            <p class="text-sm text-amber-700 mt-1">Estás usando tu DNI/RUC como contraseña. Crea una propia para proteger tu cuenta.</p>
        @endif
        @if ($errors->any())<p class="text-sm text-rose-600 mt-2">{{ $errors->first() }}</p>@endif
        <form method="POST" action="{{ route('tienda.clave') }}" class="mt-3 grid sm:grid-cols-[1fr_1fr_auto] gap-3">
            @csrf
            <input type="password" name="password" required minlength="6" placeholder="Nueva contraseña" class="h-11 rounded-xl border-slate-300">
            <input type="password" name="password_confirmation" required minlength="6" placeholder="Repetir contraseña" class="h-11 rounded-xl border-slate-300">
            <button class="h-11 px-5 rounded-xl bg-slate-900 text-white text-sm font-bold">Guardar</button>
        </form>
    </section>

    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h2 class="font-bold mb-3">Mis pedidos en línea</h2>
        @forelse ($pedidos as $p)
            <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0 text-sm">
                <span><strong class="font-mono">{{ \App\Support\Proformas::numero($p) }}</strong><span class="block text-xs text-slate-400">{{ \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') }}</span></span>
                <span class="text-right">
                    <strong>S/ {{ number_format($p->total, 2) }}</strong>
                    <span class="block text-xs font-semibold {{ $p->estado === 'FACTURADA' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $p->estado === 'FACTURADA' ? 'Atendido' : 'En proceso' }}</span>
                </span>
            </div>
        @empty
            <p class="text-sm text-slate-400">Aún no tienes pedidos en línea.</p>
        @endforelse
    </section>

    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h2 class="font-bold mb-3">Mis comprobantes</h2>
        @forelse ($compras as $c)
            <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0 text-sm">
                <span><strong class="font-mono">{{ $c->serdoc }}-{{ str_pad($c->numdoc, 8, '0', STR_PAD_LEFT) }}</strong><span class="block text-xs text-slate-400">{{ \Carbon\Carbon::parse($c->ccafem)->format('d/m/Y') }}</span></span>
                <strong>S/ {{ number_format($c->ccaitv, 2) }}</strong>
            </div>
        @empty
            <p class="text-sm text-slate-400">Aún no hay comprobantes a tu nombre.</p>
        @endforelse
    </section>
</div>
@endsection
