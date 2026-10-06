<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carnet socio {{ $socio->codigo }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; background: #e5e7eb; color: #111827; }
        .barra { padding: 12px; text-align: center; }
        .barra button { padding: 10px 22px; border: 0; border-radius: 10px; background: #059669; color: #fff; font-weight: bold; font-size: 15px; cursor: pointer; }
        .hoja { display: flex; flex-wrap: wrap; gap: 6mm; justify-content: center; padding: 6mm; }
        /* Tamaño tarjeta de crédito (CR80): 85.6 x 54 mm */
        .carnet { width: 85.6mm; height: 54mm; box-sizing: border-box; border-radius: 3mm; background: #fff; overflow: hidden;
                  border: 0.3mm solid #d1d5db; display: flex; flex-direction: column; break-inside: avoid; }
        .cab { background: #065f46; color: #fff; padding: 2mm 3mm; display: flex; align-items: center; gap: 2mm; }
        .cab img { height: 7mm; max-width: 16mm; object-fit: contain; background: #fff; border-radius: 1mm; padding: 0.5mm; }
        .cab b { font-size: 3mm; line-height: 1.1; }
        .cuerpo { flex: 1; display: flex; padding: 2mm 3mm; gap: 2mm; }
        .datos { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 1mm; min-width: 0; }
        .tipo { font-size: 2.4mm; font-weight: bold; color: #059669; letter-spacing: 0.2mm; }
        .nombre { font-size: 3.3mm; font-weight: bold; line-height: 1.15; }
        .linea { font-size: 2.5mm; color: #374151; }
        .qr { width: 26mm; height: 26mm; align-self: center; }
        .qr svg { width: 100%; height: 100%; }
        .pie { background: #ecfdf5; font-size: 2.2mm; color: #065f46; text-align: center; padding: 1mm; }
        @media print { body { background: #fff; } .barra { display: none; } .hoja { padding: 0; } }
    </style>
</head>
<body>
    <div class="barra"><button onclick="window.print()">Imprimir carnets</button></div>
    @php $logo = $negocio->logo_suc ?: $negocio->LogEmpresa; @endphp
    <div class="hoja">
        @foreach ($carnets as $c)
            <div class="carnet">
                <div class="cab">
                    @if ($logo)<img src="{{ asset($logo) }}" alt="">@endif
                    <b>{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</b>
                </div>
                <div class="cuerpo">
                    <div class="datos">
                        <span class="tipo">{{ $c['tipo'] === 'TITULAR' ? 'SOCIO TITULAR' : 'FAMILIAR · ' . $c['tipo'] }}</span>
                        <span class="nombre">{{ $c['nombre'] }}</span>
                        @if ($c['dni'])<span class="linea">DNI: {{ $c['dni'] }}</span>@endif
                        <span class="linea">Socio N° <b>{{ $socio->codigo }}</b>{{ $socio->categoria ? ' · ' . $socio->categoria : '' }}</span>
                        @if ($c['tipo'] !== 'TITULAR')<span class="linea">Titular: {{ $socio->clinom }}</span>@endif
                    </div>
                    <div class="qr">{!! $c['qr'] !!}</div>
                </div>
                <div class="pie">Presente este carnet en portería · escanee el QR para verificar</div>
            </div>
        @endforeach
    </div>
</body>
</html>
