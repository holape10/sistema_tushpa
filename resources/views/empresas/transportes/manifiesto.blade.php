<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Manifiesto {{ $salida->origen }} - {{ $salida->destino }} {{ \Carbon\Carbon::parse($salida->salida)->format('d/m/Y H:i') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 5px 6px; text-align: left; }
        th { background: #eee; }
        .datos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px 16px; margin-top: 8px; }
        .firmas { display: flex; justify-content: space-around; margin-top: 60px; text-align: center; }
        .firmas div { border-top: 1px solid #111; width: 220px; padding-top: 4px; }
        @media print { .no-imprimir { display: none; } body { margin: 10mm; } }
    </style>
</head>
<body>
    <button class="no-imprimir" onclick="window.print()" style="float:right;padding:8px 14px;font-weight:bold">Imprimir</button>
    <h1>MANIFIESTO DE PASAJEROS</h1>
    <div>{{ $negocio->nombre_comercial ?? '' }} · RUC {{ $negocio->IdEmpresa ?? '' }}</div>
    <div class="datos">
        <div><b>Ruta:</b> {{ $salida->origen }} → {{ $salida->destino }}</div>
        <div><b>Salida:</b> {{ \Carbon\Carbon::parse($salida->salida)->format('d/m/Y H:i') }}</div>
        <div><b>Unidad:</b> {{ $salida->unidad }}{{ $salida->placa ? ' · '.$salida->placa : '' }}</div>
        <div><b>Conductor / capitán:</b> {{ $salida->conductor ?: '—' }}</div>
        <div><b>Pasajeros:</b> {{ count($pasajeros) }} de {{ $salida->asientos }}</div>
        <div><b>Tipo:</b> {{ \App\Support\Transportes::TIPOS[$salida->tipo][0] ?? $salida->tipo }}</div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Asiento</th><th>Piso</th><th>Documento</th><th>Nombres y apellidos</th><th>Celular</th><th>Comprobante</th></tr></thead>
        <tbody>
            @forelse ($pasajeros as $i => $p)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $p->asiento }} ({{ $p->posicion === 'V' ? 'ventana' : 'pasillo' }})</td>
                    <td>{{ $p->piso }}</td>
                    <td>{{ $p->doc }}</td>
                    <td>{{ $p->nombre }}</td>
                    <td>{{ $p->telefono }}</td>
                    <td>{{ $p->serdoc ? $p->serdoc.'-'.$p->numdoc : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#777">Aún no hay pasajes vendidos.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="firmas"><div>Conductor / capitán</div><div>Responsable de la empresa</div></div>
</body>
</html>
