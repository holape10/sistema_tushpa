<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de pagos N° {{ str_pad((string) $prestamo->numero, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11.5px; color: #111; background: #e5e7eb; margin: 0; }
        .hoja { width: 190mm; max-width: 100%; margin: 12px auto; background: #fff; padding: 9mm; border-radius: 6px; }
        .cab { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #047857; padding-bottom: 6px; }
        .cab img { max-height: 46px; max-width: 110px; object-fit: contain; }
        h1 { margin: 0; font-size: 16px; color: #047857; }
        .datos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin: 10px 0; }
        .datos div { border: 1px solid #d1fae5; background: #ecfdf5; border-radius: 6px; padding: 5px 7px; }
        .datos b { display: block; font-size: 14px; }
        .datos span { font-size: 9.5px; color: #065f46; text-transform: uppercase; }
        .grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; }
        .c { border: 1.5px solid #9ca3af; border-radius: 6px; padding: 4px; min-height: 58px; position: relative; }
        .c .n { font-size: 9px; color: #6b7280; }
        .c .f { font-weight: bold; font-size: 12px; }
        .c .m { font-size: 10px; }
        .c.ok { border-color: #059669; background: #ecfdf5; }
        .c.ok::after { content: '✔'; position: absolute; right: 5px; bottom: 3px; color: #059669; font-size: 18px; font-weight: bold; }
        .firma { border-top: 1px dotted #9ca3af; margin-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
        .pie { display: flex; justify-content: space-between; margin-top: 28px; text-align: center; font-size: 10px; }
        .pie div { width: 45%; border-top: 1px solid #111; padding-top: 3px; }
        .acciones { text-align: center; margin: 12px; }
        .acciones button { padding: 10px 22px; border: 0; border-radius: 8px; background: #047857; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; } .hoja { margin: 0; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
@php $soles = fn ($n) => 'S/ '.number_format((float) $n, 2); @endphp
<div class="acciones"><button onclick="window.print()">Imprimir</button></div>
<div class="hoja">
    <div class="cab">
        <div><h1>TARJETA DE PAGOS · N° {{ str_pad((string) $prestamo->numero, 5, '0', STR_PAD_LEFT) }}</h1>
            <b>{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</b>{{ $negocio->telefono ? ' · Tel. '.$negocio->telefono : '' }}</div>
        @if ($negocio->logo)<img src="{{ asset($negocio->logo) }}" alt="">@endif
    </div>
    <p style="margin:8px 0 0"><b>Cliente:</b> {{ $cliente->clinom }} · <b>DNI:</b> {{ $cliente->clinum }}{{ $cliente->telefono ? ' · Cel. '.$cliente->telefono : '' }}<br>
        <b>Dirección:</b> {{ collect([$cliente->direccion, $cliente->referencia])->filter()->implode(' · ') ?: '—' }}</p>
    <div class="datos">
        <div><span>Préstamo</span><b>{{ $soles($prestamo->monto) }}</b></div>
        <div><span>Total a pagar</span><b>{{ $soles($prestamo->total) }}</b></div>
        <div><span>{{ $prestamo->cuotas }} cuotas {{ ['DIARIA' => 'diarias', 'SEMANAL' => 'semanales', 'QUINCENAL' => 'quincenales', 'MENSUAL' => 'mensuales'][$prestamo->frecuencia] }} de</span><b>{{ $soles($prestamo->cuota) }}</b></div>
        <div><span>Del · al</span><b style="font-size:12px">{{ \Carbon\Carbon::parse($prestamo->primera_cuota)->format('d/m/Y') }} · {{ \Carbon\Carbon::parse($prestamo->ultima_cuota)->format('d/m/Y') }}</b></div>
    </div>
    <div class="grid">
        @foreach ($situacion['cuotas'] as $c)
            <div class="c {{ $c['estado'] === 'PAGADA' ? 'ok' : '' }}">
                <div class="n">Cuota {{ $c['numero'] }} · {{ \App\Support\Prestamos::DIAS[\Carbon\Carbon::parse($c['fecha'])->dayOfWeekIso] }}</div>
                <div class="f">{{ \Carbon\Carbon::parse($c['fecha'])->format('d/m/Y') }}</div>
                <div class="m">{{ $soles($c['monto']) }}</div>
                <div class="firma">firma</div>
            </div>
        @endforeach
    </div>
    <p style="margin-top:10px;font-size:10px;color:#4b5563">Se cobra: {{ collect(explode(',', $prestamo->dias_cobro))->map(fn ($d) => \App\Support\Prestamos::DIAS[(int) $d])->implode(', ') }}.
        Si no puede pagar algún día, avise a su cobrador. Conserve esta tarjeta.</p>
    <div class="pie"><div>Firma del cliente<br>DNI {{ $cliente->clinum }}</div><div>{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</div></div>
</div>
</body>
</html>
