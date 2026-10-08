@php
    $colores = [
        'PENDIENTE' => 'bg-gray-100 text-gray-700', 'ACEPTADO' => 'bg-green-100 text-green-700',
        'OBSERVADO' => 'bg-amber-100 text-amber-800', 'RECHAZADO' => 'bg-red-100 text-red-700',
        'ERROR' => 'bg-orange-100 text-orange-700', 'EN RESUMEN' => 'bg-blue-100 text-blue-700',
        'ENVIADO' => 'bg-blue-100 text-blue-700', 'EN PROCESO' => 'bg-blue-100 text-blue-700', 'GENERADO' => 'bg-gray-100 text-gray-700',
        'ANULADO' => 'bg-red-600 text-white',
    ];
@endphp
<span class="estado-badge inline-block px-2 py-0.5 rounded-full text-xs font-bold whitespace-nowrap {{ $colores[$estado] ?? 'bg-gray-100 text-gray-700' }}">{{ $estado ?? '—' }}</span>
