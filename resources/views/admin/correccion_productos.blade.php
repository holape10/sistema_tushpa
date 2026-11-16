@extends('admin.layout')
@section('title', 'Corregir productos')

@section('content')
    <h1 class="text-xl font-bold mb-1">🛠️ Corregir productos con Excel</h1>
    <p class="text-sm text-slate-500 mb-4">Cambia nombre, costo, precio, estado o stock de los productos de una empresa, sin entrar con su usuario.
        Solo toca productos que ya existen (los busca por su <b>código</b>): no crea ninguno. Primero ves qué cambiaría y recién después se aplica.</p>

    @if (session('ok'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 text-sm font-semibold">✔ {{ session('ok') }}</div>@endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
    @endif

    @if (! $pendiente)
        {{-- 1. Elegir la empresa y subir el Excel --}}
        <form method="POST" action="{{ route('admin.correccion_productos.revisar') }}" enctype="multipart/form-data" class="rounded-2xl bg-white shadow-sm p-5 space-y-4 max-w-3xl"
              x-data="{ q: '', enviando: false }" @submit="enviando = true">
            @csrf
            <div>
                <p class="text-sm font-bold text-slate-700 mb-1">1. Empresa</p>
                <input type="search" x-model="q" placeholder="Buscar: nombre, RUC, subdominio o base…" autocomplete="off"
                       @input="const t = q.trim().toLowerCase(); [...$refs.lista.options].forEach(o => o.hidden = t !== '' && o.value !== '' && !o.text.toLowerCase().includes(t))"
                       class="w-full rounded-xl border-slate-300 text-sm mb-2">
                <select name="base" x-ref="lista" required class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="">— Elige la empresa —</option>
                    @foreach ($bases as $clave => $nombre)<option value="{{ $clave }}" @selected($clave === $base)>{{ $nombre }}</option>@endforeach
                </select>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-700 mb-1">2. Excel</p>
                <input type="file" name="archivo" accept="{{ \App\Support\ExcelLector::ACCEPT }}" required class="block w-full text-sm border border-slate-300 rounded-xl p-2">
                <p class="mt-1 text-xs text-slate-500">Columnas: <b>Código</b> (obligatoria) y las que quieras cambiar: <b>Nombre</b>, <b>Costo</b>, <b>Precio venta</b>,
                    <b>Estado</b> (Activo / Inactivo) y <b>Stock</b> (se corrige con un inventario). Celda vacía = no se cambia.</p>
            </div>
            <button :disabled="enviando" class="h-11 px-5 rounded-xl bg-indigo-600 text-white font-bold disabled:opacity-60" x-text="enviando ? 'Revisando…' : 'Revisar (todavía no cambia nada)'"></button>
        </form>
    @else
        {{-- 2. Lo que cambiaría --}}
        @php $r = $pendiente['revision']['resumen']; $total = $r['precio'] + $r['nombre'] + $r['costo'] + $r['estado'] + $r['stock']; @endphp
        <section class="rounded-2xl bg-white shadow-sm p-5 space-y-4 max-w-3xl">
            <div>
                <p class="text-xs text-slate-500">Empresa</p>
                <p class="font-bold">{{ $bases[$pendiente['base']] ?? $pendiente['base'] }}</p>
                <p class="text-xs text-slate-500 mt-1">Archivo: {{ $pendiente['nombre'] }}</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-center">
                @foreach (['encontrados' => 'Productos encontrados', 'precio' => 'Precios', 'nombre' => 'Nombres', 'costo' => 'Costos', 'estado' => 'Estados', 'stock' => 'Stocks (inventario)'] as $k => $t)
                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">{{ $t }}</p><p class="text-2xl font-extrabold">{{ number_format($r[$k]) }}</p></div>
                @endforeach
            </div>
            <p class="text-sm text-slate-600">{{ number_format($r['filas']) }} filas en el Excel · {{ number_format($r['sin_cambios']) }} ya estaban igual.</p>
            @if ($pendiente['revision']['aviso'])<p class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-900">⚠ {{ $pendiente['revision']['aviso'] }}</p>@endif
            @if ($pendiente['revision']['no_encontrados'])
                <details class="rounded-xl bg-rose-50 px-4 py-2 text-sm text-rose-800"><summary class="cursor-pointer font-semibold">{{ count($pendiente['revision']['no_encontrados']) }} código(s) no existen en esta empresa (no se tocan)</summary>
                    <p class="mt-1 font-mono text-xs">{{ implode(', ', array_slice($pendiente['revision']['no_encontrados'], 0, 200)) }}</p></details>
            @endif
            @if ($pendiente['revision']['repetidos'])
                <details class="rounded-xl bg-amber-50 px-4 py-2 text-sm text-amber-900"><summary class="cursor-pointer font-semibold">{{ count($pendiente['revision']['repetidos']) }} código(s) repetidos en la empresa (no se tocan)</summary>
                    <p class="mt-1 font-mono text-xs">{{ implode(', ', array_slice($pendiente['revision']['repetidos'], 0, 200)) }}</p></details>
            @endif
            @if ($pendiente['errores'])
                <details class="rounded-xl bg-slate-100 px-4 py-2 text-sm text-slate-700"><summary class="cursor-pointer font-semibold">{{ count($pendiente['errores']) }} fila(s) del Excel con problemas (se omiten)</summary>
                    <ul class="mt-1 text-xs list-disc list-inside">@foreach (array_slice($pendiente['errores'], 0, 100) as $e)<li>{{ $e }}</li>@endforeach</ul></details>
            @endif
            <div class="flex flex-wrap gap-2 pt-2">
                <a href="{{ route('admin.correccion_productos.index') }}" class="h-11 px-4 inline-flex items-center rounded-xl bg-slate-100 font-bold text-slate-700">Cancelar</a>
                @if ($total > 0)
                    <form method="POST" action="{{ route('admin.correccion_productos.aplicar', $pendiente['token']) }}" x-data="{ enviando: false }"
                          @submit="if (!confirm('¿Aplicar estos cambios en la base {{ $pendiente['base'] }}? Queda registrado en su bitácora.')) { $event.preventDefault(); return; } enviando = true">
                        @csrf
                        <button :disabled="enviando" class="h-11 px-5 rounded-xl bg-emerald-600 text-white font-bold disabled:opacity-60" x-text="enviando ? 'Aplicando…' : '✔ Aplicar cambios'"></button>
                    </form>
                @else
                    <p class="text-sm font-semibold text-slate-500 self-center">No hay nada que cambiar.</p>
                @endif
            </div>
        </section>
    @endif
@endsection
