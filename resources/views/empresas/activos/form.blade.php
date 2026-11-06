@extends('layouts.app')
@section('title', $a ? 'Editar '.$a->codigo : 'Nuevo activo fijo')
@section('content')
@php
    $v = fn ($campo, $defecto = '') => old($campo, $a->{$campo} ?? $defecto);
    $in = 'mt-1 w-full h-10 rounded-xl border-gray-300 text-sm';
    $tasas = collect($categorias)->map(fn ($c) => ['tasa' => $c[1], 'cuenta' => $c[2]]);
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $a ? route('activos.update', $a->id) : route('activos.store') }}" class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 space-y-4"
          x-data="{ categoria: @js($v('categoria', 'COMPUTO')), tasa: @js($v('tasa', '')), tasas: @js($tasas), costo: @js($v('costo', '')),
                    get maxima() { return this.tasas[this.categoria]?.tasa ?? 0 }, get cuota() { return ((Number(this.costo) || 0) * (Number(this.tasa) || 0) / 100 / 12).toFixed(2) } }"
          x-init="if (tasa === '' || tasa === null) tasa = maxima" @change.capture="if ($event.target.name === 'categoria') tasa = maxima">
        @csrf @if ($a) @method('PUT') @endif
        <div>
            <a href="{{ $a ? route('activos.show', $a->id) : route('activos.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">← Activos fijos</a>
            <h1 class="text-xl font-extrabold text-gray-800">{{ $a ? 'Editar '.$a->codigo : 'Nuevo activo fijo' }}</h1>
            @if ($procesado)<p class="text-sm text-amber-700">Ya tiene depreciación procesada: costo, tasa, fechas y cuentas ya no se cambian.</p>@endif
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs font-semibold text-gray-600">Código *<input name="codigo" value="{{ $v('codigo') }}" required maxlength="20" placeholder="AF-001" class="{{ $in }} uppercase"></label>
            <label class="text-xs font-semibold text-gray-600 lg:col-span-3">Descripción *<input name="descripcion" value="{{ $v('descripcion') }}" required maxlength="200" placeholder="Laptop Lenovo ThinkPad" class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Categoría (SUNAT)
                <select name="categoria" x-model="categoria" @disabled($procesado) class="{{ $in }}">
                    @foreach ($categorias as $k => [$nombre, $tasa])<option value="{{ $k }}">{{ $nombre }} — hasta {{ $tasa }}% al año</option>@endforeach
                </select>
                @if ($procesado)<input type="hidden" name="categoria" value="{{ $a->categoria }}">@endif</label>
            <label class="text-xs font-semibold text-gray-600">Marca / modelo<input name="marca_modelo" value="{{ $v('marca_modelo') }}" maxlength="100" class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Serie<input name="serie" value="{{ $v('serie') }}" maxlength="60" class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Fecha de compra *<input type="date" name="fecha_adquisicion" value="{{ $v('fecha_adquisicion', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required @readonly($procesado) class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Empezó a usarse *<input type="date" name="fecha_uso" value="{{ $v('fecha_uso', now()->toDateString()) }}" required @readonly($procesado) class="{{ $in }}">
                <span class="text-[11px] text-gray-400">Se deprecia desde ese mes.</span></label>
            <label class="text-xs font-semibold text-gray-600">Costo (S/, sin IGV) *<input type="number" step="0.01" min="0.01" name="costo" x-model="costo" required @readonly($procesado) class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Tasa anual %<input type="number" step="0.01" min="0" :max="maxima" name="tasa" x-model="tasa" required @readonly($procesado) class="{{ $in }}">
                <span class="text-[11px] text-gray-400" x-text="'Máximo SUNAT ' + maxima + '% · S/ ' + cuota + ' al mes'"></span></label>
            <label class="text-xs font-semibold text-gray-600">Valor residual (S/)<input type="number" step="0.01" min="0" name="valor_residual" value="{{ $v('valor_residual', 0) }}" @readonly($procesado) class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Ya depreciado antes (S/)<input type="number" step="0.01" min="0" name="depreciacion_inicial" value="{{ $v('depreciacion_inicial', 0) }}" @readonly($procesado) class="{{ $in }}">
                <span class="text-[11px] text-gray-400">Si lo traes de antes de usar el sistema.</span></label>
            <label class="text-xs font-semibold text-gray-600">Comprobante<input name="documento" value="{{ $v('documento') }}" maxlength="60" placeholder="F001-1234" class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Ubicación<input name="ubicacion" value="{{ $v('ubicacion') }}" maxlength="100" placeholder="Caja principal" class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Responsable<input name="responsable" value="{{ $v('responsable') }}" maxlength="100" class="{{ $in }}"></label>
            <label class="text-xs font-semibold text-gray-600">Centro de costo
                <select name="centro_costo_id" class="{{ $in }}"><option value="">— Ninguno —</option>
                    @foreach ($centros as $c)<option value="{{ $c->id }}" @selected($v('centro_costo_id') == $c->id)>{{ $c->codigo }} · {{ $c->nombre }}</option>@endforeach
                </select></label>
        </div>

        <details class="rounded-xl border border-gray-200 p-3" @if (! $a) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-gray-700">Contabilidad</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach (['cuenta_activo' => ['Cuenta del activo (33)', 'activo'], 'cuenta_depreciacion' => ['Depreciación acumulada (39)', 'depreciacion'], 'cuenta_gasto' => ['Gasto de depreciación (68)', 'gasto']] as $campo => [$titulo, $grupo])
                    <label class="text-xs font-semibold text-gray-600">{{ $titulo }}
                        <select name="{{ $campo }}" @disabled($procesado) class="{{ $in }}">
                            @if ($campo === 'cuenta_activo')<option value="">Según la categoría</option>@endif
                            @foreach ($cuentas[$grupo] as $codigo => $nombre)<option value="{{ $codigo }}" @selected($v($campo, ['cuenta_depreciacion' => '3913', 'cuenta_gasto' => '681'][$campo] ?? '') == $codigo)>{{ $codigo }} · {{ $nombre }}</option>@endforeach
                        </select>
                        @if ($procesado)<input type="hidden" name="{{ $campo }}" value="{{ $a->{$campo} }}">@endif</label>
                @endforeach
                @if (! $a)
                    <label class="text-xs font-semibold text-gray-600 sm:col-span-3">Asiento de alta
                        <select name="contrapartida_alta" class="{{ $in }}">
                            <option value="">No crear asiento (ya está en la contabilidad: vino de una compra o del saldo inicial)</option>
                            @foreach ($cuentas['pago'] as $codigo => $nombre)<option value="{{ $codigo }}" @selected(old('contrapartida_alta') == $codigo)>Crear asiento: 33 (D) contra {{ $codigo }} · {{ $nombre }}</option>@endforeach
                        </select></label>
                @endif
            </div>
        </details>
        <div class="flex justify-end"><button class="h-10 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Guardar</button></div>
    </form>
</div>
@endsection
