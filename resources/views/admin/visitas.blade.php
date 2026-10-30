@extends('admin.layout')
@section('title', 'Visitas web')

@section('content')
    @php
        $maxDia = max(1, $serie->max('personas'));
        $maxHora = max(1, (int) $porHora->max());
        $barra = fn ($valor, $max) => max($valor > 0 ? 3 : 0, round($valor / $max * 100));
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Visitas a {{ config('tenancy.dominio') }}</h1>
            <p class="text-sm text-slate-500">Personas que entraron a tu página principal. No cuenta robots ni a ti mismo.</p>
        </div>
        <div class="ml-auto flex gap-1 p-1 bg-white rounded-xl shadow-sm text-sm">
            @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $n => $texto)
                <a href="{{ route('admin.visitas.index', ['dias' => $n]) }}" class="px-3 py-1.5 rounded-lg font-semibold {{ $dias === $n ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $texto }}</a>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        @foreach (['Hoy' => $hoy, 'Últimos 7 días' => $semana, 'Últimos 30 días' => $mes] as $titulo => $r)
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-slate-500 uppercase">{{ $titulo }}</p>
                <p class="text-3xl font-extrabold text-indigo-700">{{ number_format($r->personas) }}</p>
                <p class="text-xs text-slate-400">personas · {{ number_format($r->vistas) }} páginas vistas</p>
            </div>
        @endforeach
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500 uppercase">Se registraron ({{ $dias }} días)</p>
            <p class="text-3xl font-extrabold text-emerald-600">{{ $registros }}</p>
            <p class="text-xs text-slate-400">{{ $conversion }}% de los visitantes · {{ $mensajes }} mensaje(s) de contacto</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5 mb-4">
        <p class="font-bold mb-3">Personas por día <span class="text-sm font-normal text-slate-400">· {{ number_format($personas) }} en {{ $dias }} días</span></p>
        <div class="flex items-end gap-[2px] h-40">
            @foreach ($serie as $d)
                <div class="flex-1 h-full flex flex-col justify-end group relative" title="{{ \Carbon\Carbon::parse($d->dia)->format('d/m') }}: {{ $d->personas }}">
                    <div class="rounded-t bg-indigo-500 group-hover:bg-indigo-700" style="height: {{ $barra($d->personas, $maxDia) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-between text-[11px] text-slate-400 mt-1">
            <span>{{ \Carbon\Carbon::parse($serie->first()->dia)->format('d/m') }}</span><span>Hoy</span>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm p-5 min-w-0">
            <p class="font-bold mb-3">Páginas más vistas</p>
            @forelse ($paginas as $pg)
                <div class="flex justify-between gap-2 py-1.5 border-b border-slate-100 last:border-0 text-sm">
                    <span class="font-mono text-xs truncate">{{ $pg->ruta === '/' ? '/ (inicio)' : $pg->ruta }}</span>
                    <span class="whitespace-nowrap"><b>{{ $pg->vistas }}</b> <span class="text-xs text-slate-400">({{ $pg->personas }} pers.)</span></span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Aún no hay visitas.</p>
            @endforelse
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 min-w-0">
            <p class="font-bold mb-3">¿De dónde llegan?</p>
            @php $maxOrigen = max(1, (int) $origenes->max('personas')); @endphp
            @forelse ($origenes as $o)
                <div class="py-1.5 text-sm">
                    <div class="flex justify-between"><span class="capitalize">{{ $o->origen === 'Directo' ? 'Directo (escribió la dirección)' : $o->origen }}</span><b>{{ $o->personas }}</b></div>
                    <div class="h-1.5 rounded-full bg-slate-100 mt-1"><div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ $barra($o->personas, $maxOrigen) }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-slate-400">Aún no hay visitas.</p>
            @endforelse
            <p class="text-[11px] text-slate-400 mt-3 break-words">Tip: en tus anuncios usa enlaces como {{ config('tenancy.dominio') }}/?utm_source=facebook para saber cuál funciona.</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 min-w-0">
            <p class="font-bold mb-3">Dispositivo</p>
            @php $totalDisp = max(1, (int) $dispositivos->sum('personas')); @endphp
            @foreach ($dispositivos as $d)
                <div class="flex justify-between py-1 text-sm"><span>{{ ['Celular' => '📱', 'PC' => '💻', 'Tablet' => '📟'][$d->dispositivo] ?? '' }} {{ $d->dispositivo }}</span>
                    <b>{{ round($d->personas / $totalDisp * 100) }}%</b></div>
            @endforeach
            <p class="font-bold mt-5 mb-2">Horas con más visitas</p>
            <div class="flex items-end gap-[2px] h-20">
                @foreach (range(0, 23) as $h)
                    <div class="flex-1 h-full flex flex-col justify-end" title="{{ $h }}:00 · {{ (int) ($porHora[$h] ?? 0) }} vistas">
                        <div class="rounded-t bg-amber-400" style="height: {{ $barra((int) ($porHora[$h] ?? 0), $maxHora) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[11px] text-slate-400 mt-1"><span>0h</span><span>12h</span><span>23h</span></div>
        </div>
    </div>

    {{-- Desde dónde entran: países y ciudades del Perú (aproximado, por la conexión) --}}
    <div class="grid lg:grid-cols-2 gap-4 mt-4">
        <div class="bg-white rounded-2xl shadow-sm p-5 min-w-0">
            <p class="font-bold mb-3">🌎 ¿Desde qué país?</p>
            @php $maxPais = max(1, (int) $paises->max('personas')); @endphp
            @forelse ($paises as $p)
                <div class="py-1.5 text-sm">
                    <div class="flex justify-between gap-2"><span>{{ \App\Support\GeoIp::bandera($p->pais) }} {{ \App\Support\GeoIp::nombrePais($p->pais) }}</span><b>{{ $p->personas }}</b></div>
                    <div class="h-1.5 rounded-full bg-slate-100 mt-1"><div class="h-1.5 rounded-full bg-sky-500" style="width: {{ $barra($p->personas, $maxPais) }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-slate-400">Todavía no hay visitas con ubicación. Se empiezan a contar desde hoy.</p>
            @endforelse
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 min-w-0">
            <p class="font-bold mb-3">📍 Ciudades del Perú</p>
            @php $maxCiudad = max(1, (int) $ciudades->max('personas')); @endphp
            @forelse ($ciudades as $c)
                <div class="py-1.5 text-sm">
                    @php $region = trim(str_ireplace(' region', '', (string) $c->region)); @endphp
                    <div class="flex justify-between gap-2"><span>{{ $c->ciudad }} @if ($region && $region !== $c->ciudad)<span class="text-xs text-slate-400">· {{ $region }}</span>@endif</span><b>{{ $c->personas }}</b></div>
                    <div class="h-1.5 rounded-full bg-slate-100 mt-1"><div class="h-1.5 rounded-full bg-rose-500" style="width: {{ $barra($c->personas, $maxCiudad) }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-slate-400">Todavía no hay visitas del Perú con ubicación.</p>
            @endforelse
        </div>
        <p class="lg:col-span-2 text-xs text-slate-400 -mt-2">Es aproximado, según la conexión: muchos celulares figuran en Lima aunque estén en otra ciudad.
            @if ($sinUbicacion) {{ $sinUbicacion }} persona(s) del periodo entraron antes de que se guardara la ubicación o desde una conexión desconocida.@endif</p>
    </div>

    {{-- Negocios que crearon su carta gratis (posibles clientes) --}}
    <div class="bg-white rounded-2xl shadow-sm mt-4 overflow-x-auto">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <p class="font-bold">📋 Cartas creadas en "Crea tu carta"</p>
            <span class="text-sm text-slate-400 hidden sm:inline">· {{ $cartasPeriodo }} en {{ $dias }} días · posibles clientes</span>
        </div>
        <table class="w-full text-sm tabla-movil">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-4 py-2">Negocio</th><th class="text-left px-4 py-2">Contacto</th><th class="text-center px-4 py-2">Visitas</th><th class="text-left px-4 py-2">Creada</th><th class="px-4 py-2"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($cartas as $c)
                    @php $wa = preg_replace('/\D/', '', (string) $c->whatsapp); @endphp
                    <tr class="{{ $c->estado === 'ACTIVA' ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2"><a href="{{ \App\Http\Controllers\CartaWebController::url($c) }}" target="_blank" class="font-semibold text-indigo-700 hover:underline">{{ $c->nombre }}</a>
                            <span class="block text-xs text-slate-400">{{ $c->direccion }}</span></td>
                        <td class="px-4 py-2 text-xs">{{ $c->email }}@if ($wa)<br><a href="https://wa.me/{{ strlen($wa) === 9 ? '51'.$wa : $wa }}" target="_blank" class="text-emerald-600 font-semibold">WhatsApp {{ $c->whatsapp }}</a>@endif</td>
                        <td class="px-4 py-2 text-center font-semibold">{{ $c->visitas }}</td>
                        <td class="px-4 py-2 text-xs">{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('admin.cartas.estado', $c->id) }}">@csrf
                                <button class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $c->estado === 'ACTIVA' ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700' }}">{{ $c->estado === 'ACTIVA' ? 'Bloquear' : 'Activar' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Aún nadie creó su carta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
