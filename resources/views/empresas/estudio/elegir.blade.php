@extends('layouts.app')
@section('title', 'Elige la empresa')
@section('content')
@php
    // Cuándo vence la declaración del mes (y si ya se hizo), para ver de un vistazo cuál atender primero
    $vence = function (?object $v): ?array {
        if (! $v) {
            return null;
        }
        $fecha = $v->vence->locale('es')->isoFormat('D MMM');

        return match (true) {
            (bool) $v->declarado => ['texto' => '✔ declarado', 'tono' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
            $v->dias < 0 => ['texto' => "venció el {$fecha}", 'tono' => 'bg-rose-100 text-rose-800 ring-rose-300'],
            $v->dias === 0 => ['texto' => 'vence HOY', 'tono' => 'bg-rose-100 text-rose-800 ring-rose-300'],
            $v->dias <= 3 => ['texto' => "vence {$fecha} · en {$v->dias} día(s)", 'tono' => 'bg-amber-50 text-amber-800 ring-amber-200'],
            default => ['texto' => "vence {$fecha}", 'tono' => 'bg-gray-50 text-gray-500 ring-gray-200'],
        };
    };
    $lista =$empresas->map(fn ($e) => ['ruc' => (string) $e->IdEmpresa, 'nombre' => $e->NomEmpresa, 'propia' => (bool) $e->propia,
        'digito' => substr((string) $e->IdEmpresa, -1), 'buscar' => mb_strtolower(\Illuminate\Support\Str::ascii($e->NomEmpresa)).' '.$e->IdEmpresa,
        'vence' => $vence($vencimientos->get($e->IdEmpresa))])->values();
@endphp
<div class="max-w-4xl mx-auto space-y-4" x-data="elegirEmpresa(@js($lista), @js($actual))">
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-800">🏛️ ¿Con qué empresa vas a trabajar?</h1>
            <p class="text-sm text-gray-500">Tienes {{ $empresas->count() }} empresa(s). Puedes cambiar de empresa cuando quieras desde la parte de arriba.</p>
        </div>
        @php
            $pendientes = $vencimientos->filter(fn ($v) => ! $v->declarado);
            $urgentes = $pendientes->filter(fn ($v) => $v->dias <= 3)->count();
        @endphp
        <a href="{{ route('estudio.vencimientos') }}" class="flex flex-wrap items-center justify-between gap-2 rounded-xl px-4 py-3 text-sm ring-1 {{ $urgentes ? 'bg-rose-50 ring-rose-200 text-rose-800' : 'bg-indigo-50 ring-indigo-100 text-indigo-800' }} hover:brightness-95">
            <span>📅 <b>Vencimientos SUNAT de {{ \App\Support\Contabilidad\Contabilidad::nombrePeriodo($periodo) }}:</b>
                {{ $vencimientos->count() - $pendientes->count() }} de {{ $vencimientos->count() }} declaradas{{ $urgentes ? " · {$urgentes} vencen en 3 días o menos (o ya vencieron)" : '' }}</span>
            <span class="font-bold">Ver cronograma →</span>
        </a>
        @if ($descarga)
            <a href="{{ route('estudio.descargas', ['periodo' => $descarga['periodo']]) }}" class="flex flex-wrap items-center justify-between gap-2 rounded-xl px-4 py-3 text-sm ring-1 {{ $descarga['errores'] ? 'bg-amber-50 ring-amber-200 text-amber-900' : 'bg-sky-50 ring-sky-100 text-sky-900' }} hover:brightness-95">
                <span>📥 <b>Descarga de {{ \App\Support\Contabilidad\Contabilidad::nombrePeriodo($descarga['periodo']) }}:</b>
                    {{ $descarga['pendientes'] ? $descarga['pendientes'].' en curso · ' : '' }}{{ $descarga['listas'] }} listas{{ $descarga['errores'] ? ' · '.$descarga['errores'].' con error' : '' }}{{ $descarga['omitidas'] ? ' · '.$descarga['omitidas'].' sin credenciales' : '' }}</span>
                <span class="font-bold">Ver detalle →</span>
            </a>
        @endif
        <input x-ref="buscar" x-model="texto" @keydown.enter.prevent="entrarPrimera()" autofocus type="search"
               placeholder="Busca por nombre o RUC…" class="w-full h-12 rounded-xl border-gray-300 text-base">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-gray-500">Último dígito del RUC:</span>
            <button type="button" @click="digito = null" class="h-9 px-3 rounded-full font-semibold ring-1" :class="digito === null ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-700 ring-gray-200'">Todos</button>
            @foreach (range(0, 9) as $d)
                <button type="button" @click="digito = digito === '{{ $d }}' ? null : '{{ $d }}'" @disabled(! ($digitos[(string) $d] ?? 0))
                        class="h-9 min-w-9 px-3 rounded-full font-bold ring-1 disabled:opacity-30"
                        :class="digito === '{{ $d }}' ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-200'"
                        title="{{ $digitos[(string) $d] ?? 0 }} empresa(s)">{{ $d }}</button>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
        <template x-for="e in visibles" :key="e.ruc">
            <form method="POST" action="{{ route('estudio.cambiar') }}">
                @csrf
                <input type="hidden" name="ruc" :value="e.ruc">
                <button class="w-full flex items-center gap-3 px-4 sm:px-5 py-3 text-left hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none" :class="e.ruc === actual ? 'bg-indigo-50/60' : ''">
                    <span class="h-10 w-10 shrink-0 rounded-xl flex items-center justify-center text-lg" :class="e.propia ? 'bg-indigo-100' : 'bg-gray-100'" x-text="e.propia ? '🏛️' : '🏢'"></span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-bold text-gray-800 truncate" x-text="e.nombre"></span>
                        <span class="block text-sm font-mono text-gray-500"><span x-text="e.ruc.slice(0, -1)"></span><b class="text-indigo-700" x-text="e.digito"></b>
                            <span class="font-sans text-xs" x-show="e.propia"> · tu estudio</span>
                            <span class="font-sans text-xs text-indigo-700" x-show="e.ruc === actual"> · la última que usaste</span></span>
                    </span>
                    <span x-show="e.vence" class="hidden sm:inline-flex shrink-0 h-7 px-2.5 items-center rounded-full text-xs font-semibold ring-1" :class="e.vence?.tono" x-text="e.vence?.texto"></span>
                    <span class="shrink-0 h-9 px-4 inline-flex items-center rounded-xl bg-indigo-600 text-white text-sm font-bold">Entrar →</span>
                </button>
            </form>
        </template>
        <p x-show="!visibles.length" class="px-5 py-10 text-center text-gray-400">No hay empresas con esa búsqueda.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('elegirEmpresa', (empresas, actual) => ({
            empresas, actual, texto: '', digito: null,
            get visibles() {
                const t = this.texto.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                return this.empresas.filter(e => (this.digito === null || e.digito === this.digito) && (!t || e.buscar.includes(t)));
            },
            // Enter en el buscador entra a la primera de la lista
            entrarPrimera() { this.$root.querySelector('form button')?.click(); },
        }));
    });
</script>
@endpush
