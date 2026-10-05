@extends('layouts.app')
@section('title', 'Productos')

@section('content')
    @include('empresas.partials.alert')

    <div class="flex flex-col sm:flex-row justify-between gap-3 mb-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-1">
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por nombre, código o código de barras..."
                class="w-full sm:max-w-sm rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">

            <select name="tipo" onchange="this.form.submit()"
                class="w-full sm:w-auto rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="" @selected($tipo === '')>Todos los tipos</option>
                <option value="0" @selected($tipo == '0')>Productos</option>
                <option value="4" @selected($tipo == '4')>Insumos</option>
                <option value="2" @selected($tipo == '2')>Preparados</option>
                <option value="6" @selected($tipo == '6')>Combos</option>
            </select>
        </form>

        <div class="flex flex-wrap gap-2" x-data="{ importar: false }">
            <a href="{{ route('productos.exportar', ['tipo' => $tipo]) }}"
               class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition whitespace-nowrap">
                ⬇ Exportar Excel
            </a>
            @if (auth()->user()->esAdmin())
                <button type="button" @click="importar = true"
                        class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-semibold hover:bg-emerald-100 transition whitespace-nowrap">
                    ⬆ Importar Excel
                </button>
            @endif
            <a href="{{ route('productos.create') }}"
               class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition whitespace-nowrap">
                + Nuevo Producto
            </a>

            {{-- Modal de importación --}}
            <div x-show="importar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="importar = false" @keydown.escape.window="importar = false">
                <form method="POST" action="{{ route('productos.importar') }}" enctype="multipart/form-data"
                      class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-5 space-y-4"
                      x-data="{ enviando: false }" @submit="enviando = true">
                    @csrf
                    <h3 class="font-bold text-gray-700">Importar productos desde Excel</h3>
                    <ol class="text-sm text-gray-600 list-decimal list-inside space-y-1">
                        <li>Descarga la <a href="{{ route('productos.plantilla') }}" class="text-indigo-600 font-semibold hover:underline">plantilla Excel</a>
                            (o usa <em>Exportar Excel</em> para editar tus productos actuales).</li>
                        <li>Llena una fila por producto. Si el <strong>código</strong> ya existe, el producto se actualiza.</li>
                        <li>Sube el archivo. Las filas con errores se omiten y se listan al terminar.</li>
                    </ol>
                    <label class="block text-sm">Archivo (.xlsx o .csv)
                        <input type="file" name="archivo" accept=".xlsx,.csv" required class="block w-full text-sm mt-1 border border-gray-300 rounded-lg p-2">
                    </label>
                    <label class="block text-sm">Almacén para el stock inicial
                        <select name="id_almacen" class="block w-full rounded-lg border-gray-300 text-sm">
                            @foreach ($almacenes as $a)
                                <option value="{{ $a->id_almacen }}">{{ $a->descripcion }}{{ $a->predeterminado ? ' (predeterminado)' : '' }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-400">La columna "Stock inicial" solo se carga a productos sin movimientos en este almacén.</span>
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="importar = false" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold">Cancelar</button>
                        <button :disabled="enviando" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50"
                                x-text="enviando ? 'Importando...' : 'Importar'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if (session('import_errores'))
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-sm max-h-48 overflow-y-auto">
            <p class="font-semibold mb-1">{{ count(session('import_errores')) }} filas no se importaron o tuvieron observaciones:</p>
            <ul class="list-disc list-inside text-xs">
                @foreach (session('import_errores') as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Categoría</th>
                    <th class="px-4 py-3 text-right">Precio</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($productos as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($p->imagen_url)
                                    <img src="{{ $p->imagen_url }}" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0" loading="lazy">
                                @else
                                    <span class="w-10 h-10 rounded-lg bg-gray-100 shrink-0"></span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-700">{{ $p->pronom }}</p>
                                    <p class="text-xs text-gray-400">{{ $p->procod }}@if ($p->codigo_barra) · ▮▯▮ {{ $p->codigo_barra }}@endif</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600">{{ $p->tipo_nombre }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $p->categoria->cat_nom ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">S/ {{ number_format($p->propun, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $p->proest == 'Activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $p->proest }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('productos.edit', $p->IdProducto) }}" class="text-indigo-600 hover:underline">Editar</a>
                            <form action="{{ route('productos.destroy', $p->IdProducto) }}" method="POST" class="inline"
                                  onsubmit="return confirm('¿Eliminar este producto?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin productos registrados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $productos->links() }}</div>
@endsection