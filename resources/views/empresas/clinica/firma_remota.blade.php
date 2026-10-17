<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Firmar · {{ $negocio->nombre_comercial ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f0fdfa; color: #0f172a; }
        .caja { max-width: 640px; margin: 0 auto; padding: 16px; }
        .cab { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .cab img { height: 44px; max-width: 110px; object-fit: contain; }
        .cab b { font-size: 17px; }
        .tarjeta { background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f766e; }
        .gris { color: #64748b; font-size: 13px; }
        dl { margin: 8px 0 0; font-size: 14px; }
        dt { font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; margin-top: 8px; }
        dd { margin: 2px 0 0; white-space: pre-line; }
        canvas { width: 100%; height: 220px; border: 2px dashed #94a3b8; border-radius: 14px; background: #f8fafc; touch-action: none; display: block; }
        .botones { display: flex; gap: 8px; margin-top: 10px; }
        button { flex: 1; padding: 14px; border: 0; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .borrar { background: #e2e8f0; color: #334155; }
        .listo { background: #0f766e; color: #fff; }
        button:disabled { opacity: .5; }
        .aviso { padding: 14px; border-radius: 12px; font-weight: 700; text-align: center; margin-top: 10px; display: none; }
        .ok { background: #dcfce7; color: #166534; } .mal { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="caja">
    <div class="cab">
        @if (!empty($negocio->logo_suc) && is_file(public_path($negocio->logo_suc)))<img src="{{ asset($negocio->logo_suc) }}" alt="">@endif
        <b>{{ $negocio->nombre_comercial ?? '' }}</b>
    </div>
    <div class="tarjeta">
        <h1>{{ $titulo }}</h1>
        <p class="gris">Paciente: <b>{{ $doc->tipo === 'MASCOTA' ? $doc->mascota.' · '.$doc->clinom : $doc->clinom }}</b> · {{ $doc->his_cli_cod }}</p>
        <dl>
            @foreach ($resumen as $k => $v)
                <dt>{{ $k }}</dt><dd>{{ $v }}</dd>
            @endforeach
        </dl>
        @if ($doc->tipo_doc === 'consentimiento')
            <p class="gris" style="margin-top:10px">Al firmar declaro que me explicaron el tratamiento, sus beneficios, alternativas y riesgos, que pude hacer preguntas
                y que <b>autorizo</b> su realización.</p>
        @elseif ($doc->tipo_doc === 'presupuesto')
            <p class="gris" style="margin-top:10px">Al firmar acepto este presupuesto y el plan de tratamiento propuesto.</p>
        @endif
    </div>

    <div class="tarjeta" id="zona">
        @if ($doc->firma)
            <p class="aviso ok" style="display:block">✔ Este documento ya está firmado. Gracias.</p>
        @else
            <p style="margin:0 0 8px;font-weight:700">Firme con su dedo dentro del recuadro:</p>
            <canvas id="pad"></canvas>
            <div class="botones">
                <button type="button" class="borrar" id="borrar">Borrar</button>
                <button type="button" class="listo" id="enviar">Firmar</button>
            </div>
            <p class="aviso" id="aviso"></p>
        @endif
    </div>
</div>
@unless ($doc->firma)
<script>
    (function () {
        const c = document.getElementById('pad'), ctx = c.getContext('2d');
        let dibujando = false, firmado = false;
        function preparar() {
            const r = window.devicePixelRatio || 1;
            c.width = c.offsetWidth * r; c.height = c.offsetHeight * r;
            ctx.setTransform(r, 0, 0, r, 0, 0); ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
            firmado = false;
        }
        preparar();
        const punto = e => { const b = c.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; };
        c.addEventListener('pointerdown', e => { e.preventDefault(); dibujando = true; c.setPointerCapture(e.pointerId); const [x, y] = punto(e); ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + 0.1, y + 0.1); ctx.stroke(); });
        c.addEventListener('pointermove', e => { if (!dibujando) return; e.preventDefault(); const [x, y] = punto(e); ctx.lineTo(x, y); ctx.stroke(); firmado = true; });
        ['pointerup', 'pointercancel'].forEach(t => c.addEventListener(t, () => dibujando = false));
        document.getElementById('borrar').onclick = preparar;
        const aviso = (t, ok) => { const a = document.getElementById('aviso'); a.textContent = t; a.className = 'aviso ' + (ok ? 'ok' : 'mal'); a.style.display = 'block'; };
        document.getElementById('enviar').onclick = async function () {
            if (!firmado) { aviso('Primero firme dentro del recuadro.', false); return; }
            this.disabled = true;
            try {
                const r = await fetch(@js($accion), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ firma: c.toDataURL('image/png') }) });
                const j = await r.json().catch(() => ({}));
                if (r.status === 403) { aviso('El enlace venció. Pida uno nuevo en la clínica.', false); this.disabled = false; return; }
                aviso(j.mensaje || 'No se pudo guardar.', !!j.ok);
                if (j.ok) { document.querySelector('.botones').style.display = 'none'; c.style.pointerEvents = 'none'; } else this.disabled = false;
            } catch (e) { aviso('Sin conexión. Intente otra vez.', false); this.disabled = false; }
        };
    })();
</script>
@endunless
</body>
</html>
