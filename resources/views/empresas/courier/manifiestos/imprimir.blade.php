@php
    $kg = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $porCobrar = $envios->filter(fn ($e) => $e->pago === 'DESTINO' && ! $e->IdCpe_cabecera)->sum('total');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifiesto N° {{ $m->numero }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 11px; }
        h1 { margin: 0; font-size: 20px; }
        .cabeza { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111; padding-bottom: 8px; }
        .datos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 14px; margin: 10px 0; }
        .datos b { display: block; font-size: 10px; color: #555; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #eee; font-size: 10px; text-transform: uppercase; }
        .num { text-align: right; }
        .firmas { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 45px; text-align: center; }
        .firmas div { border-top: 1px solid #111; padding-top: 4px; }
        .boton { position: fixed; right: 16px; top: 16px; padding: 10px 18px; border: 0; border-radius: 8px; background: #ea580c; color: #fff; font-weight: 700; cursor: pointer; }
        @media print { .boton { display: none; } }
    </style>
</head>
<body>
    <div class="cabeza">
        <div><h1>MANIFIESTO DE CARGA N° {{ str_pad($m->numero, 6, '0', STR_PAD_LEFT) }}</h1>{{ $empresa->NomEmpresa ?? '' }} · RUC {{ $empresa->IdEmpresa ?? '' }}</div>
        <div style="text-align:right">Impreso {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="datos">
        <div><b>Origen</b>{{ \App\Support\Courier::etiquetaAgencia($origen) }}</div>
        <div><b>Destino</b>{{ $destino ? \App\Support\Courier::etiquetaAgencia($destino) : 'Varias agencias' }}</div>
        <div><b>Transporte</b>{{ $medios[$m->medio][0] }}{{ $m->transportista ? ' · '.$m->transportista : '' }}</div>
        <div><b>Placa / vuelo / nave</b>{{ $m->vehiculo ?: '—' }}</div>
        <div><b>Conductor / encargado</b>{{ $m->conductor ?: '—' }}</div>
        <div><b>Salida</b>{{ $m->salida ? \Carbon\Carbon::parse($m->salida)->format('d/m/Y H:i') : '—' }}</div>
        <div><b>Envíos · bultos</b>{{ $envios->count() }} · {{ $envios->sum('piezas') }}</div>
        <div><b>Peso total</b>{{ $kg($envios->sum('peso_cobrado')) }} kg</div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Guía</th><th>Destino</th><th>Remitente</th><th>Destinatario</th><th>Contenido</th><th class="num">Bultos</th><th class="num">Kg</th><th class="num">Cobrar S/</th><th>Recibido</th></tr></thead>
        <tbody>
            @foreach ($envios as $i => $e)
                <tr>
                    <td>{{ $i + 1 }}</td><td><b>{{ $e->codigo }}</b></td><td>{{ $agencias[$e->destino_id]->ciudad ?? '' }}</td>
                    <td>{{ $e->rem_nombre }}</td><td>{{ $e->des_nombre }}{{ $e->des_telefono ? ' · '.$e->des_telefono : '' }}</td><td>{{ $e->contenido }}</td>
                    <td class="num">{{ $e->piezas }}</td><td class="num">{{ $kg($e->peso_cobrado) }}</td>
                    <td class="num">{{ $e->pago === 'DESTINO' && ! $e->IdCpe_cabecera ? number_format($e->total, 2) : '—' }}</td><td></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><th colspan="6">TOTAL</th><th class="num">{{ $envios->sum('piezas') }}</th><th class="num">{{ $kg($envios->sum('peso_cobrado')) }}</th><th class="num">{{ number_format($porCobrar, 2) }}</th><th></th></tr></tfoot>
    </table>
    <div class="firmas"><div>Despachó (agencia de origen)</div><div>Transportista</div><div>Recibió (agencia de destino)</div></div>
    <button type="button" class="boton" onclick="window.print()">Imprimir</button>
</body>
</html>
