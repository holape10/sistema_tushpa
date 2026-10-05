@use('App\Http\Controllers\ReporteController', 'R')
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 14mm 10mm 14mm 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1f2937; }
        .cab { border-bottom: 2px solid #312e81; padding-bottom: 6px; margin-bottom: 8px; }
        .cab h1 { font-size: 15px; margin: 0; color: #312e81; }
        .cab p { margin: 2px 0 0; color: #4b5563; }
        .resumen { margin-bottom: 8px; }
        .resumen span { display: inline-block; background: #eef2ff; border-radius: 4px; padding: 4px 8px; margin-right: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #312e81; color: #fff; padding: 5px 4px; text-align: left; font-size: 8px; text-transform: uppercase; }
        td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        .r { text-align: right; }
        tfoot td { font-weight: bold; background: #e0e7ff; border-top: 1.5px solid #312e81; }
        .pie { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5px; color: #9ca3af; text-align: right; }
        .nota { margin-top: 6px; color: #b45309; }
    </style>
</head>
<body>
    <div class="cab">
        <h1>{{ mb_strtoupper($titulo) }}</h1>
        <p><strong>{{ $empresa->NomEmpresa ?? '' }}</strong> · RUC {{ $empresa->IdEmpresa ?? '' }} · {{ $sucursal }}</p>
        <p>Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }} · {{ count($filas) }} registro(s)</p>
    </div>
    @if (!empty($resumen))
        <div class="resumen">@foreach ($resumen as $res)<span>{{ $res[0] }}: <strong>{{ isset($res[2]) ? 'S/ ' . number_format($res[1], 2) : $res[1] }}</strong></span>@endforeach</div>
    @endif
    <table>
        <thead><tr>@foreach ($columnas as [$t, $tipo])<th class="{{ in_array($tipo, ['money', 'num', 'pct']) ? 'r' : '' }}">{{ $t }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($filas as $f)
                <tr>@foreach ($columnas as $k => [$t, $tipo])<td class="{{ in_array($tipo, ['money', 'num', 'pct']) ? 'r' : '' }}">{{ R::texto($f[$k] ?? '', $tipo) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columnas) }}" style="text-align:center; padding:20px; color:#9ca3af">Sin datos en el periodo.</td></tr>
            @endforelse
        </tbody>
        @if ($filas)
            <tfoot><tr>@foreach (array_keys($columnas) as $i => $k)<td class="r">{{ $i === 0 ? 'TOTALES' : (isset($totales[$k]) ? R::texto($totales[$k], $columnas[$k][1]) : '') }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
    @if (!empty($nota))<p class="nota">{{ $nota }}</p>@endif
    <div class="pie">Generado el {{ now()->format('d/m/Y H:i') }} · Sistema Tushpa</div>
</body>
</html>
