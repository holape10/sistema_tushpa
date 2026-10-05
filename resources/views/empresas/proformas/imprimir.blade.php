@php $numero = \App\Support\Proformas::numero($p); $a4 = $formato === 'A4'; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma {{ $numero }}</title>
    <style>
        body { font-family: {{ $a4 ? 'Arial, Helvetica, sans-serif' : "'Courier New', monospace" }}; font-size: 12px; background: #eee; margin: 0; padding: 15px; color: #111; }
        .hoja { width: {{ $a4 ? '760px' : '300px' }}; max-width: 100%; margin: 0 auto; background: #fff; padding: {{ $a4 ? '28px' : '12px' }}; box-sizing: border-box; }
        .c { text-align: center; } .r { text-align: right; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        .det th { text-align: left; border-bottom: 1px solid #000; padding: 4px 2px; font-size: 11px; }
        .det td { padding: 4px 2px; vertical-align: top; {{ $a4 ? 'border-bottom: 1px solid #eee;' : '' }} }
        .cab { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; }
        .caja { border: 2px solid #111; border-radius: 8px; padding: 10px 18px; text-align: center; font-weight: bold; }
        .total { font-size: {{ $a4 ? '16px' : '14px' }}; font-weight: bold; }
        .nota { font-size: 11px; color: #444; margin-top: 10px; }
        .acciones { text-align: center; margin-top: 15px; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; }
        .b1 { background: #28a745; color: #fff; } .b2 { background: #3498db; color: #fff; }
        @media print { body { background: #fff; padding: 0; } .acciones { display: none; } .hoja { width: 100%; padding: {{ $a4 ? '0' : '0' }}; } }
    </style>
</head>
<body>
<div class="hoja">
    @if ($a4)
        <div class="cab">
            <div>
                @if (!empty($empresa->LogEmpresa))<img src="{{ asset($empresa->LogEmpresa) }}" alt="" style="max-height:70px;max-width:200px;margin-bottom:6px"><br>@endif
                <strong style="font-size:15px">{{ $empresa->NomEmpresa ?? '' }}</strong><br>
                {{ $negocio->nombre_comercial ?? '' }}<br>
                {{ $negocio->direccion ?? '' }}<br>
                @if (!empty($negocio->departamento)) {{ $negocio->distrito }} - {{ $negocio->provincia }} - {{ $negocio->departamento }}<br>@endif
                @if (!empty($negocio->telefono)) Tel: {{ $negocio->telefono }}@endif
            </div>
            <div class="caja">RUC {{ $p->IdEmpresa }}<br><span style="font-size:15px">PROFORMA</span><br>{{ $numero }}</div>
        </div>
        <hr style="border-top:1px solid #ccc;margin:14px 0">
    @else
        <div class="c">
            <strong>{{ $empresa->NomEmpresa ?? '' }}</strong><br>
            RUC: {{ $p->IdEmpresa }}<br>
            {{ $negocio->direccion ?? '' }}<br>
            @if (!empty($negocio->telefono)) Tel: {{ $negocio->telefono }}<br>@endif
            <hr>
            <strong>PROFORMA</strong><br>
            <strong>{{ $numero }}</strong>
        </div>
        <hr>
    @endif

    Fecha: {{ \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') }}<br>
    Cliente: {{ $p->clinom }}<br>
    @if ($p->clinum !== '00000000') {{ $p->tdicod === '6' ? 'RUC' : 'Doc' }}: {{ $p->clinum }}<br>@endif
    @if ($p->clidir) Dir: {{ $p->clidir }}<br>@endif
    @if ($usuario) Atendió: {{ $usuario }}<br>@endif
    @if ($p->observaciones)<strong>{{ $p->observaciones }}</strong><br>@endif
    @if ($p->estado === 'FACTURADA')<strong>COBRADA</strong><br>@endif
    {!! $a4 ? '<br>' : '<hr>' !!}

    <table class="det">
        <thead><tr>
            <th>DESCRIPCIÓN</th>
            @if ($a4)<th>UNID.</th>@endif
            <th class="r">CANT</th><th class="r">P.U</th><th class="r">TOTAL</th>
        </tr></thead>
        <tbody>
        @foreach ($detalle as $d)
            <tr>
                <td>{{ $d->descripcion }}</td>
                @if ($a4)<td>{{ $d->umecod }}</td>@endif
                <td class="r">{{ rtrim(rtrim(number_format($d->cantidad, 2), '0'), '.') }}</td>
                <td class="r">{{ number_format($d->precio, 2) }}</td>
                <td class="r">{{ number_format($d->total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {!! $a4 ? '' : '<hr>' !!}
    <table style="margin-top:8px">
        <tr><td class="r total">TOTAL S/ {{ number_format($p->total, 2) }}</td></tr>
    </table>

    <p class="nota {{ $a4 ? '' : 'c' }}">Documento sin valor tributario.</p>
</div>

<div class="acciones">
    <button class="b1" onclick="window.print()">IMPRIMIR</button>
    <a class="b2" href="{{ request()->fullUrlWithQuery(['formato' => $a4 ? 'TICKET' : 'A4', 'imprimir' => null]) }}">VER EN {{ $a4 ? 'TICKET' : 'A4' }}</a>
</div>

@if (request('imprimir') == 1)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
@endif
</body>
</html>
