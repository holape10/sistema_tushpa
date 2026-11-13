@extends('layouts.app')
@section('title', 'Stock de Productos')
@section('content')
    @include('empresas.partials.alert')

    <form class="flex flex-wrap items-end gap-2 mb-4">
        <label class="text-sm">Almacén
            <select name="almacen" class="block rounded-lg border-gray-300 text-sm">
                @foreach ($almacenes as $a)
                    <option value="{{ $a->id_almacen }}" @selected($a->id_almacen == $idAlmacen)>{{ $a->descripcion }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Buscar<input name="q" value="{{ $q }}" placeholder="Nombre del producto" class="block rounded-lg border-gray-300 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Filtrar</button>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('kardex.movimiento', ['tipo' => 'I']) }}" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">+ Ingreso</a>
            <a href="{{ route('kardex.movimiento', ['tipo' => 'E']) }}" class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700">− Salida</a>
        </div>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Código</th><th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-left">Tipo</th><th class="px-4 py-3 text-right">Stock mín.</th>
                    <th class="px-4 py-3 text-right">Stock</th><th class="px-4 py-3 text-right">Valorizado</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($productos as $p)
                    @php $bajo = $p->stock <= $p->stock_min; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">{{ $p->procod }}</td>
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $p->pronom }}</td>
                        <td class="px-4 py-3">{{ $p->tipo_nombre }}</td>
                        <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format($p->stock_min, 2), '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right font-bold {{ $p->stock < 0 ? 'text-red-600' : ($bajo ? 'text-amber-600' : 'text-gray-800') }}">
                            {{ \App\Support\Unidades::texto((float) $p->stock, $p->umecod ?: 'NIU', $p->ume_equivalente, (float) $p->factor_equivalente) }}
                            @if ($bajo)<span title="Stock bajo">⚠️</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ number_format($p->stock * $p->costo, 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($p->control_lote || $p->control_serie)
                                <span class="mr-2 text-xs text-gray-400" title="Se controla por {{ $p->control_lote ? 'lote' : 'serie' }}: usa Ingreso o Salida">Ajustar: por {{ $p->control_lote ? 'lote' : 'serie' }}</span>
                            @else
                                <button type="button" class="ajustar mr-2 px-3 py-1.5 rounded-lg bg-sky-600 text-white text-xs font-bold hover:bg-sky-700"
                                        data-id="{{ $p->IdProducto }}" data-nombre="{{ $p->pronom }}" data-stock="{{ (float) $p->stock }}"
                                        data-unidad="{{ \App\Support\Unidades::nombre($p->umecod ?: 'NIU') }}">AJUSTAR STOCK</button>
                            @endif
                            <a href="{{ route('kardex.index', ['producto' => $p->IdProducto, 'almacen' => $idAlmacen]) }}" class="text-indigo-600 hover:underline">Kardex</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin productos con stock.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $productos->links() }}</div>

    {{-- Ajustar stock: se escribe lo que hay de verdad; el sistema hace el ingreso o la salida de la diferencia --}}
    <div id="modal_ajuste" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <form method="POST" action="{{ route('kardex.ajustar') }}" class="w-full max-w-sm rounded-2xl bg-white shadow-xl p-5 space-y-3">
            @csrf
            <input type="hidden" name="IdProducto" id="aj_id">
            <input type="hidden" name="id_almacen" value="{{ $idAlmacen }}">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-bold uppercase text-sky-700">Ajustar stock</p><p id="aj_nombre" class="font-bold text-gray-800"></p></div>
                <button type="button" data-cerrar class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
            </div>
            <p class="text-sm text-gray-600">En el sistema: <b id="aj_sistema"></b></p>
            <label class="block text-sm font-semibold text-gray-700">Stock real (lo que hay de verdad)
                <input type="number" name="stock_real" id="aj_real" step="any" min="0" required class="mt-1 w-full h-11 rounded-xl border-gray-300 text-right text-lg font-bold"></label>
            <p id="aj_resultado" class="hidden rounded-xl px-3 py-2 text-sm font-semibold"></p>
            <label class="block text-sm text-gray-600">Motivo (opcional)
                <input name="observacion" maxlength="200" placeholder="Ej. conteo físico, producto dañado" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-cerrar class="h-10 px-4 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Cancelar</button>
                <button id="aj_guardar" class="h-10 px-4 rounded-xl bg-sky-600 text-white text-sm font-bold hover:bg-sky-700 disabled:opacity-50">Ajustar</button>
            </div>
        </form>
    </div>
    <script>
        (() => {
            const modal = document.getElementById('modal_ajuste'), real = document.getElementById('aj_real'), res = document.getElementById('aj_resultado');
            let actual = 0, unidad = '';
            const num = n => Number(Math.round(n * 1000) / 1000).toLocaleString('es-PE', { maximumFractionDigits: 3 });
            // Lo que va a hacer el sistema: hay 10, real 6 → salida de 4; real 12 → ingreso de 2
            function pintar() {
                const v = parseFloat(real.value);
                const ok = !isNaN(v) && v >= 0;
                const dif = ok ? Math.round((v - actual) * 100000) / 100000 : 0;
                res.classList.toggle('hidden', !ok);
                res.className = 'rounded-xl px-3 py-2 text-sm font-semibold ' + (dif > 0 ? 'bg-emerald-50 text-emerald-800' : dif < 0 ? 'bg-rose-50 text-rose-800' : 'bg-gray-50 text-gray-600') + (ok ? '' : ' hidden');
                res.textContent = !ok ? '' : dif > 0 ? `Se hará un INGRESO de ${num(dif)} ${unidad}: quedará en ${num(v)}.`
                    : dif < 0 ? `Se hará una SALIDA de ${num(-dif)} ${unidad}: quedará en ${num(v)}.` : 'Es igual al sistema: no hay nada que ajustar.';
                document.getElementById('aj_guardar').disabled = !ok || dif === 0;
            }
            document.addEventListener('click', e => {
                const b = e.target.closest('.ajustar');
                if (b) {
                    actual = parseFloat(b.dataset.stock) || 0; unidad = b.dataset.unidad;
                    document.getElementById('aj_id').value = b.dataset.id;
                    document.getElementById('aj_nombre').textContent = b.dataset.nombre;
                    document.getElementById('aj_sistema').textContent = num(actual) + ' ' + unidad;
                    real.value = ''; pintar();
                    modal.classList.remove('hidden'); modal.classList.add('flex');
                    setTimeout(() => real.focus(), 50);
                }
                if (e.target.closest('[data-cerrar]') || e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            });
            real.addEventListener('input', pintar);
            modal.querySelector('form').addEventListener('submit', () => { document.getElementById('aj_guardar').disabled = true; });
        })();
    </script>
@endsection
