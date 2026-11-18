{{--
    Vista previa de un ticket: las mismas líneas que se mandan a la ticketera (Escpos::vista), en letra de ancho fijo
    y del ancho del papel. Variables: $lineas, $columnas
--}}
@php
    $publico = rtrim(str_replace('\\', '/', public_path()), '/').'/';
@endphp
<div class="mx-auto bg-white shadow-lg ring-1 ring-gray-200 px-3 py-4 font-mono text-[12px] leading-[1.35] text-gray-900"
     style="width: calc({{ $columnas }}ch + 1.5rem); font-family: 'Courier New', ui-monospace, monospace;">
    @foreach ($lineas as $l)
        @php
            $alinear = ['izq' => 'left', 'centro' => 'center', 'der' => 'right'][$l['alinear'] ?? 'izq'] ?? 'left';
            $ancho = (int) ($l['ancho'] ?? 1);
            $alto = (int) ($l['alto'] ?? 1);
            $chica = ($l['fuente'] ?? 'a') === 'b';
            // Letra grande: el doble de ancho (entran la mitad); alta: el doble de alto; fuente B: más pequeña
            $estilo = 'text-align:'.$alinear.';'.(($l['negrita'] ?? false) ? 'font-weight:700;' : '')
                .($ancho > 1 ? 'font-size:'.$ancho.'em;' : ($chica ? 'font-size:0.75em;' : ''))
                .($alto > $ancho ? 'transform:scaleY('.($alto / $ancho).');transform-origin:top;margin-bottom:'.(($alto / $ancho) - 1) * 1.35.'em;' : '');
        @endphp
        @if ($l['tipo'] === 'texto')
            <div style="{{ $estilo }} white-space:pre;">{{ $l['texto'] === '' ? ' ' : $l['texto'] }}</div>
        @elseif ($l['tipo'] === 'logo' && ! empty($l['ruta']) && str_starts_with(str_replace('\\', '/', $l['ruta']), $publico))
            <div class="py-1 text-center"><img src="{{ asset(substr(str_replace('\\', '/', $l['ruta']), strlen($publico))) }}" alt="Logo" class="mx-auto max-h-24 grayscale"></div>
        @elseif ($l['tipo'] === 'qr')
            <div style="text-align:{{ $alinear }}" class="py-1"><span class="inline-flex h-20 w-20 items-center justify-center border-2 border-gray-800 text-[10px] font-bold">QR SUNAT</span></div>
        @elseif ($l['tipo'] === 'corte')
            <div class="mt-2 border-t-2 border-dashed border-gray-300 pt-1 text-center text-[10px] text-gray-400">✂ corte del papel</div>
        @endif
    @endforeach
</div>
