<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        .hoja { page-break-after: always; }
        .hoja:last-child { page-break-after: auto; }
        .cab { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .cab b { font-size: 15px; }
        .estado { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 12px; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 4px; vertical-align: top; }
        .etq { color: #666; font-size: 9px; text-transform: uppercase; }
        .val { font-size: 13px; font-weight: bold; }
        .grande { font-size: 22px; font-weight: bold; }
        .codigo { font-family: 'DejaVu Sans Mono', monospace; font-size: 15px; letter-spacing: 2px; font-weight: bold; }
        .pie { margin-top: 14px; padding-top: 6px; border-top: 1px dashed #999; font-size: 9px; color: #555; text-align: center; }
    </style>
</head>
<body>
@foreach ($pasajes as $p)
    <div class="hoja">
        <div class="cab">
            <b>{{ $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? '') }}</b><br>
            @if ($empresa)RUC {{ $empresa->IdEmpresa }} · @endif{{ $negocio->direccion ?? '' }}
        </div>
        <table>
            <tr>
                <td style="width:62%">
                    <span class="estado" style="background: {{ $p->pagado ? '#059669' : '#d97706' }}">{{ $p->pagado ? 'PAGADO' : 'RESERVA · PENDIENTE DE PAGO' }}</span>
                    <p class="grande" style="margin:10px 0 4px">{{ $p->tramo }}</p>
                    <p class="val" style="margin:0">{{ \Carbon\Carbon::parse($p->salida)->format('d/m/Y') }} · {{ \Carbon\Carbon::parse($p->salida)->format('H:i') }}</p>
                </td>
                <td style="text-align:right">
                    <img src="{{ $p->qr }}" style="width:130px; height:130px">
                    <div class="codigo">{{ $p->codigo }}</div>
                </td>
            </tr>
        </table>
        <table style="margin-top:8px; border-top:1px solid #ddd">
            <tr>
                <td><div class="etq">Asiento</div><div class="grande">{{ $p->asiento }}</div><div>{{ $p->posicion === 'V' ? 'Ventana' : 'Pasillo' }} · piso {{ $p->piso }}</div></td>
                <td><div class="etq">Servicio</div><div class="val">{{ \App\Support\Transportes::CATEGORIAS[$p->categoria] ?? $p->categoria }}</div></td>
                <td><div class="etq">Unidad</div><div class="val">{{ $p->unidad }}</div><div>{{ $p->placa }}</div></td>
                <td style="text-align:right"><div class="etq">Precio</div><div class="val">S/ {{ number_format((float) $p->precio, 2) }}</div></td>
            </tr>
            <tr>
                <td colspan="2"><div class="etq">Pasajero</div><div class="val">{{ $p->nombre }}</div></td>
                <td><div class="etq">Documento</div><div class="val">{{ $p->doc }}</div></td>
                <td style="text-align:right"><div class="etq">Comprobante</div><div class="val">{{ $p->pagado && $p->serdoc ? $p->serdoc.'-'.str_pad($p->numdoc, 8, '0', STR_PAD_LEFT) : '—' }}</div></td>
            </tr>
        </table>
        <div class="pie">
            {{ $p->pagado ? 'Presenta este código QR y tu documento al embarcar.' : 'Tu asiento está reservado hasta '.\Carbon\Carbon::parse($p->reservado_hasta)->format('d/m H:i').'. La agencia te llamará para confirmar tu pago.' }}
        </div>
    </div>
@endforeach
</body>
</html>
