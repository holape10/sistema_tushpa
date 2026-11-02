{{--
    Mesas de un piso en Comandas: en cuadrícula (como siempre) o, si la sucursal lo eligió y el piso tiene plano, en el
    plano del local. Las mesas que aún no están en el plano salen debajo, en cuadrícula, para que ninguna se pierda.
    Usa $mesas (ComandasController::mesasDelPiso), $usaPlano y $elementos.
--}}
@php $enPlano = ($usaPlano ?? false) ? $mesas->whereNotNull('plano_x') : collect(); @endphp
@if ($enPlano->isEmpty())
    @include('empresas.comandas.partials.mesas_grid', ['mesas' => $mesas])
@else
    <div class="col-span-full overflow-x-auto -mx-1 px-1 pb-2">
        @php
            $alto = $alto ?? \App\Support\PlanoMesas::ALTO;
            $ancho = $ancho ?? \App\Support\PlanoMesas::ANCHO;
        @endphp
        <div class="plano-lienzo relative w-full rounded-2xl bg-slate-50 ring-1 ring-slate-200" style="aspect-ratio: {{ $ancho }} / {{ $alto }}; width: {{ round(100 * $ancho / max($ancho, 1000), 3) }}%; min-width: {{ round(640 * $ancho / max($ancho, 1000)) }}px">
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
                <div class="pointer-events-none absolute z-10 flex items-center justify-center text-center text-[11px] font-bold leading-tight sm:text-xs"
                     style="{{ \App\Support\PlanoMesas::estilo($e->x, $e->y, $e->w, $e->h, $alto, $ancho) }};{{ $estiloElemento }}">
                    {{ trim((\App\Support\PlanoMesas::ELEMENTOS[$e->tipo][0] ?? '').' '.$e->texto) }}</div>
            @endforeach
            @foreach ($enPlano as $m)
                @include('empresas.comandas.partials.mesa_boton', ['m' => $m, 'plano' => true, 'alto' => $alto, 'ancho' => $ancho])
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
