<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $codigo }} · Requerimiento</title>
    @php $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0'; @endphp
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; margin: 0; padding: 20px; background: #fff; font-size: 13px; }
        .hoja { max-width: 760px; margin: 0 auto; }
        .cab { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #312e81; padding-bottom: 10px; }
        .cab h1 { margin: 0; font-size: 17px; } .cab p { margin: 2px 0; }
        .doc { text-align: right; border: 2px solid #312e81; border-radius: 8px; padding: 6px 12px; }
        .doc b { display: block; font-size: 16px; color: #312e81; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #eef2ff; text-align: left; padding: 6px 8px; font-size: 11px; text-transform: uppercase; }
        td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .cat td { background: #f9fafb; font-weight: bold; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        .der { text-align: right; white-space: nowrap; }
        .caja { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #374151; border-radius: 3px; }
        .linea { display: inline-block; min-width: 70px; border-bottom: 1px solid #9ca3af; }
        .firmas { display: flex; justify-content: space-around; margin-top: 60px; text-align: center; }
        .firmas div { border-top: 1px solid #6b7280; width: 200px; padding-top: 4px; font-size: 11px; }
        .acciones { text-align: center; margin-bottom: 14px; }
        .acciones button { background: #4f46e5; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font-weight: bold; cursor: pointer; }
        @media print { .acciones { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="hoja">
    <div class="acciones"><button onclick="window.print()">🖨 Imprimir o guardar como PDF</button></div>
    <div class="cab">
        <div>
            @include('empresas.partials.logo_reporte', ['sucursal' => $r->id_empresa_negocio])
            <h1>{{ $empresa->NomEmpresa ?? '' }}</h1>
            <p>{{ $sucursal->nombre_comercial ?? '' }} · {{ $sucursal->direccion ?? '' }}</p>
            <p>Almacén: {{ $almacen }}</p>
        </div>
        <div class="doc">REQUERIMIENTO<b>{{ $codigo }}</b>Para el {{ \Carbon\Carbon::parse($r->fecha_para)->format('d/m/Y') }}</div>
    </div>
    @if ($r->observaciones)<p><b>Observaciones:</b> {{ $r->observaciones }}</p>@endif
    <table>
        <thead><tr><th style="width:24px"></th><th>Producto / insumo</th><th class="der">Pedido</th><th class="der">Había</th><th class="der">Compré</th><th class="der">Precio</th></tr></thead>
        <tbody>
            @foreach ($lineas->groupBy(fn ($l) => $l->cat_nom ?? 'Sin categoría') as $categoria => $grupo)
                <tr class="cat"><td colspan="6">{{ $categoria }}</td></tr>
                @foreach ($grupo as $l)
                    <tr>
                        <td><span class="caja"></span></td>
                        <td>{{ $l->pronom }}@if ($l->nota)<br><small>{{ $l->nota }}</small>@endif</td>
                        <td class="der"><b>{{ $num($l->cantidad) }} {{ \App\Support\Recetas::nombreUnidad($l->umecod ?: 'NIU') }}</b></td>
                        <td class="der">{{ $num($l->stock) }}</td>
                        <td class="der"><span class="linea">{{ $l->comprado !== null ? $num($l->comprado) : '' }}</span></td>
                        <td class="der"><span class="linea">{{ $l->precio !== null ? number_format((float) $l->precio, 2) : '' }}</span></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    <div class="firmas"><div>Pidió<br>{{ $pidio }}</div><div>Compró<br>{{ $atendio ?: '' }}</div></div>
</div>
</body>
</html>
