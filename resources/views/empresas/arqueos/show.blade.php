@extends('layouts.app')
@section('title', 'Arqueo N° '.$a->id)
@section('content')
@php
    $billetes = json_decode((string) $a->billetes, true) ?: [];
    $nombre = fn ($campo) => ($v = $denominaciones[$campo]) < 1 ? number_format($v * 100).' cént.' : 'S/ '.$v;
    $dif = (float) $a->diferencia;
@endphp
<div class="max-w-md mx-auto space-y-4">
    @include('empresas.partials.alert')
    <div class="flex justify-between print:hidden">
        <a href="{{ route('arqueos.index') }}" class="text-sm text-indigo-600 font-semibold">← Arqueos</a>
        <button type="button" onclick="window.print()" class="h-9 px-4 rounded-xl bg-gray-100 text-sm font-bold">🖨 Imprimir</button>
    </div>
    {{-- Ticket del arqueo (80 mm al imprimir) --}}
    <div class="bg-white rounded-2xl shadow-sm p-5 text-sm print:shadow-none print:p-0">
        <p class="text-center font-extrabold">{{ $negocio }}</p>
        <p class="text-center font-bold">ARQUEO DE CAJA N° {{ $a->id }}</p>
        <p class="text-center text-xs text-gray-500">{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i') }}</p>
        <div class="mt-3 space-y-0.5">
            <p>Caja: turno N° {{ $a->turno }} de <b>{{ $a->nombre_cajero }}</b></p>
            <p>Contó: <b>{{ $a->conto }}</b></p>
            @if ($a->observacion)<p>Obs.: {{ $a->observacion }}</p>@endif
        </div>
        @if ($billetes)
            <table class="w-full mt-3 text-xs">
                @foreach ($billetes as $campo => $n)
                    <tr><td>{{ $nombre($campo) }}</td><td class="text-right">× {{ $n }}</td><td class="text-right">S/ {{ number_format($n * ($denominaciones[$campo] ?? 0), 2) }}</td></tr>
                @endforeach
            </table>
        @endif
        <div class="mt-3 border-t border-dashed border-gray-300 pt-2 space-y-1">
            <p class="flex justify-between"><span>Contado</span><b>S/ {{ number_format($a->contado, 2) }}</b></p>
            @if ($ciego)
                <p class="text-xs text-gray-500">Cierre ciego: cuánto debía haber lo ve el administrador.</p>
            @else
                <p class="flex justify-between"><span>Debía haber</span><b>S/ {{ number_format($a->esperado, 2) }}</b></p>
                <p class="flex justify-between text-base font-extrabold {{ $dif < 0 ? 'text-rose-600' : ($dif > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                    <span>{{ $dif == 0 ? 'CUADRA' : ($dif < 0 ? 'FALTA' : 'SOBRA') }}</span><span>S/ {{ number_format(abs($dif), 2) }}</span></p>
            @endif
        </div>
        <div class="mt-10 grid grid-cols-2 gap-6 text-center text-xs">
            <p class="border-t border-gray-400 pt-1">Cajero</p><p class="border-t border-gray-400 pt-1">Quien contó</p>
        </div>
    </div>
</div>
@endsection
