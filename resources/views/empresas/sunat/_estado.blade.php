@php
    $colores = [
        'PENDIENTE' => 'bg-gray-100 text-gray-700', 'ACEPTADO' => 'bg-green-100 text-green-700',
        'OBSERVADO' => 'bg-green-100 text-green-700', 'RECHAZADO' => 'bg-red-100 text-red-700',
        'ERROR' => 'bg-orange-100 text-orange-700', 'EN RESUMEN' => 'bg-blue-100 text-blue-700',
        'ENVIADO' => 'bg-blue-100 text-blue-700', 'EN PROCESO' => 'bg-blue-100 text-blue-700', 'GENERADO' => 'bg-gray-100 text-gray-700',
        'ANULADO' => 'bg-red-600 text-white', 'CONTINGENCIA' => 'bg-violet-100 text-violet-800',
    ];
@endphp
{{-- OBSERVADO es aceptado por SUNAT (vale igual): solo trae una advertencia, que se ve al pasar el mouse --}}
<span class="estado-badge inline-block px-2 py-0.5 rounded-full text-xs font-bold whitespace-nowrap {{ $colores[$estado] ?? 'bg-gray-100 text-gray-700' }}"
      @if ($estado === 'OBSERVADO') title="Aceptado por SUNAT con una observación (es válido). Suele ser un dato de la dirección o del cliente." @endif>{{ $estado === 'OBSERVADO' ? 'ACEPTADO c/obs.' : ($estado ?? '—') }}</span>
