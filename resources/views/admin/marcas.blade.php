@extends('admin.layout')
@section('title', 'Marcas')

@php
    $in = 'mt-1 w-full rounded-xl border-slate-300 text-sm';
    $lista = $marcas->push(new \App\Models\Central\Marca(['activa' => true]));
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-bold">Marcas (revendedores con dominio propio)</h1>
        <p class="text-sm text-slate-500">Cada marca entra por su propio dominio: su panel en <b>admin.{dominio}/{{ config('tenancy.ruta_admin') }}</b> y sus empresas en
            <b>{RUC}.{dominio}</b>, con bases <b>{prefijo}{RUC}</b>. Sus usuarios del panel se crean en <a href="{{ route('admin.usuarios.index') }}" class="text-indigo-700 underline">Usuarios</a>.</p>
    </div>

    @if (session('ok'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-semibold">{{ session('ok') }}</div>@endif
    @if (session('error'))<div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>@endif

    <div class="grid lg:grid-cols-2 gap-4">
        @foreach ($lista as $m)
            @php $nueva = ! $m->exists; $fija = ! $nueva && $m->clientes_count > 0; @endphp
            <form method="POST" action="{{ $nueva ? route('admin.marcas.guardar') : route('admin.marcas.guardar', $m->id) }}" enctype="multipart/form-data"
                  class="bg-white rounded-2xl shadow-sm p-5 space-y-3 border-2 {{ $nueva ? 'border-dashed border-slate-300' : 'border-transparent' }}">
                @csrf
                <div class="flex items-center gap-3">
                    @if (! $nueva && $m->logo && is_file(public_path($m->logo)))
                        <img src="{{ asset($m->logo) }}?v={{ @filemtime(public_path($m->logo)) }}" alt="" class="h-12 w-12 object-contain rounded-lg bg-slate-50">
                    @endif
                    <div class="flex-1">
                        <p class="font-bold text-slate-800">{{ $nueva ? '+ Nueva marca' : $m->nombre }}</p>
                        @unless ($nueva)
                            <p class="text-xs text-slate-500">{{ $m->clientes_count }} empresa(s) · panel: admin.{{ $m->dominio }}/{{ config('tenancy.ruta_admin') }}
                                · usuarios: {{ ($usuarios[$m->id] ?? collect())->pluck('email')->implode(', ') ?: 'ninguno aún' }}</p>
                        @endunless
                    </div>
                    @unless ($nueva)<span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $m->activa ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $m->activa ? 'Activa' : 'Desactivada' }}</span>@endunless
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="text-xs font-semibold text-slate-500">Nombre de la marca
                        <input name="nombre" value="{{ $m->nombre }}" required maxlength="100" placeholder="WILCAT SYSTEMS" class="{{ $in }} font-bold"></label>
                    <label class="text-xs font-semibold text-slate-500">Razón social (contratos)
                        <input name="razon_social" value="{{ $m->razon_social }}" maxlength="200" placeholder="WILCAT SYSTEMS E.I.R.L." class="{{ $in }} uppercase"></label>
                    <label class="text-xs font-semibold text-slate-500">Dominio
                        <input name="dominio" value="{{ $m->dominio }}" required maxlength="100" placeholder="wilcatsystems.pe" @readonly($fija) class="{{ $in }} font-mono {{ $fija ? 'bg-slate-50' : '' }}"></label>
                    <label class="text-xs font-semibold text-slate-500">Prefijo de las bases
                        <input name="prefijo_bd" value="{{ $m->prefijo_bd }}" required maxlength="20" placeholder="bd_ws_" @readonly($fija) class="{{ $in }} font-mono {{ $fija ? 'bg-slate-50' : '' }}"></label>
                    <label class="text-xs font-semibold text-slate-500">RUC
                        <input name="ruc" value="{{ $m->ruc }}" maxlength="11" inputmode="numeric" class="{{ $in }}"></label>
                    <label class="text-xs font-semibold text-slate-500">Dirección
                        <input name="direccion" value="{{ $m->direccion }}" maxlength="250" class="{{ $in }}"></label>
                    <label class="text-xs font-semibold text-slate-500">Representante legal
                        <input name="representante" value="{{ $m->representante }}" maxlength="150" class="{{ $in }} uppercase"></label>
                    <label class="text-xs font-semibold text-slate-500">DNI del representante
                        <input name="representante_dni" value="{{ $m->representante_dni }}" maxlength="12" inputmode="numeric" class="{{ $in }}"></label>
                    <label class="text-xs font-semibold text-slate-500">Teléfono / WhatsApp
                        <input name="telefono" value="{{ $m->telefono }}" maxlength="30" class="{{ $in }}"></label>
                    <label class="text-xs font-semibold text-slate-500">Correo
                        <input name="correo" type="email" value="{{ $m->correo }}" maxlength="150" class="{{ $in }}"></label>
                    <label class="text-xs font-semibold text-slate-500">Color principal
                        <input name="color" type="color" value="{{ $m->color ?: '#1565c0' }}" class="mt-1 h-10 w-full rounded-xl border-slate-300"></label>
                    <label class="text-xs font-semibold text-slate-500">Color secundario
                        <input name="color_secundario" type="color" value="{{ $m->color_secundario ?: '#65b32e' }}" class="mt-1 h-10 w-full rounded-xl border-slate-300"></label>
                    <label class="text-xs font-semibold text-slate-500">Logo <span class="font-normal text-slate-400">(mejor sin fondo; se guarda en PNG)</span>
                        <input name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700"></label>
                    <label class="text-xs font-semibold text-slate-500">Ícono cuadrado <span class="font-normal text-slate-400">(pestaña y app; opcional)</span>
                        <span class="mt-1 flex items-center gap-2">
                            @if (! $nueva && is_file(public_path(\App\Http\Controllers\Admin\MarcaController::rutaIcono($m))))
                                <img src="{{ asset(\App\Http\Controllers\Admin\MarcaController::rutaIcono($m)) }}?v={{ @filemtime(public_path(\App\Http\Controllers\Admin\MarcaController::rutaIcono($m))) }}" alt="" class="h-10 w-10 rounded-lg bg-slate-100 object-contain">
                            @endif
                            <input name="icono" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700">
                        </span>
                        <span class="block font-normal text-[11px] text-slate-400">Vacío = se arma solo con el logo.</span></label>
                </div>
                @if ($fija)<p class="text-[11px] text-slate-400">Ya tiene empresas: el dominio y el prefijo quedan fijos.</p>@endif
                <div class="flex items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm"><input type="hidden" name="activa" value="0"><input type="checkbox" name="activa" value="1" @checked($m->activa ?? true) class="rounded"> Activa</label>
                    <button class="h-10 px-5 rounded-xl {{ $nueva ? 'bg-slate-800' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white text-sm font-bold">{{ $nueva ? 'Crear marca' : 'Guardar' }}</button>
                </div>
            </form>
        @endforeach
    </div>
@endsection
