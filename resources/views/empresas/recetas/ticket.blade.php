{{-- Recetas en formato ticket (80 mm) para imprimir desde el navegador cuando no hay ticketera configurada --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $fichas->count() === 1 ? 'Receta '.$fichas->first()->pronom : 'Recetas' }}</title>
    <style>
        @page { margin: 0; }
        body { width: 72mm; margin: 0 auto; font: 12px monospace; color: #000; }
        .ficha { padding: 6px 0 14px; }
        .ficha + .ficha { page-break-before: always; }
        .c { text-align: center; }
        h1 { font-size: 15px; margin: 2px 0; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .fila { display: flex; justify-content: space-between; gap: 6px; }
        .ing { margin-top: 4px; font-weight: bold; }
    </style>
</head>
<body onload="window.print()">
    @foreach ($fichas as $f)
        <div class="ficha">
            <div class="c"><b>RECETA</b><h1>{{ mb_strtoupper($f->pronom) }}</h1>{{ $f->cat_nom ?: 'SIN CATEGORÍA' }}</div>
            <hr>
            @foreach ($f->lineas as $l)
                <div class="ing">{{ $l->nombre }}</div>
                <div class="fila"><span>&nbsp;&nbsp;{{ \App\Http\Controllers\RecetaExportarController::cantidad($l->cantidad) }} {{ \App\Support\Recetas::nombreUnidad($l->umecod) }}</span><span>S/ {{ number_format($l->costo, 2) }}</span></div>
            @endforeach
            <hr>
            <div class="fila"><span>Costo de ingredientes</span><span>S/ {{ number_format($f->costo, 2) }}</span></div>
            <div class="fila"><span>Precio de venta</span><span>S/ {{ number_format($f->precio, 2) }}</span></div>
            <div class="fila"><b>Ganancia</b><b>S/ {{ number_format($f->ganancia, 2) }}</b></div>
            <div class="fila"><span>Food cost</span><span>{{ $f->food_cost !== null ? $f->food_cost.'% · '.$f->estado['texto'] : '—' }}</span></div>
        </div>
    @endforeach
</body>
</html>
