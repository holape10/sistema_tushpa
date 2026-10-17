<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto {{ str_pad((string) $p->numero, 5, '0', STR_PAD_LEFT) }} · {{ $h->his_cli_cod }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12.5px; line-height: 1.5; color: #111; background: #e5e7eb; margin: 0; }
        .hoja { width: 210mm; max-width: 100%; margin: 12px auto; background: #fff; padding: 15mm; box-sizing: border-box; }
        .cab { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 12px; gap: 12px; }
        .cab img { max-height: 55px; max-width: 120px; object-fit: contain; }
        h1 { font-size: 17px; color: #0f766e; margin: 0; }
        .datos { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 18px; margin: 10px 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f0fdfa; color: #0f766e; text-align: left; font-size: 11px; text-transform: uppercase; padding: 6px; border-bottom: 2px solid #99f6e4; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .totales { margin-left: auto; width: 260px; margin-top: 10px; }
        .totales div { display: flex; justify-content: space-between; padding: 3px 0; }
        .total { font-size: 16px; font-weight: bold; color: #0f766e; border-top: 2px solid #0f766e; margin-top: 4px; padding-top: 6px !important; }
        .nota { background: #f8fafc; border-radius: 8px; padding: 8px 10px; margin-top: 14px; white-space: pre-line; }
        .firmas { display: flex; justify-content: space-between; margin-top: 50px; text-align: center; gap: 20px; }
        .firmas > div { width: 70mm; }
        .linea { border-top: 1px solid #111; padding-top: 4px; }
        .firma-img { height: 60px; display: block; margin: 0 auto -4px; }
        .sello { display: inline-block; padding: 2px 8px; border-radius: 6px; font-weight: bold; font-size: 11px; }
        .acciones { text-align: center; margin: 12px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .acciones button { padding: 10px 22px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; } .hoja { margin: 0; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
<div class="acciones">
    <button onclick="window.print()">Imprimir</button>
    @unless ($firma)
        <button onclick="tushpaFirmar({ tipo: 'presupuesto', id: {{ $p->pre_id }}, titulo: 'Presupuesto N° {{ str_pad((string) $p->numero, 5, '0', STR_PAD_LEFT) }}', alFirmar: () => location.reload() })"
                style="background:#4f46e5">✍️ Firmar (aquí o en el celular)</button>
    @endunless
</div>
<div class="hoja">
    <div class="cab">
        <div><b>{{ $negocio->nombre_comercial ?: $negocio->razon }}</b><br>{{ $negocio->direccion }}@if ($negocio->telefono) · Tel. {{ $negocio->telefono }}@endif</div>
        @if ($negocio->logo)<img src="{{ asset($negocio->logo) }}" alt="">@endif
    </div>
    <div style="display:flex;justify-content:space-between;align-items:baseline">
        <h1>PRESUPUESTO / PLAN DE TRATAMIENTO</h1>
        <b style="font-size:15px">N° {{ str_pad((string) $p->numero, 5, '0', STR_PAD_LEFT) }}</b>
    </div>
    <div class="datos">
        <div><b>Paciente:</b> {{ $h->tipo === 'MASCOTA' ? $h->mascota.' ('.$h->clinom.')' : $h->clinom }}</div>
        <div><b>Historia:</b> {{ $h->his_cli_cod }}</div>
        <div><b>DNI:</b> {{ $h->clinum }}</div>
        <div><b>Fecha:</b> {{ \Carbon\Carbon::parse($p->fecha)->format('d/m/Y') }} · válido hasta {{ \Carbon\Carbon::parse($p->fecha)->addDays((int) $p->validez_dias)->format('d/m/Y') }}</div>
        @if ($doctor)<div><b>Profesional:</b> {{ $doctor }}</div>@endif
        <div><b>Estado:</b>
            <span class="sello" style="{{ ['ACEPTADO' => 'background:#dcfce7;color:#166534', 'RECHAZADO' => 'background:#fee2e2;color:#991b1b'][$p->estado] ?? 'background:#fef3c7;color:#92400e' }}">{{ $p->estado === 'RECHAZADO' ? 'NO ACEPTADO' : $p->estado }}</span></div>
    </div>
    <table>
        <thead><tr><th style="width:28px">#</th><th style="width:60px">Pieza</th><th>Procedimiento</th><th class="r">Cant.</th><th class="r">P. unit.</th><th class="r">Importe</th></tr></thead>
        <tbody>
            @foreach ($items as $i => $it)
                <tr><td>{{ $i + 1 }}</td><td>{{ $it['pieza'] ?: '—' }}</td><td>{{ $it['descripcion'] }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($it['cantidad'], 2), '0'), '.') }}</td><td class="r">{{ number_format($it['precio'], 2) }}</td>
                    <td class="r">{{ number_format($it['cantidad'] * $it['precio'], 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="totales">
        <div><span>Subtotal</span><span>S/ {{ number_format($p->subtotal, 2) }}</span></div>
        @if ((float) $p->descuento > 0)<div><span>Descuento</span><span>- S/ {{ number_format($p->descuento, 2) }}</span></div>@endif
        <div class="total"><span>TOTAL</span><span>S/ {{ number_format($p->total, 2) }}</span></div>
    </div>
    @if ($p->observaciones)<div class="nota"><b>Observaciones:</b> {{ $p->observaciones }}</div>@endif
    <p style="margin-top:16px">Declaro que se me explicó el diagnóstico, el plan de tratamiento propuesto, sus alternativas y su costo, y que <b>acepto</b> este presupuesto.
        Los precios pueden variar si durante el tratamiento se encuentran hallazgos que requieran procedimientos adicionales, lo que se me informará antes.</p>
    <div class="firmas">
        <div>
            @if ($firma)<img src="{{ $firma }}" alt="Firma" class="firma-img">@else<div style="height:60px"></div>@endif
            <div class="linea">Firma del paciente<br>DNI {{ $h->clinum }}
                @if ($p->firmado)<br><small>Firmado digitalmente el {{ \Carbon\Carbon::parse($p->firmado)->format('d/m/Y H:i') }}</small>@endif</div>
        </div>
        <div><div style="height:60px"></div><div class="linea">Firma y sello del profesional<br>{{ $doctor }}</div></div>
    </div>
</div>
@include('empresas.clinica._firma_js')
</body>
</html>
