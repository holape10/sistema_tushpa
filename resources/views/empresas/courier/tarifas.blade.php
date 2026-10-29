@extends('layouts.app')
@section('title', 'Tarifas y agencias')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $campo = 'mt-1 w-full h-10 rounded-lg border-gray-300 text-sm';
        $etiqueta = 'text-xs font-semibold text-gray-500';
        $tarjeta = 'bg-white rounded-2xl shadow-sm p-5';
        $porId = $agencias->keyBy('age_id');
        $sucursalesPorId = $sucursales->keyBy('id_empresa_negocio');
        $precios = [['base', 'Base S/', 'incluye los primeros kilos'], ['kg_incluidos', 'Kilos incluidos', ''], ['por_kg', 'Kilo adicional S/', ''],
            ['sobre', 'Sobre S/', 'documentos, cada uno'], ['domicilio', 'Reparto a domicilio S/', ''], ['dias', 'Días de llegada', '']];
    @endphp

    <div class="space-y-5" x-data="{ agencia: null, ruta: { origen_id: '', destino_id: '', base: {{ (float) $cfg->base }}, kg_incluidos: {{ (float) $cfg->kg_incluidos }}, por_kg: {{ (float) $cfg->por_kg }}, sobre: {{ (float) $cfg->sobre }}, domicilio: {{ (float) $cfg->domicilio }}, dias: {{ (int) $cfg->dias }} } }">
        @include('empresas.courier.partials.nav')
        <div>
            <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-route text-orange-600"></i> Tarifas y agencias</h1>
        </div>
        @include('empresas.partials.alert')

        {{-- Agencias --}}
        <div class="{{ $tarjeta }}">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <h2 class="font-black text-gray-800"><i class="fas fa-warehouse text-orange-500"></i> Agencias</h2>
                    <p class="text-xs text-gray-400">Donde se deja y se recoge. Si la atiende una sucursal tuya, esa sucursal recibe y entrega sus envíos; sin sucursal es un aliado y lo maneja la agencia que envía.</p>
                </div>
                <button type="button" @click="agencia = { age_id: '', nombre: '', ciudad: '', direccion: '', telefono: '', id_empresa_negocio: '', activo: true, comision_pct: 0 }"
                        class="h-10 px-4 rounded-xl bg-orange-600 text-white text-sm font-bold"><i class="fas fa-plus"></i> Agencia</button>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($agencias as $a)
                    <div class="py-2.5 flex flex-wrap items-center gap-3 {{ $a->activo ? '' : 'opacity-50' }}">
                        <div class="flex-1 min-w-56">
                            <p class="font-bold text-gray-800">{{ $a->ciudad }} · {{ $a->nombre }} @unless ($a->activo)<span class="text-xs text-gray-400">(inactiva)</span>@endunless</p>
                            <p class="text-xs text-gray-500">{{ $a->direccion ?: 'Sin dirección' }}{{ $a->telefono ? ' · '.$a->telefono : '' }}
                                · {{ $a->id_empresa_negocio ? 'Sucursal: '.($sucursalesPorId[$a->id_empresa_negocio]->nombre_comercial ?? '—') : 'Aliado (sin sucursal) · comisión '.rtrim(rtrim(number_format($a->comision_pct, 2), '0'), '.').'%' }}</p>
                        </div>
                        <button type="button" @click="agencia = @js(['age_id' => $a->age_id, 'nombre' => $a->nombre, 'ciudad' => $a->ciudad, 'direccion' => $a->direccion, 'telefono' => $a->telefono, 'id_empresa_negocio' => $a->id_empresa_negocio, 'activo' => (bool) $a->activo, 'comision_pct' => (float) $a->comision_pct])"
                                class="h-9 px-3 rounded-lg bg-gray-100 text-sm font-semibold"><i class="fas fa-pen"></i> Editar</button>
                    </div>
                @endforeach
            </div>
            <form x-show="agencia" x-cloak method="POST" action="{{ route('courier.agencias.guardar') }}" class="mt-4 rounded-xl bg-orange-50/60 p-4 grid sm:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="age_id" :value="agencia?.age_id">
                <label class="{{ $etiqueta }}">Ciudad<input name="ciudad" :value="agencia?.ciudad" required maxlength="60" placeholder="LIMA" class="{{ $campo }} uppercase"></label>
                <label class="{{ $etiqueta }}">Nombre de la agencia<input name="nombre" :value="agencia?.nombre" required maxlength="100" placeholder="AGENCIA LA VICTORIA" class="{{ $campo }} uppercase"></label>
                <label class="{{ $etiqueta }}">Teléfono<input name="telefono" :value="agencia?.telefono" maxlength="30" class="{{ $campo }}"></label>
                <label class="sm:col-span-2 {{ $etiqueta }}">Dirección<input name="direccion" :value="agencia?.direccion" maxlength="200" class="{{ $campo }} uppercase"></label>
                <label class="{{ $etiqueta }}">La atiende
                    <select name="id_empresa_negocio" class="{{ $campo }}">
                        <option value="">Un aliado (sin sucursal)</option>
                        @foreach ($sucursales as $s)<option value="{{ $s->id_empresa_negocio }}" :selected="agencia?.id_empresa_negocio == {{ $s->id_empresa_negocio }}">{{ $s->nombre_comercial }}</option>@endforeach
                    </select></label>
                <label class="{{ $etiqueta }}">Comisión del aliado % <span class="font-normal">(sobre lo que entrega)</span>
                    <input type="number" name="comision_pct" :value="agencia?.comision_pct" step="0.01" min="0" max="100" class="{{ $campo }} text-right"></label>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" :checked="agencia?.activo" class="rounded"> Activa</label>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="agencia = null" class="px-4 h-10 rounded-xl bg-white font-bold">Cancelar</button>
                    <button class="px-5 h-10 rounded-xl bg-orange-600 text-white font-bold">Guardar agencia</button>
                </div>
            </form>
        </div>

        {{-- Tarifas por ruta --}}
        <div class="{{ $tarjeta }}">
            <h2 class="font-black text-gray-800"><i class="fas fa-route text-orange-500"></i> Tarifa de cada ruta</h2>
            <p class="text-xs text-gray-400 mb-3">Vale en los dos sentidos (Iquitos → Lima y Lima → Iquitos). Las rutas sin tarifa usan la tarifa general.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-400"><th class="py-2">Ruta</th><th class="text-right">Base</th><th class="text-right">Incluye</th>
                        <th class="text-right">Kg adic.</th><th class="text-right">Sobre</th><th class="text-right">Domicilio</th><th class="text-right">Días</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tarifas as $t)
                            <tr>
                                <td class="py-2 font-semibold">{{ $porId[$t->origen_id]->ciudad ?? '?' }} ⇄ {{ $porId[$t->destino_id]->ciudad ?? '?' }}
                                    <span class="block text-xs font-normal text-gray-400">{{ $porId[$t->origen_id]->nombre ?? '' }} · {{ $porId[$t->destino_id]->nombre ?? '' }}</span></td>
                                <td class="text-right">{{ number_format($t->base, 2) }}</td><td class="text-right">{{ rtrim(rtrim(number_format($t->kg_incluidos, 2), '0'), '.') }} kg</td>
                                <td class="text-right">{{ number_format($t->por_kg, 2) }}</td><td class="text-right">{{ number_format($t->sobre, 2) }}</td>
                                <td class="text-right">{{ number_format($t->domicilio, 2) }}</td><td class="text-right">{{ $t->dias }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="ruta = @js(['origen_id' => $t->origen_id, 'destino_id' => $t->destino_id] + collect(['base', 'kg_incluidos', 'por_kg', 'sobre', 'domicilio', 'dias'])->mapWithKeys(fn ($k) => [$k => (float) $t->$k])->all()); $refs.ruta.scrollIntoView({ behavior: 'smooth' })"
                                            class="text-orange-600 px-2"><i class="fas fa-pen"></i></button>
                                    <form method="POST" action="{{ route('courier.tarifas.quitar', $t->tar_id) }}" class="inline" onsubmit="return confirm('¿Quitar la tarifa de esta ruta? Usará la tarifa general.')">@csrf
                                        <button class="text-rose-500 px-2"><i class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-4 text-center text-gray-400">Aún no hay tarifas por ruta: todas usan la tarifa general.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <form x-ref="ruta" method="POST" action="{{ route('courier.tarifas.ruta') }}" class="mt-4 rounded-xl bg-orange-50/60 p-4 grid sm:grid-cols-4 gap-3">
                @csrf
                <label class="sm:col-span-2 {{ $etiqueta }}">Desde
                    <select name="origen_id" x-model="ruta.origen_id" required class="{{ $campo }}"><option value="">Agencia…</option>
                        @foreach ($agencias->where('activo', 1) as $a)<option value="{{ $a->age_id }}">{{ $a->ciudad }} · {{ $a->nombre }}</option>@endforeach</select></label>
                <label class="sm:col-span-2 {{ $etiqueta }}">Hasta
                    <select name="destino_id" x-model="ruta.destino_id" required class="{{ $campo }}"><option value="">Agencia…</option>
                        @foreach ($agencias->where('activo', 1) as $a)<option value="{{ $a->age_id }}">{{ $a->ciudad }} · {{ $a->nombre }}</option>@endforeach</select></label>
                @foreach ($precios as [$k, $texto])
                    <label class="{{ $etiqueta }}">{{ $texto }}<input type="number" name="{{ $k }}" x-model="ruta.{{ $k }}" step="{{ $k === 'dias' ? 1 : '0.01' }}" min="0" required class="{{ $campo }} text-right"></label>
                @endforeach
                <div class="sm:col-span-2 flex items-end justify-end"><button class="px-5 h-10 rounded-xl bg-orange-600 text-white font-bold">Guardar tarifa de la ruta</button></div>
            </form>
        </div>

        {{-- Tarifa general --}}
        <form method="POST" action="{{ route('courier.tarifas.general') }}" class="{{ $tarjeta }}">
            @csrf
            <h2 class="font-black text-gray-800"><i class="fas fa-scale-balanced text-orange-500"></i> Tarifa general y seguro</h2>
            <p class="text-xs text-gray-400 mb-3">Para las rutas que no tienen su propia tarifa. Un paquete paga la base (con sus kilos incluidos) y cada kilo adicional o fracción; se cobra por el mayor entre el peso real y el volumétrico.</p>
            <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach ($precios as [$k, $texto, $ayuda])
                    <label class="{{ $etiqueta }}">{{ $texto }}<input type="number" name="{{ $k }}" value="{{ $k === 'dias' ? (int) $cfg->$k : (float) $cfg->$k }}" step="{{ $k === 'dias' ? 1 : '0.01' }}" min="0" required class="{{ $campo }} text-right">
                        @if ($ayuda)<span class="font-normal text-gray-400">{{ $ayuda }}</span>@endif</label>
                @endforeach
                <label class="{{ $etiqueta }}">Divisor volumétrico<input type="number" name="divisor_vol" value="{{ (int) $cfg->divisor_vol }}" min="0" required class="{{ $campo }} text-right">
                    <span class="font-normal text-gray-400">largo×ancho×alto (cm) ÷ esto. 0 = no usar</span></label>
                <label class="{{ $etiqueta }}">Seguro % del valor<input type="number" name="seguro_pct" value="{{ (float) $cfg->seguro_pct }}" step="0.01" min="0" required class="{{ $campo }} text-right"></label>
                <label class="{{ $etiqueta }}">Seguro mínimo S/<input type="number" name="seguro_min" value="{{ (float) $cfg->seguro_min }}" step="0.01" min="0" required class="{{ $campo }} text-right"></label>
                <label class="{{ $etiqueta }}">Rezagado desde (días)<input type="number" name="dias_almacen" value="{{ (int) $cfg->dias_almacen }}" min="1" max="365" required class="{{ $campo }} text-right">
                    <span class="font-normal text-gray-400">sin recoger en la agencia</span></label>
                <label class="sm:col-span-2 flex items-start gap-2 text-sm pt-5"><input type="checkbox" name="web_publico" value="1" @checked($cfg->web_publico) class="rounded mt-0.5">
                    <span><b>Web pública de envíos</b> — tus clientes cotizan, se pre-registran y reclaman en <a href="{{ route('envios.web') }}" target="_blank" class="text-orange-700 underline">{{ route('envios.web') }}</a></span></label>
            </div>
            <div class="mt-4 flex justify-end"><button class="px-5 h-10 rounded-xl bg-orange-600 text-white font-bold">Guardar tarifa general</button></div>
        </form>
    </div>
@endsection
