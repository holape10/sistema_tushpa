<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Precuenta {{ $mesa->mes_nom ?? strtoupper($pedido->ped_tip) }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; background: #eee; margin: 0; padding: 15px; }
        .ticket { width: 300px; margin: 0 auto; background: #fff; padding: 12px; }
        .c { text-align: center; } .r { text-align: right; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .fila { display: flex; justify-content: space-between; }
        .acciones { text-align: center; margin-top: 15px; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold;
            cursor: pointer; text-decoration: none; font-size: 13px; background: #3498db; color: #fff; }
        .acciones a { background: #28a745; }
        @media print { body { background: #fff; padding: 0; } .acciones { display: none; } .ticket { width: 100%; } }
    </style>
</head>
<body>
<div class="ticket">
    <div class="c">
        <strong>{{ $negocio->nombre_comercial ?? '' }}</strong><br>
        <hr>
        <strong style="font-size:15px;">PRECUENTA</strong><br>
        <strong>{{ $mesa ? (($piso->pis_nom ?? '') . ' / ' . $mesa->mes_nom) : strtoupper($pedido->ped_tip) }}</strong>
    </div>
    <hr>
    Fecha: {{ now()->format('d/m/Y H:i') }}<br>
    Mozo: {{ $mozo }}<br>
    Pedido N°: {{ $pedido->ped_id }}
    <hr>
    <table>
        <thead><tr><td>DESCRIPCION</td><td class="r">CANT</td><td class="r">P.U</td><td class="r">TOTAL</td></tr></thead>
        <tbody>
            @foreach ($items as $i)
                <tr>
                    <td>{{ $i->descripcion }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($i->cantidad, 2), '0'), '.') }}</td>
                    <td class="r">{{ number_format($i->precio, 2) }}</td>
                    <td class="r">{{ number_format($i->cantidad * $i->precio, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <hr>
    <div class="fila" style="font-size:15px;"><strong>TOTAL</strong><strong>S/ {{ number_format($total, 2) }}</strong></div>
    @if ($pagado > 0)
        <div class="fila"><span>YA PAGADO (cuentas separadas)</span><span>- {{ number_format($pagado, 2) }}</span></div>
        <div class="fila" style="font-size:14px;"><strong>SALDO</strong><strong>S/ {{ number_format($total - $pagado, 2) }}</strong></div>
    @endif
    <hr>
    <div class="c" style="font-size:10px;">
        *** NO VÁLIDO COMO COMPROBANTE DE PAGO ***<br>
        Solicite su boleta o factura al pagar.
    </div>
</div>

<div class="acciones">
    <button onclick="window.print()">IMPRIMIR</button>
    <a href="{{ route('comandas.seleccion') }}">VOLVER A MESAS</a>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
