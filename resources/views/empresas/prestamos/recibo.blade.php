<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de pago</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; margin: 0; background: #e5e7eb; }
        .t { width: 74mm; margin: 10px auto; background: #fff; padding: 4mm; }
        .c { text-align: center; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .f { display: flex; justify-content: space-between; }
        .g { font-size: 16px; font-weight: bold; }
        button { display: block; margin: 10px auto; padding: 8px 18px; border: 0; border-radius: 6px; background: #047857; color: #fff; font-weight: bold; }
        @media print { body { background: #fff; } .t { margin: 0; width: auto; } button { display: none; } }
    </style>
</head>
<body onload="window.print()">
<button onclick="window.print()">Imprimir</button>
<div class="t">
    @if ($negocio->logo)<div class="c"><img src="{{ asset($negocio->logo) }}" alt="" style="max-height:40px;max-width:60mm"></div>@endif
    <div class="c"><b>{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</b>@if ($negocio->telefono)<br>Tel. {{ $negocio->telefono }}@endif</div>
    <hr>
    <div class="c"><b>RECIBO DE PAGO</b><br>Préstamo N° {{ str_pad((string) $p->numero, 5, '0', STR_PAD_LEFT) }}</div>
    <hr>
    <div>Cliente: {{ $cli->clinom }}<br>DNI: {{ $cli->clinum }}<br>Fecha: {{ \Carbon\Carbon::parse($g->fecha_hora)->format('d/m/Y H:i') }}</div>
    <hr>
    <div class="f"><span>Cuota(s)</span><span>S/ {{ number_format($g->monto, 2) }}</span></div>
    @if ($g->mora > 0)<div class="f"><span>Mora</span><span>S/ {{ number_format($g->mora, 2) }}</span></div>@endif
    <div class="f g"><span>PAGÓ</span><span>S/ {{ number_format($g->monto + $g->mora, 2) }}</span></div>
    <div class="f"><span>Medio</span><span>{{ $g->medio }}{{ $g->operacion ? ' '.$g->operacion : '' }}</span></div>
    <hr>
    <div class="f"><span>Total del préstamo</span><span>S/ {{ number_format($p->total, 2) }}</span></div>
    <div class="f g"><span>SALDO</span><span>S/ {{ number_format($saldo, 2) }}</span></div>
    @if ($saldo <= 0)<div class="c"><b>*** PRÉSTAMO CANCELADO ***</b></div>@endif
    @if ($g->anulado)<div class="c"><b>*** PAGO ANULADO ***</b></div>@endif
    <hr>
    <div class="c">Cobró: {{ $usuario }}<br>¡Gracias por su puntualidad!</div>
</div>
</body>
</html>
