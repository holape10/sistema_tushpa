<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tdodes }} {{ $cab->serdoc }}-{{ str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT) }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; background: #eee; margin: 0; padding: 15px; }
        .ticket { width: 300px; margin: 0 auto; background: #fff; padding: 12px; }
        .c { text-align: center; } .r { text-align: right; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        .acciones { text-align: center; margin-top: 15px; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; }
        .b1 { background: #28a745; color: #fff; } .b2 { background: #3498db; color: #fff; }
        @media print { body { background: #fff; padding: 0; } .acciones { display: none; } .ticket { width: 100%; } }
    </style>
</head>
<body>
<div class="ticket">
    <div class="c">
        <strong>{{ $empresa->NomEmpresa ?? '' }}</strong><br>
        RUC: {{ $cab->IdEmpresa }}<br>
        {{ $negocio->direccion ?? '' }}<br>
        @if (!empty($negocio->departamento)) {{ $negocio->distrito }} - {{ $negocio->provincia }} - {{ $negocio->departamento }}<br>@endif
        @if (!empty($negocio->telefono)) Tel: {{ $negocio->telefono }}<br>@endif
        <hr>
        <strong>{{ $tdodes }}</strong><br>
        <strong>{{ $cab->serdoc }}-{{ str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT) }}</strong>
    </div>
    <hr>
    Fecha: {{ \Carbon\Carbon::parse($cab->fecha_hora)->format('d/m/Y H:i') }}<br>
    Cliente: {{ $cab->ccanom }}<br>
    Doc: {{ $cab->ccandi }}<br>
    @if ($cab->direccion && $cab->direccion !== '--') Dir: {{ $cab->direccion }}<br>@endif
    Condición: {{ $cab->estadopago }}
    @if ($cab->estadopago === 'CREDITO')<br>Vence: {{ \Carbon\Carbon::parse($cab->ccafve)->format('d/m/Y') }}@endif
    <hr>
    <table>
        <thead><tr><td>DESCRIPCION</td><td class="r">CANT</td><td class="r">P.U</td><td class="r">TOTAL</td></tr></thead>
        <tbody>
        @if ($cab->consumo)
            <tr><td>POR CONSUMO</td><td class="r">1</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td><td class="r">{{ number_format($cab->ccaitv, 2) }}</td></tr>
        @else
            @foreach ($detalle as $d)
                <tr>
                    <td>{{ $d->cdedes }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($d->cdecan, 2), '0'), '.') }}</td>
                    <td class="r">{{ number_format($d->cdepuni, 2) }}</td>
                    <td class="r">{{ number_format($d->cdevve, 2) }}</td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    <hr>
    @foreach ($medios as $m)
        <div style="display:flex; justify-content:space-between;"><span>{{ $m->nom_med_pag }}</span><span>S/ {{ number_format($m->monto, 2) }}</span></div>
    @endforeach
    @if ($cab->ccatvg > 0)<div style="display:flex; justify-content:space-between;"><span>OP. GRAVADA</span><span>S/ {{ number_format($cab->ccatvg, 2) }}</span></div>@endif
    @if ($cab->ccatexo > 0)<div style="display:flex; justify-content:space-between;"><span>OP. EXONERADA</span><span>S/ {{ number_format($cab->ccatexo, 2) }}</span></div>@endif
    @if ($cab->ccaigv > 0)<div style="display:flex; justify-content:space-between;"><span>IGV</span><span>S/ {{ number_format($cab->ccaigv, 2) }}</span></div>@endif
    <div style="display:flex; justify-content:space-between; font-size:15px;"><strong>TOTAL</strong><strong>S/ {{ number_format($cab->ccaitv, 2) }}</strong></div>
    @if ($cab->paga > 0)<div style="display:flex; justify-content:space-between;"><span>PAGA CON</span><span>S/ {{ number_format($cab->paga, 2) }}</span></div>@endif
    @if ($cab->vuelto > 0)<div style="display:flex; justify-content:space-between;"><span>VUELTO</span><span>S/ {{ number_format($cab->vuelto, 2) }}</span></div>@endif
    <hr>
    <div class="c" style="font-size:10px;">
        REPRESENTACIÓN IMPRESA DE LA {{ $tdodes }}<br>
        BIENES TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONIA
    </div>
</div>

<div class="acciones">
    <button class="b2" onclick="window.print()">IMPRIMIR</button>
    <a class="b1" href="{{ route('comandas.seleccion') }}">VOLVER A MESAS</a>
</div>

@if (request('imprimir') == 1)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
@endif
</body>
</html>