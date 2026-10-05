<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo {{ $pago->numero_recibo }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; background: #eee; margin: 0; padding: 15px; }
        .ticket { width: 300px; margin: 0 auto; background: #fff; padding: 12px; position: relative; }
        .c { text-align: center; } .r { text-align: right; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .fila { display: flex; justify-content: space-between; gap: 8px; }
        .anulado { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 34px; font-weight: bold; color: rgba(220,38,38,.35); transform: rotate(-20deg); }
        .acciones { text-align: center; margin-top: 15px; }
        .acciones button { padding: 10px 18px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; background: #3498db; color: #fff; }
        @media print { body { background: #fff; padding: 0; } .acciones { display: none; } .ticket { width: 100%; } }
    </style>
</head>
<body>
<div class="ticket">
    @if ($estado === 'ANULADO')<div class="anulado">ANULADO</div>@endif
    <div class="c">
        <strong>{{ $empresa->NomEmpresa ?? '' }}</strong><br>
        RUC: {{ $empresa->IdEmpresa ?? '' }}<br>
        {{ $negocio->direccion ?? '' }}
        <hr>
        <strong>{{ $tipo === 'cobrar' ? 'RECIBO DE COBRO' : 'CONSTANCIA DE PAGO' }}</strong><br>
        <strong>{{ $pago->numero_recibo }}</strong>
    </div>
    <hr>
    Fecha: {{ \Carbon\Carbon::parse($pago->fec_dep)->format('d/m/Y') }}<br>
    {{ $tx['persona'] }}: {{ $cuenta->persona }}<br>
    Doc.: {{ $cuenta->doc_persona }}<br>
    Por: {{ $tiposDoc[$cuenta->tdocod] ?? '' }} {{ $cuenta->serie }}-{{ $cuenta->numero }}
    ({{ \Carbon\Carbon::parse($cuenta->fecha)->format('d/m/Y') }})
    <hr>
    @foreach ($medios as $m)
        <div class="fila"><span>{{ $m->nom_med_pag }}</span><span>{{ number_format($m->monto, 2) }}</span></div>
    @endforeach
    @if ($pago->num_oper)<div>N° operación: {{ $pago->num_oper }}</div>@endif
    <hr>
    <div class="fila"><span>Total del documento</span><span>{{ number_format($cuenta->total, 2) }}</span></div>
    <div class="fila" style="font-size:14px"><strong>{{ $tipo === 'cobrar' ? 'RECIBIDO' : 'PAGADO' }}</strong><strong>{{ number_format($pago->abono, 2) }}</strong></div>
    <div class="fila"><span>Saldo pendiente</span><span>{{ number_format($pago->saldo_detalle, 2) }}</span></div>
    @if ($pago->comentario)<hr>{{ $pago->comentario }}@endif
    <hr>
    <div class="c" style="font-size:10px">
        Atendido por: {{ $usuario }}<br>
        Registrado: {{ \Carbon\Carbon::parse($pago->fec_reg)->format('d/m/Y H:i') }}
    </div>
    <br><br>
    <div class="c">______________________<br>Firma</div>
</div>
<div class="acciones"><button onclick="window.print()">IMPRIMIR</button></div>
</body>
</html>
