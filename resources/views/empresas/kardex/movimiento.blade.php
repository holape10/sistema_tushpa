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

        {{-- Como en el Punto de venta: se escribe el nombre o el código (o se escanea), ↑ ↓ y Enter agrega; luego la cantidad y Enter vuelve aquí --}}
        <div class="relative mb-3">
            <input id="buscar_prod" placeholder="Busca por nombre o código, o escanea el código de barras (↑ ↓ para elegir, Enter agrega)" autocomplete="off"
                   class="w-full rounded-lg border-gray-300 text-sm">
            <div id="sugerencias" class="hidden absolute z-20 mt-1 w-full bg-white rounded-xl shadow-xl border max-h-72 overflow-y-auto"></div>
        </div>
        @php
            $catalogo = $productos->map(function ($p) {
                $conEq = \App\Support\Unidades::mixto(0, $p->umecod ?: 'NIU', $p->ume_equivalente, (float) $p->factor_equivalente) !== null;

                return ['id' => (int) $p->IdProducto, 'nom' => $p->pronom, 'cod' => (string) $p->procod, 'barra' => (string) ($p->codigo_barra ?? ''),
                    'costo' => (float) $p->costo, 'lote' => (bool) $p->control_lote, 'serie' => (bool) $p->control_serie,
                    'factor' => $conEq ? (float) $p->factor_equivalente : 0, 'abr' => \App\Support\Unidades::nombre($p->umecod ?: 'NIU'),
                    'abreq' => $conEq ? \App\Support\Unidades::nombre($p->ume_equivalente) : ''];
            })->values();
        @endphp

        <table class="w-full text-sm mb-4">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr><th class="px-3 py-2 text-left">Producto</th><th class="px-3 py-2 w-32">Cantidad</th><th class="px-3 py-2 w-32">Costo unit.</th>@if ($tipo === 'I')<th class="px-3 py-2 w-32">Lote</th><th class="px-3 py-2 w-36">Vence</th>@endif<th class="w-10"></th></tr>
            </thead>
            <tbody id="items"></tbody>
        </table>
        <p id="sin_items" class="text-center text-gray-400 text-sm mb-4">Agrega al menos un producto.</p>
        <p id="total_mov" class="hidden text-right text-sm text-gray-600 mb-4">Total: <b id="total_monto" class="text-lg text-gray-800"></b></p>

        <button class="w-full py-3 rounded-xl font-bold text-white {{ $tipo === 'I' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }}">
            REGISTRAR {{ $tipo === 'I' ? 'INGRESO' : 'SALIDA' }}
        </button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <div class="flex items-center p-4 pb-0">
            <h3 class="font-semibold text-gray-700 mr-auto">Últimos {{ $tipo === 'I' ? 'ingresos' : 'salidas' }}</h3>
            <a href="{{ route('kardex.movimientos', ['tipo' => $tipo]) }}" class="text-sm font-semibold text-indigo-600 hover:underline">Ver todos →</a>
        </div>
        <table class="w-full text-sm">
            <thead class="text-gray-500 text-xs uppercase"><tr><th class="px-4 py-2 text-left">N°</th><th class="px-4 py-2 text-left">Fecha</th><th class="px-4 py-2 text-left">Operación</th><th class="px-4 py-2 text-left">Observación</th><th class="px-4 py-2 text-right">Ítems</th><th class="px-4 py-2 text-left">Usuario</th><th></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($recientes as $r)
                    <tr class="hover:bg-gray-50"><td class="px-4 py-2"><a href="{{ route('kardex.movimiento.ver', $r->mov_cab_id) }}" class="font-semibold text-indigo-600 hover:underline">MOV-{{ $r->mov_cab_id }}</a></td>
                        <td class="px-4 py-2">{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td><td class="px-4 py-2">{{ $r->des_tip_ope }}</td><td class="px-4 py-2">{{ $r->observaciones }}</td>
                        <td class="px-4 py-2 text-right">{{ $r->items }}</td><td class="px-4 py-2">{{ $r->apeusu }}</td>
                        <td class="px-4 py-2 text-right"><a href="{{ route('kardex.movimiento.ver', $r->mov_cab_id) }}" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200">Ver</a></td></tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-4 text-center text-gray-400">Sin registros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        let fila = 0;
        const CON_LOTE = @json($tipo === 'I');
        const PRODUCTOS = @json($catalogo);
        const tbody = document.getElementById('items');
        const buscar = document.getElementById('buscar_prod');
        const lista = document.getElementById('sugerencias');
        let resultados = [], sel = 0;

        const norm = t => String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        // Todas las palabras en el nombre o el código; primero los que empiezan con lo escrito
        function filtrar(q) {
            const palabras = norm(q).split(/\s+/).filter(Boolean);
            if (!palabras.length) return [];
            return PRODUCTOS.filter(p => { const t = norm(p.nom + ' ' + p.cod + ' ' + p.barra); return palabras.every(w => t.includes(w)); })
                .sort((a, b) => (norm(b.nom).startsWith(palabras[0]) ? 1 : 0) - (norm(a.nom).startsWith(palabras[0]) ? 1 : 0))
                .slice(0, 30);
        }
        function pintarLista() {
            lista.innerHTML = '';
            resultados.forEach((p, idx) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'w-full text-left px-3 py-2 text-sm hover:bg-indigo-50' + (idx === sel ? ' bg-indigo-100' : '');
                b.innerHTML = '<span class="font-semibold"></span> <span class="text-xs text-gray-400"></span>';
                b.children[0].textContent = p.nom;
                b.children[1].textContent = p.cod + (p.barra ? ' · ' + p.barra : '');
                b.addEventListener('mousedown', ev => { ev.preventDefault(); agregar(p); });
                lista.appendChild(b);
            });
            lista.classList.toggle('hidden', !resultados.length);
            lista.children[sel]?.scrollIntoView({ block: 'nearest' });
        }
        function cerrarLista() { resultados = []; sel = 0; pintarLista(); }

        buscar.addEventListener('input', () => { resultados = filtrar(buscar.value); sel = 0; pintarLista(); });
        buscar.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!resultados.length) return;
                sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + resultados.length) % resultados.length;
                pintarLista();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                // Código o código de barras exacto (lector): se agrega directo
                const q = buscar.value.trim().toUpperCase();
                const exacto = q && PRODUCTOS.find(p => p.cod.toUpperCase() === q || (p.barra && p.barra.toUpperCase() === q));
                const p = exacto || resultados[sel];
                if (p) agregar(p); else if (q) alert('No hay un producto con ese nombre o código.');
            } else if (e.key === 'Escape') {
                cerrarLista();
            }
        });
        buscar.addEventListener('blur', () => setTimeout(cerrarLista, 150));

        // Enter en la cantidad o el costo vuelve al buscador (para el siguiente producto)
        tbody.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.matches('input') && !e.target.matches('textarea')) {
                e.preventDefault();
                buscar.focus();
            }
        });

        function enfocarCantidad(tr) {
            const c = tr.querySelector('.series') || tr.querySelector('.cant');
            if (c) { c.focus(); c.select?.(); }
        }

        function agregar(p) {
            buscar.value = '';
            cerrarLista();
            const existente = (CON_LOTE && p.lote) || p.serie ? null : tbody.querySelector(`tr[data-id="${p.id}"]`);
            if (existente) {
                const cant = existente.querySelector('.cant');
                cant.value = (parseFloat(cant.value) || 0) + 1;
                cant.dispatchEvent(new Event('input', { bubbles: true }));
                enfocarCantidad(existente);
                return;
            }

            const i = fila++;
            const tr = document.createElement('tr');
            tr.dataset.id = p.id;
            tr.className = 'border-t border-gray-100';
            tr.innerHTML = `
                <td class="px-3 py-2"><span class="nombre font-medium"></span> <span class="codigo text-xs text-gray-400"></span>
                    ${p.serie ? `<textarea name="items[${i}][series]" rows="2" required placeholder="Series, una por línea (escanea o escribe)"
                        class="series mt-1 w-full rounded-lg border-sky-300 bg-sky-50 text-xs uppercase font-mono"></textarea>` : ''}</td>
                <td class="px-3 py-2"><input type="hidden" name="items[${i}][IdProducto]" value="${p.id}">
                    ${p.factor ? `
                    <input type="hidden" name="items[${i}][cantidad]" value="1" class="total">
                    <div class="flex items-center gap-1">
                        <input type="number" step="any" min="0" value="1" class="cant mixto w-16 rounded-lg border-gray-300 text-sm text-right">
                        <span class="abr text-xs text-gray-600"></span><span class="text-gray-400">+</span>
                        <input type="number" step="any" min="0" value="0" class="frac mixto w-16 rounded-lg border-gray-300 text-sm text-right">
                        <span class="abreq text-xs text-gray-600"></span>
                    </div>` : `
                    <input type="number" step="any" min="0.00001" required name="items[${i}][cantidad]" value="1" class="cant w-full rounded-lg border-gray-300 text-sm">`}</td>
                <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="items[${i}][costo]" value="${(p.costo || 0).toFixed(2)}" class="costo w-full rounded-lg border-gray-300 text-sm"></td>
                ${CON_LOTE ? `<td class="px-3 py-2"><input name="items[${i}][lote]" maxlength="50" ${p.lote ? 'required placeholder="Obligatorio"' : ''} class="w-full rounded-lg border-gray-300 text-sm uppercase"></td>
                <td class="px-3 py-2"><input type="date" name="items[${i}][vencimiento]" ${p.lote ? 'required' : ''} class="w-full rounded-lg border-gray-300 text-sm"></td>` : ''}
                <td class="px-3 py-2 text-center"><button type="button" class="quitar text-red-600 font-bold" title="Quitar">✕</button></td>`;
            tr.querySelector('.nombre').textContent = p.nom; // texto plano, sin inyectar HTML
            tr.querySelector('.codigo').textContent = p.cod;
            // Unidad equivalente: 1 botella + 5 onzas (1 botella = 25 onzas) se registra como 1.2 botellas
            if (p.factor) {
                tr.querySelector('.abr').textContent = p.abr;
                tr.querySelector('.abreq').textContent = p.abreq;
                const total = tr.querySelector('.total');
                tr.addEventListener('input', e => {
                    if (!e.target.classList.contains('mixto')) return;
                    const ent = parseFloat(tr.querySelector('.cant').value) || 0, frac = parseFloat(tr.querySelector('.frac').value) || 0;
                    total.value = Math.round((ent + frac / p.factor) * 100000) / 100000;
                });
            }
            // Con número de serie: la cantidad es la cantidad de series escritas
            const series = tr.querySelector('.series');
            if (series) {
                const cant = tr.querySelector('.cant');
                cant.readOnly = true;
                cant.value = 0;
                series.addEventListener('input', () => { cant.value = series.value.split(/[\r\n,;]+/).filter(s => s.trim()).length; cant.dispatchEvent(new Event('input', { bubbles: true })); });
            }
            tbody.appendChild(tr);
            refrescar();
            enfocarCantidad(tr);
        }

        // Total del movimiento: cantidad × costo de cada producto
        function refrescar() {
            const hay = tbody.children.length > 0;
            document.getElementById('sin_items').style.display = hay ? 'none' : '';
            let total = 0;
            tbody.querySelectorAll('tr').forEach(tr => {
                const cant = parseFloat((tr.querySelector('.total') || tr.querySelector('.cant')).value) || 0;
                total += cant * (parseFloat(tr.querySelector('.costo').value) || 0);
            });
            document.getElementById('total_mov').classList.toggle('hidden', !hay);
            document.getElementById('total_monto').textContent = 'S/ ' + total.toFixed(2);
        }

        tbody.addEventListener('input', refrescar);
        tbody.addEventListener('click', e => { if (e.target.closest('.quitar')) { e.target.closest('tr').remove(); refrescar(); buscar.focus(); } });
        document.getElementById('form_mov').addEventListener('submit', e => {
            if (!tbody.children.length) { e.preventDefault(); alert('Agrega al menos un producto.'); return; }
            if ([...tbody.querySelectorAll('.total')].some(t => !(parseFloat(t.value) > 0))) { e.preventDefault(); alert('Escribe la cantidad (unidades o equivalente) de cada producto.'); }
        });
        buscar.focus();
    </script>
@endsection
