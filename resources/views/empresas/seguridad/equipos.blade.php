@extends('layouts.app')
@section('title', 'Equipos y sesiones')

@section('content')
<div class="max-w-6xl mx-auto space-y-4">
    @include('empresas.partials.alert')

    <div>
        <h1 class="text-xl font-bold text-gray-800">🔐 Equipos y sesiones</h1>
        <p class="text-sm text-gray-500">Desde qué PC, celular o tablet entra cada usuario. Un equipo nuevo queda <b>por revisar</b> y se te avisa:
            si fue tu trabajador, confírmalo; si no, bloquéalo («no fui yo») y cámbiale la clave.</p>
    </div>

    @unless ($deConfianza)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            ⚠️ <b>Este equipo todavía no es de confianza.</b> Para confirmar o bloquear equipos entra desde tu PC o celular de siempre;
            ahí podrás confirmar también este.
        </div>
    @endunless

    @forelse ($porUsuario as $equipos)
        @php($u = $equipos->first())
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <header class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                <span class="font-bold text-gray-800">{{ $u->apeusu }}</span>
                <span class="text-xs text-gray-400">{{ $u->usuario_login }}</span>
                @if ($equipos->sum('abiertas'))<span class="ml-auto text-xs font-semibold text-emerald-700">● {{ $equipos->sum('abiertas') }} sesión(es) abierta(s)</span>@endif
            </header>
            <div class="divide-y divide-gray-100">
                @foreach ($equipos as $e)
                    @php($esEste = $actual && (int) $actual->id === (int) $e->id)
                    <div class="px-4 py-3 flex flex-wrap items-center gap-3 {{ $e->por_revisar && ! $e->bloqueado_el ? 'bg-amber-50' : ($e->bloqueado_el ? 'bg-rose-50/60' : '') }}">
                        <span class="text-2xl">{{ $e->tipo === 'Celular' ? '📱' : ($e->tipo === 'Tablet' ? '📲' : '💻') }}</span>
                        <div class="flex-1 min-w-[16rem]">
                            <form method="POST" action="{{ route('seguridad.equipos.accion', $e->id) }}" class="flex items-center gap-2" x-data="{ editar: false }">
                                @csrf <input type="hidden" name="accion" value="nombre">
                                <p x-show="!editar" class="font-semibold text-gray-800">{{ $e->nombre }}
                                    @if ($esEste)<span class="ml-1 text-[11px] font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">ESTE EQUIPO</span>@endif
                                    <button type="button" @click="editar = true" class="ml-1 text-xs font-normal text-indigo-600 hover:underline">renombrar</button></p>
                                <template x-if="editar">
                                    <span class="flex gap-1"><input name="nombre" value="{{ $e->nombre }}" maxlength="100" placeholder="Ej. CAJA 1, CELULAR DE JUAN" class="h-8 rounded-lg border-gray-300 text-sm uppercase">
                                        <button class="h-8 px-2 rounded-lg bg-indigo-600 text-white text-xs font-bold">Guardar</button></span>
                                </template>
                            </form>
                            <p class="text-xs text-gray-500">{{ $e->navegador }} en {{ $e->sistema }}@if ($e->equipo_pc) · PC {{ $e->equipo_pc }}@endif
                                · IP {{ $e->ip }}@if ($e->ciudad) ({{ $e->ciudad }})@endif</p>
                            <p class="text-xs text-gray-400">Primera vez {{ \Carbon\Carbon::parse($e->primera_vez)->format('d/m/Y H:i') }} · última {{ \Carbon\Carbon::parse($e->ultima_vez)->diffForHumans() }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($e->bloqueado_el)
                                <span class="text-xs font-bold px-2 py-1 rounded-full bg-rose-100 text-rose-700">⛔ Bloqueado {{ \Carbon\Carbon::parse($e->bloqueado_el)->format('d/m H:i') }}</span>
                                <form method="POST" action="{{ route('seguridad.equipos.accion', $e->id) }}">@csrf <input type="hidden" name="accion" value="confiar">
                                    <button class="h-8 px-3 rounded-lg bg-white border border-gray-200 text-xs font-semibold">Desbloquear</button></form>
                            @else
                                @if ($e->por_revisar)
                                    <span class="text-xs font-bold px-2 py-1 rounded-full bg-amber-200 text-amber-900">Nuevo · por revisar</span>
                                    <form method="POST" action="{{ route('seguridad.equipos.accion', $e->id) }}">@csrf <input type="hidden" name="accion" value="confiar">
                                        <button class="h-8 px-3 rounded-lg bg-emerald-600 text-white text-xs font-bold">✓ Sí, fue él</button></form>
                                @elseif ($e->confiable)
                                    <span class="text-xs font-bold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">✓ De confianza</span>
                                @endif
                                @if ($e->abiertas)
                                    <form method="POST" action="{{ route('seguridad.equipos.accion', $e->id) }}">@csrf <input type="hidden" name="accion" value="cerrar">
                                        <button class="h-8 px-3 rounded-lg bg-white border border-gray-200 text-xs font-semibold" @disabled($esEste)>Cerrar sesión</button></form>
                                @endif
                                @unless ($esEste)
                                    <form method="POST" action="{{ route('seguridad.equipos.accion', $e->id) }}" onsubmit="return confirm('¿Bloquear este equipo? Se cierra su sesión y ya no podrá entrar.')">
                                        @csrf <input type="hidden" name="accion" value="bloquear">
                                        <button class="h-8 px-3 rounded-lg bg-rose-600 text-white text-xs font-bold">⛔ No fui yo · bloquear</button></form>
                                @endunless
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-center text-gray-400 py-10">Todavía no hay equipos registrados: aparecen cuando los usuarios entran al sistema.</p>
    @endforelse

    <p class="text-xs text-gray-400">El nombre del equipo lo da el navegador (Chrome en Windows, Android…): ponle un nombre fácil con «renombrar». Las PCs con el agente de impresión muestran su nombre de PC.</p>
</div>
@endsection
