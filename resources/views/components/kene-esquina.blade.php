{{--
    Esquina escalonada kené para tarjetas de color (arriba a la derecha).
    La tarjeta debe tener: relative isolate overflow-hidden.
--}}
@props(['color' => 'text-white/20'])

<div class="kene kene-triangulos pointer-events-none absolute right-0 top-0 -z-10 h-12 w-12 -scale-y-100 {{ $color }}" style="--k:1" aria-hidden="true"></div>
