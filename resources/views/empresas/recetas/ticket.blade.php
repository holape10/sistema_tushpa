{{--
    Recetas en ticket de 80 mm, igual a lo que sale en la ticketera. Con $vistaPrevia se ve como un papel dentro de la pantalla
    (sin imprimir); si no, se imprime desde el navegador (cuando no hay ticketera configurada).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $fichas->count() === 1 ? 'Receta '.$fichas->first()->pronom : 'Recetas' }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font: 12px/1.35 'Courier New', monospace; color: #000; }
        .papel { width: 72mm; margin: 0 auto; padding: 4mm 3mm; box-sizing: content-box; background: #fff; }
        .ficha + .ficha { page-break-before: always; }
        .vista { background: #e5e7eb; padding: 16px 0; }
        .vista .papel { box-shadow: 0 6px 20px rgba(0, 0, 0, .18); }
        .vista .ficha + .ficha { margin-top: 10px; padding-top: 10px; border-top: 2px dashed #9ca3af; }
        .vista .ficha + .ficha::before { content: '✂ corte'; display: block; text-align: center; font-size: 10px; color: #9ca3af; margin: -18px 0 8px; background: #fff; }
        .c { text-align: center; }
        h1 { font-size: 16px; margin: 2px 0; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        hr.doble { border-top: 3px double #000; }
        .fila { display: flex; justify-content: space-between; gap: 6px; }
        .ing { margin-top: 6px; font-weight: bold; }
        .det { padding-left: 2ch; }
        .grande { font-size: 14px; font-weight: bold; }
    </style>
</head>
<body class="{{ $vistaPrevia ? 'vista' : '' }}" @unless ($vistaPrevia) onload="window.print()" @endunless>
    <div class="papel">
        @foreach ($fichas as $f)
            @php $lineas = \App\Support\FichaReceta::lineas($f); $r = \App\Support\FichaReceta::resumen($f); @endphp
            <div class="ficha">
                <div class="c"><b>RECETA</b><h1>{{ mb_strtoupper($f->pronom) }}</h1>{{ $f->cat_nom ?: 'SIN CATEGORÍA' }}</div>
                <hr class="doble">
                <b>INGREDIENTES ({{ count($lineas) }})</b>
                <hr>
                @foreach ($lineas as $n => $l)
                    <div class="ing">{{ $n + 1 }}. {{ $l['nombre'] }}</div>
                    <div class="fila det"><span>Lleva: {{ $l['lleva'] }}</span><span>S/ {{ number_format($l['costo'], 2) }}</span></div>
                    <div class="det">{{ $l['compra'] ? 'Se compra a '.$l['compra'] : 'SIN PRECIO DE COMPRA' }}</div>
                    @if ($l['equivale'])<div class="det">({{ $l['equivale'] }})</div>@endif
                @endforeach
                <hr>
                <div class="fila"><span>Te cuesta prepararlo</span><span>{{ $r['cuesta'] }}</span></div>
                <div class="fila"><span>Lo vendes a</span><span>{{ $r['vende'] }}</span></div>
                <div class="fila grande"><span>GANAS POR CADA UNO</span><span>{{ $r['ganas'] }}</span></div>
                <hr>
                <div>Food cost: {{ $r['food_cost'] }}</div>
                @if ($r['explica'])<div>{{ $r['explica'] }}</div>@endif
            </div>
        @endforeach
    </div>
</body>
</html>
