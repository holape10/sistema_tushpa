@extends('layouts.app')
@section('title', 'Sorteo de pensión')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $nombres = $candidatos->map(fn ($c) => ['nombre' => $c->nombres, 'grado' => \App\Support\Colegio::etiquetaGrado($c)])->values();
    @endphp

    <div class="space-y-5 max-w-5xl" x-data="sorteoColegio()">
        <div class="flex flex-wrap items-end gap-3">
            <div class="mr-auto">
                <a href="{{ route('colegio.index') }}" class="text-sm text-gray-500">← Alumnos</a>
                <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-gift text-amber-500"></i> Sorteo de una pensión gratis</h1>
                <p class="text-sm text-gray-500">Participan los padres puntuales: nunca pagaron tarde, no deben nada vencido y aún no pagan la pensión del mes que se sortea.</p>
            </div>
            <form>
                <select name="periodo" onchange="this.form.submit()" class="h-10 rounded-xl border-gray-300 text-sm font-semibold">
                    @foreach ($periodos as $p)<option value="{{ $p }}" @selected($p === $periodo)>Pensión de {{ \App\Support\Colegio::nombreMes($p) }}</option>@endforeach
                </select>
            </form>
        </div>

        <div class="rounded-3xl bg-gradient-to-br from-amber-400 via-orange-500 to-rose-500 text-white p-6 sm:p-10 text-center shadow-lg">
            <p class="text-sm font-bold uppercase tracking-widest opacity-90">Pensión de {{ \App\Support\Colegio::nombreMes($periodo) }}</p>
            <p class="mt-4 text-3xl sm:text-5xl font-black min-h-[3.5rem] break-words" x-text="pantalla.nombre || '{{ $candidatos->count() }} participantes'"></p>
            <p class="mt-1 text-lg font-semibold opacity-90 min-h-[1.75rem]" x-text="pantalla.grado"></p>
            <p x-show="ganador" x-cloak class="mt-3 text-lg font-bold">🎉 ¡Ganó la pensión gratis! <span x-text="ganador ? 'S/ ' + Number(ganador.monto).toFixed(2) : ''"></span></p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <button type="button" @click="sortear()" :disabled="girando || ganador || !{{ $candidatos->count() }}"
                        class="h-14 px-8 rounded-2xl bg-white text-orange-600 text-lg font-black shadow disabled:opacity-50">
                    <i class="fas fa-shuffle"></i> <span x-text="girando ? 'Sorteando…' : 'Sortear'"></span></button>
                <a x-show="ganador" x-cloak :href="ganador ? @js(url('colegio/alumnos')) + '/' + ganador.id : '#'" class="h-14 px-6 rounded-2xl bg-white/20 font-bold inline-flex items-center">Ver alumno</a>
            </div>
            @if ($candidatos->isEmpty())
                <p class="mt-4 text-sm font-semibold">No hay participantes para este mes (nadie cumple las condiciones o ya pagaron).</p>
            @endif
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            <div class="bg-white rounded-2xl shadow-sm">
                <h2 class="p-4 font-black text-gray-800 border-b border-gray-100">Participantes ({{ $candidatos->count() }})</h2>
                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    @forelse ($candidatos as $c)
                        <div class="px-4 py-2 flex justify-between gap-3 text-sm"><span class="font-semibold text-gray-800">{{ $c->nombres }}</span>
                            <span class="text-gray-500 text-right">{{ \App\Support\Colegio::etiquetaGrado($c) }}</span></div>
                    @empty
                        <p class="p-6 text-center text-sm text-gray-400">Nadie.</p>
                    @endforelse
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm">
                <h2 class="p-4 font-black text-gray-800 border-b border-gray-100">Ganadores anteriores</h2>
                <div class="divide-y divide-gray-100">
                    @forelse ($historial as $h)
                        <div class="px-4 py-2 text-sm flex justify-between gap-3">
                            <a href="{{ route('colegio.ver', $h->alu_id) }}" class="font-semibold text-gray-800 hover:text-indigo-600">{{ $h->nombres }}</a>
                            <span class="text-gray-500 text-right">{{ \App\Support\Colegio::nombreMes($h->periodo) }} · entre {{ $h->candidatos }} · {{ \Carbon\Carbon::parse($h->fecha)->format('d/m/Y') }}</span>
                        </div>
                    @empty
                        <p class="p-6 text-center text-sm text-gray-400">Aún no hay sorteos.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        function sorteoColegio() {
            const NOMBRES = @json($nombres);
            return {
                pantalla: {}, ganador: null, girando: false,
                async sortear() {
                    if (!confirm('¿Sortear la pensión de {{ \App\Support\Colegio::nombreMes($periodo) }} entre {{ $candidatos->count() }} participantes? El ganador no la pagará.')) return;
                    this.girando = true;
                    // Pasan los nombres mientras el servidor elige al ganador
                    let i = 0;
                    const ruleta = setInterval(() => { this.pantalla = NOMBRES[i++ % NOMBRES.length] || {}; }, 70);
                    try {
                        const r = await fetch(@json(route('colegio.sortear')), { method: 'POST', body: JSON.stringify({ periodo: @json($periodo) }),
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } });
                        const d = await r.json();
                        await new Promise(res => setTimeout(res, 2500));
                        clearInterval(ruleta);
                        if (!d.ok) { this.pantalla = {}; return (window.tushpaAviso || alert)(d.mensaje || 'No se pudo sortear.', 'error'); }
                        this.ganador = d.ganador;
                        this.pantalla = { nombre: d.ganador.nombre, grado: d.ganador.grado };
                    } catch (e) {
                        clearInterval(ruleta); this.pantalla = {};
                        (window.tushpaAviso || alert)('Error de conexión. Recarga para ver si se registró.', 'error');
                    } finally { this.girando = false; }
                },
            };
        }
    </script>
@endsection
