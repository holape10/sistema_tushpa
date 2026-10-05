@php
    $f = fn($n) => number_format((float) $n, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boletas de pago {{ $nombrePeriodo }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; color: #111827; background: #e5e7eb; margin: 0; padding: 16px; }
        .boleta { width: 190mm; margin: 0 auto 14px; background: #fff; padding: 9mm 10mm; border: 1px solid #d1d5db; page-break-inside: avoid; }
        .boleta + .boleta { page-break-before: auto; }
        h1 { font-size: 14px; margin: 0; text-align: center; letter-spacing: .5px; }
        .sub { text-align: center; color: #4b5563; margin: 2px 0 8px; }
        .emp { display: flex; justify-content: space-between; border-bottom: 2px solid #312e81; padding-bottom: 6px; margin-bottom: 8px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3px 10px; border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 8px; }
        .grid b { color: #374151; font-size: 9px; text-transform: uppercase; display: block; }
        .cols { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #312e81; color: #fff; text-align: left; padding: 4px 6px; font-size: 9.5px; text-transform: uppercase; }
        td { padding: 3px 6px; border-bottom: 1px solid #f3f4f6; }
        .r { text-align: right; }
        .tot td { font-weight: bold; background: #f3f4f6; border-top: 1px solid #9ca3af; }
        .neto { margin-top: 8px; display: flex; justify-content: flex-end; }
        .neto div { background: #312e81; color: #fff; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: bold; }
        .firmas { display: flex; justify-content: space-around; margin-top: 30px; text-align: center; }
        .firmas div { width: 38%; border-top: 1px solid #111; padding-top: 4px; }
        .acciones { text-align: center; margin-bottom: 14px; }
        .acciones button { padding: 10px 20px; border: 0; border-radius: 6px; background: #4f46e5; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .acciones { display: none; } .boleta { border: 0; width: auto; margin: 0 0 6mm; padding: 0; } }
    </style>
</head>
<body>
<div class="acciones"><button onclick="window.print()">IMPRIMIR / GUARDAR PDF ({{ $detalle->count() }} boleta{{ $detalle->count() === 1 ? '' : 's' }})</button></div>

@foreach ($detalle as $d)
    @php $t = $trab[$d->emp_id] ?? null; @endphp
    <div class="boleta">
        <div class="emp">
            <div><strong style="font-size:12px">{{ $empresa->NomEmpresa ?? '' }}</strong><br>RUC {{ $empresa->IdEmpresa ?? '' }}<br>{{ $negocio->direccion ?? $empresa->DirEmpresa ?? '' }}</div>
            <div style="text-align:right">Periodo: <strong>{{ mb_strtoupper($nombrePeriodo) }}</strong><br>Fecha de pago: {{ $pl->fecha_pago ? \Carbon\Carbon::parse($pl->fecha_pago)->format('d/m/Y') : '—' }}</div>
        </div>
        <h1>BOLETA DE PAGO DE REMUNERACIONES</h1>
        <p class="sub">D.S. N° 001-98-TR</p>
        <div class="grid">
            <div style="grid-column: span 2"><b>Trabajador</b>{{ $d->nombre }}</div>
            <div><b>DNI</b>{{ $d->dni }}</div>
            <div><b>Cargo</b>{{ $d->cargo ?: '—' }}</div>
            <div><b>Fecha de ingreso</b>{{ $t && $t->fecha_ingreso ? \Carbon\Carbon::parse($t->fecha_ingreso)->format('d/m/Y') : '—' }}</div>
            <div><b>Sistema de pensiones</b>{{ $d->sistema_pension }}</div>
            <div><b>CUSPP</b>{{ $t->cuspp ?? '—' }}</div>
            <div><b>Días laborados</b>{{ $d->dias - (float) $d->faltas }} de {{ $d->dias }}</div>
            <div><b>Sueldo básico</b>S/ {{ $f($t->sueldo ?? $d->sueldo) }}</div>
            <div><b>Horas extra</b>{{ (float) $d->he25 + (float) $d->he35 }}</div>
            <div><b>Tardanza</b>{{ $d->tardanza_min }} min</div>
            <div><b>Cuenta</b>{{ trim(($t->banco ?? '') . ' ' . ($t->cuenta ?? '')) ?: '—' }}</div>
        </div>
        <div class="cols">
            <table>
                <tr><th>Ingresos</th><th class="r">S/</th></tr>
                <tr><td>Remuneración básica</td><td class="r">{{ $f($d->sueldo) }}</td></tr>
                @if ($d->asig_familiar > 0)<tr><td>Asignación familiar</td><td class="r">{{ $f($d->asig_familiar) }}</td></tr>@endif
                @if ($d->horas_extra > 0)<tr><td>Horas extra</td><td class="r">{{ $f($d->horas_extra) }}</td></tr>@endif
                @if ($d->bonos > 0)<tr><td>Bonificaciones</td><td class="r">{{ $f($d->bonos) }}</td></tr>@endif
                <tr class="tot"><td>Total ingresos</td><td class="r">{{ $f($d->total_ingresos) }}</td></tr>
            </table>
            <table>
                <tr><th>Descuentos</th><th class="r">S/</th></tr>
                @if ($d->desc_faltas > 0)<tr><td>Faltas ({{ (float) $d->faltas }} días)</td><td class="r">{{ $f($d->desc_faltas) }}</td></tr>@endif
                @if ($d->desc_tardanza > 0)<tr><td>Tardanzas</td><td class="r">{{ $f($d->desc_tardanza) }}</td></tr>@endif
                @if ($d->onp > 0)<tr><td>ONP ({{ (float) $p->onp }} %)</td><td class="r">{{ $f($d->onp) }}</td></tr>@endif
                @if ($d->afp_aporte > 0)<tr><td>AFP aporte obligatorio</td><td class="r">{{ $f($d->afp_aporte) }}</td></tr>@endif
                @if ($d->afp_prima > 0)<tr><td>AFP prima de seguro</td><td class="r">{{ $f($d->afp_prima) }}</td></tr>@endif
                @if ($d->afp_comision > 0)<tr><td>AFP comisión</td><td class="r">{{ $f($d->afp_comision) }}</td></tr>@endif
                @if ($d->renta_quinta > 0)<tr><td>Renta de 5ta categoría</td><td class="r">{{ $f($d->renta_quinta) }}</td></tr>@endif
                @if ($d->adelantos > 0)<tr><td>Adelantos</td><td class="r">{{ $f($d->adelantos) }}</td></tr>@endif
                @if ($d->otros_descuentos > 0)<tr><td>Otros descuentos</td><td class="r">{{ $f($d->otros_descuentos) }}</td></tr>@endif
                <tr class="tot"><td>Total descuentos</td><td class="r">{{ $f($d->total_descuentos) }}</td></tr>
            </table>
            <table>
                <tr><th>Aportes del empleador</th><th class="r">S/</th></tr>
                <tr><td>EsSalud ({{ (float) $p->essalud }} %)</td><td class="r">{{ $f($d->essalud) }}</td></tr>
                <tr class="tot"><td>Total aportes</td><td class="r">{{ $f($d->essalud) }}</td></tr>
            </table>
        </div>
        <div class="neto"><div>NETO A PAGAR: S/ {{ $f($d->neto) }}</div></div>
        <div class="firmas"><div>EMPLEADOR</div><div>TRABAJADOR<br>{{ $d->nombre }}</div></div>
    </div>
@endforeach
</body>
</html>
