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
                @if (\App\Support\Rubros::permite('insumos'))<option value="4" @selected($tipo == '4')>Insumos</option>@endif
                @if (\App\Support\Rubros::permite('preparados'))<option value="2" @selected($tipo == '2')>Preparados</option>@endif
                <option value="6" @selected($tipo == '6')>Combos</option>
                <option value="9" @selected($tipo == '9')>Servicios</option>
            </select>
        </form>

        <div class="flex flex-wrap gap-2" x-data="{ importar: false }">
            <a href="{{ route('productos.etiquetas') }}"
               class="inline-flex justify-center items-center px-4 py-2 rounded-xl bg-white text-slate-700 border border-slate-200 text-sm font-semibold hover:bg-slate-50 transition whitespace-nowrap">
                🏷️ Etiquetas
            </a>
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
            <button type="button" @click="$dispatch('crear-con-voz')"
                    class="inline-flex justify-center items-center gap-1 px-4 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white text-sm font-semibold hover:opacity-90 transition whitespace-nowrap">
                🎤 Crear con voz (IA)
            </button>
            <button type="button" @click="$dispatch('crear-con-foto')"
                    class="inline-flex justify-center items-center gap-1 px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-violet-600 text-white text-sm font-semibold hover:opacity-90 transition whitespace-nowrap">
                📷 Crear con foto (IA)
            </button>
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
                        <li>Llena una fila por producto: solo son obligatorios el <strong>nombre</strong> y el <strong>precio</strong>. La hoja <em>Cómo llenarlo</em> explica cada columna y la hoja <em>Ejemplo</em> muestra filas llenas.</li>
                        <li>Sube el archivo. Las filas con errores se omiten y se listan al terminar.</li>
                    </ol>
                    <label class="block text-sm">Archivo (Excel .xlsx, .xls, .ods o .csv)
                        <input type="file" name="archivo" accept="{{ \App\Support\ExcelLector::ACCEPT }}" required class="block w-full text-sm mt-1 border border-gray-300 rounded-lg p-2">
                    </label>
                    <label class="block text-sm">Almacén para el stock inicial
                        <select name="id_almacen" class="block w-full rounded-lg border-gray-300 text-sm">
                            @foreach ($almacenes as $a)
                                <option value="{{ $a->id_almacen }}">{{ $a->descripcion }}{{ $a->predeterminado ? ' (predeterminado)' : '' }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-400">La columna "Stock inicial" solo se carga a productos sin movimientos en este almacén.</span>
                    </label>
                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input type="checkbox" name="mismo_precio" value="1" class="mt-0.5 rounded">
                        <span>Sí, quiero poner el mismo precio a muchos productos. <span class="text-gray-400">(Sin marcarla, si el Excel deja a {{ \App\Http\Controllers\ProductoController::FRENO_MISMO_PRECIO }} o más productos con un mismo precio nuevo, no se importa y se avisa.)</span></span>
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
                    <th class="px-4 py-3 text-right">Costo</th>
                    <th class="px-4 py-3 text-right">Precio</th>
                    <th class="px-4 py-3 text-right">Stock</th>
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
                        <td class="px-4 py-3 text-right text-gray-500">{{ (float) $p->costo > 0 ? 'S/ '.number_format($p->costo, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right">S/ {{ number_format($p->propun, 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if (in_array((int) $p->promocion, [0, 4], true))
                                @php($stock = (float) ($p->stock_total ?? 0))
                                <span class="font-semibold {{ $stock <= 0 ? 'text-rose-600' : ($p->stock_min > 0 && $stock <= (float) $p->stock_min ? 'text-amber-600' : 'text-gray-700') }}">{{ rtrim(rtrim(number_format($stock, 3), '0'), '.') }}</span>
                                <span class="text-xs text-gray-400">{{ $p->umecod }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $p->proest == 'Activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $p->proest }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            {{-- Ajustar stock: solo el administrador, y no a lo que se controla por lote o serie (eso va con su lote o sus series) --}}
                            @if (auth()->user()->esAdmin() && in_array((int) $p->promocion, [0, 4], true) && ! $p->control_lote && ! $p->control_serie)
                                <button type="button" class="text-sky-600 hover:underline" x-data
                                        @click="$dispatch('ajustar-stock', @js(['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'unidad' => $p->umecod ?: 'NIU', 'stock' => $stockPorAlmacen[$p->IdProducto] ?? []]))">Ajustar</button>
                            @endif
                            <a href="{{ route('productos.edit', $p->IdProducto) }}" class="text-indigo-600 hover:underline">Editar</a>
                            <form action="{{ route('productos.destroy', $p->IdProducto) }}" method="POST" class="inline"
                                  onsubmit="return confirm('¿Eliminar este producto?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">Sin productos registrados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (auth()->user()->esAdmin())
        {{-- Ajustar stock: se escribe lo que hay de verdad en ese almacén; el sistema hace el ingreso o la salida de la diferencia (Kardex) --}}
        <div x-data="{ abierto: false, p: null, almacen: @js((string) ($almacenes->first()->id_almacen ?? '')), real: '',
                       actual() { return Number(this.p?.stock?.[this.almacen] ?? 0); },
                       dif() { const v = parseFloat(this.real); return isNaN(v) || v < 0 ? null : Math.round((v - this.actual()) * 100000) / 100000; },
                       num(n) { return Number(Math.round(n * 1000) / 1000).toLocaleString('es-PE', { maximumFractionDigits: 3 }); } }"
             @ajustar-stock.window="p = $event.detail; real = ''; abierto = true; $nextTick(() => $refs.real.focus())"
             x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="abierto = false" @keydown.escape.window="abierto = false">
            <form method="POST" action="{{ route('kardex.ajustar') }}" class="w-full max-w-sm rounded-2xl bg-white shadow-xl p-5 space-y-3" x-data="{ enviando: false }" @submit="enviando = true">
                @csrf
                <input type="hidden" name="IdProducto" :value="p?.id">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-xs font-bold uppercase text-sky-700">Ajustar stock</p><p class="font-bold text-gray-800" x-text="p?.nombre"></p></div>
                    <button type="button" @click="abierto = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                </div>
                @if ($almacenes->count() > 1)
                    <label class="block text-sm font-semibold text-gray-700">Almacén
                        <select name="id_almacen" x-model="almacen" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                            @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
                        </select></label>
                @else
                    <input type="hidden" name="id_almacen" :value="almacen">
                @endif
                <p class="text-sm text-gray-600">En el sistema: <b x-text="num(actual()) + ' ' + (p?.unidad ?? '')"></b></p>
                <label class="block text-sm font-semibold text-gray-700">Stock real (lo que hay de verdad)
                    <input x-ref="real" type="number" name="stock_real" x-model="real" step="any" min="0" required class="mt-1 w-full h-11 rounded-xl border-gray-300 text-right text-lg font-bold"></label>
                <p x-show="dif() !== null" class="rounded-xl px-3 py-2 text-sm font-semibold"
                   :class="dif() > 0 ? 'bg-emerald-50 text-emerald-800' : (dif() < 0 ? 'bg-rose-50 text-rose-800' : 'bg-gray-50 text-gray-600')"
                   x-text="dif() > 0 ? 'Se hará un INGRESO de ' + num(dif()) + ' ' + p.unidad + ': quedará en ' + num(parseFloat(real)) + '.'
                         : (dif() < 0 ? 'Se hará una SALIDA de ' + num(-dif()) + ' ' + p.unidad + ': quedará en ' + num(parseFloat(real)) + '.' : 'Es igual al sistema: no hay nada que ajustar.')"></p>
                <label class="block text-sm text-gray-600">Motivo (opcional)
                    <input name="observacion" maxlength="200" placeholder="Ej. conteo físico, producto dañado" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="abierto = false" class="h-10 px-4 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Cancelar</button>
                    <button :disabled="enviando || dif() === null || dif() === 0" class="h-10 px-4 rounded-xl bg-sky-600 text-white text-sm font-bold hover:bg-sky-700 disabled:opacity-50"
                            x-text="enviando ? 'Ajustando…' : 'Ajustar'"></button>
                </div>
            </form>
        </div>
    @endif

    <div class="mt-4">{{ $productos->links() }}</div>

    @include('empresas.productos.partials.crear_voz')
@endsection