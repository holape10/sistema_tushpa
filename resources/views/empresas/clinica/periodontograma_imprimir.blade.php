<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Periodontograma · {{ $h->his_cli_cod }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; background: #e5e7eb; margin: 0; }
        .hoja { width: 277mm; max-width: 100%; margin: 12px auto; background: #fff; padding: 10mm; box-sizing: border-box; }
        .cab { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f766e; padding-bottom: 6px; margin-bottom: 8px; }
        .cab img { max-height: 45px; max-width: 110px; object-fit: contain; }
        h1 { font-size: 15px; color: #0f766e; margin: 0; }
        .kpis { display: flex; gap: 6px; flex-wrap: wrap; margin: 8px 0; }
        .kpis div { border: 1px solid #ccfbf1; background: #f0fdfa; border-radius: 6px; padding: 4px 8px; text-align: center; }
        .kpis b { display: block; font-size: 14px; }
        table { border-collapse: collapse; margin-bottom: 8px; width: 100%; table-layout: fixed; }
        td, th { border: 1px solid #d1d5db; text-align: center; padding: 1px; font-size: 9.5px; }
        th { background: #f0fdfa; }
        td.t { text-align: left; font-weight: bold; width: 62px; background: #f9fafb; }
        .s3 { display: flex; justify-content: space-around; }
        .aus { background: #e5e7eb; color: #6b7280; }
        .a { background: #fde68a; } .r { background: #f43f5e; color: #fff; }
        .sg { color: #e11d48; } .pl { color: #0284c7; }
        .sep td { background: #0f766e; color: #fff; font-weight: bold; text-align: left; }
        .acciones { text-align: center; margin: 12px; }
        .acciones button { padding: 10px 22px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { body { background: #fff; } .hoja { margin: 0; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
@php
    $arcadas = ['SUPERIOR' => [[18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28], 'Palatino'], 'INFERIOR' => [[48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38], 'Lingual']];
    $ps = fn ($v) => $v === null ? '' : ($v >= 6 ? 'r' : ($v >= 4 ? 'a' : ''));
@endphp
<div class="acciones"><button onclick="window.print()">Imprimir</button></div>
<div class="hoja">
    <div class="cab">
        <div><b>{{ $negocio->nombre_comercial ?: $negocio->razon }}</b><br>{{ $negocio->direccion }}</div>
        @if ($negocio->logo)<img src="{{ asset($negocio->logo) }}" alt="">@endif
    </div>
    <h1>PERIODONTOGRAMA</h1>
    <p style="margin:4px 0"><b>Paciente:</b> {{ $h->clinom }} · <b>HC:</b> {{ $h->his_cli_cod }} · <b>DNI:</b> {{ $h->clinum }} ·
        <b>Fecha:</b> {{ \Carbon\Carbon::parse($a->ate_cli_fec)->format('d/m/Y') }} @if ($doctor) · <b>Profesional:</b> {{ $doctor }} @endif</p>
    @if ($resumen)
        <div class="kpis">
            <div><b class="sg">{{ $resumen['sangrado'] }}%</b>Sangrado</div>
            <div><b class="pl">{{ $resumen['placa'] }}%</b>Placa</div>
            <div><b>{{ $resumen['ps_promedio'] ?? '—' }} mm</b>PS promedio</div>
            <div><b>{{ $resumen['bolsas_4'] }}</b>Bolsas 4-5 mm</div>
            <div><b>{{ $resumen['bolsas_6'] }}</b>Bolsas ≥ 6 mm</div>
            <div><b>{{ $resumen['nic_promedio'] ?? '—' }} mm</b>NIC promedio</div>
            <div><b>{{ $resumen['nic_max'] ?? '—' }} mm</b>NIC máximo</div>
            <div><b>{{ $resumen['movilidad'] }}</b>Con movilidad</div>
            <div><b>{{ $resumen['furcas'] }}</b>Con furca</div>
        </div>
    @else
        <p>No se registró el periodontograma en esta atención.</p>
    @endif
    @foreach ($arcadas as $arcada => [$dientes, $interna])
        <table>
            <tr><th style="width:62px">{{ $arcada }}</th>@foreach ($dientes as $n)<th class="{{ !empty($perio[$n]['aus']) ? 'aus' : '' }}">{{ $n }}</th>@endforeach</tr>
            @foreach (['mov' => 'Movilidad', 'fur' => 'Furca'] as $k => $t)
                <tr><td class="t">{{ $t }}</td>@foreach ($dientes as $n)<td class="{{ !empty($perio[$n]['aus']) ? 'aus' : '' }}">{{ $perio[$n][$k] ?? '' }}</td>@endforeach</tr>
            @endforeach
            @foreach (['v' => 'Vestibular', 'l' => $interna] as $cara => $nc)
                <tr class="sep"><td colspan="17">{{ $nc }}</td></tr>
                @foreach (['s' => 'Sangrado', 'p' => 'Placa', 'mg' => 'MG', 'ps' => 'PS', 'nic' => 'NIC'] as $k => $t)
                    <tr><td class="t">{{ $t }}</td>
                        @foreach ($dientes as $n)
                            @php $c = $perio[$n][$cara] ?? null; @endphp
                            <td class="{{ !empty($perio[$n]['aus']) ? 'aus' : '' }}"><div class="s3">
                                @foreach ([0, 1, 2] as $i)
                                    @if (! $c)
                                        <span>&nbsp;</span>
                                    @elseif ($k === 's')
                                        <span class="sg">{{ $c['s'][$i] ? '●' : '·' }}</span>
                                    @elseif ($k === 'p')
                                        <span class="pl">{{ $c['p'][$i] ? '●' : '·' }}</span>
                                    @elseif ($k === 'nic')
                                        <span>{{ $c['ps'][$i] === null ? '' : $c['ps'][$i] + (int) $c['mg'][$i] }}</span>
                                    @elseif ($k === 'ps')
                                        <span class="{{ $ps($c['ps'][$i]) }}" style="padding:0 2px">{{ $c['ps'][$i] }}</span>
                                    @else
                                        <span>{{ $c[$k][$i] }}</span>
                                    @endif
                                @endforeach
                            </div></td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </table>
    @endforeach
    <p style="font-size:9.5px;color:#555">PS: profundidad de sondaje · MG: margen gingival (+ recesión / − agrandamiento) · NIC: nivel de inserción clínica (PS + MG) · ● sangrado / placa al sondaje · PS 4-5 mm amarillo, ≥ 6 mm rojo.</p>
</div>
</body>
</html>
