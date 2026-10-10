<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket N° {{ $t->numero }} · {{ $t->placa }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; width: 74mm; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 12px; }
        .centro { text-align: center; }
        .negocio { font-size: 15px; font-weight: 900; }
        .chico { font-size: 10px; }
        .linea { border-top: 1px dashed #000; margin: 8px 0; }
        .numero { font-size: 13px; font-weight: 700; letter-spacing: .1em; }
        .placa { display: inline-block; margin: 6px 0; border: 2px solid #000; border-radius: 6px; padding: 2px 14px 4px; font-family: Consolas, monospace; font-size: 28px; font-weight: 900; letter-spacing: .12em; }
        .placa small { display: block; font-size: 8px; letter-spacing: .5em; font-family: Arial; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        td:last-child { text-align: right; font-weight: 700; }
        .qr svg { width: 38mm; height: 38mm; }
        .boton { display: block; margin: 14px auto; padding: 10px 18px; border: 0; border-radius: 8px; background: #4338ca; color: #fff; font-weight: 700; cursor: pointer; }
        @media print { .boton { display: none; } }
    </style>
</head>
<body>
    <div class="centro">
        <div class="negocio">{{ $negocio->nombre_comercial ?? 'ESTACIONAMIENTO' }}</div>
        @if (!empty($negocio->direccion))<div class="chico">{{ $negocio->direccion }}</div>@endif
        @if (!empty($negocio->telefono))<div class="chico">Tel. {{ $negocio->telefono }}</div>@endif
        <div class="linea"></div>
        <div class="numero">TICKET DE ESTACIONAMIENTO N° {{ str_pad($t->numero, 6, '0', STR_PAD_LEFT) }}</div>
        <div class="placa"><small>PERÚ</small>{{ $t->placa }}</div>
    </div>

    <table>
        <tr><td>Vehículo</td><td>{{ $t->tipo }}{{ $t->color ? ' · '.$t->color : '' }}</td></tr>
        @if ($t->marca)<tr><td>Marca</td><td>{{ $t->marca }}</td></tr>@endif
        <tr><td>Entrada</td><td>{{ \Carbon\Carbon::parse($t->entrada)->format('d/m/Y h:i a') }}</td></tr>
        @if ($t->espacio)<tr><td>Espacio</td><td>{{ $t->espacio }}</td></tr>@endif
        @if ($t->valet)<tr><td>Valet · llavero</td><td>{{ $t->llavero ?: 'SÍ' }}</td></tr>@endif
        @if ($t->abo_id)<tr><td colspan="2" style="text-align:center">ABONADO (PENSIÓN)</td></tr>@endif
    </table>
    @if ($t->observaciones)
        <div class="chico" style="margin-top:4px"><b>Observaciones:</b> {{ $t->observaciones }}</div>
    @endif

    @if ($tarifa)
        <div class="linea"></div>
        <div class="centro chico"><b>Tarifa:</b> {{ $tarifa }}</div>
    @endif

    <div class="linea"></div>
    <div class="centro">
        <div class="qr">{!! $qr !!}</div>
        <div class="chico">{{ $t->valet ? 'Escanea para ver tu tiempo y pedir tu auto' : 'Escanea para ver tu tiempo' }}</div>
        <div class="chico">Código: <b>{{ $t->codigo }}</b></div>
    </div>
    <div class="linea"></div>
    <div class="centro chico">
        Presente este ticket a la salida.<br>
        La pérdida del ticket tiene un recargo.<br>
        No nos responsabilizamos por objetos de valor no declarados.
    </div>

    <button type="button" class="boton" onclick="window.print()">Imprimir</button>
    <script>
        if (new URLSearchParams(location.search).has('imprimir')) {
            window.addEventListener('load', () => { window.print(); });
        }
    </script>
</body>
</html>
