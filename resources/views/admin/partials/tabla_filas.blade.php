{{-- Filas de una tabla o de una consulta del explorador de bases. Variables: $filas (list<array>), $titulos (list<string>) --}}
@if (! $filas)
    <p class="p-6 text-center text-sm text-slate-500">Sin filas.</p>
@else
    <div class="overflow-auto max-h-[65vh]">
        <table class="text-xs">
            <thead class="sticky top-0 bg-slate-100 text-slate-600">
                <tr>@foreach ($titulos as $titulo)<th class="px-3 py-2 text-left font-mono whitespace-nowrap">{{ $titulo }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($filas as $fila)
                    <tr class="hover:bg-amber-50">
                        @foreach ($titulos as $titulo)
                            @php $v = $fila[$titulo] ?? null; @endphp
                            <td class="px-3 py-1.5 font-mono whitespace-nowrap max-w-[320px] truncate {{ $v === null ? 'text-slate-300 italic' : '' }}" title="{{ is_scalar($v) ? $v : '' }}">{{ $v === null ? 'NULL' : (is_scalar($v) ? $v : json_encode($v)) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
