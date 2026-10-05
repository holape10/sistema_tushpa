@extends('layouts.app')
@section('title', 'Envío de Comprobantes a SUNAT')
@section('content')
    @include('empresas.partials.alert')

    {{-- Ambiente y certificado --}}
    <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
        @if ((string) $empresa->produccion === '1')
            <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 font-bold">PRODUCCIÓN</span>
        @else
            <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 font-bold">BETA (pruebas, sin validez tributaria)</span>
        @endif
        @if (!$tieneCertificado)
            <span class="px-3 py-1 rounded-full bg-red-100 text-red-700">⚠️ Falta el certificado digital —
                <a href="{{ route('empresas.edit', $empresa->IdEmpresa) }}" class="underline font-semibold">súbelo aquí</a></span>
        @elseif ($empresa->fec_fin_cer)
            <span class="px-3 py-1 rounded-full {{ \Carbon\Carbon::parse($empresa->fec_fin_cer)->isPast() ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                Certificado vigente hasta {{ \Carbon\Carbon::parse($empresa->fec_fin_cer)->format('d/m/Y') }}</span>
        @endif
        <a href="{{ route('sunat.resumenes') }}" class="ml-auto px-4 py-2 rounded-xl bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Resumen diario de boletas →</a>
    </div>

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
        <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
        <label class="text-sm">Tipo
            <select name="tipo" class="block rounded-lg border-gray-300 text-sm">
                <option value="">Todos</option>
                @foreach (['01' => 'Facturas', '03' => 'Boletas', '07' => 'Notas de crédito', '08' => 'Notas de débito'] as $cod => $nom)
                    <option value="{{ $cod }}" @selected($tipo === $cod)>{{ $nom }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Estado
            <select name="estado" class="block rounded-lg border-gray-300 text-sm">
                <option value="pendientes" @selected($estado === 'pendientes')>Por enviar (pendientes, con error o rechazados)</option>
                <option value="todos" @selected($estado === 'todos')>Todos</option>
                @foreach (['PENDIENTE', 'ACEPTADO', 'OBSERVADO', 'RECHAZADO', 'ERROR', 'EN RESUMEN'] as $e)
                    <option value="{{ $e }}" @selected($estado === $e)>{{ $e }}</option>
                @endforeach
            </select>
        </label>
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Filtrar</button>
    </form>

    <div class="flex flex-wrap gap-2 mb-3 text-xs">
        @foreach ($conteo as $est => $cant)
            <span class="px-2 py-1 rounded-lg bg-white shadow-sm">@include('empresas.sunat._estado', ['estado' => $est]) {{ $cant }}</span>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <div class="flex items-center gap-3 p-3 border-b border-gray-100">
            <button type="button" id="btn_lote" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 disabled:opacity-50" disabled>
                Enviar seleccionados (<span id="n_sel">0</span>)
            </button>
            <span id="progreso" class="text-sm text-gray-500"></span>
            <span class="ml-auto text-xs text-gray-400">Las boletas normalmente van por resumen diario, pero también puedes enviarlas aquí una por una.</span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-3 py-3 w-8"><input type="checkbox" id="chk_todos"></th>
                    <th class="px-3 py-3 text-left">Fecha</th>
                    <th class="px-3 py-3 text-left">Comprobante</th>
                    <th class="px-3 py-3 text-left">Cliente</th>
                    <th class="px-3 py-3 text-right">Total</th>
                    <th class="px-3 py-3 text-left">Estado SUNAT</th>
                    <th class="px-3 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($comprobantes as $c)
                    @php $reenviable = in_array($c->est_sunat, \App\Support\Sunat\SunatService::ESTADOS_REENVIABLES, true); @endphp
                    <tr class="hover:bg-gray-50 align-top" data-id="{{ $c->IdCpe_cabecera }}">
                        <td class="px-3 py-3">@if ($reenviable)<input type="checkbox" class="chk" value="{{ $c->IdCpe_cabecera }}">@endif</td>
                        <td class="px-3 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($c->ccafem)->format('d/m/Y') }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <span class="font-semibold">{{ $c->serdoc }}-{{ str_pad($c->numdoc, 8, '0', STR_PAD_LEFT) }}</span>
                            <span class="block text-xs text-gray-400">{{ $c->tdodes }}</span>
                            @if ($c->serie_ref)<span class="block text-xs text-gray-400">Modifica: {{ $c->serie_ref }}-{{ $c->num_ref }}</span>@endif
                        </td>
                        <td class="px-3 py-3">{{ $c->ccanom }}<span class="block text-xs text-gray-400">{{ $c->ccandi }}</span></td>
                        <td class="px-3 py-3 text-right font-semibold whitespace-nowrap">S/ {{ number_format($c->ccaitv, 2) }}</td>
                        <td class="px-3 py-3 max-w-md">
                            <span class="celda-estado">@include('empresas.sunat._estado', ['estado' => $c->est_sunat])</span>
                            <span class="celda-msg block text-xs text-gray-500 mt-1 break-words">{{ $c->ccadessun }}</span>
                        </td>
                        <td class="px-3 py-3 text-right whitespace-nowrap space-x-2">
                            @if ($reenviable)
                                <button type="button" class="btn-enviar px-3 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700">Enviar</button>
                            @endif
                            <a href="{{ route('sunat.descargar', [$c->IdCpe_cabecera, 'xml']) }}" class="text-xs text-indigo-600 hover:underline">XML</a>
                            <a href="{{ route('sunat.descargar', [$c->IdCpe_cabecera, 'cdr']) }}" class="text-xs text-indigo-600 hover:underline">CDR</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No hay comprobantes con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $comprobantes->links() }}</div>

    <script>
        const CSRF = '{{ csrf_token() }}';
        const URL_ENVIAR = "{{ url('sunat/enviar') }}/";
        const COLORES = {
            'ACEPTADO': 'bg-green-100 text-green-700', 'OBSERVADO': 'bg-amber-100 text-amber-800',
            'RECHAZADO': 'bg-red-100 text-red-700', 'ERROR': 'bg-orange-100 text-orange-700', 'PENDIENTE': 'bg-gray-100 text-gray-700',
        };

        // Envía uno y actualiza su fila (se usa para el botón individual y para el lote, uno tras otro)
        async function enviar(id) {
            const fila = document.querySelector(`tr[data-id="${id}"]`);
            const btn = fila.querySelector('.btn-enviar');
            if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
            let res;
            try {
                const r = await fetch(URL_ENVIAR + id, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } });
                res = await r.json();
            } catch (e) {
                res = { success: false, estado: 'ERROR', mensaje: 'Sin conexión con el servidor.' };
            }
            // estado null = no llegó a enviarse (falta certificado, etc.): la etiqueta se queda como estaba
            if (res.estado) {
                fila.querySelector('.celda-estado').innerHTML =
                    `<span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold ${COLORES[res.estado] || COLORES.ERROR}"></span>`;
                fila.querySelector('.celda-estado span').textContent = res.estado;
            }
            fila.querySelector('.celda-msg').textContent = res.mensaje || '';
            if (res.success) {
                btn?.remove();
                fila.querySelector('.chk')?.remove();
            } else if (btn) {
                btn.disabled = false; btn.textContent = 'Reintentar';
            }
            actualizarSeleccion();
            return res.success;
        }

        document.addEventListener('click', e => {
            const b = e.target.closest('.btn-enviar');
            if (b) enviar(b.closest('tr').dataset.id);
        });

        function actualizarSeleccion() {
            const n = document.querySelectorAll('.chk:checked').length;
            document.getElementById('n_sel').textContent = n;
            document.getElementById('btn_lote').disabled = n === 0;
        }
        document.addEventListener('change', e => { if (e.target.classList.contains('chk')) actualizarSeleccion(); });
        document.getElementById('chk_todos').addEventListener('change', function () {
            document.querySelectorAll('.chk').forEach(c => c.checked = this.checked);
            actualizarSeleccion();
        });

        document.getElementById('btn_lote').addEventListener('click', async function () {
            const ids = [...document.querySelectorAll('.chk:checked')].map(c => c.value);
            if (!ids.length || !confirm(`¿Enviar ${ids.length} comprobante(s) a SUNAT?`)) return;
            this.disabled = true;
            let ok = 0;
            for (let i = 0; i < ids.length; i++) {
                document.getElementById('progreso').textContent = `Enviando ${i + 1} de ${ids.length}...`;
                if (await enviar(ids[i])) ok++;
            }
            document.getElementById('progreso').textContent = `Listo: ${ok} aceptado(s), ${ids.length - ok} con problema.`;
        });
    </script>
@endsection
