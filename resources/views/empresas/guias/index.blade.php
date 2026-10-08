@extends('layouts.app')
@section('title', 'Guías de remisión')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php $in = 'rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    <div class="space-y-5" x-data="guias()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-truck-fast text-indigo-600"></i> Guías de remisión</h1>
                <p class="text-sm text-gray-500">Emite una guía sola, o desde <a href="{{ route('ventas.index') }}" class="text-indigo-600 font-semibold underline">Panel de ventas</a> (botón ⋮ de la venta → Guía de remisión) para que se llene sola.</p>
            </div>
            <a href="{{ route('guias.crear') }}" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700"><i class="fas fa-plus"></i> Nueva guía</a>
        </div>

        @if ((string) $empresa->produccion !== '1' || !$empresa->client_id)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900 space-y-1">
                <p class="font-bold"><i class="fas fa-triangle-exclamation"></i> Para enviar guías a SUNAT falta:</p>
                @if ((string) $empresa->produccion !== '1')<p>• Poner la empresa en <b>PRODUCCIÓN</b> (SUNAT no tiene ambiente de pruebas para guías). Mientras tanto se guardan como PENDIENTE.</p>@endif
                @if (!$empresa->client_id)<p>• Las <b>credenciales API de SUNAT</b> (client_id y client_secret) en Mantenimiento &gt; Empresas &gt; Editar. Se generan en SUNAT Operaciones en Línea &gt; Empresa &gt; Credenciales de API SUNAT.</p>@endif
            </div>
        @endif

        <form class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap gap-3 items-end text-sm">
            <label>Desde<input type="date" name="desde" value="{{ $desde }}" class="block {{ $in }} mt-1"></label>
            <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block {{ $in }} mt-1"></label>
            <label>Estado
                <select name="estado" class="block {{ $in }} mt-1">
                    <option value="">Todos</option>
                    @foreach (['PENDIENTE', 'ENVIADO', 'ACEPTADO', 'RECHAZADO', 'ERROR'] as $e)<option @selected($estado === $e)>{{ $e }}</option>@endforeach
                </select></label>
            <label class="flex-1 min-w-[200px]">Buscar<input name="q" value="{{ $q }}" placeholder="Destinatario, documento, placa…" class="block w-full {{ $in }} mt-1"></label>
            <button class="px-4 py-2 rounded-lg bg-gray-800 text-white font-semibold">Filtrar</button>
        </form>

        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr><th class="px-3 py-2 text-left">Guía</th><th class="px-3 py-2 text-left">Destinatario</th><th class="px-3 py-2 text-left">Traslado</th>
                        <th class="px-3 py-2 text-center">SUNAT</th><th class="px-3 py-2 text-center">Acciones</th></tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($guias as $g)
                        <tr data-id="{{ $g->gre_id }}" class="align-top {{ (int) request('resaltar') === $g->gre_id ? 'bg-indigo-50' : '' }}">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <p class="font-bold text-gray-800">{{ $g->serie }}-{{ str_pad($g->numero, 8, '0', STR_PAD_LEFT) }}</p>
                                <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($g->fecha_emision)->format('d/m/Y') }}{{ $g->doc_numero ? ' · '.$g->doc_numero : '' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-semibold text-gray-700">{{ $g->dest_nom }}</p>
                                <p class="text-xs text-gray-500">{{ $g->dest_num }} · {{ $g->llegada_direccion }}</p>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600">
                                <p>{{ $motivos[$g->motivo] ?? $g->motivo }} · {{ \Carbon\Carbon::parse($g->fecha_traslado)->format('d/m/Y') }}</p>
                                <p>{{ $g->modalidad === '01' ? 'Público: '.$g->transp_nom : ($g->vehiculo_m1l ? 'Privado (vehículo M1/L)' : 'Privado: '.$g->placa.' · '.$g->cond_nombres) }}</p>
                                <p>{{ rtrim(rtrim(number_format($g->peso, 3), '0'), '.') }} {{ $g->unidad_peso === 'TNE' ? 't' : 'kg' }}</p>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="celda-estado">@include('empresas.sunat._estado', ['estado' => $g->est_sunat])</span>
                                <p class="celda-msg text-[11px] text-gray-500 mt-1 max-w-[260px] mx-auto">{{ $g->mensaje }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap justify-center gap-1">
                                    @if (in_array($g->est_sunat, ['PENDIENTE', 'ERROR']))
                                        <button type="button" @click="accion({{ $g->gre_id }}, 'enviar', $event)" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-bold">{{ $g->est_sunat === 'ERROR' ? 'Reintentar' : 'Enviar' }}</button>
                                    @endif
                                    @if ($g->est_sunat === 'ENVIADO')
                                        <button type="button" @click="accion({{ $g->gre_id }}, 'consultar', $event)" class="px-2.5 py-1 rounded-lg bg-sky-600 text-white text-xs font-bold">Consultar</button>
                                    @endif
                                    <a href="{{ route('guias.imprimir', $g->gre_id) }}" target="_blank" title="Imprimir" class="px-2 py-1 rounded-lg bg-gray-100 text-gray-700"><i class="fas fa-print"></i></a>
                                    @if ($g->est_sunat !== 'PENDIENTE')
                                        <a href="{{ route('guias.archivo', [$g->gre_id, 'xml']) }}" title="XML" class="px-2 py-1 rounded-lg bg-sky-50 text-sky-700"><i class="fas fa-file-code"></i></a>
                                    @endif
                                    @if ($g->est_sunat === 'ACEPTADO')
                                        <a href="{{ route('guias.archivo', [$g->gre_id, 'cdr']) }}" title="CDR" class="px-2 py-1 rounded-lg bg-amber-50 text-amber-700"><i class="fas fa-file-zipper"></i></a>
                                    @endif
                                    @if (in_array($g->est_sunat, ['PENDIENTE', 'RECHAZADO', 'ERROR']))
                                        <button type="button" @click="eliminar({{ $g->gre_id }})" title="Eliminar" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600"><i class="fas fa-trash"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No hay guías en esas fechas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $guias->links() }}</div>
    </div>

    <script>
        function guias() {
            const CSRF = @json(csrf_token());
            const post = url => fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } }).then(r => r.json()).catch(() => ({ ok: false, mensaje: 'Sin conexión con el servidor.' }));
            return {
                async accion(id, que, e) {
                    const b = e.currentTarget; b.disabled = true; b.textContent = que === 'enviar' ? 'Enviando…' : 'Consultando…';
                    const r = await post(@json(url('guias')) + '/' + id + '/' + que);
                    alert(r.mensaje || 'Sin respuesta.');
                    location.reload();
                },
                async eliminar(id) {
                    if (!confirm('¿Eliminar esta guía? (no fue aceptada por SUNAT)')) return;
                    const r = await post(@json(url('guias')) + '/' + id + '/eliminar');
                    alert(r.mensaje);
                    if (r.ok) location.reload();
                },
            };
        }
    </script>
@endsection
