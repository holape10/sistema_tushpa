{{-- Comprobante A4 en PDF (Dompdf, venta masiva / descarga): mismo diseño que la vista A4 --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tdodes }} {{ $cab->serdoc }}-{{ str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT) }}</title>
    <style>@page { margin: 12mm 11mm; } body { margin: 0; }</style>
</head>
<body>
    @include('empresas.cobros.partials.a4_cuerpo', ['paraPdf' => true])
</body>
</html>
