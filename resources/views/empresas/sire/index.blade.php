@extends('layouts.app')
@section('title', $libro === 'ventas' ? 'SIRE Ventas (RVIE)' : 'SIRE Compras (RCE)')

@php
    $tipos = ['01' => 'Factura', '03' => 'Boleta', '07' => 'N. Crédito', '08' => 'N. Débito'];
    $persona = $libro === 'ventas' ? 'Cliente' : 'Proveedor';
    $nombrePeriodo = fn($p) => \Carbon\Carbon::createFromFormat('Ym', $p)->locale('es')->translatedFormat('F Y');
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-4"
     x-data="sirePagina(@js($solicitud && !$solicitud->archivo && !in_array($solicitud->cod_estado, ['03']) ? route('sire.estado', [$libro, $solicitud->id]) : null))" x-init="iniciar()">

    {{-- Pestañas --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('sire.index', ['ventas', 'periodo' => $periodo]) }}"
           class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold {{ $libro === 'ventas' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Ventas (RVIE)</a>
        <a href="{{ route('sire.index', ['compras', 'periodo' => $periodo]) }}"
           class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold {{ $libro === 'compras' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Compras (RCE)</a>
        <a href="{{ route('sire.credenciales') }}" class="ml-auto text-sm font-semibold text-slate-500 hover:text-indigo-700">⚙ Credenciales SIRE</a>
    </div>

    @unless ($configurado)
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-5 text-amber-900">
            <p class="font-bold">Falta conectar el SIRE con SUNAT</p>
            <p class="text-sm mt-1">Ingresa el ID y la CLAVE de "Credenciales de API SUNAT" para traer la propuesta de SUNAT.</p>
            <div class="flex flex-wrap gap-2 mt-3">
                <a href="{{ route('empresas.edit', auth()->user()->IdEmpresa) }}" class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-bold">Ponerlas en Editar Empresa</a>
                <a href="{{ route('sire.credenciales') }}" class="px-4 py-2 rounded-xl bg-white border border-amber-300 text-amber-800 text-sm font-semibold">o en Credenciales SIRE</a>
            </div>
        </div>
    @endunless

    @if ($errorSunat)
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errorSunat }}</div>
    @endif

    {{-- Periodo y pedido a SUNAT --}}
    <section class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        <form method="GET" class="flex items-end gap-2">
            <label class="text-xs font-semibold text-slate-500">Periodo
                @if ($periodos)
                    <select name="periodo" onchange="this.form.submit()" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm font-semibold capitalize">
                        @foreach ($periodos as $p)
                            <option value="{{ $p['periodo'] }}" @selected($p['periodo'] === $periodo)>{{ $nombrePeriodo($p['periodo']) }} · {{ $p['estado'] }}</option>
                        @endforeach
                        @unless (collect($periodos)->contains('periodo', $periodo))
                            <option value="{{ $periodo }}" selected>{{ $nombrePeriodo($periodo) }}</option>
                        @endunless
                    </select>
                @else
                    <input type="month" value="{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}"
                           onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
                    <input type="hidden" name="periodo" value="{{ $periodo }}">
                @endif
            </label>
        </form>

        <div class="flex-1 min-w-[200px] text-sm">
            @if ($solicitud)
                <p class="text-slate-500">Último pedido: <strong class="text-slate-700">ticket {{ $solicitud->num_ticket }}</strong>
                    · {{ \Carbon\Carbon::parse($solicitud->created_at)->format('d/m/Y H:i') }}</p>
                <p x-show="!listo" class="font-semibold" :class="error ? 'text-rose-600' : 'text-indigo-600'">
                    <span x-show="consultando && !error" class="inline-block w-3 h-3 mr-1 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin align-middle"></span>
                    <span x-text="mensaje || @js($solicitud->des_estado ?: 'Pedido enviado')"></span>
                </p>
                @if ($solicitud->archivo)
                    <p class="text-emerald-700 font-semibold">✔ Propuesta descargada · {{ number_format($solicitud->filas) }} comprobantes
                    </p>
                    <div class="flex flex-wrap gap-2 mt-1.5">
                        <a href="{{ route('sire.excel', [$libro, $solicitud->id]) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descargar Excel
                        </a>
                        <a href="{{ route('sire.archivo', [$libro, $solicitud->id]) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold">.zip original de SUNAT</a>
                    </div>
                @endif
            @else
                <p class="text-slate-400">Aún no has traído la propuesta de {{ $nombrePeriodo($periodo) }}.</p>
            @endif
        </div>

        <button type="button" @click="solicitar()" :disabled="enviando || !@js($configurado)"
                class="h-10 px-5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold disabled:opacity-50">
            <span x-text="enviando ? 'Pidiendo a SUNAT…' : @js($solicitud ? 'Volver a traer propuesta' : 'Traer propuesta de SUNAT')"></span>
        </button>
    </section>

    {{-- Cuadre --}}
    @if ($cuadre)
        @if (!$cuadre['columnasOk'])
            <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
                No pude reconocer las columnas del archivo de SUNAT. Descarga el .zip y envíamelo para ajustar la lectura.
            </div>
        @endif

        @php
            $presentado = $estadoPeriodo && str_contains(mb_strtolower($estadoPeriodo), 'present');
            $sinDatos = $cuadre['cantSistema'] === 0;
        @endphp

        @if ($presentado)
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 text-sm">
                <strong>✔ SUNAT indica que {{ $nombrePeriodo($periodo) }} ya está {{ mb_strtolower($estadoPeriodo) }}.</strong>
                Las diferencias de abajo solo comparan con lo registrado en <strong>este sistema</strong>; no significan que falte declarar.
            </div>
        @endif
        @if ($sinDatos)
            <div class="rounded-xl bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 text-sm">
                Este sistema no tiene {{ $libro === 'ventas' ? 'ventas electrónicas' : 'compras' }} de {{ $nombrePeriodo($periodo) }}
                (probablemente se registraron en tu sistema anterior). El cuadre sirve para los periodos que trabajes desde este sistema;
                aquí abajo igual puedes ver todo lo que SUNAT tiene registrado.
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">SUNAT (propuesta)</p>
                <p class="text-2xl font-extrabold">{{ number_format($cuadre['totalSunat'], 2) }}</p>
                <p class="text-xs text-slate-400">{{ $cuadre['cantSunat'] }} comprobantes</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">Tu sistema</p>
                <p class="text-2xl font-extrabold">{{ number_format($cuadre['totalSistema'], 2) }}</p>
                <p class="text-xs text-slate-400">{{ $cuadre['cantSistema'] }} comprobantes</p>
            </div>
            @php $dif = round($cuadre['totalSunat'] - $cuadre['totalSistema'], 2); @endphp
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">Diferencia</p>
                <p class="text-2xl font-extrabold {{ abs($dif) < 0.01 ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format($dif, 2) }}</p>
                <p class="text-xs text-slate-400">SUNAT − sistema</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500">Cuadre con este sistema</p>
                @php $problemas = count($cuadre['diferencias']) + count($cuadre['soloSunat']) + count($cuadre['soloSistema']); @endphp
                @if ($sinDatos)
                    <p class="text-lg font-extrabold text-slate-400">Sin datos aquí</p>
                    <p class="text-xs text-slate-400">{{ $cuadre['cantSunat'] }} comprobantes solo en SUNAT</p>
                @else
                    <p class="text-lg font-extrabold {{ $problemas ? 'text-amber-600' : 'text-emerald-600' }}">{{ $problemas ? $problemas . ($problemas === 1 ? ' diferencia' : ' diferencias') : '✔ Todo cuadra' }}</p>
                    <p class="text-xs text-slate-400">{{ count($cuadre['coinciden']) }} coinciden</p>
                @endif
            </div>
        </div>

        @foreach ([
            ['diferencias', 'Total distinto', 'Están en ambos lados pero el importe no coincide.', 'bg-rose-500'],
            ['soloSunat', 'Solo en SUNAT', $libro === 'ventas' ? 'SUNAT los tiene y tu sistema no (¿emitidos desde otro sistema o anulados aquí?).' : 'Tus proveedores los emitieron a tu RUC y no los registraste en Compras.', 'bg-amber-500'],
            ['soloSistema', 'Solo en tu sistema', $libro === 'ventas' ? 'Tu sistema los tiene y SUNAT no: revisa que estén enviados o en un resumen diario.' : 'Los registraste en Compras pero SUNAT no los tiene (¿serie/número mal escritos o no son electrónicos?).', 'bg-sky-500'],
        ] as [$clave, $titulo, $ayuda, $color])
            @continue(empty($cuadre[$clave]))
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{ abierto: true }">
                <button type="button" @click="abierto = !abierto" class="w-full flex items-center gap-3 px-4 py-3 text-left border-b border-slate-100">
                    <span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span>
                    <span class="font-bold">{{ $titulo }} <span class="text-slate-400 font-normal">({{ count($cuadre[$clave]) }})</span></span>
                    <span class="text-xs text-slate-400 flex-1">{{ $ayuda }}</span>
                    <span x-text="abierto ? '−' : '+'" class="text-slate-400 text-lg"></span>
                </button>
                <div x-show="abierto" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                            <tr>
                                <th class="text-left px-4 py-2">Comprobante</th>
                                <th class="text-left px-4 py-2">Fecha</th>
                                <th class="text-left px-4 py-2">{{ $persona }}</th>
                                @if ($clave === 'diferencias')
                                    <th class="text-right px-4 py-2">SUNAT</th><th class="text-right px-4 py-2">Sistema</th><th class="text-right px-4 py-2">Dif.</th>
                                @else
                                    <th class="text-right px-4 py-2">Total</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($cuadre[$clave] as $fila)
                                @php $c = $clave === 'diferencias' ? $fila['sunat'] : $fila; @endphp
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap"><span class="font-semibold">{{ $c['serie'] }}-{{ $c['numero'] }}</span>
                                        <span class="text-xs text-slate-400">{{ $tipos[$c['tipo']] ?? $c['tipo'] }}</span></td>
                                    <td class="px-4 py-2 whitespace-nowrap">{{ $c['fecha'] }}</td>
                                    <td class="px-4 py-2">{{ $c['nombre'] }} <span class="text-xs text-slate-400">{{ $c['doc'] }}</span>
                                        @if (!empty($c['est_sunat']) && $clave === 'soloSistema')<span class="ml-1 text-[11px] font-semibold text-sky-700 bg-sky-50 px-1.5 rounded">{{ $c['est_sunat'] }}</span>@endif
                                    </td>
                                    @if ($clave === 'diferencias')
                                        <td class="px-4 py-2 text-right">{{ number_format($fila['sunat']['total'], 2) }}</td>
                                        <td class="px-4 py-2 text-right">{{ number_format($fila['sistema']['total'], 2) }}</td>
                                        <td class="px-4 py-2 text-right font-bold text-rose-600">{{ number_format($fila['dif'], 2) }}</td>
                                    @else
                                        <td class="px-4 py-2 text-right font-semibold">{{ number_format($c['total'], 2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        @if ($cuadre['coinciden'])
            <details class="bg-white rounded-2xl shadow-sm">
                <summary class="px-4 py-3 cursor-pointer font-bold"><span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 mr-2"></span>Coinciden <span class="text-slate-400 font-normal">({{ count($cuadre['coinciden']) }})</span></summary>
                <div class="overflow-x-auto border-t border-slate-100">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($cuadre['coinciden'] as $c)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap"><span class="font-semibold">{{ $c['serie'] }}-{{ $c['numero'] }}</span> <span class="text-xs text-slate-400">{{ $tipos[$c['tipo']] ?? $c['tipo'] }}</span></td>
                                    <td class="px-4 py-2">{{ $c['fecha'] }}</td>
                                    <td class="px-4 py-2">{{ $c['nombre'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($c['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Pide la propuesta y espera a que SUNAT termine de generarla (consulta el ticket cada 8 s)
    function sirePagina(urlEstado) {
        return {
            urlEstado, enviando: false, consultando: false, listo: false, error: false, mensaje: '', _timer: null,
            iniciar() { if (this.urlEstado) this.consultar(); },
            async solicitar() {
                this.enviando = true;
                try {
                    const r = await fetch(@json(route('sire.solicitar', $libro)), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ periodo: @json($periodo) }),
                    });
                    const d = await r.json();
                    if (d.estado !== 'ok') { alert(d.mensaje || 'No se pudo pedir la propuesta.'); return; }
                    window.location.reload();
                } catch (e) { alert('Error de conexión.'); } finally { this.enviando = false; }
            },
            async consultar() {
                this.consultando = true;
                try {
                    const d = await (await fetch(this.urlEstado, { headers: { Accept: 'application/json' } })).json();
                    if (d.estado === 'listo') { this.listo = true; window.location.reload(); return; }
                    if (d.estado === 'error') { this.error = true; this.mensaje = d.mensaje; return; }
                    this.mensaje = (d.descripcion || 'SUNAT está generando el archivo') + '… (se revisa sola cada 8 segundos)';
                    this._timer = setTimeout(() => this.consultar(), 8000);
                } catch (e) {
                    this.mensaje = 'Sin conexión; reintentando…';
                    this._timer = setTimeout(() => this.consultar(), 15000);
                }
            },
        };
    }
</script>
@endpush
