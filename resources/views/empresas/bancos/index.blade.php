@extends('layouts.app')
@section('title', 'Bancos y conciliación')
@section('content')
<div class="max-w-6xl mx-auto space-y-4">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5">
        <h1 class="text-xl font-extrabold text-gray-800">🏦 Bancos y conciliación</h1>
        <p class="text-sm text-gray-500">Tus cuentas bancarias, lo que el sistema dice que entró y salió, y el cruce con el estado de cuenta del banco. Todo amarrado a la contabilidad.</p>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        @forelse ($cuentas as $c)
            <a href="{{ route('bancos.show', $c->id) }}" class="bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-indigo-200 {{ $c->activo ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-extrabold text-gray-800">{{ $c->banco }} <span class="font-semibold text-gray-500">· {{ $c->numero }}</span></p>
                        <p class="text-xs text-gray-500">Cuenta contable {{ $c->cuenta_contable }}{{ $c->activo ? '' : ' · inactiva' }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $c->medios ? 'Medios: '.implode(', ', $c->medios) : 'Sin medios de pago asignados' }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-[11px] text-gray-400">Saldo según el sistema</p>
                        <p class="text-xl font-extrabold {{ $c->saldo < 0 ? 'text-rose-600' : 'text-gray-900' }}">S/ {{ number_format($c->saldo, 2) }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                    @if ($c->pendientes)<span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5">{{ $c->pendientes }} línea(s) del banco por conciliar</span>@endif
                    <span class="rounded-full bg-gray-100 text-gray-600 px-2 py-0.5">{{ $c->ultimo_extracto ? 'Extracto hasta el '.\Carbon\Carbon::parse($c->ultimo_extracto)->format('d/m/Y') : 'Aún sin extracto' }}</span>
                </div>
            </a>
        @empty
            <div class="md:col-span-2 bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">Registra tu primera cuenta bancaria abajo.</div>
        @endforelse
    </div>

    <details class="bg-white rounded-2xl shadow-sm" @if (! $cuentas->count() || $errors->any() || session('error')) open @endif>
        <summary class="cursor-pointer list-none px-4 py-3 font-bold text-indigo-700">+ Nueva cuenta bancaria</summary>
        <div class="px-4 pb-4">@include('empresas.bancos._form_cuenta')</div>
    </details>
</div>
@endsection
