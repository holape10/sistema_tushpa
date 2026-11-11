@extends('layouts.app')
@section('title', 'Arqueo N° '.$a->id)
@section('content')
@php
    $billetes = json_decode((string) $a->billetes, true) ?: [];
    $foto = json_decode((string) ($a->resumen ?? ''), true) ?: null;
    $nombre = fn ($campo) => ($v = $denominaciones[$campo]) < 1 ? number_format($v * 100).' céntimos' : ($v < 10 ? 'Moneda S/ '.$v : 'Billete S/ '.$v);
    $dif = (float) $a->diferencia;
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    @include('empresas.partials.alert')
    <div class="flex justify-between print:hidden">
        <a href="{{ route('arqueos.index') }}" class="text-sm text-indigo-600 font-semibold">← Arqueos</a>
        <div class="flex gap-2">
            @if (! $ciego)<a href="{{ route('turnos.show', $a->id_turno) }}" target="_blank" class="h-9 px-4 inline-flex items-center rounded-xl bg-white ring-1 ring-gray-200 text-sm font-bold">📄 Ventas del turno</a>@endif
            <button type="button" onclick="window.print()" class="h-9 px-4 rounded-xl bg-gray-100 text-sm font-bold">🖨 Imprimir</button>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4 print:shadow-none print:p-0">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase text-gray-400">{{ $negocio }}</p>
                <h1 class="text-xl font-extrabold text-gray-800">Arqueo de caja N° {{ $a->id }}</h1>
                <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($a->created_at)->locale('es')->isoFormat('dddd D [de] MMMM, h:mm a') }}
                    · Caja: turno N° {{ $a->turno }} de <b>{{ $a->nombre_cajero }}</b> · Contó: <b>{{ $a->conto }}</b></p>
                @if ($a->observacion)<p class="text-sm text-gray-600">📌 {{ $a->observacion }}</p>@endif
            </div>
            {{-- Resultado --}}
            <div class="grid grid-cols-3 gap-2 text-center min-w-72">
                <div class="rounded-xl bg-gray-50 p-2"><p class="text-[11px] uppercase text-gray-500">Contado</p><p class="font-extrabold">S/ {{ number_format($a->contado, 2) }}</p></div>
                <div class="rounded-xl bg-gray-50 p-2"><p class="text-[11px] uppercase text-gray-500">Debía haber</p><p class="font-extrabold">{{ $ciego ? '—' : 'S/ '.number_format($a->esperado, 2) }}</p></div>
                <div class="rounded-xl p-2 {{ $ciego ? 'bg-gray-50' : ($dif < 0 ? 'bg-rose-50 text-rose-700' : ($dif > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700')) }}">
                    <p class="text-[11px] uppercase">{{ $ciego ? 'Diferencia' : ($dif == 0 ? 'Cuadra' : ($dif < 0 ? 'Falta' : 'Sobra')) }}</p>
                    <p class="font-extrabold">{{ $ciego ? '—' : 'S/ '.number_format(abs($dif), 2) }}</p></div>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4 items-start">
            {{-- Detalle de lo contado --}}
            <div class="rounded-2xl ring-1 ring-gray-200 p-4">
                <p class="text-xs font-bold uppercase text-gray-400 mb-2">Lo que se contó</p>
                @if ($billetes)
                    <table class="w-full text-sm">
                        @foreach (array_reverse($denominaciones, true) as $campo => $valor)
                            @if (! empty($billetes[$campo]))
                                <tr><td class="py-0.5">{{ $nombre($campo) }}</td><td class="text-right text-gray-500">× {{ $billetes[$campo] }}</td>
                                    <td class="text-right">S/ {{ number_format($billetes[$campo] * $valor, 2) }}</td></tr>
                            @endif
                        @endforeach
                        <tr class="border-t border-gray-200 font-extrabold"><td class="pt-1" colspan="2">Total</td><td class="pt-1 text-right">S/ {{ number_format($a->contado, 2) }}</td></tr>
                    </table>
                @else
                    <p class="text-sm text-gray-600">Se escribió el total: <b>S/ {{ number_format($a->contado, 2) }}</b> (sin el detalle de billetes).</p>
                @endif
            </div>
            <div class="md:col-span-2">
                @if ($foto && ! $ciego)
                    @include('empresas.arqueos._resumen', ['f' => $foto])
                @elseif ($ciego)
                    <p class="rounded-xl bg-slate-800 text-white px-4 py-3 text-sm">🙈 Cierre ciego: cuánto debía haber y la diferencia los ve el administrador.</p>
                @else
                    <p class="text-sm text-gray-400">Este arqueo es anterior al resumen detallado.</p>
                @endif
            </div>
        </div>

        <div class="pt-10 grid grid-cols-2 gap-10 text-center text-xs">
            <p class="border-t border-gray-400 pt-1">{{ $a->nombre_cajero }}<br>Cajero</p><p class="border-t border-gray-400 pt-1">{{ $a->conto }}<br>Quien contó</p>
        </div>
    </div>
</div>
@endsection
