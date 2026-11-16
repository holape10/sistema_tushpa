{{-- Ventas: correlativos que faltan en cada serie del periodo (1 2 3 6 → faltan 4–5). Variables: $comprobantes --}}
@php $faltantes = \App\Support\Contabilidad\SireDbf::correlativosFaltantes($comprobantes); @endphp
@if ($faltantes)
    <div class="rounded-xl bg-amber-50 border border-amber-300 text-amber-900 px-4 py-3 text-sm">
        <p class="font-bold">⚠ Revisar: faltan correlativos en {{ count($faltantes) }} serie(s)</p>
        <p class="text-xs text-amber-800 mb-1">Entre el primero y el último comprobante del mes que trae SUNAT. Puede ser uno que no se envió, uno anulado sin informar o un salto de numeración.</p>
        <ul class="space-y-0.5">
            @foreach ($faltantes as $f)
                <li><b class="font-mono">{{ $f['serie'] }}</b>: falta{{ $f['cantidad'] > 1 ? 'n' : '' }}
                    <span class="font-mono">{{ implode(', ', array_slice($f['faltan'], 0, 30)) }}{{ count($f['faltan']) > 30 ? '…' : '' }}</span>
                    <span class="text-xs text-amber-700">({{ $f['cantidad'] }})</span></li>
            @endforeach
        </ul>
    </div>
@endif
