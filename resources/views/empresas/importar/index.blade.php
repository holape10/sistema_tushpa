@extends('layouts.app')
@section('title', 'Importar Sistema Antiguo')

@section('content')
<div class="max-w-5xl mx-auto pb-10">
    @include('empresas.partials.alert')

    {{-- Pasos --}}
    <ol class="grid grid-cols-3 gap-2 mb-6 text-xs sm:text-sm">
        @foreach ([1 => 'Respaldo', 2 => 'Revisar y elegir', 3 => 'Resultado'] as $n => $titulo)
            <li class="flex items-center gap-2 rounded-2xl px-3 py-3 {{ $paso === $n ? 'bg-indigo-600 text-white shadow' : ($paso > $n ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-400') }}">
                <span class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center font-extrabold {{ $paso === $n ? 'bg-white/20' : ($paso > $n ? 'bg-emerald-100' : 'bg-gray-100') }}">
                    {{ $paso > $n ? '✓' : $n }}</span>
                <span class="font-semibold leading-tight">{{ $titulo }}</span>
            </li>
        @endforeach
    </ol>

    @if ($paso === 1)
        <div class="grid lg:grid-cols-2 gap-6">
            <section class="bg-white rounded-2xl shadow-sm p-5 sm:p-6" x-data="{ enviando: false, nombre: '' }">
                <h2 class="font-bold text-gray-800 text-lg">Subir respaldo del sistema antiguo</h2>
                <p class="text-sm text-gray-500 mt-1 mb-4">El archivo <strong>.sql</strong> (o .sql.gz) que exportas de la base antigua. Se carga en una base temporal
                    y solo se leen productos, categorías, clientes, proveedores, stock, medios de pago y mesas; las ventas antiguas no se tocan.</p>
                <form method="POST" action="{{ route('importar.cargar') }}" enctype="multipart/form-data" @submit="enviando = true" class="space-y-4">
                    @csrf
                    <label class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300 hover:border-indigo-400 bg-gray-50 p-8 cursor-pointer text-center">
                        <span class="text-4xl">🗄️</span>
                        <span class="font-semibold text-indigo-600" x-text="nombre || 'Elegir archivo .sql'"></span>
                        <span class="text-xs text-gray-400">Si está en .rar o .zip, descomprímelo primero</span>
                        <input type="file" name="archivo" accept=".sql,.gz" required class="sr-only" @change="nombre = $event.target.files[0]?.name || ''">
                    </label>
                    <button :disabled="enviando" class="w-full h-12 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 disabled:opacity-60"
                            x-text="enviando ? 'Cargando respaldo… puede tardar unos minutos' : 'Cargar respaldo'"></button>
                </form>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-5 sm:p-6">
                <h2 class="font-bold text-gray-800 text-lg">O usar una base ya cargada</h2>
                <p class="text-sm text-gray-500 mt-1 mb-4">Bases con estructura del sistema antiguo disponibles en el servidor.</p>
                @forelse ($bases as $b)
                    <a href="{{ route('importar.revisar', ['bd' => $b]) }}" class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 mb-2 hover:border-indigo-400 hover:bg-indigo-50">
                        <span class="font-mono text-sm text-gray-700 truncate">{{ $b }}</span>
                        <span class="text-sm font-semibold text-indigo-600 shrink-0">Usar →</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-400 py-6 text-center">No hay bases cargadas todavía.</p>
                @endforelse
                <p class="text-xs text-gray-400 mt-4">Respaldos muy grandes: también se pueden cargar desde el servidor con
                    <code class="bg-gray-100 px-1 rounded">php artisan antiguo:cargar archivo.sql --ruc={{ preg_replace('/\D/', '', auth()->user()->IdEmpresa) }}</code></p>
            </section>
        </div>
    @endif

    @if ($paso === 2)
        @if (session('errores_carga'))
            <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-xs max-h-32 overflow-y-auto">
                <p class="font-semibold mb-1">Algunas sentencias del respaldo no se pudieron cargar:</p>
                <ul class="list-disc list-inside">@foreach (session('errores_carga') as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Base antigua</p>
                    <p class="font-mono font-bold text-gray-800">{{ $bd }}</p>
                </div>
                <a href="{{ route('importar.index') }}" class="text-sm font-semibold text-indigo-600">← Cambiar</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ($resumen as $nombre => $cantidad)
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                        <p class="text-xs text-gray-500">{{ $nombre }}</p>
                        <p class="text-xl font-extrabold {{ $cantidad ? 'text-gray-800' : 'text-gray-300' }}">{{ $cantidad === null ? '—' : number_format($cantidad) }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('importar.ejecutar') }}" enctype="multipart/form-data" class="space-y-6"
              x-data="{ enviando: false, secciones: @js(array_keys(\App\Support\Antiguo\Importador::SECCIONES)) }" @submit="enviando = true">
            @csrf
            <input type="hidden" name="bd" value="{{ $bd }}">

            <section class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-2 gap-5">
                <label class="block text-sm font-medium text-gray-700">Sucursal del sistema antiguo
                    <select name="sucursal" onchange="location.href='{{ route('importar.revisar', ['bd' => $bd]) }}&sucursal=' + this.value"
                            class="mt-1 w-full h-11 rounded-xl border-gray-300 text-sm">
                        @foreach ($sucursales as $s)
                            <option value="{{ $s['id'] }}" @selected($s['id'] === $suc)>{{ $s['nombre'] }}{{ $s['ruc'] ? ' · RUC ' . $s['ruc'] : '' }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-gray-400">Se importa a tu sucursal actual: <strong>{{ auth()->user()->id_empresa_negocio }}</strong>.</span>
                </label>
                <label class="block text-sm font-medium text-gray-700">Almacén antiguo para el stock
                    <select name="almacen" class="mt-1 w-full h-11 rounded-xl border-gray-300 text-sm">
                        @forelse ($almacenes as $a)
                            <option value="{{ $a['id'] }}">{{ $a['nombre'] }}</option>
                        @empty
                            <option value="">Todos</option>
                        @endforelse
                    </select>
                    <span class="text-xs text-gray-400">Entra al almacén predeterminado del sistema nuevo.</span>
                </label>
                <label class="block text-sm font-medium text-gray-700">En los precios por día, el día 0 era
                    <select name="dia0" class="mt-1 w-full h-11 rounded-xl border-gray-300 text-sm">
                        <option value="domingo">Domingo (0 = domingo … 6 = sábado)</option>
                        <option value="lunes">Lunes (0 = lunes … 6 = domingo)</option>
                    </select>
                    <span class="text-xs text-gray-400">Revisa después un producto con precio dinámico para confirmar.</span>
                </label>
                <label class="block text-sm font-medium text-gray-700">Imágenes de productos (opcional)
                    <input type="file" name="imagenes" accept=".zip" class="mt-1 block w-full text-sm border border-gray-300 rounded-xl p-2">
                    <span class="text-xs text-gray-400">Un .zip con la carpeta de imágenes del sistema antiguo; se buscan por nombre de archivo.</span>
                </label>
            </section>

            <section class="bg-white rounded-2xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-gray-800">¿Qué importar?</h3>
                    <span class="text-xs text-gray-400">Se puede repetir: lo que ya existe no se duplica.</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach (\App\Support\Antiguo\Importador::SECCIONES as $clave => $texto)
                        <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" name="secciones[]" value="{{ $clave }}" x-model="secciones" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">{{ $texto }}</span>
                        </label>
                    @endforeach
                </div>
                <ul class="mt-4 text-xs text-gray-500 space-y-1 list-disc list-inside">
                    <li>Productos: se reconocen por <strong>nombre y tipo</strong> y se actualizan; los nuevos se crean. El código antiguo se conserva si no está repetido. Tipos antiguos: 0 producto, 2 preparado, 3 combo, 4 insumo.</li>
                    <li>Presentaciones: las filas "tipo presentación" del sistema antiguo (six pack, caja, saco…) pasan como presentaciones de su producto principal.</li>
                    <li>Stock: solo productos sin movimientos en el sistema nuevo; entra como inventario inicial (se ve en Almacén &gt; Inventarios).</li>
                    <li>Clientes y proveedores: solo se agregan los que faltan (por número de documento).</li>
                </ul>
            </section>

            <div class="flex flex-wrap justify-between gap-3">
                @if ($temporal)
                    <button type="submit" form="eliminar-bd" class="px-4 h-12 rounded-xl text-sm font-semibold text-rose-600 hover:bg-rose-50">Eliminar base temporal</button>
                @else <span></span> @endif
                <button :disabled="enviando || !secciones.length" class="px-8 h-12 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 disabled:opacity-60"
                        x-text="enviando ? 'Importando… no cierres esta ventana' : 'Importar ahora'"></button>
            </div>
        </form>
        @if ($temporal)
            <form id="eliminar-bd" method="POST" action="{{ route('importar.eliminar') }}" onsubmit="return confirm('¿Eliminar la base temporal {{ $bd }}?')">
                @csrf <input type="hidden" name="bd" value="{{ $bd }}">
            </form>
        @endif
    @endif

    @if ($paso === 3)
        <section class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 mb-6">
            <h2 class="font-bold text-gray-800 text-lg mb-4">Importación terminada</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ($resultado['secciones'] as $clave => $r)
                    <div class="rounded-2xl border {{ !empty($r['error']) ? 'border-rose-200 bg-rose-50' : 'border-gray-200' }} p-4">
                        <p class="font-semibold text-gray-700 text-sm mb-2">{{ \App\Support\Antiguo\Importador::SECCIONES[$clave] }}</p>
                        @if (!empty($r['error']))
                            <p class="text-sm text-rose-700">No se importó (ver avisos).</p>
                        @else
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <span><strong class="text-emerald-600 text-lg">{{ number_format($r['creados']) }}</strong> creados</span>
                                <span><strong class="text-indigo-600 text-lg">{{ number_format($r['actualizados']) }}</strong> actualizados</span>
                                <span><strong class="text-gray-400 text-lg">{{ number_format($r['omitidos']) }}</strong> ya existían u omitidos</span>
                                @isset($r['presentaciones'])<span><strong class="text-violet-600 text-lg">{{ number_format($r['presentaciones']) }}</strong> presentaciones</span>@endisset
                                @isset($r['precios_dinamicos'])<span><strong class="text-orange-600 text-lg">{{ number_format($r['precios_dinamicos']) }}</strong> precios dinámicos</span>@endisset
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @if ($resultado['avisos'])
            <section class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6">
                <p class="font-semibold text-amber-800 mb-2">{{ count($resultado['avisos']) }} avisos</p>
                <ul class="text-xs text-amber-800 list-disc list-inside space-y-0.5 max-h-72 overflow-y-auto">
                    @foreach ($resultado['avisos'] as $a)<li>{{ $a }}</li>@endforeach
                </ul>
            </section>
        @endif

        <div class="flex flex-wrap justify-between gap-3">
            <a href="{{ route('importar.revisar', ['bd' => $bd]) }}" class="px-4 h-11 inline-flex items-center rounded-xl text-sm font-semibold text-indigo-600 hover:bg-indigo-50">← Importar otra sección</a>
            <div class="flex gap-2">
                @if ($temporal)
                    <form method="POST" action="{{ route('importar.eliminar') }}" onsubmit="return confirm('¿Eliminar la base temporal {{ $bd }}?')">
                        @csrf <input type="hidden" name="bd" value="{{ $bd }}">
                        <button class="px-4 h-11 rounded-xl text-sm font-semibold text-rose-600 hover:bg-rose-50">Eliminar base temporal</button>
                    </form>
                @endif
                <a href="{{ route('productos.index') }}" class="px-5 h-11 inline-flex items-center rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Ver productos</a>
            </div>
        </div>
    @endif
</div>
@endsection
