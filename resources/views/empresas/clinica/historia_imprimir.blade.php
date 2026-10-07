<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historia clínica {{ $h->his_cli_cod }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11.5px; color: #111; background: #e5e7eb; margin: 0; }
        .hoja { width: 210mm; margin: 12px auto; background: #fff; padding: 14mm; box-sizing: border-box; }
        .cab { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
        .cab img { max-height: 55px; max-width: 120px; object-fit: contain; }
        h1 { font-size: 16px; margin: 0; color: #0f766e; }
        h2 { font-size: 12.5px; color: #0f766e; margin: 14px 0 6px; text-transform: uppercase; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 4px; vertical-align: top; }
        .datos td:nth-child(odd) { font-weight: bold; width: 18%; color: #374151; }
        .ate { border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; page-break-inside: avoid; }
        .ate .t { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 4px; }
        .ate p { margin: 3px 0; }
        .vit span { margin-right: 10px; }
        .alerta { color: #b91c1c; font-weight: bold; }
        .acciones { text-align: center; margin: 12px; }
        .acciones button { padding: 10px 22px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; } .hoja { margin: 0; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
@php $mascota = $h->tipo === 'MASCOTA'; @endphp
<div class="acciones"><button onclick="window.print()">Imprimir historia</button></div>
<div class="hoja">
    <div class="cab">
        <div>
            <h1>HISTORIA CLÍNICA {{ $h->his_cli_cod }}</h1>
            <div>{{ $negocio->nombre_comercial ?: $negocio->razon }} · {{ $negocio->direccion }}</div>
        </div>
        @if ($negocio->logo)<img src="{{ asset($negocio->logo) }}" alt="">@endif
    </div>

    <h2>Datos del paciente</h2>
    <table class="datos">
        @if ($mascota)
            <tr><td>Mascota</td><td>{{ $h->mascota }}</td><td>Especie / raza</td><td>{{ trim($h->especie . ' ' . $h->raza) }}</td></tr>
            <tr><td>Propietario</td><td>{{ $h->clinom }}</td><td>DNI</td><td>{{ $h->clinum }}</td></tr>
        @else
            <tr><td>Paciente</td><td>{{ $h->clinom }}</td><td>DNI</td><td>{{ $h->clinum }}</td></tr>
        @endif
        <tr><td>Nacimiento</td><td>{{ $h->fecha_nac ? \Carbon\Carbon::parse($h->fecha_nac)->format('d/m/Y') . ' (' . \App\Support\Clinica::edad($h->fecha_nac) . ')' : '-' }}</td>
            <td>Sexo</td><td>{{ $h->sexo ?: '-' }}</td></tr>
        <tr><td>Teléfono</td><td>{{ $h->telefono ?: '-' }}</td><td>{{ $mascota ? 'Apertura' : 'Grupo sanguíneo' }}</td>
            <td>{{ $mascota ? \Carbon\Carbon::parse($h->his_cli_fec)->format('d/m/Y') : ($h->grupo_sanguineo ?: '-') }}</td></tr>
        @if (!$mascota)<tr><td>Ocupación</td><td>{{ $h->ocupacion ?: '-' }}</td><td>Emergencia</td><td>{{ $h->contacto_emergencia ?: '-' }}</td></tr>@endif
        <tr><td>Alergias</td><td colspan="3" class="{{ $h->alergias ? 'alerta' : '' }}">{{ $h->alergias ?: 'Ninguna conocida' }}</td></tr>
        <tr><td>Antecedentes</td><td colspan="3">{!! $h->antecedentes ? nl2br(e($h->antecedentes)) : '-' !!}</td></tr>
    </table>

    @if ($vacunas->isNotEmpty())
        <h2>Vacunas y desparasitaciones</h2>
        <table>
            @foreach ($vacunas as $v)
                <tr><td>{{ \Carbon\Carbon::parse($v->fecha)->format('d/m/Y') }}</td><td>{{ $v->nombre }}</td><td>{{ $v->tipo === 'VACUNA' ? 'Vacuna' : 'Desparasitación' }}</td>
                    <td>{{ $v->proxima ? 'Próxima: ' . \Carbon\Carbon::parse($v->proxima)->format('d/m/Y') : '' }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($h->odontograma)
        <h2>Odontograma</h2>
        <p>@foreach (\App\Support\Clinica::resumenOdontograma($h->odontograma) as $linea)<span style="display:inline-block; margin-right:14px;">{{ $linea }}</span>@endforeach</p>
    @endif

    <h2>Atenciones ({{ $atenciones->count() }})</h2>
    @forelse ($atenciones as $a)
        <div class="ate">
            <div class="t"><span>{{ \Carbon\Carbon::parse($a->ate_cli_fec)->format('d/m/Y') }} · {{ $a->esp_des }}</span><span>{{ $a->doctor_nom }}</span></div>
            @php $vit = array_filter(['PA' => $a->pre_art, 'FC' => $a->fre_car, 'FR' => $a->fre_res, 'T°' => $a->temperatura, 'SatO₂' => $a->saturacion, 'Peso' => $a->peso ? $a->peso . ' kg' : null, 'Talla' => $a->talla ? $a->talla . ' m' : null]); @endphp
            @if ($vit)<p class="vit">@foreach ($vit as $k => $v)<span><b>{{ $k }}:</b> {{ $v }}</span>@endforeach</p>@endif
            @foreach (['mot_con' => 'Motivo', 'antecedente' => 'Enfermedad actual', 'exa_fis' => 'Examen físico', 'diagnostico' => 'Diagnóstico', 'tratamiento' => 'Tratamiento', 'examenes' => 'Exámenes', 'indicaciones' => 'Indicaciones'] as $c => $t)
                @if ($a->$c)<p><b>{{ $t }}:</b> {{ $c === 'diagnostico' && $a->cie10 ? '[' . $a->cie10 . '] ' : '' }}{!! nl2br(e($a->$c)) !!}</p>@endif
            @endforeach
            @if (!empty($recetas[$a->ate_cli_id]))
                <p><b>Receta:</b> {{ $recetas[$a->ate_cli_id]->map(fn($r) => trim($r->medicamento . ' ' . $r->dosis . ' ' . $r->frecuencia . ($r->duracion ? ' por ' . $r->duracion : '')))->implode(' · ') }}</p>
            @endif
        </div>
    @empty
        <p>Sin atenciones terminadas.</p>
    @endforelse
    <p style="text-align:right; color:#6b7280; margin-top:14px;">Impreso el {{ now()->format('d/m/Y H:i') }}</p>
</div>
</body>
</html>
