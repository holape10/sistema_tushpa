@php
    $n = fn ($v) => number_format((float) $v, 2);
    $cant = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $modo === 'categorias' ? 'Categorías y productos' : 'Productos vendidos' }} · Turno {{ $turno->turno }}</title>
    <style>
        * { font-family: "Lucida Console", "Lucida Sans Typewriter", Monaco, monospace; }
        body { font-size: 12px; color: #111; background: #f3f4f6; margin: 0; }
        .hoja { width: 8cm; margin: 16px auto; background: #fff; padding: 10px 12px; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        hr { border: none; border-top: 1px dashed #555; margin: 6px 0; }
        .titulo { text-align: center; font-weight: bold; text-decoration: underline; margin: 6px 0; }
        .cat { font-weight: bold; margin-top: 4px; }
        .btns { text-align: center; margin: 12px 0; font-family: Arial, sans-serif; }
        .btns a, .btns button { display: inline-block; padding: 8px 14px; border-radius: 8px; border: none; background: #4f46e5; color: #fff; text-decoration: none; font-size: 13px; cursor: pointer; margin: 2px; font-family: Arial, sans-serif; }
        .btns a { background: #6b7280; }
        @media print { body { background: #fff; } .hoja { margin: 0; padding: 0; } .btns { display: none; } @page { margin: 2mm; } }
    </style>
</head>
<body>
<div class="btns">
    <button onclick="window.print()">Imprimir</button>
    <a href="{{ route('turnos.show', $turno->id_turno) }}">Reporte de caja</a>
    <a href="{{ route($modo === 'categorias' ? 'turnos.productos' : 'turnos.categorias', $turno->id_turno) }}">{{ $modo === 'categorias' ? 'Productos' : 'Categorías' }}</a>
    <a href="{{ route('turnos.listado') }}">Listado de turnos</a>
    @if (auth()->user()->esAdmin())
        <a href="{{ route('descuentos.reporte', ['desde' => $turno->apertura?->toDateString(), 'hasta' => ($turno->cierre ?? now())->toDateString(), 'cajero' => $turno->IdUsuario]) }}">Descuentos y cortesías</a>
    @endif
</div>
<div class="hoja">
    <p class="c" style="margin:0">
        <span class="b" style="font-size:14px">{{ $empresa->NomEmpresa }}</span><br>
        @if ($negocio->nombre_comercial && $negocio->nombre_comercial !== $empresa->NomEmpresa){{ $negocio->nombre_comercial }}<br>@endif
        {{ $negocio->direccion ?: $empresa->DirEmpresa }}<br>
        R.U.C: {{ $empresa->IdEmpresa }}
    </p>
    <hr>
    <div class="b">CAJERO: {{ $turno->usuario->apeusu ?? '' }}</div>
    <div class="b">TURNO N° {{ $turno->turno }}: {{ $turno->apertura?->format('d-m-Y H:i') }} - {{ $turno->cierre?->format('d-m-Y H:i') ?? 'ABIERTO' }}</div>

    @if ($modo === 'productos')
        <div class="titulo">PRODUCTOS VENDIDOS</div>
        <table>
            <tr class="b"><td>PRODUCTO</td><td class="r">CANT.</td><td class="r">PRECIO</td><td class="r">TOTAL</td></tr>
            @forelse ($productos as $p)
                <tr><td colspan="4">{{ $p->nombre }}</td></tr>
                <tr><td></td><td class="r">{{ $cant($p->cantidad) }}</td><td class="r">{{ $n($p->precio) }}</td><td class="r">{{ $n($p->total) }}</td></tr>
            @empty
                <tr><td colspan="4" class="c">Sin ventas en este turno.</td></tr>
            @endforelse
        </table>
        <hr>
        <table><tr class="b"><td>TOTAL VENTA PRODUCTOS:</td><td class="r">{{ $n($total) }}</td></tr></table>
    @else
        <div class="titulo">REPORTE POR CATEGORÍA Y PRODUCTOS</div>
        @forelse ($categorias as $nombre => $cat)
            <hr>
            <div class="cat">CATEGORÍA: {{ $nombre }}</div>
            <table>
                <tr class="b"><td>PRODUCTO</td><td class="r">CANT.</td><td class="r">P.UNIT.</td><td class="r">TOTAL</td></tr>
                @foreach ($cat['productos'] as $p)
                    <tr><td colspan="4">{{ $p->nombre }}</td></tr>
                    <tr><td></td><td class="r">{{ $cant($p->cantidad) }}</td><td class="r">{{ $n($p->precio) }}</td><td class="r">{{ $n($p->total) }}</td></tr>
                @endforeach
                <tr class="b"><td>Subtotal</td><td class="r">{{ $cant($cat['cantidad']) }}</td><td></td><td class="r">{{ $n($cat['total']) }}</td></tr>
            </table>
        @empty
            <p class="c">Sin ventas en este turno.</p>
        @endforelse
        <hr>
        <table><tr class="b"><td>GRAN TOTAL GENERAL:</td><td class="r">{{ $n($total) }}</td></tr></table>
    @endif

    {{-- Lo que se regaló: no suma en la venta, pero salió del almacén --}}
    @if ($cortesias->isNotEmpty())
        <div class="titulo">CORTESÍAS (NO COBRADAS)</div>
        <table>
            <tr class="b"><td>PRODUCTO</td><td class="r">CANT.</td><td class="r">VALOR</td></tr>
            @foreach ($cortesias as $c)
                <tr><td>{{ $c->nombre }}</td><td class="r">{{ $cant($c->cantidad) }}</td><td class="r">{{ $n($c->valor) }}</td></tr>
            @endforeach
        </table>
        <hr>
        <table><tr class="b"><td>TOTAL REGALADO:</td><td class="r">{{ $n($cortesias->sum('valor')) }}</td></tr></table>
    @endif
    <p class="c" style="margin-top:8px">Impreso: {{ now()->format('d-m-Y H:i') }}</p>
</div>
@if (request('imprimir'))
    <script>window.print();</script>
@endif
</body>
</html>
