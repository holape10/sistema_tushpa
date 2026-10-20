{{-- Varias cajas: en qué PC está este navegador (sus comprobantes y comandas salen en las impresoras de esa PC) --}}
@php
    $equiposCaja = auth()->user()->esAdminOCaja() ? \App\Support\Impresion\Impresion::equipos((int) auth()->user()->id_empresa_negocio) : collect();
    $equipoCaja = $equiposCaja->isNotEmpty() ? \App\Support\Impresion\Impresion::equipo((int) auth()->user()->id_empresa_negocio)?->pc : null;
@endphp
@if ($equiposCaja->isNotEmpty())
    <div class="relative" x-data="{ abierto: false }" @click.outside="abierto = false">
        <button type="button" @click="abierto = !abierto" title="¿En qué caja (PC) estás? Aquí salen tus comprobantes y comandas"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold {{ $equipoCaja ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-amber-100 text-amber-800 hover:bg-amber-200 animate-pulse' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
            {{ $equipoCaja ?? '¿Qué caja?' }}
        </button>
        <form x-show="abierto" x-cloak method="POST" action="{{ route('impresion.equipo') }}"
              class="absolute right-0 mt-2 w-60 bg-white rounded-xl shadow-lg border p-2 z-50">
            @csrf
            <p class="px-2 pb-1 text-[11px] text-gray-500">Esta PC es… (se recuerda en este navegador)</p>
            @foreach ($equiposCaja as $e)
                <button name="pc" value="{{ $e->pc }}" class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-indigo-50 {{ $equipoCaja === $e->pc ? 'bg-green-50 text-green-700 font-bold' : 'text-gray-700' }}">
                    {{ $e->pc }} @if ($equipoCaja === $e->pc) ✔ @endif
                </button>
            @endforeach
            @if ($equipoCaja)
                <button name="pc" value="" class="w-full text-left px-3 py-2 rounded-lg text-xs text-gray-500 hover:bg-gray-50">No es una caja</button>
            @endif
        </form>
    </div>
@endif
