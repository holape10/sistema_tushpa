{{-- Logo de la empresa en los reportes (PDF y para imprimir). Opcional: $alto (px), $logoSucursal (id; por defecto la del usuario) --}}
@php $logoReporte = \App\Support\Impresion\Impresion::logoReporte(isset($logoSucursal) ? (int) $logoSucursal : null); @endphp
@if ($logoReporte)
    <img src="{{ $logoReporte }}" alt="" style="float:right; max-height:{{ $alto ?? 46 }}px; max-width:160px; margin:0 0 4px 12px">
@endif
