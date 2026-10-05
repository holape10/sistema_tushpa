@extends('admin.layout')
@php $nuevo = !$cliente->exists; @endphp
@section('title', $nuevo ? 'Nuevo cliente' : 'Editar cliente')

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('admin.clientes.index') }}" class="text-sm text-slate-500 hover:text-indigo-700">← Clientes</a>
    <h1 class="text-xl font-bold mt-1 mb-4">{{ $nuevo ? 'Nuevo cliente' : $cliente->razon_social }}</h1>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ $nuevo ? route('admin.clientes.store') : route('admin.clientes.update', $cliente) }}"
          x-data="clienteForm()" @submit="enviando = true" class="space-y-5">
        @csrf
        @unless ($nuevo) @method('PATCH') @endunless

        <section class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="font-bold text-slate-700">Empresa</h2>
            <div class="grid sm:grid-cols-3 gap-4">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">RUC</span>
                    @if ($nuevo)
                        <div class="mt-1 flex gap-2">
                            <input type="text" name="ruc" x-model="ruc" @input="if (/^(10|15|17|20)\d{9}$/.test(ruc)) buscarRuc()" value="{{ old('ruc') }}"
                                   inputmode="numeric" maxlength="11" required class="w-full rounded-xl border-slate-300 font-mono">
                            <button type="button" @click="buscarRuc()" :disabled="buscando" class="px-3 rounded-xl bg-slate-800 text-white text-sm disabled:opacity-50" x-text="buscando ? '…' : 'SUNAT'"></button>
                        </div>
                        <p class="text-xs mt-1" :class="msgOk ? 'text-emerald-600' : 'text-rose-600'" x-text="msg"></p>
                    @else
                        <p class="mt-1 font-mono font-semibold">{{ $cliente->ruc }}</p>
                        <p class="text-xs text-slate-400">Base: {{ $cliente->base_datos }}</p>
                    @endif
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">Razón social</span>
                    <input type="text" name="razon_social" x-ref="razon" value="{{ old('razon_social', $cliente->razon_social) }}" required class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                <label class="block sm:col-span-3">
                    <span class="text-sm font-semibold text-slate-600">Nombre comercial <span class="font-normal text-slate-400">(opcional)</span></span>
                    <input type="text" name="nombre_comercial" value="{{ old('nombre_comercial', $cliente->nombre_comercial) }}" class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                @if ($nuevo)
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-semibold text-slate-600">Dirección fiscal</span>
                        <input type="text" name="direccion" x-ref="direccion" value="{{ old('direccion') }}" required class="mt-1 w-full rounded-xl border-slate-300">
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-600">Ubigeo</span>
                        <input type="text" name="ubigeo" x-ref="ubigeo" value="{{ old('ubigeo') }}" maxlength="6" inputmode="numeric" class="mt-1 w-full rounded-xl border-slate-300 font-mono">
                    </label>
                @endif
            </div>
        </section>

        @if ($nuevo)
            <section class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                <div>
                    <h2 class="font-bold text-slate-700">Acceso del administrador del cliente</h2>
                    <p class="text-xs text-slate-400">Si los dejas vacíos, el usuario y la contraseña son el RUC de la empresa.</p>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-600">Usuario</span>
                        <input type="text" name="usuario" value="{{ old('usuario') }}" :placeholder="ruc || 'RUC'" autocomplete="off" class="mt-1 w-full rounded-xl border-slate-300">
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-600">Contraseña</span>
                        <input type="text" name="password" :placeholder="ruc || 'RUC'" autocomplete="new-password" minlength="8" class="mt-1 w-full rounded-xl border-slate-300">
                    </label>
                </div>
            </section>
        @endif

        <section class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="font-bold text-slate-700">Contrato y contacto</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Plan</span>
                    <input type="text" name="plan" value="{{ old('plan', $cliente->plan) }}" placeholder="Ej. Mensual, Anual" class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Vence el</span>
                    <input type="date" name="vence_el" value="{{ old('vence_el', $cliente->vence_el?->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Contacto</span>
                    <input type="text" name="contacto_nombre" value="{{ old('contacto_nombre', $cliente->contacto_nombre) }}" class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Teléfono</span>
                    <input type="text" name="contacto_telefono" value="{{ old('contacto_telefono', $cliente->contacto_telefono) }}" class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">Correo</span>
                    <input type="email" name="contacto_correo" value="{{ old('contacto_correo', $cliente->contacto_correo) }}" class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">Notas</span>
                    <textarea name="notas" rows="2" class="mt-1 w-full rounded-xl border-slate-300">{{ old('notas', $cliente->notas) }}</textarea>
                </label>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.clientes.index') }}" class="px-4 h-11 inline-flex items-center rounded-xl bg-white border border-slate-300 text-sm font-semibold">Cancelar</a>
            <button :disabled="enviando" class="px-5 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-60">
                <span x-text="enviando ? '{{ $nuevo ? 'Creando base de datos…' : 'Guardando…' }}' : '{{ $nuevo ? 'Crear cliente' : 'Guardar' }}'"></span>
            </button>
        </div>
    </form>
</div>

<script>
    function clienteForm() {
        return {
            ruc: @json(old('ruc', '')), buscando: false, msg: '', msgOk: true, enviando: false, ultimo: '',
            async buscarRuc() {
                if (this.buscando || this.ruc === this.ultimo || !/^\d{11}$/.test(this.ruc)) return;
                this.buscando = true; this.ultimo = this.ruc; this.msg = 'Consultando SUNAT…'; this.msgOk = true;
                try {
                    const r = await fetch(@json(url(config('tenancy.ruta_admin') . '/ruc')) + '/' + this.ruc, { headers: { Accept: 'application/json' } });
                    const d = await r.json();
                    if (d.error) { this.msg = d.error; this.msgOk = false; return; }
                    this.$refs.razon.value = d.nom || '';
                    this.$refs.direccion.value = d.dir || '';
                    this.$refs.ubigeo.value = d.ubigeo || '';
                    this.msg = '✔ Datos de SUNAT cargados';
                } catch (e) { this.msg = 'No se pudo consultar. Llena los datos a mano.'; this.msgOk = false; }
                finally { this.buscando = false; }
            },
        };
    }
</script>
@endsection
