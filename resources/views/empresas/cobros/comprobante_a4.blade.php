{{-- Comprobante A4 en el navegador: mismo diseño que el PDF (partials/a4_cuerpo) --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tdodes }} {{ $cab->serdoc }}-{{ str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { size: A4; margin: 12mm 11mm; }
        body { background: #e5e7eb; margin: 0; padding: 20px; }
        .hoja { width: 210mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 12mm 11mm; box-sizing: border-box; box-shadow: 0 4px 20px rgba(0,0,0,.12); }
        .acciones { text-align: center; margin: 18px 0 4px; font-family: Arial, sans-serif; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; color: #fff; background: #007bff; }
        .acciones .gris { background: #6b7280; }
        body.embed { background: #fff; padding: 0; } body.embed .hoja { box-shadow: none; }
        @media (max-width: 800px) { body { padding: 0; } .hoja { padding: 10px; } }
        @media print { body { background: #fff; padding: 0; } .hoja { box-shadow: none; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body class="{{ request('embed') ? 'embed' : '' }}">
<div class="hoja">
    @include('empresas.cobros.partials.a4_cuerpo', ['paraPdf' => false])
</div>
@unless (request('embed'))
    <div class="acciones">
        <button onclick="window.print()">IMPRIMIR</button>
        <a href="{{ route('ventas.masiva.pdf', $cab->IdCpe_cabecera) }}" target="_blank">DESCARGAR PDF</a>
        <a class="gris" href="{{ request()->fullUrlWithQuery(['formato' => 'ticket']) }}">VER EN TICKET</a>
    </div>
@endunless
@if (request('imprimir') == 1)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 600));</script>
@endif
</body>
</html>
