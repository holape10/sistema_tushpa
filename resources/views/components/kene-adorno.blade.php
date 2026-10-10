{{--
    Adorno kené para cabeceras con fondo de color (título grande).
    El contenedor debe tener: relative isolate overflow-hidden.
    El patrón aparece a la derecha y se desvanece hacia el título; abajo va una greca.
--}}
@props(['patron' => 'rombos', 'color' => 'text-white/10', 'franja' => 'text-white/25'])

<div class="kene-desvanecer pointer-events-none absolute inset-y-0 right-0 -z-10 w-3/4 sm:w-1/2" aria-hidden="true">
    <div class="kene kene-{{ $patron }} h-full w-full {{ $color }}"></div>
</div>
@if ($franja)
    <div class="kene kene-franja pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-3 {{ $franja }}" aria-hidden="true"></div>
@endif
