{{-- Campanita: comprobantes pendientes de SUNAT --}}
@if (!empty($notifSunat))
    <div class="relative" id="campana-sunat" x-data="{ abierto: false }" @click.outside="abierto = false" @keydown.escape.window="abierto = false">
        <button type="button" @click="abierto = !abierto" class="relative p-2 rounded-full hover:bg-gray-100 text-gray-600"
                title="Comprobantes pendientes de SUNAT">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            @if ($notifSunat['total'] > 0)
                <span class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full text-[11px] font-bold text-white flex items-center justify-center {{ $notifSunat['vencidas'] > 0 ? 'bg-red-600 animate-pulse' : 'bg-orange-500' }}">
                    {{ $notifSunat['total'] > 99 ? '99+' : $notifSunat['total'] }}
                </span>
            @endif
        </button>

        <div x-show="abierto" x-cloak x-transition
             class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <p class="font-semibold text-gray-800 text-sm">Comprobantes pendientes de SUNAT</p>
                @if ($notifSunat['vencidas'] > 0)
                    <p class="text-xs text-red-600 mt-0.5">⚠️ {{ $notifSunat['vencidas'] }} con 5 días o más: envíalos antes de que venza el plazo.</p>
                @endif
            </div>
            @forelse ($notifSunat['items'] as $n)
                @php
                    $esResumen = $n->tdocod === '03' || (in_array($n->tdocod, ['07', '08']) && str_starts_with($n->serdoc, 'B'));
                    $link = $esResumen ? route('sunat.resumenes', ['fecha' => $n->ccafem]) : route('sunat.envios');
                    $viejo = \Carbon\Carbon::parse($n->ccafem)->lte(now()->subDays(5));
                @endphp
                <a href="{{ $link }}" class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-gray-50 border-b border-gray-50 text-sm">
                    <span>
                        <span class="font-semibold text-gray-700">{{ $n->serdoc }}-{{ str_pad($n->numdoc, 8, '0', STR_PAD_LEFT) }}</span>
                        <span class="block text-xs {{ $viejo ? 'text-red-600' : 'text-gray-400' }}">
                            {{ \Carbon\Carbon::parse($n->ccafem)->format('d/m/Y') }} · S/ {{ number_format($n->ccaitv, 2) }} · {{ $esResumen ? 'va en resumen' : 'envío individual' }}
                        </span>
                    </span>
                    @include('empresas.sunat._estado', ['estado' => $n->est_sunat])
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-400">✔ Todo enviado a SUNAT.</p>
            @endforelse
            @if ($notifSunat['total'] > 0)
                <div class="grid grid-cols-2 text-center text-xs font-semibold">
                    <a href="{{ route('sunat.envios') }}" class="py-2.5 text-indigo-600 hover:bg-indigo-50 border-r border-gray-100">Envío individual</a>
                    <a href="{{ route('sunat.resumenes') }}" class="py-2.5 text-indigo-600 hover:bg-indigo-50">Resumen diario</a>
                </div>
                @if ($notifSunat['total'] > count($notifSunat['items']))
                    <p class="text-center text-xs text-gray-400 pb-2">y {{ $notifSunat['total'] - count($notifSunat['items']) }} más…</p>
                @endif
            @endif
        </div>
    </div>
@endif
