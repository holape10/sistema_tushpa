{{-- Estudio contable: en qué empresa se está trabajando y el cambio a otra (solo las que el usuario puede ver) --}}
@if (\App\Support\Estudio::activo())
    @php
        $empresasEstudio = \App\Support\Estudio::empresas(auth()->user());
        $empresaActual = $empresasEstudio->firstWhere('IdEmpresa', (string) auth()->user()->IdEmpresa);
        $enCliente = $empresaActual && ! $empresaActual->propia;
    @endphp
    <div class="relative" x-data="{ abierto: false, filtro: '' }" @keydown.escape.window="abierto = false">
        <button type="button" @click="abierto = !abierto; $nextTick(() => $refs.buscarEmpresa?.focus())"
                class="inline-flex max-w-[11rem] sm:max-w-xs items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold ring-1 {{ $enCliente ? 'bg-amber-100 text-amber-900 ring-amber-300' : 'bg-indigo-50 text-indigo-700 ring-indigo-200' }}"
                title="Empresa en la que trabajas">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
            <span class="truncate">{{ $empresaActual->NomEmpresa ?? 'Elige empresa' }}</span>
            <span class="hidden sm:inline font-mono font-semibold opacity-70">{{ auth()->user()->IdEmpresa }}</span>
            <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div x-show="abierto" x-cloak @click.outside="abierto = false"
             class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl bg-white p-2 shadow-2xl ring-1 ring-slate-200">
            <input x-ref="buscarEmpresa" x-model="filtro" placeholder="Buscar empresa o RUC…" class="mb-2 w-full h-10 rounded-xl border-slate-300 text-sm">
            <div class="max-h-80 overflow-y-auto">
                @foreach ($empresasEstudio as $emp)
                    <form method="POST" action="{{ route('estudio.cambiar') }}"
                          x-show="!filtro || @js(mb_strtolower($emp->NomEmpresa.' '.$emp->IdEmpresa)).includes(filtro.toLowerCase())">
                        @csrf<input type="hidden" name="ruc" value="{{ $emp->IdEmpresa }}">
                        <button class="w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-slate-50 {{ $emp->IdEmpresa === (string) auth()->user()->IdEmpresa ? 'bg-indigo-50' : '' }}">
                            <span class="block font-semibold text-slate-800 truncate">{{ $emp->propia ? '🏛️ ' : '' }}{{ $emp->NomEmpresa }}</span>
                            <span class="block text-xs font-mono text-slate-400">{{ $emp->IdEmpresa }}{{ $emp->propia ? ' · tu estudio' : '' }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
            <a href="{{ route('estudio.elegir') }}" class="mt-1 block rounded-xl border-t border-slate-100 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">📋 Ver todas mis empresas</a>
            <a href="{{ route('estudio.vencimientos') }}" class="mt-1 block rounded-xl border-t border-slate-100 px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-50">📅 Vencimientos SUNAT</a>
            @if (auth()->user()->esAdmin() || auth()->user()->tieneModulo('/sire/compras') || auth()->user()->tieneModulo('/sire/ventas'))
                <a href="{{ route('sire.bandeja') }}" class="mt-1 block rounded-xl border-t border-slate-100 px-3 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-50">📥 Bandeja SIRE (todas las empresas)</a>
            @endif
            @if (auth()->user()->esAdmin())
                <a href="{{ route('estudio.index') }}" class="mt-1 block rounded-xl border-t border-slate-100 px-3 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-50">⚙ Empresas del estudio</a>
            @endif
        </div>
    </div>
@endif
