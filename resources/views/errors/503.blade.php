{{--
    Pantalla mientras se actualiza el sistema (php artisan down --render="errors::503" en deploy/actualizar.sh).
    Se arma una sola vez al entrar en mantenimiento: no usa la base de datos ni los archivos de /build (se reemplazan durante la actualización).
    La página se recarga sola cada 20 segundos hasta que el sistema vuelve.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Actualizando el sistema · TUSHPA</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #0f172a;
               background: radial-gradient(1000px 500px at 10% -10%, #c7d2fe 0%, transparent 60%), radial-gradient(800px 450px at 110% 110%, #bae6fd 0%, transparent 55%), #eef2f7; }
        .tarjeta { width: 100%; max-width: 480px; background: #fff; border-radius: 28px; padding: 40px 32px 32px; text-align: center;
                   box-shadow: 0 30px 80px -20px rgba(30, 41, 59, .25), 0 0 0 1px rgba(15, 23, 42, .04); }
        .marca { font-weight: 800; letter-spacing: .1em; font-size: 13px; color: #4338ca; }
        .engranes { width: 96px; height: 96px; margin: 26px auto 8px; border-radius: 50%; background: #eef2ff; display: grid; place-items: center; }
        .engranes svg { width: 52px; height: 52px; color: #4338ca; animation: girar 3s linear infinite; }
        @keyframes girar { to { transform: rotate(360deg); } }
        h1 { font-size: 26px; line-height: 1.2; margin: 16px 0 10px; font-weight: 800; letter-spacing: -.02em; }
        p { color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 10px; }
        p strong { color: #0f172a; }
        .barra { margin: 22px auto 12px; height: 6px; border-radius: 99px; background: #e0e7ff; overflow: hidden; }
        .barra i { display: block; height: 100%; width: 40%; border-radius: 99px; background: linear-gradient(90deg, #6366f1, #0ea5e9); animation: avanzar 1.6s ease-in-out infinite; }
        @keyframes avanzar { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
        .nota { font-size: 13px; color: #94a3b8; }
        button { margin-top: 18px; border: 0; cursor: pointer; padding: 12px 22px; border-radius: 14px; background: #4338ca; color: #fff; font: 700 14px system-ui, sans-serif; }
        button:hover { background: #3730a3; }
    </style>
</head>
<body>
    <main class="tarjeta">
        <div class="marca">TUSHPA</div>
        <div class="engranes" aria-hidden="true">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3c.4-1.8 3-1.8 3.4 0a1.7 1.7 0 002.6 1.1c1.6-1 3.4.8 2.4 2.4a1.7 1.7 0 001 2.6c1.8.4 1.8 3 0 3.4a1.7 1.7 0 00-1 2.6c1 1.6-.8 3.4-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-3 1.8-3.4 0a1.7 1.7 0 00-2.6-1c-1.6 1-3.4-.8-2.4-2.4a1.7 1.7 0 00-1-2.6c-1.8-.4-1.8-3 0-3.4a1.7 1.7 0 001-2.6c-1-1.6.8-3.4 2.4-2.4a1.7 1.7 0 002.6-1.1z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <h1>Estamos actualizando el sistema</h1>
        <p>Estamos instalando mejoras. En <strong>unos minutos</strong> vuelve a funcionar.</p>
        <p><strong>Tus datos están seguros</strong>: no se pierde nada de lo que registraste.</p>
        <div class="barra"><i></i></div>
        <p class="nota">No cierres esta página: se recarga sola apenas termine.</p>
        <button type="button" onclick="location.reload()">Volver a intentar</button>
    </main>
    <script>setTimeout(() => location.reload(), 20000);</script>
</body>
</html>
