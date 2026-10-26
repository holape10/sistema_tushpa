@php
    $nombre = $negocio->nombre_comercial ?: $negocio->NomEmpresa;
    $logo = collect([$negocio->logo_suc, $negocio->LogEmpresa])->first(fn ($l) => $l && is_file(public_path($l)));
    $f = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';
    $n = fn ($v, $suf = '') => $v !== null && $v !== '' ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.').$suf : '';
    $filas = ['edad' => 'EDAD', 'peso' => 'PESO (kg)', 'estatura' => 'ESTATURA (m)'] + collect($perimetros)->mapWithKeys(fn ($v, $k) => [$k => mb_strtoupper($v).' (cm)'])->all()
        + ['grasa' => '% GRASA', 'imc' => 'IMC'];
    $columnas = $medidas->pad(5, null);
    $marcados = $ficha->antecedentes ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rutina de entrenamiento · {{ $socio->clinom }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 11px; margin: 0; }
        .hoja { max-width: 190mm; margin: 0 auto; }
        .cabecera { background: #1f1f1f; color: #fff; display: flex; align-items: center; gap: 14px; padding: 10px 14px; border-radius: 6px; }
        .cabecera img { height: 56px; background: #fff; border-radius: 6px; padding: 3px; }
        .cabecera .gym { font-weight: 900; font-size: 15px; }
        .cabecera h1 { margin: 0 0 0 auto; font-size: 24px; letter-spacing: 2px; text-align: right; line-height: 1.05; }
        .datos { border: 1.5px solid #333; border-radius: 10px; padding: 8px 12px; margin-top: 8px; display: grid; grid-template-columns: 2fr 1fr; gap: 4px 16px; }
        .datos b { display: inline-block; min-width: 70px; }
        h2 { font-size: 12px; margin: 10px 0 4px; text-transform: uppercase; }
        .ant { display: grid; grid-template-columns: 1fr 1fr 1.1fr; border: 1.5px solid #333; }
        .ant ul { list-style: none; margin: 0; padding: 6px 8px; }
        .ant li { margin: 2px 0; }
        .caja { display: inline-block; width: 11px; height: 11px; border: 1.3px solid #111; margin-right: 5px; vertical-align: -2px; text-align: center; line-height: 9px; font-size: 10px; font-weight: 900; }
        .obs { border-left: 1.5px solid #333; padding: 6px 8px; display: flex; flex-direction: column; }
        .obs .firma { margin-top: auto; border-top: 1px solid #333; text-align: center; font-size: 8px; padding-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 4px 5px; }
        th { background: #9a9a9a; color: #111; font-size: 10px; }
        td.et { font-weight: bold; text-align: center; background: #f2f2f2; width: 22%; }
        td.v { text-align: center; }
        .calent { display: flex; gap: 10px; margin-top: 6px; border: 1.5px solid #333; padding: 6px; }
        .calent div { flex: 1; text-align: center; font-weight: bold; }
        .calent span { display: block; border: 1px solid #333; margin-top: 3px; padding: 3px; font-size: 13px; min-height: 22px; }
        .dias { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .dia { border: 1px solid #555; break-inside: avoid; }
        .dia p { margin: 0; padding: 3px 6px; background: #1f1f1f; color: #fff; font-weight: bold; }
        .dia td { font-size: 10px; }
        .pie { margin-top: 8px; font-size: 9px; color: #555; text-align: center; }
        @media screen { body { background: #e5e7eb; padding: 16px; } .hoja { background: #fff; padding: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.15); } .imprimir { display: block; margin: 0 auto 12px; padding: 10px 24px; font-weight: bold; } }
        @media print { .imprimir { display: none; } }
    </style>
</head>
<body>
<button class="imprimir" onclick="window.print()">🖨 Imprimir</button>
<div class="hoja">
    <div class="cabecera">
        @if ($logo)<img src="{{ asset($logo) }}" alt="">@endif
        <span class="gym">{{ $nombre }}</span>
        <h1>RUTINA DE<br>ENTRENAMIENTO</h1>
    </div>

    <div class="datos">
        <div><b>NOMBRE:</b> {{ $socio->clinom }}</div>
        <div><b>F. INICIO:</b> {{ $f($inicio) }}</div>
        <div><b>OBJETIVO:</b> {{ $ficha->objetivo ?? $rutina['objetivo'] ?? '' }}</div>
        <div><b>COD.:</b> {{ $socio->codigo }}</div>
    </div>

    <h2>Antecedentes médicos</h2>
    <div class="ant">
        @foreach (array_chunk($antecedentes, 5, true) as $grupo)
            <ul>
                @foreach ($grupo as $k => $texto)
                    <li><span class="caja">{{ in_array($k, $marcados, true) ? '✓' : '' }}</span>{{ $texto }}</li>
                @endforeach
                @if ($loop->last)<li><span class="caja">{{ ! empty($ficha->otros) ? '✓' : '' }}</span>Otros: {{ $ficha->otros ?? '' }}</li>@endif
            </ul>
        @endforeach
        <div class="obs">
            <b style="font-size:9px;">OBSERVACIONES IMPORTANTES:</b>
            <span>{{ $ficha->observaciones ?? '' }}</span>
            <span class="firma">FIRMA DEL CLIENTE</span>
        </div>
    </div>

    <h2>Medidas</h2>
    <table>
        <tr><th>MEDIDAS</th>@foreach ($columnas as $m)<th>FECHA: {{ $m ? $f($m['fecha']) : '' }}</th>@endforeach</tr>
        @foreach ($filas as $k => $titulo)
            <tr><td class="et">{{ $titulo }}:</td>
                @foreach ($columnas as $m)<td class="v">{{ $m ? $n($m[$k] ?? null) : '' }}</td>@endforeach</tr>
        @endforeach
    </table>

    <h2>Calentamiento (minutos)</h2>
    <div class="calent">
        @foreach ($calentamiento as $k => $nom)
            <div>{{ mb_strtoupper($nom) }}<span>{{ $rutina['calentamiento'][$k] ?? '' }}</span></div>
        @endforeach
    </div>

    @if ($rutina && $rutina['dias'])
        <h2>Rutina · {{ ucfirst(\Carbon\Carbon::parse($rutina['desde'])->locale('es')->translatedFormat('F Y')) }}{{ $rutina['entrenador'] ? ' · Entrenador: '.$rutina['entrenador'] : '' }}</h2>
        <div class="dias">
            @foreach ($rutina['dias'] as $d)
                <div class="dia">
                    <p>{{ $d['dia'] }}{{ $d['grupo'] ? ' · '.$d['grupo'] : '' }}</p>
                    <table>
                        <tr><th style="text-align:left">EJERCICIO</th><th>SERIES</th><th>REPS</th><th>NOTA</th></tr>
                        @foreach ($d['ejercicios'] as $e)
                            <tr><td>{{ $e['nombre'] }}</td><td class="v">{{ $e['series'] }}</td><td class="v">{{ $e['reps'] }}</td><td>{{ $e['nota'] }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @endforeach
        </div>
        @if ($rutina['indicaciones'])<p><b>Indicaciones:</b> {{ $rutina['indicaciones'] }}</p>@endif
    @endif
    <p class="pie">{{ $nombre }} · Impreso el {{ now()->format('d/m/Y H:i') }}</p>
</div>
</body>
</html>
