@extends('layouts.app')
@section('title', $tipo === 'I' ? 'Ingreso de Productos' : 'Salida de Productos')
@section('content')
    @include('empresas.partials.alert')

    <div class="flex gap-2 mb-4">
        <a href="{{ route('kardex.movimiento', ['tipo' => 'I']) }}" class="px-4 py-2 rounded-xl text-sm font-semibold {{ $tipo === 'I' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700' }}">Ingresos</a>
        <a href="{{ route('kardex.movimiento', ['tipo' => 'E']) }}" class="px-4 py-2 rounded-xl text-sm font-semibold {{ $tipo === 'E' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700' }}">Salidas</a>
        <a href="{{ route('kardex.stock') }}" class="ml-auto px-4 py-2 rounded-xl bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Ver stock</a>
    </div>

    <form method="POST" action="{{ route('kardex.guardar_movimiento') }}" class="bg-white rounded-2xl shadow-sm p-4 mb-6" id="form_mov">
        @csrf
        <input type="hidden" name="mov_tip" value="{{ $tipo }}">
        <div class="grid sm:grid-cols-4 gap-3 mb-4">
            <label class="text-sm">Operación
                <select name="cod_tip_ope" required class="block w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($operaciones as $op)
                        <option value="{{ $op->cod_tip_ope }}" @selected(old('cod_tip_ope') == $op->cod_tip_ope)>{{ $op->cod_tip_ope }} - {{ $op->des_tip_ope }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Almacén
                <select name="id_almacen" class="block w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Fecha<input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="block w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-sm">Observación<input name="observaciones" value="{{ old('observaciones') }}" maxlength="255" class="block w-full rounded-lg border-gray-300 text-sm"></label>
        </div>

        <div class="flex gap-2 mb-3">
            <input list="lista_productos" id="buscar_prod" placeholder="Buscar producto o insumo y presiona Enter..." autocomplete="off" class="flex-1 rounded-lg border-gray-300 text-sm">
            <datalist id="lista_productos">
                @foreach ($productos as $p)
                    @php $conEq = \App\Support\Unidades::mixto(0, $p->umecod ?: 'NIU', $p->ume_equivalente, (float) $p->factor_equivalente) !== null; @endphp
                    <option data-id="{{ $p->IdProducto }}" data-costo="{{ $p->costo }}" data-lote="{{ $p->control_lote ? 1 : 0 }}" data-serie="{{ $p->control_serie ? 1 : 0 }}"
                            data-factor="{{ $conEq ? (float) $p->factor_equivalente : 0 }}" data-abr="{{ \App\Support\Unidades::abreviatura($p->umecod ?: 'NIU') }}"
                            data-abreq="{{ $conEq ? \App\Support\Unidades::abreviatura($p->ume_equivalente) : '' }}" value="{{ $p->pronom }}">{{ $p->procod }}</option>
                @endforeach
            </datalist>
            <button type="button" id="btn_agregar" class="px-4 rounded-lg bg-indigo-600 text-white text-sm font-semibold">Agregar</button>
        </div>

        <table class="w-full text-sm mb-4">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 w-32">Cantidad</th><th class="px-3 py-2 w-32">Costo unit.</th>@if ($tipo === 'I')<th class="px-3 py-2 w-32">Lote</th><th class="px-3 py-2 w-36">Vence</th>@endif<th class="w-10"></th></tr>
            </thead>
            <tbody id="items"></tbody>
        </table>
        <p id="sin_items" class="text-center text-gray-400 text-sm mb-4">Agrega al menos un producto.</p>

        <button class="w-full py-3 rounded-xl font-bold text-white {{ $tipo === 'I' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }}">
            REGISTRAR {{ $tipo === 'I' ? 'INGRESO' : 'SALIDA' }}
        </button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <h3 class="font-semibold text-gray-700 p-4 pb-0">Últimos {{ $tipo === 'I' ? 'ingresos' : 'salidas' }}</h3>
        <table class="w-full text-sm">
            <thead class="text-gray-500 text-xs uppercase"><tr><th class="px-4 py-2 text-left">N°</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Operación</th><th class="px-4 py-2 text-left">Observación</th><th class="px-4 py-2 text-right">Ítems</th><th class="px-4 py-2 text-left">Usuario</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($recientes as $r)
                    <tr><td class="px-4 py-2">MOV-{{ $r->mov_cab_id }}</td><td class="px-4 py-2">{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td><td class="px-4 py-2">{{ $r->des_tip_ope }}</td><td class="px-4 py-2">{{ $r->observaciones }}</td><td class="px-4 py-2 text-right">{{ $r->items }}</td><td class="px-4 py-2">{{ $r->apeusu }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-4 text-center text-gray-400">Sin registros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        let fila = 0;
        const CON_LOTE = @json($tipo === 'I');
        const tbody = document.getElementById('items');

        function agregar() {
            const txt = document.getElementById('buscar_prod');
            const op = [...document.querySelectorAll('#lista_productos option')].find(o => o.value === txt.value);
            if (!op) { alert('Elige un producto de la lista.'); return; }

            const conSerie = op.dataset.serie === '1';
            const factor = parseFloat(op.dataset.factor) || 0;
            const existente = (CON_LOTE && op.dataset.lote === '1') || conSerie ? null : tbody.querySelector(`tr[data-id="${op.dataset.id}"] .cant`);
            if (existente) {
                existente.value = (parseFloat(existente.value) || 0) + 1;
                existente.dispatchEvent(new Event('input', { bubbles: true }));
                txt.value = '';
                return;
            }

            const i = fila++;
            const tr = document.createElement('tr');
            tr.dataset.id = op.dataset.id;
            tr.className = 'border-t border-gray-100';
            tr.innerHTML = `
                <td class="px-3 py-2"><span class="nombre"></span>
                    ${conSerie ? `<textarea name="items[${i}][series]" rows="2" required placeholder="Series, una por línea (escanea o escribe)"
                        class="series mt-1 w-full rounded-lg border-sky-300 bg-sky-50 text-xs uppercase font-mono"></textarea>` : ''}</td>
                <td class="px-3 py-2"><input type="hidden" name="items[${i}][IdProducto]" value="${op.dataset.id}">
                    ${factor ? `
                    <input type="hidden" name="items[${i}][cantidad]" value="1" class="total">
                    <div class="flex items-center gap-1">
                        <input type="number" step="any" min="0" value="1" class="cant mixto w-16 rounded-lg border-gray-300 text-sm text-right">
                        <span class="abr text-[10px] text-gray-500"></span><span class="text-gray-400">+</span>
                        <input type="number" step="any" min="0" value="0" class="frac mixto w-16 rounded-lg border-gray-300 text-sm text-right">
                        <span class="abreq text-[10px] text-gray-500"></span>
                    </div>` : `
                    <input type="number" step="any" min="0.00001" required name="items[${i}][cantidad]" value="1" class="cant w-full rounded-lg border-gray-300 text-sm">`}</td>
                <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="items[${i}][costo]" value="${parseFloat(op.dataset.costo || 0).toFixed(2)}" class="w-full rounded-lg border-gray-300 text-sm"></td>
                ${CON_LOTE ? `<td class="px-3 py-2"><input name="items[${i}][lote]" maxlength="50" ${op.dataset.lote === '1' ? 'required placeholder="Obligatorio"' : ''} class="w-full rounded-lg border-gray-300 text-sm uppercase"></td>
                <td class="px-3 py-2"><input type="date" name="items[${i}][vencimiento]" ${op.dataset.lote === '1' ? 'required' : ''} class="w-full rounded-lg border-gray-300 text-sm"></td>` : ''}
                <td class="px-3 py-2 text-center"><button type="button" class="quitar text-red-600 font-bold">✕</button></td>`;
            tr.querySelector('.nombre').textContent = op.value; // texto plano, sin inyectar HTML
            // Unidad equivalente: 1 botella + 5 onzas (1 botella = 25 onzas) se registra como 1.2 botellas
            if (factor) {
                tr.querySelector('.abr').textContent = op.dataset.abr;
                tr.querySelector('.abreq').textContent = op.dataset.abreq;
                const total = tr.querySelector('.total');
                tr.addEventListener('input', e => {
                    if (!e.target.classList.contains('mixto')) return;
                    const ent = parseFloat(tr.querySelector('.cant').value) || 0, frac = parseFloat(tr.querySelector('.frac').value) || 0;
                    total.value = Math.round((ent + frac / factor) * 100000) / 100000;
                });
            }
            // Con número de serie: la cantidad es la cantidad de series escritas
            const series = tr.querySelector('.series');
            if (series) {
                const cant = tr.querySelector('.cant');
                cant.readOnly = true;
                cant.value = 0;
                series.addEventListener('input', () => { cant.value = series.value.split(/[\r\n,;]+/).filter(s => s.trim()).length; });
            }
            tbody.appendChild(tr);
            txt.value = '';
            refrescar();
        }

        function refrescar() { document.getElementById('sin_items').style.display = tbody.children.length ? 'none' : ''; }

        document.getElementById('btn_agregar').addEventListener('click', agregar);
        document.getElementById('buscar_prod').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); agregar(); } });
        tbody.addEventListener('click', e => { if (e.target.closest('.quitar')) { e.target.closest('tr').remove(); refrescar(); } });
        document.getElementById('form_mov').addEventListener('submit', e => {
            if (!tbody.children.length) { e.preventDefault(); alert('Agrega al menos un producto.'); return; }
            if ([...tbody.querySelectorAll('.total')].some(t => !(parseFloat(t.value) > 0))) { e.preventDefault(); alert('Escribe la cantidad (unidades o equivalente) de cada producto.'); }
        });
    </script>
@endsection
