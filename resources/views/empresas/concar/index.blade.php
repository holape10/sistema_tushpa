@extends('layouts.app')
@section('title', 'Exportar a CONCAR')

@php
    $mes = \Carbon\Carbon::createFromFormat('Ym', $periodo)->locale('es')->translatedFormat('F Y');
    $sumas = function (array $filas) {
        $debe = $haber = 0;
        foreach ($filas as $f) { $f[13] === 'D' ? $debe += $f[14] : $haber += $f[14]; }
        return [round($debe, 2), round($haber, 2)];
    };
    $libros = [
        'ventas' => ['titulo' => 'Registro de Ventas', 'datos' => $ventas, 'subdiario' => $cfg['subdiario_ventas'], 'color' => 'bg-indigo-600 hover:bg-indigo-700'],
        'compras' => ['titulo' => 'Registro de Compras', 'datos' => $compras, 'subdiario' => $cfg['subdiario_compras'], 'color' => 'bg-emerald-600 hover:bg-emerald-700'],
    ];
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-4">
    @if (session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1">
            <h1 class="text-lg font-bold">Exportar a CONCAR</h1>
            <p class="text-sm text-slate-500">Asientos de ventas y compras listos para <strong>Importar asientos desde Excel</strong> en CONCAR.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <label class="text-xs font-semibold text-slate-500">Periodo
                <input type="month" value="{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}" max="{{ now()->format('Y-m') }}"
                       onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()"
                       class="block mt-1 h-10 rounded-xl border-slate-300 text-sm font-semibold">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
            </label>
        </form>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        @foreach ($libros as $clave => $l)
            @php [$debe, $haber] = $sumas($l['datos']['filas']); $cuadra = abs($debe - $haber) < 0.01; @endphp
            <section class="bg-white rounded-2xl shadow-sm p-5 flex flex-col">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-bold text-slate-700">{{ $l['titulo'] }}</h2>
                        <p class="text-xs text-slate-400 capitalize">{{ $mes }} · Subdiario {{ $l['subdiario'] }}</p>
                    </div>
                    @if ($l['datos']['asientos'])
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $cuadra ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $cuadra ? '✔ Debe = Haber' : '⚠ No cuadra' }}
                        </span>
                    @endif
                </div>

                <dl class="grid grid-cols-3 gap-2 mt-4 text-center">
                    <div class="bg-slate-50 rounded-xl py-2"><dt class="text-[11px] text-slate-500">Asientos</dt><dd class="text-xl font-extrabold">{{ $l['datos']['asientos'] }}</dd></div>
                    <div class="bg-slate-50 rounded-xl py-2"><dt class="text-[11px] text-slate-500">Líneas</dt><dd class="text-xl font-extrabold">{{ count($l['datos']['filas']) }}</dd></div>
                    <div class="bg-slate-50 rounded-xl py-2"><dt class="text-[11px] text-slate-500">Total (S/)</dt><dd class="text-xl font-extrabold">{{ number_format($l['datos']['total'], 2) }}</dd></div>
                </dl>
                @if ($l['datos']['asientos'])
                    <p class="text-xs text-slate-500 mt-2">Debe {{ number_format($debe, 2) }} · Haber {{ number_format($haber, 2) }}</p>
                @endif
                @foreach ($l['datos']['omitidos'] as $motivo => $cant)
                    <p class="text-xs text-amber-700 mt-1">No se exportan {{ $cant }}: {{ mb_strtolower($motivo) }}.</p>
                @endforeach

                <form method="GET" action="{{ route('concar.excel', $clave) }}" class="mt-auto pt-4 flex items-end gap-2">
                    <input type="hidden" name="periodo" value="{{ $periodo }}">
                    <label class="text-xs font-semibold text-slate-500 w-36" title="Si en CONCAR ya tienes asientos en este subdiario y mes, empieza después del último">
                        Correlativo inicial
                        <input type="number" name="desde" value="1" min="1" max="9999" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm">
                    </label>
                    <button @disabled(!$l['datos']['asientos'])
                            class="flex-1 h-10 rounded-xl {{ $l['color'] }} text-white text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed">
                        Descargar Excel CONCAR
                    </button>
                </form>
            </section>
        @endforeach
    </div>

    {{-- Vista previa del primer asiento de ventas --}}
    @php $muestra = collect($ventas['filas'])->where(2, $ventas['filas'][0][2] ?? null); @endphp
    @if ($muestra->isNotEmpty())
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <h2 class="px-4 py-3 font-bold text-slate-700 border-b border-slate-100">Así queda un asiento <span class="font-normal text-slate-400 text-sm">(primera venta del mes)</span></h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                        <tr><th class="text-left px-4 py-2">Comprobante</th><th class="text-left px-4 py-2">Cuenta</th><th class="text-left px-4 py-2">Anexo</th>
                            <th class="text-left px-4 py-2">Documento</th><th class="text-left px-4 py-2">Glosa</th><th class="text-right px-4 py-2">Debe</th><th class="text-right px-4 py-2">Haber</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($muestra as $f)
                            <tr>
                                <td class="px-4 py-2 font-mono">{{ $f[1] }}-{{ $f[2] }}</td>
                                <td class="px-4 py-2 font-mono font-semibold">{{ $f[10] }}</td>
                                <td class="px-4 py-2">{{ $f[11] }}</td>
                                <td class="px-4 py-2">{{ $f[17] }} {{ $f[18] }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $f[22] }}</td>
                                <td class="px-4 py-2 text-right">{{ $f[13] === 'D' ? number_format($f[14], 2) : '' }}</td>
                                <td class="px-4 py-2 text-right">{{ $f[13] === 'H' ? number_format($f[14], 2) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Configuración de cuentas --}}
    <details class="bg-white rounded-2xl shadow-sm" @if ($errors->any()) open @endif>
        <summary class="px-4 py-3 cursor-pointer font-bold text-slate-700">⚙ Cuentas y subdiarios <span class="font-normal text-slate-400 text-sm">(según tu plan contable en CONCAR)</span></summary>
        <form method="POST" action="{{ route('concar.config') }}" class="p-4 border-t border-slate-100 space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                @foreach ([
                    ['subdiario_ventas', 'Subdiario de ventas'], ['subdiario_compras', 'Subdiario de compras'],
                    ['cta_por_cobrar', 'Cuenta por cobrar (12)'], ['cta_por_pagar', 'Cuenta por pagar (42)'],
                    ['cta_igv', 'Cuenta IGV (40)'], ['cta_ventas', 'Ventas gravadas (70)'],
                    ['cta_ventas_exo', 'Ventas exoneradas / inafectas (70)'], ['cta_compras', 'Compras (60)'],
                    ['anexo_varios', 'Anexo para "clientes varios"'],
                ] as [$campo, $etiqueta])
                    <label class="text-xs font-semibold text-slate-500">{{ $etiqueta }}
                        <input name="{{ $campo }}" value="{{ old($campo, $cfg[$campo]) }}" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm font-mono">
                    </label>
                @endforeach
                <label class="text-xs font-semibold text-slate-500">Tipo de conversión
                    <select name="tipo_conversion" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm">
                        <option value="V" @selected($cfg['tipo_conversion'] === 'V')>V · Venta</option>
                        <option value="M" @selected($cfg['tipo_conversion'] === 'M')>M · Compra</option>
                        <option value="F" @selected($cfg['tipo_conversion'] === 'F')>F · Según fecha del documento</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">Formato del Excel
                    <select name="formato" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm">
                        <option value="PLANTILLA" @selected($cfg['formato'] === 'PLANTILLA')>Plantilla oficial (3 filas de títulos)</option>
                        <option value="ANTERIOR" @selected($cfg['formato'] === 'ANTERIOR')>Como el sistema anterior (1 fila, anulados en 0)</option>
                    </select>
                </label>
                @foreach ([['doc_factura', 'Código factura'], ['doc_boleta', 'Código boleta'], ['doc_nc', 'Código nota de crédito'], ['doc_nd', 'Código nota de débito']] as [$campo, $etiqueta])
                    <label class="text-xs font-semibold text-slate-500">{{ $etiqueta }} <span class="font-normal">(T.G. 06)</span>
                        <input name="{{ $campo }}" value="{{ old($campo, $cfg[$campo]) }}" maxlength="2" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm font-mono uppercase">
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-slate-400">Las compras en dólares usan siempre conversión especial (C) con el tipo de cambio registrado en la compra.
                Cada línea de venta usa la cuenta Debe/Haber de su producto (Productos &gt; Contabilidad); si no tiene, la de su tipo de producto y, al final, las de aquí.</p>
            <div class="flex justify-end">
                <button class="h-10 px-5 rounded-xl bg-slate-800 text-white text-sm font-bold">Guardar cuentas</button>
            </div>
        </form>
    </details>

    <div class="bg-sky-50 border border-sky-200 rounded-2xl p-4 text-sm text-sky-900 space-y-1">
        <p class="font-bold">Para subirlo a CONCAR</p>
        <ol class="list-decimal pl-5 space-y-0.5">
            <li>Revisa que las cuentas de arriba existan en tu plan de cuentas y que los clientes/proveedores existan como <strong>anexos</strong>.</li>
            <li>En CONCAR entra a la opción de <strong>importar asientos desde Excel</strong> y elige el archivo descargado.</li>
            <li>Si ya registraste asientos en ese subdiario y mes, cambia el <strong>correlativo inicial</strong> para no repetir números.</li>
        </ol>
    </div>
</div>
@endsection
