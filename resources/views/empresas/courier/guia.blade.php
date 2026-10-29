@php
    $kg = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $porCobrar = $e->pago === 'DESTINO' && ! $e->IdCpe_cabecera && (float) $e->total > 0;
    // La copia del remitente va una vez; la etiqueta, una por bulto
    $hojas = collect($copias)->flatMap(fn ($c) => $c === 'paquete' ? $qrs->keys()->map(fn ($b) => ['paquete', $b]) : [['remitente', 1]]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía {{ $e->codigo }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; width: 74mm; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 12px; }
        .copia + .copia { break-before: page; page-break-before: always; }
        .centro { text-align: center; }
        .negocio { font-size: 15px; font-weight: 900; }
        .chico { font-size: 10px; }
        .linea { border-top: 1px dashed #000; margin: 7px 0; }
        .titulo { font-size: 11px; font-weight: 700; letter-spacing: .15em; }
        .codigo { font-family: Consolas, monospace; font-size: 22px; font-weight: 900; letter-spacing: .12em; }
        .ruta { font-size: 18px; font-weight: 900; margin: 4px 0; }
        .destino { font-size: 24px; font-weight: 900; border: 3px solid #000; padding: 4px; margin: 4px 0; }
        .clave { display: inline-block; border: 2px solid #000; border-radius: 6px; padding: 2px 12px; font-family: Consolas, monospace; font-size: 26px; font-weight: 900; letter-spacing: .3em; }
        .cobrar { border: 3px solid #000; font-size: 18px; font-weight: 900; padding: 4px; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td:last-child { text-align: right; font-weight: 700; }
        .qr svg { width: 34mm; height: 34mm; }
        .boton { display: block; margin: 14px auto; padding: 10px 18px; border: 0; border-radius: 8px; background: #ea580c; color: #fff; font-weight: 700; cursor: pointer; }
        @media print { .boton { display: none; } }
    </style>
</head>
<body>
    @foreach ($hojas as [$copia, $bulto])
        <div class="copia">
            <div class="centro">
                <div class="negocio">{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</div>
                <div class="chico">RUC {{ $negocio->IdEmpresa }}{{ $negocio->telefono ? ' · Tel. '.$negocio->telefono : '' }}</div>
                <div class="linea"></div>
                <div class="titulo">{{ $copia === 'remitente' ? 'GUÍA DE ENVÍO · COPIA DEL REMITENTE' : 'ETIQUETA DEL PAQUETE' }}</div>
                <div class="codigo">{{ $e->codigo }}</div>
                @if ($copia === 'paquete')
                    <div class="chico">ORIGEN: {{ $origen->ciudad ?? '' }}</div>
                    <div class="destino">{{ $destino->ciudad ?? '' }}</div>
                    <div class="chico">{{ $destino->nombre ?? '' }}</div>
                    <div class="titulo" style="margin-top:3px">BULTO {{ $bulto }} DE {{ $qrs->count() }}</div>
                @else
                    <div class="ruta">{{ $origen->ciudad ?? '' }} → {{ $destino->ciudad ?? '' }}</div>
                @endif
            </div>
            <div class="linea"></div>
            <table>
                <tr><td>Para</td><td>{{ $e->des_nombre }}</td></tr>
                @if ($e->des_doc)<tr><td>Doc.</td><td>{{ $e->des_doc }}</td></tr>@endif
                @if ($e->des_telefono)<tr><td>Celular</td><td>{{ $e->des_telefono }}</td></tr>@endif
                @if ($e->entrega === 'DOMICILIO')<tr><td colspan="2" style="text-align:left"><b>A DOMICILIO:</b> {{ $e->direccion_entrega }}</td></tr>@endif
                <tr><td>De</td><td>{{ $e->rem_nombre }}</td></tr>
                <tr><td>Piezas · peso</td><td>{{ $e->piezas }} · {{ $kg($e->peso_cobrado) }} kg</td></tr>
                @if ($copia === 'remitente')
                    @foreach ($paquetes as $p)
                        <tr><td class="chico" colspan="2" style="text-align:left">{{ $p->cantidad }} {{ $p->tipo === 'SOBRE' ? 'SOBRE(S)' : 'PAQUETE(S)' }}{{ $p->tipo === 'PAQUETE' ? ' · '.$kg($p->peso).' KG' : '' }}{{ $p->descripcion ? ' · '.$p->descripcion : '' }}</td></tr>
                    @endforeach
                    @if ($e->contenido)<tr><td>Contenido</td><td>{{ $e->contenido }}</td></tr>@endif
                    @if ((float) $e->valor_declarado > 0)<tr><td>Valor declarado</td><td>S/ {{ number_format($e->valor_declarado, 2) }}</td></tr>@endif
                    <tr><td>Fecha</td><td>{{ \Carbon\Carbon::parse($e->creado)->format('d/m/Y h:i a') }}</td></tr>
                    @if ($e->llegada_estimada)<tr><td>Llegada estimada</td><td>{{ \Carbon\Carbon::parse($e->llegada_estimada)->format('d/m/Y') }}</td></tr>@endif
                    <tr><td>Total</td><td>S/ {{ number_format($e->total, 2) }}</td></tr>
                @endif
            </table>
            @if ($e->observacion)<div class="chico" style="margin-top:3px"><b>Obs.:</b> {{ $e->observacion }}</div>@endif

            @if ($porCobrar)
                <div class="centro cobrar">COBRAR AL ENTREGAR S/ {{ number_format($e->total, 2) }}</div>
            @elseif ($copia === 'remitente')
                <div class="centro chico" style="margin-top:4px"><b>{{ $e->pago === 'CREDITO' ? 'A CRÉDITO' : ((float) $e->total > 0 ? 'PAGADO' : 'SIN COSTO') }}</b></div>
            @endif

            @if ($copia === 'remitente')
                <div class="linea"></div>
                <div class="centro">
                    <div class="chico">Clave de recojo (dásela a quien recogerá)</div>
                    <div class="clave">{{ $e->clave }}</div>
                </div>
            @endif

            <div class="linea"></div>
            <div class="centro">
                <div class="qr">{!! $qrs[$bulto] !!}</div>
                <div class="chico">Sigue tu envío: escanea el código o entra a<br><b>{{ route('rastreo') }}</b></div>
            </div>
            @if ($copia === 'remitente')
                <div class="linea"></div>
                <div class="centro chico">
                    Para recoger: DNI del destinatario y la clave.<br>
                    No transportamos dinero, objetos prohibidos ni valores no declarados.
                </div>
            @endif
        </div>
    @endforeach

    <button type="button" class="boton" onclick="window.print()">Imprimir</button>
    <script>
        if (new URLSearchParams(location.search).has('imprimir')) {
            window.addEventListener('load', () => { window.print(); });
        }
    </script>
</body>
</html>
