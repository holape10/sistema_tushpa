{{-- Foto del usuario del panel o, si no subió, sus iniciales. Variables: $usuario, $tamano (clases de tamaño) --}}
@php
    $iniciales = collect(preg_split('/\s+/', trim((string) $usuario->nombre)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
@if ($usuario->foto && is_file(public_path($usuario->foto)))
    <img src="{{ asset($usuario->foto) }}?v={{ @filemtime(public_path($usuario->foto)) }}" alt="{{ $usuario->nombre }}" class="{{ $tamano }} shrink-0 rounded-full object-cover ring-2 ring-white">
@else
    <span class="{{ $tamano }} shrink-0 rounded-full bg-indigo-100 text-indigo-700 font-black flex items-center justify-center ring-2 ring-white">{{ $iniciales ?: '?' }}</span>
@endif
