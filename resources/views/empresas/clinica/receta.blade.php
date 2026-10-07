<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta {{ $h->his_cli_cod }}</title>
    <style>
        @page { size: A5; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; background: #e5e7eb; margin: 0; }
        .hoja { width: 148mm; min-height: 200mm; margin: 12px auto; background: #fff; padding: 10mm; box-sizing: border-box; display: flex; flex-direction: column; }
        .cab { display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
        .cab img { max-height: 55px; max-width: 110px; object-fit: contain; }
        .cab h1 { font-size: 15px; margin: 0; color: #0f766e; }
        .cab p { margin: 1px 0; font-size: 10.5px; color: #444; }
        .datos { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 12px; margin: 10px 0; font-size: 11.5px; }
        .rx { font-size: 26px; font-weight: bold; color: #0f766e; font-family: Georgia, serif; }
        .med { border-bottom: 1px dashed #ccc; padding: 6px 0; }
        .med b { font-size: 12.5px; }
        .med span { display: block; color: #333; margin-top: 2px; }
        .bloque { margin-top: 10px; }
        .bloque h3 { font-size: 11.5px; margin: 0 0 3px; color: #0f766e; text-transform: uppercase; }
        .pie { margin-top: auto; padding-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 11px; }
        .firma { text-align: center; border-top: 1px solid #111; width: 55mm; padding-top: 4px; }
        .acciones { text-align: center; margin: 12px; }
        .acciones button { padding: 10px 22px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; } .hoja { margin: 0; width: auto; min-height: 0; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
<div class="acciones"><button onclick="window.print()">Imprimir receta</button></div>
<div class="hoja">
    <div class="cab">
        @if ($negocio->logo)<img src="{{ asset($negocio->logo) }}" alt="">@endif
        <div>
            <h1>{{ $negocio->nombre_comercial ?: $negocio->razon }}</h1>
            <p>{{ $negocio->direccion }}</p>
            @if ($negocio->telefono)<p>Tel. {{ $negocio->telefono }}</p>@endif
        </div>
    </div>

    <div class="datos">
        <div><b>Paciente:</b> {{ $h->tipo === 'MASCOTA' ? $h->mascota . ' (' . trim($h->especie . ' ' . $h->raza) . ')' : $h->clinom }}</div>
        <div><b>Fecha:</b> {{ \Carbon\Carbon::parse($a->ate_cli_fec)->format('d/m/Y') }}</div>
        @if ($h->tipo === 'MASCOTA')<div><b>Propietario:</b> {{ $h->clinom }}</div>@endif
        <div><b>Historia:</b> {{ $h->his_cli_cod }}</div>
        @if (\App\Support\Clinica::edad($h->fecha_nac))<div><b>Edad:</b> {{ \App\Support\Clinica::edad($h->fecha_nac) }}</div>@endif
        @if ($a->peso)<div><b>Peso:</b> {{ $a->peso }} kg</div>@endif
        @if ($a->diagnostico)<div style="grid-column: 1 / -1;"><b>Diagnóstico:</b> {{ $a->cie10 }} {{ $a->diagnostico }}</div>@endif
        @if ($h->alergias)<div style="grid-column: 1 / -1; color:#b91c1c;"><b>Alergias:</b> {{ $h->alergias }}</div>@endif
    </div>

    <div class="rx">℞</div>
    @forelse ($receta as $i => $r)
        <div class="med">
            <b>{{ $i + 1 }}. {{ $r->medicamento }}</b>{{ $r->cantidad ? ' — Cant.: ' . $r->cantidad : '' }}
            <span>{{ collect([$r->dosis, $r->frecuencia, $r->duracion ? 'por ' . $r->duracion : null])->filter()->implode(' · ') }}</span>
            @if ($r->indicaciones)<span><i>{{ $r->indicaciones }}</i></span>@endif
        </div>
    @empty
        <p style="color:#888;">Sin medicamentos.</p>
    @endforelse

    @if ($a->indicaciones)<div class="bloque"><h3>Indicaciones</h3>{!! nl2br(e($a->indicaciones)) !!}</div>@endif
    @if ($a->examenes)<div class="bloque"><h3>Exámenes solicitados</h3>{!! nl2br(e($a->examenes)) !!}</div>@endif
    @if ($a->pro_cit)<div class="bloque"><h3>Próxima cita</h3>{{ \Carbon\Carbon::parse($a->pro_cit)->locale('es')->isoFormat('dddd D [de] MMMM YYYY') }}</div>@endif

    <div class="pie">
        <div>{{ $esp }}</div>
        <div class="firma">{{ $doctor->nombre ?? '' }}<br><small>Firma y sello</small></div>
    </div>
</div>
</body>
</html>
