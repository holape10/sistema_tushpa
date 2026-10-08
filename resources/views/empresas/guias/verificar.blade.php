{{-- Página pública que abre el QR de la guía (para quien la revise en carretera): datos del traslado y su estado en SUNAT --}}
@php
    $aceptada = $g->est_sunat === 'ACEPTADO';
    $f = fn ($d) => \Carbon\Carbon::parse($d)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Guía {{ $g->serie }}-{{ $g->numero }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f1f5f9; color: #0f172a; }
        .caja { max-width: 520px; margin: 0 auto; padding: 16px; }
        .estado { border-radius: 18px; color: #fff; padding: 22px 18px; text-align: center; background: {{ $aceptada ? '#059669' : '#d97706' }}; }
        .estado h1 { margin: 0; font-size: 24px; }
        .estado p { margin: 6px 0 0; opacity: .95; }
        .tarjeta { background: #fff; border-radius: 16px; padding: 14px 16px; margin-top: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .tarjeta h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #2563eb; margin: 0 0 6px; }
        .fila { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        .fila span:first-child { color: #64748b; }
        .fila span:last-child { text-align: right; font-weight: 600; }
        ul { margin: 0; padding-left: 18px; font-size: 14px; }
        .boton { display: block; text-align: center; margin-top: 12px; padding: 12px; border-radius: 12px; background: #2563eb; color: #fff; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="caja">
    <div class="estado">
        <h1>{{ $aceptada ? '✔ GUÍA ACEPTADA POR SUNAT' : 'GUÍA '.$g->est_sunat.' EN SUNAT' }}</h1>
        <p>{{ $g->serie }}-{{ str_pad($g->numero, 8, '0', STR_PAD_LEFT) }} · {{ $empresa->NomEmpresa }} (RUC {{ $empresa->IdEmpresa }})</p>
    </div>
    @if ($g->qr)<a class="boton" href="{{ $g->qr }}" target="_blank" rel="noopener">Ver la guía en SUNAT</a>@endif

    <div class="tarjeta">
        <h2>Traslado</h2>
        <div class="fila"><span>Emitida</span><span>{{ $f($g->fecha_emision) }}</span></div>
        <div class="fila"><span>Inicio de traslado</span><span>{{ $f($g->fecha_traslado) }}</span></div>
        <div class="fila"><span>Motivo</span><span>{{ $motivos[$g->motivo] ?? $g->motivo }}</span></div>
        <div class="fila"><span>Destinatario</span><span>{{ $g->dest_nom }} ({{ $g->dest_num }})</span></div>
        <div class="fila"><span>Partida</span><span>{{ \App\Support\Ubigeo::nombre($g->partida_ubigeo) ?? $g->partida_ubigeo }}</span></div>
        <div class="fila"><span>Llegada</span><span>{{ \App\Support\Ubigeo::nombre($g->llegada_ubigeo) ?? $g->llegada_ubigeo }}</span></div>
        @if ($g->modalidad === '02' && ! $g->vehiculo_m1l)
            <div class="fila"><span>Placa</span><span>{{ $g->placa }}</span></div>
            <div class="fila"><span>Conductor</span><span>{{ $g->cond_nombres }} {{ $g->cond_apellidos }}</span></div>
        @elseif ($g->modalidad === '01')
            <div class="fila"><span>Transportista</span><span>{{ $g->transp_nom }}</span></div>
        @endif
    </div>
    <div class="tarjeta">
        <h2>Bienes</h2>
        <ul>@foreach ($detalle as $d)<li>{{ rtrim(rtrim(number_format($d->cantidad, 3), '0'), '.') }} {{ $d->umecod }} · {{ $d->descripcion }}</li>@endforeach</ul>
    </div>
</div>
</body>
</html>
