{{--
    Mesas de un piso en Comandas: en cuadrícula (como siempre) o, si la sucursal lo eligió y el piso tiene plano, en el
    plano del local. Las mesas que aún no están en el plano salen debajo, en cuadrícula, para que ninguna se pierda.
    Usa $mesas (ComandasController::mesasDelPiso), $usaPlano y $elementos.
--}}
@php $enPlano = ($usaPlano ?? false) ? $mesas->whereNotNull('plano_x') : collect(); @endphp
@if ($enPlano->isEmpty())
    @include('empresas.comandas.partials.mesas_grid', ['mesas' => $mesas])
@else
    {{-- La mesa más chica nunca es menor que un botón de la cuadrícula: si no entra, el plano se desliza (celular y PC).
         Si sobra espacio, el plano entra entero en la pantalla, pero sin pasar del tope: una mesa normal queda del tamaño
         de un botón (en la tablet llenaba todo y las mesas salían enormes) --}}
    <style>
        .plano-lienzo { width: max(var(--min-movil), min(100%, var(--max-movil))); }
        @media (min-width: 768px) { .plano-lienzo { width: max(var(--min-pc), min(100%, var(--entra-pc), var(--max-pc))); } }
        /* Local mucho más largo que ancho: en el celular se ve derecho; en la PC, girado de costado para usar el ancho */
        .plano-girado { aspect-ratio: var(--ar); }
        .plano-girado .plano-pos { left: var(--l); top: var(--t); width: var(--w); height: var(--h); }
        .plano-girado .btn-mesa-comanda { left: var(--l); top: var(--t); min-width: var(--w); min-height: var(--h); }
        @media (min-width: 768px) {
            .plano-girado { aspect-ratio: var(--ar-g); width: max(var(--min-pc-g), min(100%, var(--entra-pc-g), var(--max-pc-g))); }
            .plano-girado .plano-pos { left: var(--lg); top: var(--tg); width: var(--wg); height: var(--hg); }
            .plano-girado .btn-mesa-comanda { left: var(--lg); top: var(--tg); min-width: var(--wg); min-height: var(--hg); }
        }
    </style>
    <div class="col-span-full overflow-x-auto -mx-1 px-1 pb-2">
        @php
            $alto = $alto ?? \App\Support\PlanoMesas::ALTO;
            $ancho = $ancho ?? \App\Support\PlanoMesas::ANCHO;
            $minMovil = round($ancho * \App\Support\PlanoMesas::escalaMinima($enPlano, 100, 80));
            $minPc = round($ancho * \App\Support\PlanoMesas::escalaMinima($enPlano, 110, 88));
            $maxMovil = round($ancho * \App\Support\PlanoMesas::escalaMaxima($enPlano, 110));
            $maxPc = round($ancho * \App\Support\PlanoMesas::escalaMaxima($enPlano, 125));
            $girar = \App\Support\PlanoMesas::girarEnPc($alto, $ancho);
            // Girado, el ancho del lienzo en la PC corresponde al largo del local
            $giro = $girar ? '--ar: '.$ancho.' / '.$alto.'; --ar-g: '.$alto.' / '.$ancho.'; --min-pc-g: '.round($minPc * $alto / $ancho).'px; --max-pc-g: '
                .round($maxPc * $alto / $ancho).'px; --entra-pc-g: calc(85vh * '.$alto.' / '.$ancho.')' : 'aspect-ratio: '.$ancho.' / '.$alto;
        @endphp
        <div class="plano-lienzo {{ $girar ? 'plano-girado' : '' }} relative rounded-2xl bg-slate-50 ring-1 ring-slate-200" style="container-type: inline-size; {{ $giro }}; margin-inline: auto; --min-movil: {{ $minMovil }}px; --min-pc: {{ $minPc }}px; --max-movil: {{ $maxMovil }}px; --max-pc: {{ $maxPc }}px; --entra-pc: calc(85vh * {{ $ancho }} / {{ $alto }})">
            @foreach ($elementos ?? [] as $e)
                @php
                    $c = $e->color ?: (\App\Support\PlanoMesas::ELEMENTOS[$e->tipo][2] ?? '#475569');
                    $estiloElemento = match ($e->tipo) {
                        'PARED' => "background:{$c};border-radius:3px",
                        'TEXTO' => "color:{$c}",
                        'BARRA' => "background:{$c};color:#fff;border-radius:10px",
                        default => "background:{$c}22;border:2px solid {$c};color:{$c};border-radius:12px",
                    };
                @endphp
                <div class="plano-pos pointer-events-none absolute z-10 flex items-center justify-center text-center font-bold leading-tight"
                     style="{{ $girar ? \App\Support\PlanoMesas::variables($e->x, $e->y, $e->w, $e->h, $alto, $ancho) : \App\Support\PlanoMesas::estilo($e->x, $e->y, $e->w, $e->h, $alto, $ancho) }};{{ $estiloElemento }};font-size: clamp(11px, 1.7cqw, 16px)">
                    {{ trim((\App\Support\PlanoMesas::ELEMENTOS[$e->tipo][0] ?? '').' '.$e->texto) }}</div>
            @endforeach
            @foreach ($enPlano as $m)
                @include('empresas.comandas.partials.mesa_boton', ['m' => $m, 'plano' => true, 'alto' => $alto, 'ancho' => $ancho, 'girar' => $girar])
            @endforeach
        </div>
    </div>
    @php $sinUbicar = $mesas->whereNull('plano_x'); @endphp
    @if ($sinUbicar->isNotEmpty())
        <p class="col-span-full mt-1 text-xs font-semibold text-gray-500">Mesas que aún no están en el plano:</p>
        @foreach ($sinUbicar as $m)
            @include('empresas.comandas.partials.mesa_boton', ['m' => $m, 'plano' => false])
        @endforeach
    @endif
@endif
