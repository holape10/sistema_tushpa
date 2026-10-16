<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de tratamientos {{ $h->his_cli_cod }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; background: #e5e7eb; margin: 0; }
        .hoja { width: 210mm; margin: 12px auto; background: #fff; padding: 12mm; box-sizing: border-box; }
        .cab { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
        .cab img { max-height: 55px; max-width: 120px; object-fit: contain; }
        h1 { font-size: 16px; margin: 0; color: #0f766e; }
        .paciente { margin: 8px 0 4px; }
        .trat { border: 1px solid #d1d5db; border-radius: 6px; margin-top: 12px; page-break-inside: avoid; }
        .trat .t { display: flex; justify-content: space-between; gap: 10px; padding: 6px 8px; background: #f0fdfa; font-weight: bold; border-bottom: 1px solid #d1d5db; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f9fafb; font-size: 10px; text-transform: uppercase; color: #374151; padding: 4px 6px; border-bottom: 1px solid #d1d5db; text-align: left; }
        td { padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .r { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .firma img { height: 34px; }
        .cancelado { color: #047857; font-weight: bold; }
        .saldo { color: #b91c1c; }
        .acciones { text-align: center; margin: 12px; }
        .acciones button { padding: 10px 22px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; } .hoja { margin: 0; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
@php $soles = fn ($n) => 'S/ '.number_format((float) $n, 2); @endphp
<div class="acciones"><button onclick="window.print()">Imprimir</button></div>
<div class="hoja">
    <div class="cab">
        <div>
            <h1>REGISTRO DE TRATAMIENTOS</h1>
            <div>{{ $negocio->nombre_comercial ?: $negocio->razon }} · {{ $negocio->direccion }}</div>
        </div>
        @if ($negocio->logo)<img src="{{ asset($negocio->logo) }}" alt="">@endif
    </div>
    <div class="paciente">
        <b>Paciente:</b> {{ \App\Support\Clinica::nombre($h) }} · <b>DNI:</b> {{ $h->clinum }} · <b>Historia:</b> {{ $h->his_cli_cod }}
        @if ($h->telefono) · <b>Tel.:</b> {{ $h->telefono }} @endif
    </div>

    @forelse ($tratamientos as $t)
        <div class="trat">
            <div class="t">
                <span>{{ $t->descripcion }} @if ($t->esp_des)<span style="font-weight:normal">· {{ $t->esp_des }}</span>@endif</span>
                <span>Costo {{ $soles($t->costo) }} · Pagado {{ $soles($t->pagado) }} ·
                    @if ($t->estado === 'CANCELADO')<span class="cancelado">CANCELADO {{ \Carbon\Carbon::parse($t->cancelado)->format('d/m/Y') }}</span>
                    @else<span class="saldo">Saldo {{ $soles($t->saldo) }}</span>@endif</span>
            </div>
            @if (($t->modalidad ?? 'UNICO') === 'MENSUAL')
                <div style="padding:5px 8px; border-bottom:1px solid #d1d5db; font-size:10px;">
                    <b>Plan mensual:</b> @if ((float) $t->cuota_inicial > 0) inicial {{ $soles($t->cuota_inicial) }} + @endif {{ $t->cuotas }} cuotas de {{ $soles($t->cuota) }}
                    @if ((float) $t->vencido > 0) · <span class="saldo"><b>Vencido {{ $soles($t->vencido) }}</b></span>@endif
                    <div style="margin-top:3px;">
                        @foreach ($t->cuotas_plan as $c)
                            <span style="display:inline-block; margin:1px 6px 1px 0; {{ $c['estado'] === 'VENCIDA' ? 'color:#b91c1c; font-weight:bold;' : ($c['estado'] === 'PAGADA' ? 'color:#047857;' : 'color:#555;') }}">
                                {{ $c['estado'] === 'PAGADA' ? '✔' : ($c['estado'] === 'VENCIDA' ? '⚠' : '○') }} {{ $c['etiqueta'] }} ({{ \Carbon\Carbon::parse($c['vence'])->format('d/m') }})</span>
                        @endforeach
                    </div>
                </div>
            @endif
            <table>
                <thead><tr><th style="width:15%">Fecha y hora</th><th>Tratamiento realizado / indicaciones</th><th class="r">Adelanto</th><th class="r">Saldo</th>
                    <th class="c">Cancelación</th><th class="c" style="width:22%">Firma del paciente</th></tr></thead>
                <tbody>
                    @forelse ($t->registros as $r)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($r->fecha_hora)->format('d/m/Y H:i') }}</td>
                            <td>{!! nl2br(e($r->detalle ?: '—')) !!}</td>
                            <td class="r">{{ (float) $r->adelanto > 0 ? $soles($r->adelanto) : '—' }}</td>
                            <td class="r">{{ $soles($r->saldo) }}</td>
                            <td class="c">@if ((float) $r->adelanto > 0 && (float) $r->saldo <= 0)<span class="cancelado">CANCELADO</span>@endif</td>
                            <td class="c firma">@if ($r->firma_img)<img src="{{ $r->firma_img }}" alt="Firma">@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="c" style="color:#888">Sin sesiones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p style="color:#888; margin-top:20px">El paciente no tiene tratamientos registrados.</p>
    @endforelse
</div>
</body>
</html>
