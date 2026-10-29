@extends('layouts.app')
@section('title', 'Clientes con crédito')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $campo = 'mt-1 w-full h-10 rounded-lg border-gray-300 text-sm';
        $etiqueta = 'text-xs font-semibold text-gray-500';
    @endphp

    <div class="space-y-5" x-data="{ form: null, buscando: false,
            async buscar() { const d = String(this.form.clinum).replace(/\D/g, ''); if (d.length !== 8 && d.length !== 11) return; this.buscando = true;
                try { const r = await fetch(@js(url('courier/documento')) + '/' + d, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    if (r.nombre) this.form.clinom = r.nombre; if (r.telefono) this.form.telefono = r.telefono; } catch (e) {} finally { this.buscando = false; } } }">
        @include('empresas.courier.partials.nav')
        @include('empresas.partials.alert')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-handshake text-orange-600"></i> Clientes con crédito</h1>
                <p class="text-sm text-gray-500">Empresas que envían sin pagar en el momento: a fin de mes se les emite una sola factura al crédito por todos sus envíos.</p>
            </div>
            @if ($esAdmin)
                <button type="button" @click="form = { clinum: '', clinom: '', telefono: '', limite: 0, dias: 30, activo: true }" class="h-11 px-5 rounded-xl bg-orange-600 text-white font-extrabold"><i class="fas fa-plus"></i> Dar crédito</button>
            @endif
        </div>

        <form x-show="form" x-cloak method="POST" action="{{ route('courier.creditos.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-3 gap-3">
            @csrf
            <label class="{{ $etiqueta }}">RUC o DNI
                <div class="flex gap-2"><input name="clinum" x-model="form.clinum" @change="buscar()" required maxlength="11" inputmode="numeric" class="{{ $campo }}">
                    <button type="button" @click="buscar()" class="mt-1 h-10 px-3 rounded-lg bg-gray-100"><i class="fas" :class="buscando ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i></button></div></label>
            <label class="sm:col-span-2 {{ $etiqueta }}">Razón social o nombre<input name="clinom" x-model="form.clinom" required maxlength="150" class="{{ $campo }} uppercase"></label>
            <label class="{{ $etiqueta }}">Celular<input name="telefono" x-model="form.telefono" maxlength="20" class="{{ $campo }}"></label>
            <label class="{{ $etiqueta }}">Límite S/ (0 = sin límite)<input type="number" name="limite" x-model="form.limite" step="0.01" min="0" required class="{{ $campo }} text-right"></label>
            <label class="{{ $etiqueta }}">Plazo de la factura (días)<input type="number" name="dias" x-model="form.dias" min="1" max="120" required class="{{ $campo }} text-right"></label>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" :checked="form?.activo" class="rounded"> Crédito activo</label>
            <div class="sm:col-span-2 flex gap-2 justify-end">
                <button type="button" @click="form = null" class="px-4 h-10 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                <button class="px-5 h-10 rounded-xl bg-orange-600 text-white font-bold">Guardar</button>
            </div>
        </form>

        <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
            @forelse ($creditos as $c)
                <div class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $c->activo ? '' : 'opacity-50' }}">
                    <div class="flex-1 min-w-56">
                        <p class="font-black text-gray-800">{{ $c->clinom }} @unless ($c->activo)<span class="text-xs text-gray-400">(suspendido)</span>@endunless</p>
                        <p class="text-xs text-gray-500">{{ $c->clinum }} · límite {{ (float) $c->limite > 0 ? $soles($c->limite) : 'sin límite' }} · factura a {{ $c->dias }} días</p>
                    </div>
                    <div class="text-right">
                        <p class="font-black {{ $c->deuda > 0 ? 'text-rose-700' : 'text-gray-400' }}">{{ $soles($c->deuda) }}</p>
                        <p class="text-xs text-gray-400">{{ $c->envios }} envío(s) por facturar</p>
                        @if ((float) $c->limite > 0)
                            <div class="mt-1 w-32 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full {{ $c->deuda / $c->limite > .8 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ min(100, round($c->deuda * 100 / $c->limite)) }}%"></div></div>
                        @endif
                    </div>
                    <a href="{{ route('courier.creditos.cuenta', $c->clicod) }}" class="h-10 px-4 rounded-xl bg-orange-50 text-orange-700 text-sm font-bold flex items-center gap-1"><i class="fas fa-file-invoice"></i> Estado de cuenta</a>
                    @if ($esAdmin)
                        <button type="button" @click="form = @js(['clinum' => $c->clinum, 'clinom' => $c->clinom, 'telefono' => $c->telefono, 'limite' => (float) $c->limite, 'dias' => (int) $c->dias, 'activo' => (bool) $c->activo]); window.scrollTo({ top: 0, behavior: 'smooth' })"
                                class="h-10 w-10 rounded-xl bg-gray-100 text-gray-600"><i class="fas fa-pen"></i></button>
                    @endif
                </div>
            @empty
                <p class="p-10 text-center text-gray-400">Aún no hay clientes con crédito.</p>
            @endforelse
        </div>
    </div>
@endsection
