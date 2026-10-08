@php
    $s = config('soporte');
    $pago = $s['pago'];
    $empresa = $cliente->nombre_comercial ?: $cliente->razon_social;
    $mensaje = "Hola, soy de {$empresa} (RUC {$cliente->ruc}). Ya realicé el pago del servicio TUSHPA, les envío el comprobante para reactivar el acceso.";
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Servicio suspendido · TUSHPA</title>
    <link rel="icon" href="{{ asset('imagenes/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif; color: #0f172a;
               background: radial-gradient(1200px 600px at 10% -10%, #c7d2fe 0%, transparent 60%), radial-gradient(900px 500px at 110% 110%, #bae6fd 0%, transparent 55%), #eef2f7;
               display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .tarjeta { width: 100%; max-width: 980px; background: #fff; border-radius: 28px; overflow: hidden; display: grid; grid-template-columns: 1.1fr .9fr;
                   box-shadow: 0 30px 80px -20px rgba(30, 41, 59, .25), 0 0 0 1px rgba(15, 23, 42, .04); }
        .info { padding: 40px 40px 32px; display: flex; flex-direction: column; }
        .marca { display: flex; align-items: center; gap: 10px; font-weight: 800; letter-spacing: .08em; font-size: 13px; color: #4338ca; }
        .marca img { width: 32px; height: 32px; border-radius: 9px; }
        .chip { align-self: flex-start; margin-top: 28px; display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px;
                background: #fff7ed; color: #c2410c; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .chip i { width: 8px; height: 8px; border-radius: 50%; background: #f97316; box-shadow: 0 0 0 4px rgba(249, 115, 22, .2); animation: latido 1.6s infinite; }
        @keyframes latido { 50% { box-shadow: 0 0 0 7px rgba(249, 115, 22, 0); } }
        h1 { font-size: 32px; line-height: 1.15; margin: 14px 0 10px; font-weight: 800; letter-spacing: -.02em; }
        .lead { color: #475569; font-size: 15px; line-height: 1.6; margin: 0; }
        .lead strong { color: #0f172a; }
        .motivo { margin-top: 18px; padding: 12px 16px; border-radius: 14px; background: #f8fafc; border-left: 4px solid #f97316; color: #334155; font-size: 14px; }
        .motivo small { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; }
        .pasos { list-style: none; padding: 0; margin: 26px 0 0; display: grid; gap: 12px; }
        .pasos li { display: flex; gap: 12px; align-items: flex-start; font-size: 14px; color: #334155; line-height: 1.45; }
        .pasos b.n { flex: 0 0 26px; height: 26px; border-radius: 8px; background: #eef2ff; color: #4338ca; font-size: 13px; display: grid; place-items: center; }
        .cuentas { margin-top: 24px; display: grid; gap: 10px; }
        .cuenta { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 14px; }
        .cuenta small { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .cuenta span { font-size: 16px; font-weight: 800; color: #0b3c8c; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .copiar { flex: 0 0 auto; border: 0; cursor: pointer; padding: 8px 12px; border-radius: 10px; background: #eff6ff; color: #1d4ed8; font: 700 12px inherit; font-family: inherit; }
        .copiar:hover { background: #dbeafe; }
        .copiar.ok { background: #dcfce7; color: #15803d; }
        .acciones { margin-top: auto; padding-top: 26px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .wa { display: inline-flex; align-items: center; gap: 10px; padding: 13px 20px; border-radius: 14px; background: #16a34a; color: #fff; text-decoration: none; font-weight: 700; font-size: 14px;
              box-shadow: 0 10px 24px -8px rgba(22, 163, 74, .6); transition: transform .15s; }
        .wa:hover { transform: translateY(-1px); background: #15803d; }
        .wa svg { width: 20px; height: 20px; }
        .nota { font-size: 12px; color: #94a3b8; }
        .pago { background: linear-gradient(160deg, #0b2a6f 0%, #0d47a1 55%, #1565c0 100%); padding: 32px 28px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; }
        .pago::before { content: ''; position: absolute; inset: 0; background: radial-gradient(400px 200px at 80% 0%, rgba(56, 189, 248, .35), transparent 70%); pointer-events: none; }
        .pago h2 { position: relative; color: #fff; font-size: 15px; font-weight: 700; margin: 0 0 4px; text-align: center; }
        .pago p { position: relative; color: #bfdbfe; font-size: 12px; margin: 0 0 18px; text-align: center; }
        .qr { position: relative; width: 100%; max-width: 330px; border-radius: 22px; overflow: hidden; background: #fff; box-shadow: 0 24px 50px -12px rgba(0, 0, 0, .45); }
        .qr img { display: block; width: 100%; height: auto; }
        .seguro { position: relative; margin-top: 18px; display: flex; align-items: center; gap: 8px; color: #dbeafe; font-size: 12px; }
        .seguro svg { width: 16px; height: 16px; flex: 0 0 16px; }
        @media (max-width: 820px) {
            .tarjeta { grid-template-columns: 1fr; border-radius: 22px; }
            .info { padding: 28px 22px 24px; }
            h1 { font-size: 26px; }
            .pago { order: 2; padding: 26px 18px; }
            .acciones { padding-top: 22px; }
            .wa { flex: 1 1 100%; justify-content: center; }
            .cuenta { flex-wrap: wrap; }
            .cuenta span { font-size: 15px; }
        }
    </style>
</head>
<body>
    <main class="tarjeta">
        <section class="info">
            <div class="marca"><img src="{{ asset('imagenes/192.png') }}" alt="">TUSHPA</div>

            <span class="chip"><i></i> Acceso suspendido</span>
            <h1>Reactiva tu sistema en minutos</h1>
            <p class="lead">El acceso de <strong>{{ $empresa }}</strong> está suspendido temporalmente. <strong>Tus datos están seguros</strong>: apenas confirmemos tu pago, todo vuelve a funcionar tal como lo dejaste.</p>

            @if ($cliente->motivo_suspension)
                <div class="motivo"><small>Motivo</small>{{ $cliente->motivo_suspension }}</div>
            @endif

            <ol class="pasos">
                <li><b class="n">1</b><span>Escanea el QR con Yape, Plin o la app de tu banco, o transfiere a la cuenta {{ $pago['banco'] }}.</span></li>
                <li><b class="n">2</b><span>Envíanos la captura del pago por WhatsApp.</span></li>
                <li><b class="n">3</b><span>Reactivamos tu acceso y sigues trabajando.</span></li>
            </ol>

            <div class="cuentas">
                <div class="cuenta">
                    <div><small>Cuenta {{ $pago['banco'] }} · {{ $pago['titular'] }}</small><span>{{ $pago['cuenta'] }}</span></div>
                    <button type="button" class="copiar" data-valor="{{ preg_replace('/\D/', '', $pago['cuenta']) }}">Copiar</button>
                </div>
                <div class="cuenta">
                    <div><small>CCI (desde otros bancos)</small><span>{{ $pago['cci'] }}</span></div>
                    <button type="button" class="copiar" data-valor="{{ preg_replace('/\D/', '', $pago['cci']) }}">Copiar</button>
                </div>
            </div>

            <div class="acciones">
                <a class="wa" href="https://wa.me/{{ $s['whatsapp'] }}?text={{ rawurlencode($mensaje) }}" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.05 21.5h-.01a9.4 9.4 0 01-4.79-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.4 9.4 0 01-1.44-5.01c0-5.2 4.23-9.43 9.43-9.43 2.52 0 4.89.98 6.67 2.77a9.36 9.36 0 012.76 6.67c0 5.2-4.23 9.43-9.43 9.43zm8.02-17.45A11.25 11.25 0 0012.05.75C5.8.75.72 5.83.72 12.08c0 2 .52 3.95 1.52 5.66L.62 23.25l5.65-1.48a11.3 11.3 0 005.41 1.38h.01c6.25 0 11.33-5.08 11.33-11.33 0-3.03-1.18-5.87-3.32-8.01z"/></svg>
                    Ya pagué · enviar comprobante
                </a>
                <span class="nota">Soporte: {{ $s['telefono'] }} · {{ $s['horario'] }}</span>
            </div>
        </section>

        <aside class="pago">
            <h2>Paga con cualquier billetera digital</h2>
            <p>Yape · Plin · BBVA · BCP · Interbank y más</p>
            <div class="qr"><img src="{{ asset($pago['qr']) }}" alt="QR de pago {{ $pago['banco'] }} - {{ $pago['titular'] }}"></div>
            <div class="seguro">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v6c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                Titular: {{ $pago['titular'] }}
            </div>
        </aside>
    </main>

    <script>
        document.querySelectorAll('.copiar').forEach(b => b.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(b.dataset.valor); } catch (e) { return; }
            b.textContent = '¡Copiado!'; b.classList.add('ok');
            setTimeout(() => { b.textContent = 'Copiar'; b.classList.remove('ok'); }, 1800);
        }));
    </script>
</body>
</html>
