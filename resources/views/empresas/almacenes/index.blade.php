@extends('layouts.app')
@section('title', 'Almacenes')
@section('content')
    @include('empresas.partials.alert')

    <div x-data="{ form: null, nuevo() { this.form = { id: null, descripcion: '', codigo: '', direccion: '', ubigeo: '' } } }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <p class="text-sm text-gray-500">El almacén <strong>predeterminado</strong> es con el que trabaja el sistema: ventas, compras y reportes mueven su stock.</p>
            @if (auth()->user()->esAdmin())
                <button type="button" @click="nuevo()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 whitespace-nowrap">+ Nuevo almacén</button>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Almacén</th>
                        <th class="px-4 py-3 text-left">Código</th>
                        <th class="px-4 py-3 text-left">Dirección</th>
                        <th class="px-4 py-3 text-right">Productos con stock</th>
                        <th class="px-4 py-3 text-right">Valorizado (costo)</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($almacenes as $a)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-700">{{ $a->descripcion }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $a->codigo ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $a->direccion ?: '-' }}</td>
                            <td class="px-4 py-3 text-right">{{ $a->productos }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">S/ {{ number_format($a->valorizado, 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($a->predeterminado)
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">PREDETERMINADO</span>
                                @elseif (auth()->user()->esAdmin())
                                    <form method="POST" action="{{ route('almacenes.predeterminado', $a->id_almacen) }}"
                                          onsubmit="return confirm('¿El sistema pasará a vender y comprar con este almacén. Continuar?')">
                                        @csrf
                                        <button class="text-xs text-indigo-600 hover:underline">Usar como predeterminado</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                <a href="{{ route('kardex.stock', ['almacen' => $a->id_almacen]) }}" class="text-gray-600 hover:underline">Stock</a>
                                @if (auth()->user()->esAdmin())
                                    <button type="button" class="text-indigo-600 hover:underline"
                                            @click="form = @js(['id' => $a->id_almacen, 'descripcion' => $a->descripcion, 'codigo' => $a->codigo ?? '', 'direccion' => $a->direccion, 'ubigeo' => $a->ubigeo])">Editar</button>
                                    @if (!$a->predeterminado && !$a->movimientos)
                                        <form method="POST" action="{{ route('almacenes.destroy', $a->id_almacen) }}" class="inline" onsubmit="return confirm('¿Eliminar este almacén?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin almacenes registrados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal crear / editar --}}
        <template x-if="form">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="form = null" @keydown.escape.window="form = null">
            <form method="POST" :action="form.id ? '{{ url('almacenes') }}/' + form.id : '{{ route('almacenes.store') }}'"
                  class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-3">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                <h3 class="font-bold text-gray-700" x-text="form.id ? 'Editar almacén' : 'Nuevo almacén'"></h3>
                <label class="block text-sm">Nombre
                    <input name="descripcion" x-model="form.descripcion" required maxlength="150" class="block w-full rounded-lg border-gray-300 text-sm uppercase">
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <label class="block text-sm">Código
                        <input name="codigo" x-model="form.codigo" maxlength="4" class="block w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm col-span-2">Ubigeo
                        <input name="ubigeo" x-model="form.ubigeo" maxlength="100" class="block w-full rounded-lg border-gray-300 text-sm">
                    </label>
                </div>
                <label class="block text-sm">Dirección
                    <input name="direccion" x-model="form.direccion" maxlength="150" class="block w-full rounded-lg border-gray-300 text-sm">
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="form = null" class="px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold">Cancelar</button>
                    <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar</button>
                </div>
            </form>
        </div>
        </template>
    </div>
@endsection
