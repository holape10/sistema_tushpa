{{-- Recetas en PDF (A4): cada plato con sus ingredientes, cantidades y costos --}}
@php $soles = fn ($n) => 'S/ '.number_format((float) $n, 2); @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; }
        .cabecera { border-bottom: 2px solid #4338ca; padding-bottom: 6px; margin-bottom: 14px; }
        .cabecera h1 { margin: 0; font-size: 16px; color: #312e81; }
        .cabecera p { margin: 2px 0 0; color: #64748b; font-size: 9px; }
        .ficha { page-break-inside: avoid; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 14px; }
        .titulo { background: #eef2ff; padding: 7px 10px; }
        .titulo b { font-size: 12px; color: #1e1b4b; }
        .titulo span { color: #64748b; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8.5px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; padding: 5px 10px; }
        td { padding: 4px 10px; border-bottom: 1px solid #f1f5f9; }
        .d { text-align: right; white-space: nowrap; }
        .totales td { border: 0; padding: 6px 10px; background: #f8fafc; font-size: 9.5px; }
        .totales b { font-size: 11px; }
    </style>
</head>
<body>
    <div class="cabecera">
        <h1>{{ $fichas->count() === 1 ? 'Receta' : 'Recetas' }} · {{ $negocio }}</h1>
        <p>{{ $fichas->count() }} {{ $fichas->count() === 1 ? 'plato' : 'platos' }} · costos al {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @foreach ($fichas as $f)
        <div class="ficha">
            <div class="titulo"><b>{{ $f->pronom }}</b> <span>· {{ $f->cat_nom ?: 'SIN CATEGORÍA' }}</span></div>
            <table>
                <thead><tr><th>Ingrediente</th><th class="d">Cantidad</th><th class="d">Costo unitario</th><th class="d">Costo</th></tr></thead>
                <tbody>
                    @foreach ($f->lineas as $l)
                        <tr>
                            <td>{{ $l->nombre }}</td>
                            <td class="d">{{ \App\Http\Controllers\RecetaExportarController::cantidad($l->cantidad) }} {{ \App\Support\Recetas::nombreUnidad($l->umecod) }}</td>
                            <td class="d">{{ $soles($l->costo_unitario) }} / {{ \App\Support\Recetas::nombreUnidad($l->ume_insumo) }}</td>
                            <td class="d">{{ $soles($l->costo) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <table class="totales">
                <tr>
                    <td>Costo: <b>{{ $soles($f->costo) }}</b></td>
                    <td>Precio: <b>{{ $soles($f->precio) }}</b></td>
                    <td>Ganancia: <b style="color:#047857">{{ $soles($f->ganancia) }}</b></td>
                    <td class="d">Food cost: <b style="color: {{ $f->estado['color'] }}">{{ $f->food_cost !== null ? $f->food_cost.'%' : '—' }}</b> · {{ $f->estado['texto'] }}</td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
