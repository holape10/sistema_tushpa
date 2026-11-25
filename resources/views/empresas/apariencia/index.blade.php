@extends('layouts.app')
@section('title', 'Apariencia')

@section('content')
{{-- Mantenimiento › Apariencia: color, fondo y tamaño de letra del sistema de la empresa. Se ve en vivo antes de guardar. --}}
@use('App\Support\Apariencia')
<div x-data="apariencia(@js($actual), @js($paletas), @js(collect(Apariencia::FONDOS)->map(fn ($f) => $f[1])), @js(collect(Apariencia::LETRAS)->map(fn ($l) => $l[1])))"
     x-init="aplicar()" class="max-w-5xl mx-auto space-y-5">
    @include('empresas.partials.alert')

    <section class="rounded-3xl bg-indigo-700 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">🎨 Apariencia del sistema</h1>
        <p class="mt-1 text-indigo-100">Elige el color, el fondo y el tamaño de letra. Lo ves en vivo en esta pantalla; al <b>guardar</b> queda así para todos los usuarios de tu empresa.
            <b>¿Usan varias empresas en la misma PC?</b> Dale a cada una su color: desde el login se ve en cuál estás (y su nombre sale en la pestaña del navegador).</p>
    </section>

    <form method="POST" action="{{ route('apariencia.guardar') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="color" :value="a.color">
        <input type="hidden" name="fondo" :value="a.fondo">
        <input type="hidden" name="letra" :value="a.letra">

        {{-- 1. Color principal --}}
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-extrabold text-gray-800">1. Color principal</h2>
            <p class="text-sm text-gray-500 mb-3">El de los botones, el menú lateral y lo resaltado.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
                <template x-for="(p, clave) in paletas" :key="clave">
                    <button type="button" @click="a.color = clave; aplicar()"
                            :class="a.color === clave ? 'ring-4 ring-offset-2 ring-gray-900' : 'ring-1 ring-gray-200 hover:ring-gray-400'"
                            class="rounded-2xl overflow-hidden text-left bg-white">
                        <span class="flex h-12">
                            <span class="flex-1" :style="'background: rgb(' + p.vars['--ap-900'] + ')'"></span>
                            <span class="flex-1" :style="'background: rgb(' + p.vars['--ap-600'] + ')'"></span>
                            <span class="flex-1" :style="'background: rgb(' + p.vars['--ap-300'] + ')'"></span>
                            <span class="flex-1" :style="'background: rgb(' + p.vars['--ap-100'] + ')'"></span>
                        </span>
                        <span class="block px-3 py-2 text-sm font-bold text-gray-800">
                            <span x-show="a.color === clave">✓ </span><span x-text="p.nombre"></span></span>
                    </button>
                </template>
            </div>
        </section>

        <div class="grid md:grid-cols-2 gap-5">
            {{-- 2. Fondo --}}
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-extrabold text-gray-800">2. Fondo de las pantallas</h2>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    @foreach (Apariencia::FONDOS as $clave => [$nombre, $color])
                        <button type="button" @click="a.fondo = @js($clave); aplicar()"
                                :class="a.fondo === @js($clave) ? 'ring-4 ring-offset-2 ring-gray-900' : 'ring-1 ring-gray-200 hover:ring-gray-400'"
                                class="rounded-xl p-3 text-left text-sm font-bold text-gray-800" style="background: {{ $color }}">
                            <span x-show="a.fondo === @js($clave)">✓ </span>{{ $nombre }}</button>
                    @endforeach
                </div>
            </section>

            {{-- 3. Tamaño de letra --}}
            <section class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-extrabold text-gray-800">3. Tamaño de letra</h2>
                <p class="text-sm text-gray-500">Más grande ayuda a leer mejor en la caja; todo crece parejo.</p>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    @foreach (Apariencia::LETRAS as $clave => [$nombre, $tamano])
                        <button type="button" @click="a.letra = @js($clave); aplicar()"
                                :class="a.letra === @js($clave) ? 'ring-4 ring-offset-2 ring-gray-900' : 'ring-1 ring-gray-200 hover:ring-gray-400'"
                                class="rounded-xl p-3 bg-white text-center font-bold text-gray-800">
                            <span class="block" style="font-size: {{ $tamano === '100%' ? '16px' : ($tamano === '112.5%' ? '19px' : '22px') }}">Aa</span>
                            <span class="text-xs">{{ $nombre }}</span></button>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Vista previa --}}
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-extrabold text-gray-800 mb-3">Así se ve</h2>
            <div class="flex flex-wrap items-center gap-3">
                <span class="h-11 px-5 inline-flex items-center rounded-xl bg-indigo-600 text-white font-bold">Botón principal</span>
                <span class="h-11 px-5 inline-flex items-center rounded-xl bg-indigo-50 text-indigo-700 font-bold ring-1 ring-indigo-200">Botón suave</span>
                <span class="px-3 py-1 rounded-full bg-indigo-100 text-indigo-800 text-xs font-black">ETIQUETA</span>
                <a class="text-indigo-700 font-bold underline">Un enlace</a>
                <span class="h-11 px-4 inline-flex items-center rounded-xl bg-indigo-900 text-white text-sm">☰ Menú lateral</span>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button class="h-12 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-black">✓ Guardar apariencia</button>
            <button type="button" @click="a = { ...predeterminada }; aplicar()" class="h-12 px-5 rounded-xl bg-gray-100 text-gray-700 font-bold">Volver a la de siempre</button>
            <p class="text-xs text-gray-500">Se ve en el login, el Inicio, el Punto Venta y todo el sistema de tu empresa. Tus comprobantes impresos siguen igual.</p>
        </div>
    </form>
</div>

<script>
    function apariencia(actual, paletas, fondos, letras) {
        return {
            a: { ...actual }, paletas, predeterminada: @js(Apariencia::PREDETERMINADA),
            // Vista previa en vivo: las mismas variables que el sistema pone al guardar
            aplicar() {
                const raiz = document.documentElement;
                Object.entries(this.paletas[this.a.color].vars).forEach(([k, v]) => raiz.style.setProperty(k, v));
                raiz.style.fontSize = letras[this.a.letra];
                document.body.style.backgroundColor = fondos[this.a.fondo];
            },
        };
    }
</script>
@endsection
