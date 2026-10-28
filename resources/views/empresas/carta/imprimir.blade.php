<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR de la carta · {{ $nombre }}</title>
    @php
        $ticket = $formato !== 'a4';
        $ancho = (int) $formato; // 80 o 58 mm
    @endphp
    <style>
        @if ($ticket)
            @page { size: {{ $ancho }}mm auto; margin: 0; }
        @else
            @page { size: A4; margin: 10mm; }
        @endif
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #1e293b; background: #e2e8f0; }
        :root { --acento: {{ $color }}; }
        .barra { position: sticky; top: 0; background: #1e293b; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; justify-content: center; font-size: 14px; }
        .barra button { background: var(--acento); color: #fff; border: 0; border-radius: 10px; padding: 9px 18px; font-weight: 800; cursor: pointer; }
        .hojas { padding: 16px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: center; }
        .tarjeta { background: #fff; border-radius: 22px; overflow: hidden; text-align: center; break-inside: avoid; page-break-inside: avoid; display: flex; flex-direction: column; }
        .cabeza { background: linear-gradient(150deg, var(--acento), color-mix(in srgb, var(--acento) 60%, #000)); color: #fff; padding: 16px 14px 34px; }
        .logo { width: 62px; height: 62px; object-fit: contain; background: #fff; border-radius: 16px; padding: 4px; }
        .inicial { width: 62px; height: 62px; border-radius: 16px; background: rgba(255,255,255,.2); display: inline-flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 900; }
        .nombre { font-weight: 900; font-size: 18px; margin-top: 6px; letter-spacing: -.3px; }
        .qr { background: #fff; border-radius: 18px; margin: -24px auto 0; padding: 10px; box-shadow: 0 8px 22px -8px rgba(0,0,0,.35); display: inline-block; position: relative; }
        .qr svg { display: block; }
        .pie { padding: 12px 14px 16px; }
        .mesa { display: inline-block; background: var(--acento); color: #fff; border-radius: 999px; padding: 5px 18px; font-weight: 900; font-size: 20px; margin-bottom: 6px; }
        .llamado { font-weight: 900; font-size: 15px; text-transform: uppercase; letter-spacing: .5px; color: color-mix(in srgb, var(--acento) 85%, #000); }
        .paso { font-size: 12px; color: #64748b; margin-top: 4px; }
        .marca { font-size: 10px; color: #94a3b8; margin-top: 8px; }
        /* Tarjetas de mesa: 4 por hoja A4 */
        .mesas .tarjeta { width: 90mm; height: 132mm; border: 1px dashed #cbd5e1; }
        .mesas .qr svg { width: 52mm; height: 52mm; }
        /* Afiche general: una hoja */
        .general .tarjeta { width: 190mm; min-height: 270mm; }
        .general .cabeza { padding: 34px 20px 70px; }
        .general .logo, .general .inicial { width: 120px; height: 120px; border-radius: 28px; font-size: 56px; }
        .general .nombre { font-size: 40px; }
        .general .qr { margin-top: -50px; padding: 18px; border-radius: 28px; }
        .general .qr svg { width: 120mm; height: 120mm; }
        .general .llamado { font-size: 30px; margin-top: 10px; }
        .general .paso { font-size: 17px; }
        .general .mensaje { font-size: 15px; opacity: .9; margin-top: 8px; white-space: pre-line; }
        @media print { body { background: #fff; } .barra { display: none; } .hojas { padding: 0; gap: 4mm; } .tarjeta { box-shadow: none; } }

        /* Ticketera: blanco y negro (la térmica no imprime colores), al ancho del papel y una tarjeta por ticket (se corta entre cada una) */
        .ticket { flex-direction: column; align-items: center; }
        .ticket .tarjeta, .general.ticket .tarjeta, .mesas.ticket .tarjeta { width: {{ $ancho - 4 }}mm; min-height: 0; height: auto; border: 0; border-radius: 0; color: #000; }
        .ticket .cabeza, .general.ticket .cabeza { background: none; color: #000; padding: 3mm 1mm 2mm; }
        .ticket .logo, .general.ticket .logo { width: {{ $ancho >= 80 ? 22 : 16 }}mm; height: {{ $ancho >= 80 ? 22 : 16 }}mm; border-radius: 0; padding: 0; filter: grayscale(1) contrast(1.4); }
        .ticket .inicial, .general.ticket .inicial { width: 16mm; height: 16mm; border-radius: 4mm; background: #000; color: #fff; font-size: 26px; }
        .ticket .nombre, .general.ticket .nombre { font-size: {{ $ancho >= 80 ? 20 : 15 }}px; margin-top: 2mm; }
        .ticket .mensaje { font-size: 12px; opacity: 1; }
        .ticket .qr, .general.ticket .qr { margin: 0 auto; padding: 0; border-radius: 0; box-shadow: none; }
        .ticket .qr svg, .mesas.ticket .qr svg, .general.ticket .qr svg { width: {{ $ancho - 16 }}mm; height: {{ $ancho - 16 }}mm; }
        .ticket .pie { padding: 2mm 1mm 4mm; }
        .ticket .mesa { background: none; color: #000; border: 3px solid #000; font-size: {{ $ancho >= 80 ? 24 : 18 }}px; padding: 1mm 5mm; }
        .ticket .llamado, .general.ticket .llamado { color: #000; font-size: {{ $ancho >= 80 ? 14 : 11 }}px; margin-top: 0; }
        .ticket .paso, .general.ticket .paso { color: #000; font-size: {{ $ancho >= 80 ? 12 : 10 }}px; }
        .ticket .marca { color: #000; }
        .ticket .tarjeta + .tarjeta { border-top: 1px dashed #000; }
        @media print { .ticket .tarjeta { break-after: page; page-break-after: always; } .ticket .tarjeta:last-child { break-after: auto; page-break-after: auto; } .ticket .tarjeta + .tarjeta { border-top: 0; } }
    </style>
</head>
<body>
    <div class="barra">
        @if ($ticket)
            {{ $tipo === 'mesas' ? $tarjetas->count().' ticket(s) de mesa' : 'QR de la carta' }} · ticketera de {{ $ancho }} mm
        @else
            {{ $tipo === 'mesas' ? $tarjetas->count().' tarjeta(s) de mesa · 4 por hoja A4' : 'Afiche con el QR de la carta · hoja A4' }}
        @endif
        <button type="button" onclick="window.print()">🖨️ Imprimir</button>
        {{-- Cambiar de formato sin volver atrás --}}
        @foreach (['a4' => 'Hoja A4', '80' => 'Ticket 80 mm', '58' => 'Ticket 58 mm'] as $f => $texto)
            @if ($f !== $formato)<a href="{{ request()->fullUrlWithQuery(['formato' => $f]) }}" style="color:#cbd5e1;font-size:13px">{{ $texto }}</a>@endif
        @endforeach
    </div>
    <div class="hojas {{ $tipo }} {{ $ticket ? 'ticket' : '' }}">
        @forelse ($tarjetas as $t)
            <div class="tarjeta">
                <div class="cabeza">
                    @if ($logo)<img src="{{ asset($logo) }}" class="logo" alt="">@else<span class="inicial">{{ mb_substr($nombre, 0, 1) }}</span>@endif
                    <div class="nombre">{{ $nombre }}</div>
                    @if ($tipo === 'general' && $negocio->carta_mensaje)<div class="mensaje">{{ $negocio->carta_mensaje }}</div>@endif
                </div>
                <div><div class="qr">{!! $t['qr'] !!}</div></div>
                <div class="pie">
                    @if ($t['titulo'])<div class="mesa">{{ $t['titulo'] }}</div>@endif
                    <div class="llamado">📱 Escanea y mira nuestra carta</div>
                    <div class="paso">Abre la cámara de tu celular y apunta al código</div>
                    <div class="marca">Carta digital · TUSHPA</div>
                </div>
            </div>
        @empty
            <p>No hay mesas seleccionadas.</p>
        @endforelse
    </div>
</body>
</html>
