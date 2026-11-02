@extends('layouts.app')
@section('title', 'Promociones 2x1')
@section('content')
@use('App\Support\PromocionesVenta')
    @php
        $f = $editar;
        $nombres = $productos->pluck('pronom', 'IdProducto');
        $ahora = now();
        $estado = function ($p) use ($ahora) {
            $hoy = $ahora->toDateString();
            return match (true) {
                ! $p->activo => ['Pausada', 'bg-gray-100 text-gray-600'],
                $p->hasta && $hoy > $p->hasta => ['Terminó', 'bg-red-100 text-red-700'],
                $p->desde && $hoy < $p->desde => ['Aún no empieza', 'bg-amber-100 text-amber-700'],
                PromocionesVenta::vale($p, $ahora) => ['✔ Vale ahora', 'bg-green-100 text-green-700'],
                default => ['Fuera de horario', 'bg-sky-100 text-sky-700'],
            };
        };
        $diasElegidos = old('dias', $f ? array_map('intval', str_split((string) $f->dias)) : [1, 2, 3, 4, 5, 6, 7]);
        $todoElDia = old('todo_el_dia', $f ? ! ($f->hora_inicio && $f->hora_fin) : true);
        $productosElegidos = array_map('strval', old('productos', $f->productos ?? []));
    @endphp

    <div class="max-w-6xl space-y-5">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500 via-orange-500 to-rose-500 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <p class="text-amber-50 text-sm font-semibold tracking-wide uppercase">🎉 Promociones automáticas</p>
                <h1 class="text-2xl sm:text-3xl font-black mt-1">2x1, 3x2, "3 por S/ 40"</h1>
                <p class="text-amber-50 text-sm mt-1 max-w-2xl">Crea la promo una vez y el sistema la aplica solo al cobrar (mesa, para llevar, delivery y PV Restaurante):
                    el mozo marca los productos normales y la cuenta baja sola. Sale en la precuenta, en el ticket y ante SUNAT como descuento.</p>
            </div>
        </section>

        @include('empresas.partials.alert')

        {{-- Formulario --}}
        <form method="POST" action="{{ $f ? route('promociones.guardar', $f->id) : route('promociones.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-5"
              x-data="formPromo({ tipo: @js(old('tipo', $f->tipo ?? 'LLEVA_PAGA')), lleva: @js((int) old('lleva', $f->lleva ?? 3)), paga: @js((int) old('paga', $f->paga ?? 2)),
                    precio: @js(old('precio', isset($f->precio) ? (float) $f->precio : '')), todoElDia: @js((bool) $todoElDia), elegidos: @js($productosElegidos),
                    nombre: @js(old('nombre', $f->nombre ?? '')), tocado: @js((bool) old('nombre', $f->nombre ?? '')),
                    precios: @js($productos->mapWithKeys(fn ($p) => [$p->IdProducto => (float) $p->propun])), nombres: @js($nombres) })">
            @csrf
            <div class="flex items-center gap-2">
                <h2 class="font-bold text-gray-800 mr-auto">{{ $f ? 'Editar promoción' : 'Nueva promoción' }}</h2>
                @if ($f)<a href="{{ route('promociones.index') }}" class="text-sm text-gray-500 hover:underline">Cancelar</a>@endif
            </div>

            {{-- 1. Qué promo --}}
            <div>
                <p class="text-sm font-bold text-gray-700 mb-2">1. ¿Qué promo es?</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach (PromocionesVenta::TIPOS as $valor => [$icono, $titulo, $ejemplo])
                        <label class="relative cursor-pointer rounded-xl border-2 px-4 py-3 transition" :class="tipo === '{{ $valor }}' ? 'border-orange-500 bg-orange-50/60' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="tipo" value="{{ $valor }}" x-model="tipo" class="sr-only">
                            <span class="font-bold text-gray-800">{{ $icono }} {{ $titulo }}</span><span class="block text-xs text-gray-500">{{ $ejemplo }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-700" x-show="tipo === 'LLEVA_PAGA'">
                    Lleva <input type="number" name="lleva" min="2" max="50" x-model.number="lleva" :disabled="tipo !== 'LLEVA_PAGA'" class="w-20 h-10 rounded-lg border-gray-300 text-center font-bold">
                    y paga <input type="number" name="paga" min="1" max="49" x-model.number="paga" :disabled="tipo !== 'LLEVA_PAGA'" class="w-20 h-10 rounded-lg border-gray-300 text-center font-bold">
                    <span class="text-gray-400 mx-1">·</span>
                    @foreach ([[2, 1], [3, 2], [4, 3]] as [$ll, $pg])
                        <button type="button" @click="lleva = {{ $ll }}; paga = {{ $pg }}" class="h-8 px-3 rounded-full bg-orange-100 text-orange-700 text-xs font-bold">{{ $ll }}x{{ $pg }}</button>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-700" x-show="tipo === 'POR_PRECIO'" x-cloak>
                    Lleva <input type="number" name="lleva" min="2" max="50" x-model.number="lleva" :disabled="tipo !== 'POR_PRECIO'" class="w-20 h-10 rounded-lg border-gray-300 text-center font-bold">
                    por S/ <input type="number" name="precio" min="0.1" step="0.1" x-model="precio" :disabled="tipo !== 'POR_PRECIO'" placeholder="40.00" class="w-28 h-10 rounded-lg border-gray-300 text-center font-bold">
                </div>
            </div>

            {{-- 2. Productos --}}
            <div x-data="{ buscar: '' }">
                <p class="text-sm font-bold text-gray-700 mb-2">2. ¿De qué productos? <span class="font-normal text-gray-500">(puedes elegir varios y se mezclan: "2x1 en cervezas")</span></p>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <input x-model="buscar" placeholder="🔎 Buscar producto…" class="flex-1 min-w-48 h-10 rounded-lg border-gray-300 text-sm">
                    <span class="text-sm font-semibold text-orange-700" x-text="elegidos.length + ' elegido(s)'"></span>
                </div>
                <div class="max-h-64 overflow-y-auto rounded-xl border border-gray-200 divide-y divide-gray-100">
                    @foreach ($productos as $p)
                        <label class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
                               x-show="!buscar || @js(mb_strtolower($p->pronom.' '.$p->cat_nom)).includes(buscar.toLowerCase())">
                            <input type="checkbox" name="productos[]" value="{{ $p->IdProducto }}" x-model="elegidos" class="rounded text-orange-600">
                            <span class="flex-1">{{ $p->pronom }} <span class="text-xs text-gray-400">{{ $p->cat_nom }}</span></span>
                            <span class="text-gray-500">S/ {{ number_format($p->propun, 2) }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-emerald-700 font-semibold" x-show="ejemplo()" x-text="ejemplo()"></p>
            </div>

            {{-- 3. Cuándo --}}
            <div class="grid lg:grid-cols-2 gap-5">
                <div>
                    <p class="text-sm font-bold text-gray-700 mb-2">3. ¿Qué días?</p>
                    <div class="flex flex-wrap gap-1.5" x-data="{ dias: @js(array_map('strval', $diasElegidos)) }">
                        @foreach (PromocionesVenta::DIAS as $n => $dia)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="dias[]" value="{{ $n }}" x-model="dias" class="sr-only peer">
                                <span class="inline-flex h-10 w-12 items-center justify-center rounded-xl border-2 text-sm font-bold peer-checked:border-orange-500 peer-checked:bg-orange-500 peer-checked:text-white border-gray-200 text-gray-600">{{ $dia }}</span>
                            </label>
                        @endforeach
                        <button type="button" @click="dias = ['1','2','3','4','5','6','7']" class="h-10 px-3 rounded-xl bg-gray-100 text-xs font-bold text-gray-700">Todos</button>
                        <button type="button" @click="dias = ['5','6']" class="h-10 px-3 rounded-xl bg-gray-100 text-xs font-bold text-gray-700">Vie y Sáb</button>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-700 mb-2">4. ¿A qué hora?</p>
                    <label class="flex items-center gap-2 text-sm text-gray-700 mb-2"><input type="checkbox" name="todo_el_dia" value="1" x-model="todoElDia" class="rounded text-orange-600"> Todo el día</label>
                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700" x-show="!todoElDia" x-cloak>
                        Desde <input type="time" name="hora_inicio" value="{{ old('hora_inicio', $f && $f->hora_inicio ? substr($f->hora_inicio, 0, 5) : '20:00') }}" :disabled="todoElDia" class="h-10 rounded-lg border-gray-300">
                        hasta <input type="time" name="hora_fin" value="{{ old('hora_fin', $f && $f->hora_fin ? substr($f->hora_fin, 0, 5) : '23:00') }}" :disabled="todoElDia" class="h-10 rounded-lg border-gray-300">
                        <span class="block w-full text-xs text-gray-500">Si pasa la medianoche (10 pm a 2 am), la madrugada cuenta en el día que empezó. Vale si la mesa se abrió o se cobra en ese horario.</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-sm font-medium text-gray-700">Desde el día <span class="text-xs text-gray-400">(opcional)</span>
                    <input type="date" name="desde" value="{{ old('desde', $f->desde ?? '') }}" class="mt-1 block h-10 rounded-lg border-gray-300"></label>
                <label class="text-sm font-medium text-gray-700">Hasta el día <span class="text-xs text-gray-400">(opcional)</span>
                    <input type="date" name="hasta" value="{{ old('hasta', $f->hasta ?? '') }}" class="mt-1 block h-10 rounded-lg border-gray-300"></label>
                <label class="flex-1 min-w-56 text-sm font-medium text-gray-700">Nombre de la promo
                    <input name="nombre" x-model="nombre" @input="tocado = nombre.trim() !== ''" x-effect="if (!tocado) nombre = sugerido()" required maxlength="80" placeholder="3X2 CAIPIRIÑA"
                           class="mt-1 block w-full h-10 rounded-lg border-gray-300 uppercase font-bold"></label>
                <button class="h-11 px-6 rounded-xl bg-orange-600 text-white font-bold hover:bg-orange-700">{{ $f ? 'Guardar cambios' : 'Crear promoción' }}</button>
            </div>
        </form>

        {{-- Mis promociones --}}
        <section class="space-y-3">
            <h2 class="font-bold text-gray-800">Mis promociones</h2>
            @forelse ($promos as $p)
                @php [$texto, $color] = $estado($p); $u = $uso[$p->id] ?? null; @endphp
                <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-center gap-4">
                    <div class="w-16 h-16 shrink-0 rounded-2xl bg-orange-100 text-orange-700 flex items-center justify-center text-lg font-black text-center leading-tight">
                        {{ $p->tipo === 'LLEVA_PAGA' ? $p->lleva.'x'.$p->paga : $p->lleva.' x S/'.rtrim(rtrim(number_format((float) $p->precio, 2), '0'), '.') }}</div>
                    <div class="flex-1 min-w-56">
                        <p class="font-bold text-gray-800">{{ $p->nombre }} <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-bold {{ $color }}">{{ $texto }}</span></p>
                        <p class="text-xs text-gray-500">{{ PromocionesVenta::cuando($p) }}</p>
                        <p class="text-xs text-gray-600 mt-0.5">{{ collect($p->productos)->map(fn ($id) => $nombres[$id] ?? null)->filter()->implode(' · ') ?: 'Sin productos' }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="font-black text-gray-800">{{ (int) ($u->veces ?? 0) }} {{ (int) ($u->veces ?? 0) === 1 ? 'vez' : 'veces' }}</p>
                        <p class="text-xs text-gray-500">últimos 30 días · ahorro S/ {{ number_format((float) ($u->ahorro ?? 0), 2) }}</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('promociones.index', ['editar' => $p->id]) }}" class="h-9 px-3 inline-flex items-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-700">Editar</a>
                        <form method="POST" action="{{ route('promociones.estado', $p->id) }}">@csrf
                            <button class="h-9 px-3 rounded-lg text-sm font-semibold {{ $p->activo ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700' }}">{{ $p->activo ? 'Pausar' : 'Activar' }}</button></form>
                        <form method="POST" action="{{ route('promociones.eliminar', $p->id) }}" onsubmit="return confirm('¿Eliminar la promoción {{ $p->nombre }}?')">@csrf
                            <button class="h-9 px-3 rounded-lg bg-rose-50 text-sm font-semibold text-rose-700">Eliminar</button></form>
                    </div>
                </div>
            @empty
                <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-400">Aún no tienes promociones. Crea la primera arriba: por ejemplo, 3x2 en caipiriñas los viernes y sábados.</p>
            @endforelse
        </section>
    </div>

    <script>
        function formPromo(cfg) {
            return {
                ...cfg,
                /** "3X2 CAIPIRIÑA" o "3 POR S/ 40 CERVEZA": el nombre que se pone solo mientras no se escriba otro */
                sugerido() {
                    const producto = this.elegidos.length ? (this.nombres[this.elegidos[0]] || '') + (this.elegidos.length > 1 ? ' Y OTROS' : '') : '';
                    const promo = this.tipo === 'LLEVA_PAGA' ? `${this.lleva}X${this.paga}` : `${this.lleva} POR S/ ${this.precio || ''}`;
                    return (promo + ' ' + producto).trim().toUpperCase().slice(0, 80);
                },
                /** "Ejemplo: 3 × S/ 15.00 = S/ 45.00 → paga S/ 30.00 (ahorra S/ 15.00)" con el primer producto elegido */
                ejemplo() {
                    const precio = this.precios[this.elegidos[0]];
                    const lleva = Number(this.lleva) || 0;
                    if (!precio || lleva < 2) return '';
                    const normal = precio * lleva;
                    const paga = this.tipo === 'LLEVA_PAGA' ? precio * (Number(this.paga) || 0) : Number(this.precio) || 0;
                    if (!paga || paga >= normal) return '';
                    const f = n => 'S/ ' + n.toFixed(2);
                    return `Ejemplo: ${lleva} × ${f(precio)} = ${f(normal)} → paga ${f(paga)} (ahorra ${f(normal - paga)})`;
                },
            };
        }
    </script>
@endsection
