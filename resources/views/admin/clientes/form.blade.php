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
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">Nombre comercial <span class="font-normal text-slate-400">(opcional)</span></span>
                    <input type="text" name="nombre_comercial" value="{{ old('nombre_comercial', $cliente->nombre_comercial) }}" class="mt-1 w-full rounded-xl border-slate-300">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Subdominio propio <span class="font-normal text-slate-400">(opcional)</span></span>
                    <div class="mt-1 flex items-center rounded-xl border border-slate-300 bg-white overflow-hidden focus-within:ring-2 focus-within:ring-indigo-300">
                        <input type="text" name="subdominio" x-model="sub" @input="sub = sub.toLowerCase().replace(/[^a-z0-9-]/g, '')" maxlength="40"
                               placeholder="demo" class="flex-1 min-w-0 border-0 focus:ring-0 font-mono text-sm">
                        <span class="px-2 text-xs text-slate-400 whitespace-nowrap">.{{ config('tenancy.dominio') }}</span>
                    </div>
                    <span class="text-xs text-slate-400" x-text="'Entrará por ' + (sub || ruc || 'RUC') + '.{{ config('tenancy.dominio') }}'"></span>
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
                <div class="sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">Plan</span>
                    <div class="mt-1 grid sm:grid-cols-3 gap-3">
                        @foreach ($planes as $pl)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="plan_id" value="{{ $pl->id }}" class="peer sr-only" @checked((int) old('plan_id', $cliente->plan_id) === $pl->id)>
                                <div class="h-full rounded-2xl border-2 p-4 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 {{ $pl->destacado ? 'border-indigo-200' : 'border-slate-200' }}">
                                    @if ($pl->destacado)<span class="absolute -top-2 right-3 px-2 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-bold">MÁS POPULAR</span>@endif
                                    <p class="font-bold text-slate-800">{{ $pl->nombre }}</p>
                                    <p class="text-2xl font-black text-indigo-700">S/ {{ number_format($pl->precio, 0) }}<span class="text-xs font-semibold text-slate-400"> /mes</span></p>
                                    <ul class="mt-2 text-xs text-slate-500 space-y-0.5">
                                        @foreach ($pl->listaCaracteristicas() as $c)<li>✔ {{ $c }}</li>@endforeach
                                    </ul>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <label class="inline-flex items-center gap-2 mt-2 text-xs text-slate-500">
                        <input type="radio" name="plan_id" value="" class="rounded-full" @checked(!old('plan_id', $cliente->plan_id))> Sin plan (sin límites)
                    </label>
                </div>
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
                @if (!empty($usuarios) && $usuarios->isNotEmpty() && $cliente->exists)
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-semibold text-slate-600">Pertenece al usuario del panel</span>
                        <select name="creado_por" class="mt-1 w-full rounded-xl border-slate-300">
                            @foreach ($usuarios as $id => $nombre)<option value="{{ $id }}" @selected((int) old('creado_por', $cliente->creado_por) === $id)>{{ $nombre }}</option>@endforeach
                        </select>
                        <span class="text-xs text-slate-400">Ese usuario la verá en su panel. Tú (dueño) ves todas.</span>
                    </label>
                @endif
                <label class="block sm:col-span-2">
                    <span class="text-sm font-semibold text-slate-600">Notas</span>
                    <textarea name="notas" rows="2" class="mt-1 w-full rounded-xl border-slate-300">{{ old('notas', $cliente->notas) }}</textarea>
                </label>
            </div>
        </section>


        {{-- Tipo de negocio: define el menú con el que arranca su administrador (y luego se puede ajustar aquí mismo) --}}
        @php
            $todas = $catalogo->flatten()->pluck('mod_url')->unique()->values()->all();
            $presets = collect($rubros)->map(fn ($r, $k) => \App\Support\Rubros::urls($k) === ['*'] ? $todas : \App\Support\Rubros::urls($k));
            $rubroActual = old('rubro', $cliente->rubro ?: ($nuevo ? 'GENERAL' : ''));
            $marcados = old('modulos', $nuevo ? ($presets[$rubroActual] ?? []) : ($menuActual ?? []));
        @endphp
        <section class="bg-white rounded-2xl shadow-sm p-5 space-y-4"
                 x-data="{ rubro: @js($rubroActual), sel: @js(array_values($marcados)), presets: @js($presets), cambiar: {{ $nuevo ? 'true' : 'false' }},
                           aplicar() { if (this.presets[this.rubro]) this.sel = [...this.presets[this.rubro]]; } }">
            <div>
                <h2 class="font-bold text-slate-700">Tipo de negocio y menú</h2>
                <p class="text-xs text-slate-500">Su administrador verá solo estas opciones. Sus trabajadores no podrán tener más que eso.</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach ($rubros as $clave => $r)
                    <label class="cursor-pointer rounded-xl border-2 p-3 transition" :class="rubro === '{{ $clave }}' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-indigo-200'">
                        <input type="radio" name="rubro" value="{{ $clave }}" x-model="rubro" @change="if (cambiar) aplicar()" class="sr-only">
                        <span class="block font-bold text-sm text-slate-800">{{ $r['nombre'] }}</span>
                        <span class="block text-[11px] text-slate-500 leading-tight">{{ $r['ejemplos'] }}</span>
                    </label>
                @endforeach
            </div>

            @unless ($nuevo)
                @if ($menuActual === null)
                    <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2">No se pudo leer el menú actual de esta empresa (¿su base existe?).</p>
                @else
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="cambiar_menu" value="1" x-model="cambiar" class="rounded border-slate-300">
                        Cambiar el menú de sus administradores <span class="font-normal text-slate-400" x-text="'(hoy tiene ' + sel.length + ' opciones)'"></span>
                    </label>
                @endif
            @endunless

            <div x-show="cambiar" x-cloak class="space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button type="button" @click="aplicar()" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-semibold">Marcar el menú del tipo elegido</button>
                    <button type="button" @click="sel = @js($todas)" class="px-3 py-1.5 rounded-lg bg-slate-100 font-semibold">Todo</button>
                    <button type="button" @click="sel = []" class="px-3 py-1.5 rounded-lg bg-slate-100 font-semibold">Nada</button>
                    <span class="text-slate-500" x-text="sel.length + ' opciones marcadas'"></span>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[460px] overflow-y-auto pr-1">
                    @foreach ($catalogo as $grupo => $mods)
                        <div class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-bold uppercase text-indigo-700 mb-1">{{ $grupo }}</p>
                            @foreach ($mods->unique('mod_url') as $m)
                                <label class="flex items-center gap-2 text-sm py-0.5">
                                    <input type="checkbox" name="modulos[]" value="{{ $m->mod_url }}" x-model="sel" :disabled="!cambiar" class="rounded border-slate-300">
                                    {{ $m->mod_nom }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
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
            ruc: @json(old('ruc', $cliente->ruc ?? '')), sub: @json(old('subdominio', $cliente->subdominio ?? '')), buscando: false, msg: '', msgOk: true, enviando: false, ultimo: '',
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
