{{-- Guía A4 en PDF (Dompdf): mismo diseño que la vista del navegador --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Guía {{ $g->serie }}-{{ str_pad($g->numero, 8, '0', STR_PAD_LEFT) }}</title>
    <style>@page { margin: 12mm 11mm; } body { margin: 0; }</style>
</head>
<body>
    @include('empresas.guias.partials.a4_cuerpo', ['paraPdf' => true])
</body>
</html>
