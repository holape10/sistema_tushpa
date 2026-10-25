{{-- Listado de turnos de caja del rango, con sus totales (A4 horizontal) --}}
@php $n = fn ($v) => $v === null ? '—' : number_format((float) $v, 2); @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1e293b; }
        h1 { font-size: 15px; margin: 0; color: #312e81; }
        .sub { color: #64748b; font-size: 9px; margin: 2px 0 12px; border-bottom: 2px solid #4338ca; padding-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 5px 6px; background: #f8fafc; }
        td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; }
        .d { text-align: right; white-space: nowrap; }
        .rojo { color: #b91c1c; } .verde { color: #047857; }
        tfoot td { font-weight: bold; background: #f8fafc; }
    </style>
</head>
<body>
    <h1>Listado de cajas / turnos · {{ $negocio }}</h1>
    <p class="sub">Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }} · {{ $turnos->count() }} turnos</p>
    <table>
        <thead><tr><th>N°</th><th>Cajero</th><th>Apertura</th><th>Cierre</th><th class="d">Fondo</th><th class="d">Ventas</th><th class="d">Efectivo esperado</th><th class="d">Contado</th><th class="d">Diferencia</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($turnos as $t)
                <tr>
                    <td>{{ $t->turno }}</td><td>{{ $t->cajero }}</td><td>{{ $t->apertura }}</td><td>{{ $t->cierre }}</td>
                    <td class="d">{{ $n($t->fondo) }}</td><td class="d">{{ $n($t->ventas) }}</td><td class="d">{{ $n($t->esperado) }}</td>
                    <td class="d">{{ $t->contado === null ? 'No se contó' : $n($t->contado) }}</td>
                    <td class="d {{ $t->diferencia < 0 ? 'rojo' : ($t->diferencia > 0 ? 'verde' : '') }}">{{ $n($t->diferencia) }}</td><td>{{ $t->estado }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Sin turnos en el rango.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><td colspan="5">TOTAL</td><td class="d">{{ $n($turnos->sum('ventas')) }}</td><td colspan="4"></td></tr></tfoot>
    </table>
</body>
</html>
