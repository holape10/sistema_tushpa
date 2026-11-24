{{-- Primeros pasos: guía del administrador de una empresa nueva (hasta su primera venta) y bienvenida del usuario nuevo --}}
@auth
    @php
        $usuarioGuia = auth()->user();
        // Solo en la pantalla de inicio de cada uno (no estorba mientras trabaja ni consulta nada en las demás)
        $enInicio = request()->routeIs('inicio', 'dashboard', 'comandas.seleccion', 'pv.index') && request()->routeIs($usuarioGuia->rutaInicio(), 'dashboard', 'inicio');
        $guia = $enInicio ? \App\Support\PrimerosPasos::guia($usuarioGuia) : null;
        $bienvenida = $enInicio && ! $guia ? \App\Support\PrimerosPasos::bienvenida($usuarioGuia) : null;
    @endphp
    @if ($guia)
        @php($hechos = collect($guia)->where('hecho', true)->count())
        <section class="mb-5 rounded-2xl bg-white shadow-sm border border-indigo-100 overflow-hidden" x-data="{ abierta: true }">
            <div class="flex flex-wrap items-center gap-3 px-5 py-4 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                <span class="text-2xl">🚀</span>
                <div class="flex-1 min-w-[14rem]">
                    <p class="font-bold">Deja tu sistema listo en pocos pasos</p>
                    <p class="text-xs text-white/80">{{ $hechos }} de {{ count($guia) }} listos · cada paso se marca solo cuando ya está hecho</p>
                </div>
                <div class="w-40 h-2 rounded-full bg-white/25 overflow-hidden"><div class="h-full bg-white" style="width: {{ round($hechos / count($guia) * 100) }}%"></div></div>
                <button type="button" @click="abierta = !abierta" class="text-xs font-semibold text-white/90 hover:text-white" x-text="abierta ? 'Minimizar' : 'Ver pasos'"></button>
            </div>
            <ol x-show="abierta" class="divide-y divide-gray-100">
                @foreach ($guia as $i => $paso)
                    <li class="flex items-center gap-4 px-5 py-3 {{ $paso['hecho'] ? 'opacity-60' : '' }}">
                        <span class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center text-sm font-bold
                            {{ $paso['hecho'] ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-50 text-indigo-700' }}">{{ $paso['hecho'] ? '✓' : $i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 {{ $paso['hecho'] ? 'line-through' : '' }}">{{ $paso['titulo'] }}</p>
                            <p class="text-xs text-gray-500">{{ $paso['detalle'] }}</p>
                        </div>
                        @unless ($paso['hecho'])
                            <a href="{{ $paso['url'] }}" class="h-9 px-4 inline-flex items-center rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 whitespace-nowrap">Hacerlo →</a>
                        @endunless
                    </li>
                @endforeach
            </ol>
            <form x-show="abierta" method="POST" action="{{ route('primeros-pasos.ocultar') }}" class="px-5 py-2 bg-gray-50 text-right">
                @csrf
                <button class="text-xs text-gray-500 hover:text-gray-700 hover:underline">Ya tengo todo configurado · no mostrar más</button>
            </form>
        </section>
    @elseif ($bienvenida)
        <section class="mb-5 rounded-2xl bg-white shadow-sm border border-emerald-100 p-5">
            <p class="font-bold text-gray-800">👋 ¡Bienvenido, {{ $usuarioGuia->apeusu ?: $usuarioGuia->name }}! Así empiezas:</p>
            <ol class="mt-3 space-y-2">
                @foreach ($bienvenida as $i => $tip)
                    <li class="flex items-start gap-3 text-sm text-gray-700">
                        <span class="w-6 h-6 shrink-0 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">{{ $i + 1 }}</span>
                        <span class="flex-1">{{ $tip['texto'] }}
                            @if ($tip['url'])<a href="{{ $tip['url'] }}" class="ml-1 font-semibold text-indigo-600 hover:underline">Ir →</a>@endif</span>
                    </li>
                @endforeach
            </ol>
            <form method="POST" action="{{ route('primeros-pasos.bienvenida') }}" class="mt-4">
                @csrf
                <button class="h-9 px-4 rounded-xl bg-emerald-600 text-white text-sm font-bold">✓ Entendido</button>
            </form>
        </section>
    @endif
@endauth
