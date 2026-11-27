{{-- Qué cambió exactamente en una alerta: campo, valor anterior y valor nuevo (las claves salen solo como "cambió") --}}
@php($mostrar = fn ($v, $campo = null) => \App\Support\Bitacora::mostrar(is_array($v) ? implode(', ', $v) : $v, $campo))
@if (! empty($cambios))
    <ul class="mt-1.5 space-y-0.5 text-xs">
        @foreach (array_slice($cambios, 0, 20, true) as $clave => $c)
            @php($campo = \App\Support\Bitacora::campo($clave, $c))
            <li class="flex flex-wrap items-baseline gap-x-1.5">
                <span class="font-semibold text-slate-700">{{ $c['etiqueta'] ?? '' }}:</span>
                @if (isset($c['agrego']) || isset($c['quito']))
                    @foreach ($c['agrego'] ?? [] as $x)<span class="px-1 rounded bg-emerald-100 text-emerald-800">+ {{ $x }}</span>@endforeach
                    @foreach ($c['quito'] ?? [] as $x)<span class="px-1 rounded bg-rose-100 text-rose-800 line-through">{{ $x }}</span>@endforeach
                @elseif (($c['despues'] ?? null) === '(cambió)')
                    <span class="text-amber-700 font-semibold">cambió (no se muestra por seguridad)</span>
                @else
                    <span class="px-1 rounded bg-rose-50 text-rose-700 line-through">{{ $mostrar($c['antes'] ?? null, $campo) }}</span>
                    <span class="text-slate-400">→</span>
                    <span class="px-1 rounded bg-emerald-50 text-emerald-800 font-semibold">{{ $mostrar($c['despues'] ?? null, $campo) }}</span>
                @endif
            </li>
        @endforeach
        @if (count($cambios) > 20)<li class="text-slate-400">y {{ count($cambios) - 20 }} cambio(s) más (míralos en la bitácora en Excel)</li>@endif
    </ul>
@endif
