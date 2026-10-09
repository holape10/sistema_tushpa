<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR de la carta · {{ $nombre }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
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
    </style>
</head>
<body>
    <div class="barra">
        {{ $tipo === 'mesas' ? $tarjetas->count().' tarjeta(s) de mesa · 4 por hoja A4' : 'Afiche con el QR de la carta · hoja A4' }}
        <button type="button" onclick="window.print()">🖨️ Imprimir</button>
    </div>
    <div class="hojas {{ $tipo }}">
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
