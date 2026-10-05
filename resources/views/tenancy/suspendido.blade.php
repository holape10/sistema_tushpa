<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Servicio suspendido · TUSHPA</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f1f5f9; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #1e293b; padding: 16px; box-sizing: border-box; }
        .caja { background: #fff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 32px 28px; max-width: 420px; text-align: center; }
        .icono { width: 56px; height: 56px; border-radius: 50%; background: #fef3c7; color: #b45309; font-size: 28px; line-height: 56px; margin: 0 auto 12px; }
        h1 { font-size: 20px; margin: 0 0 6px; }
        p { color: #64748b; font-size: 14px; line-height: 1.5; margin: 6px 0; }
        .motivo { background: #f8fafc; border-radius: 10px; padding: 8px 12px; color: #334155; }
    </style>
</head>
<body>
    <div class="caja">
        <div class="icono">!</div>
        <h1>Servicio suspendido</h1>
        <p>El acceso de <strong>{{ $cliente->nombre_comercial ?: $cliente->razon_social }}</strong> está suspendido temporalmente.</p>
        @if ($cliente->motivo_suspension)
            <p class="motivo">{{ $cliente->motivo_suspension }}</p>
        @endif
        <p>Tus datos están seguros. Comunícate con el soporte de TUSHPA para reactivarlo.</p>
    </div>
</body>
</html>
