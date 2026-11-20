{{-- Etiquetas en PDF: una por hoja del tamaño de la etiqueta ($w x $h mm). Variables: $etiquetas, $imagenes, $w, $h, $conNombre, $conPrecio --}}
@php
    // Todo tiene que entrar en el alto de la etiqueta (si no, Dompdf pasa a otra hoja): el código de barras ocupa lo que sobra
    $chico = $h <= 20;
    $lineasNombre = $chico ? 1 : 2;
    $altoNombre = $conNombre ? $lineasNombre * ($chico ? 2.8 : 3.3) : 0;
    $altoPrecio = $conPrecio ? ($chico ? 3.4 : 4.2) : 0;
    $altoBarras = max(4, $h - 3 - 1.2 - $altoNombre - 2.8 - $altoPrecio - 0.8);
    // DejaVu en negrita: ~2 mm por letra a 7 pt; se deja margen porque las palabras no se cortan
    $letrasPorLinea = max(8, (int) floor(($w - 3) / ($chico ? 1.75 : 2.0) * 0.85));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; }
        .etiqueta { width: {{ $w - 3 }}mm; height: {{ $h - 3 }}mm; padding: 1.2mm 1.5mm 0; text-align: center; overflow: hidden; }
        .etiqueta + .etiqueta { page-break-before: always; }
        .nombre { font-size: {{ $chico ? 6 : 7 }}pt; font-weight: bold; line-height: 1.1; height: {{ $altoNombre }}mm; overflow: hidden; }
        .barras { display: block; margin: 0.4mm auto 0; width: {{ $w - 5 }}mm; height: {{ round($altoBarras, 1) }}mm; }
        .codigo { font-size: 6.5pt; letter-spacing: 0.4pt; line-height: 1.1; }
        .precio { font-size: {{ $chico ? 8 : 10 }}pt; font-weight: bold; line-height: 1.1; }
    </style>
</head>
<body>
@foreach ($etiquetas as $e)
    <div class="etiqueta">
        @if ($conNombre)<div class="nombre">{{ \Illuminate\Support\Str::limit($e['nombre'], $letrasPorLinea * $lineasNombre - 1, '…') }}</div>@endif
        <img class="barras" src="{{ $imagenes[$e['codigo']] }}" alt="">
        <div class="codigo">{{ $e['codigo'] }}</div>
        @if ($conPrecio)<div class="precio">S/ {{ number_format($e['precio'], 2) }}</div>@endif
    </div>
@endforeach
</body>
</html>
