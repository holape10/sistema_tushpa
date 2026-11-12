@extends('layouts.app')
@section('title', 'Encuestas a clientes')
@section('content')
    @php
        $s = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $estrellas = fn (?float $n) => $n === null ? '—' : number_format($n, 1).' ★';
        $colorNota = fn (?float $n) => match (true) { $n === null => 'text-gray-400', $n >= 4 => 'text-emerald-600', $n >= 3 => 'text-amber-600', default => 'text-rose-600' };
        $cara = fn (int $n) => [1 => '😡', 2 => '😕', 3 => '😐', 4 => '🙂', 5 => '😍'][$n] ?? '';
        $hoy = now()->toDateString();
        $rangos = [
            'Hoy' => [$hoy, $hoy],
            'Últimos 7 días' => [now()->subDays(6)->toDateString(), $hoy],
            'Este mes' => [now()->startOfMonth()->toDateString(), $hoy],
            'Mes pasado' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ];
        $etiquetas = ['general' => 'Nota general'] + collect(\App\Support\Encuestas::PREGUNTAS)->map(fn ($p) => ucfirst(trim(str_replace(['¿Qué tal la ', '?'], '', $p))))->all();
    @endphp

    <div class="max-w-6xl space-y-5">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-700 via-teal-600 to-cyan-600 text-white shadow-lg">
            <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
            <div class="relative p-6 sm:p-8">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[240px]">
                        <p class="text-emerald-100 text-sm font-semibold tracking-wide uppercase">😍 Encuestas a clientes</p>
                        <h1 class="text-2xl sm:text-3xl font-black mt-1">¿Qué opinan tus clientes?</h1>
                        <p class="text-emerald-50 text-sm mt-1 max-w-2xl">El cliente escanea un QR y califica la comida, la atención y la rapidez con caritas: el mozo se lo muestra con el botón ⭐ de la mesa antes de cobrar, sale en la carta QR y, si quieres, en el ticket.
                            {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}.</p>
                    </div>
                    <div class="no-print space-y-2 text-right">
                        <form method="POST" action="{{ route('encuestas.activar') }}">@csrf
                            <button class="px-4 py-2 rounded-xl text-sm font-bold {{ $activa ? 'bg-white text-emerald-700' : 'bg-amber-300 text-amber-900' }}">
                                {{ $activa ? '✔ Encuesta activada · Desactivar' : 'Activar la encuesta' }}</button>
                        </form>
                        {{-- El QR impreso en el ticket es opcional (gasta papel); la encuesta sigue en la mesa y en la carta QR --}}
                        @if ($activa)
                            <form method="POST" action="{{ route('encuestas.qr_ticket') }}">@csrf
                                <input type="hidden" name="qr_ticket" value="{{ $qrTicket ? 0 : 1 }}">
                                <button class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/15 ring-1 ring-white/30 text-xs font-bold">
                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded border-2 border-white {{ $qrTicket ? 'bg-white text-emerald-700' : '' }}">{{ $qrTicket ? '✓' : '' }}</span>
                                    Imprimir el QR de la encuesta en el ticket</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mt-6">
                    @foreach ($etiquetas as $clave => $titulo)
                        <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-emerald-50">{{ $titulo }}</p>
                            <p class="text-2xl font-black">{{ $estrellas($promedios[$clave]) }}</p></div>
                    @endforeach
                    <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-emerald-50">Respondieron</p>
                        <p class="text-2xl font-black">{{ $encuestas->count() }} <span class="text-sm font-semibold">de {{ $tickets }}</span></p>
                        @if ($contentos !== null)<p class="text-xs text-emerald-50">{{ $contentos }}% contentos (4★ o más)</p>@endif</div>
                </div>
            </div>
        </section>

        @unless ($activa)
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-800">La encuesta está <b>desactivada</b>: no sale en la mesa, ni en la carta QR, ni en el ticket. Actívala con el botón de arriba.</div>
        @endunless

        <section class="no-print bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach ($rangos as $nombre => [$d, $h])
                    <a href="{{ route('encuestas.index', ['desde' => $d, 'hasta' => $h]) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $desde === $d && $hasta === $h ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $nombre }}</a>
                @endforeach
            </div>
            <form class="flex flex-wrap items-end gap-2 text-sm ml-auto">
                <label>Desde<input type="date" name="desde" value="{{ $desde }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="block rounded-lg border-gray-300 text-sm"></label>
                <button class="h-10 px-4 rounded-lg bg-emerald-600 text-white font-semibold">Ver</button>
            </form>
        </section>

        @if ($encuestas->isNotEmpty())
            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">👤 Por mozo</h2></div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr><th class="px-4 py-2 text-left">Mozo</th><th class="px-4 py-2 text-center">Encuestas</th>
                        <th class="px-4 py-2 text-center">Atención</th><th class="px-4 py-2 text-center">Nota general</th></tr></thead>
                    <tbody class="divide-y">
                    @foreach ($porMozo as $m)
                        <tr><td class="px-4 py-2 font-semibold">{{ $m->mozo }}</td><td class="px-4 py-2 text-center">{{ $m->encuestas }}</td>
                            <td class="px-4 py-2 text-center font-bold {{ $colorNota($m->atencion) }}">{{ $estrellas($m->atencion) }}</td>
                            <td class="px-4 py-2 text-center font-bold {{ $colorNota($m->general) }}">{{ $estrellas($m->general) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </section>

            <section class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">💬 Respuestas</h2>
                    <p class="text-xs text-gray-500">Las de nota baja salen en rojo: llama al cliente si dejó su celular.</p></div>
                <div class="divide-y">
                    @foreach ($encuestas as $e)
                        <div class="px-5 py-3 flex flex-wrap gap-3 {{ $e->promedio < 3 ? 'bg-rose-50' : '' }}">
                            <div class="w-24 text-xs text-gray-500">{{ \Carbon\Carbon::parse($e->created_at)->format('d/m H:i') }}<br>{{ $e->serdoc ? $e->serdoc.'-'.(int) $e->numdoc : 'Antes de cobrar' }}
                                <span class="block mt-0.5 font-semibold text-emerald-700">{{ ['MESA' => '⭐ Mozo', 'CARTA' => '📱 Carta QR', 'TICKET' => '🧾 Ticket'][$e->origen ?? 'TICKET'] ?? '' }}</span></div>
                            <div class="flex-1 min-w-56">
                                <p class="text-sm"><span title="Comida">🍽 {{ $cara($e->comida) }}</span> · <span title="Atención">🙋 {{ $cara($e->atencion) }}</span> · <span title="Rapidez">⏱ {{ $cara($e->rapidez) }}</span>
                                    <b class="ml-1 {{ $colorNota($e->promedio) }}">{{ $estrellas($e->promedio) }}</b></p>
                                @if ($e->comentario)<p class="text-sm text-gray-800 mt-1">“{{ $e->comentario }}”</p>@endif
                                <p class="text-xs text-gray-500 mt-0.5">{{ $e->mes_nom ? 'Mesa '.$e->mes_nom.' · ' : '' }}Atendió: {{ $e->mozo ?? '—' }} · Cuenta {{ $s($e->ccaitv) }}</p>
                            </div>
                            @if ($e->telefono)
                                <a href="https://wa.me/{{ strlen($e->telefono) === 9 ? '51'.$e->telefono : $e->telefono }}" target="_blank" rel="noopener"
                                   class="self-center px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-bold">WhatsApp {{ $e->telefono }}</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <section class="bg-white rounded-2xl shadow-sm p-6 text-sm text-gray-500">Aún no hay respuestas en estas fechas.</section>
        @endif
    </div>

@if ($telegram ?? null)
    {{-- Calificaciones malas al Telegram (el mismo bot de los pedidos de Requerimientos): quien ya lo vinculó allá solo activa "Recibir" --}}
    <div class="max-w-6xl mx-auto mt-4 bg-white rounded-2xl shadow-sm p-5 space-y-3 no-print" x-data="avisosEncuestaTelegram(@js($telegram->map(fn ($a) => ['id' => (int) $a->IdUsuario,
            'nombre' => trim((string) ($a->apeusu ?: $a->name)), 'vinculado' => (bool) $a->telegram_chat_id, 'recibir' => (bool) $a->telegram_encuestas, 'enlace' => null])->values()))">
        <div>
            <h2 class="font-extrabold text-gray-800">📲 Calificaciones malas por Telegram</h2>
            <p class="text-sm text-gray-500">Cuando un cliente califica <b>2.5 o menos</b>, llega al momento al Telegram de los administradores que lo activen: la mesa, las notas,
                su comentario y su celular para llamarlo. Es el mismo bot de los pedidos: si ya lo vinculaste en Requerimientos, solo marca <b>Recibir</b>.</p>
        </div>
        <p x-show="aviso" x-text="aviso" x-cloak class="rounded-xl bg-indigo-50 text-indigo-800 px-3 py-2 text-sm"></p>
        <div class="divide-y divide-gray-100">
            <template x-for="a in admins" :key="a.id">
                <div class="py-3 flex flex-wrap items-center gap-2">
                    <p class="flex-1 min-w-40 font-semibold text-gray-800" x-text="a.nombre"></p>
                    <template x-if="!a.vinculado && !a.enlace">
                        <button type="button" @click="vincular(a)" class="h-9 px-3 rounded-xl bg-sky-600 text-white text-sm font-bold">Vincular Telegram</button>
                    </template>
                    <template x-if="!a.vinculado && a.enlace">
                        <span class="flex flex-wrap items-center gap-2 text-sm">
                            <a :href="a.enlace" target="_blank" class="h-9 px-3 inline-flex items-center rounded-xl bg-sky-50 text-sky-700 font-bold">Abrir en Telegram ↗</a>
                            <span class="text-gray-500">esperando que toque <b>Iniciar</b>…</span>
                        </span>
                    </template>
                    <template x-if="a.vinculado">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-emerald-700">✔ Vinculado</span>
                            <label class="flex items-center gap-2 rounded-xl px-3 h-9 ring-1 text-sm font-semibold cursor-pointer" :class="a.recibir ? 'ring-emerald-300 bg-emerald-50 text-emerald-800' : 'ring-gray-200 text-gray-600'">
                                <input type="checkbox" :checked="a.recibir" @change="recibir(a, $event.target.checked)" class="rounded text-emerald-600"> Recibir las calificaciones malas</label>
                            <button type="button" @click="probar(a)" class="h-9 px-3 rounded-xl bg-gray-100 text-sm font-bold">Probar</button>
                        </span>
                    </template>
                </div>
            </template>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('avisosEncuestaTelegram', (admins) => ({
            admins, aviso: '',
            async post(url, datos = {}) {
                const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(datos) });
                return r.json();
            },
            // Vincular usa el mismo enlace del bot que los pedidos de Requerimientos
            async vincular(a) {
                const r = await this.post(@js(url('requerimientos/telegram')) + '/' + a.id + '/enlace');
                if (!r.ok) { this.aviso = r.mensaje; return; }
                a.enlace = r.enlace;
                window.open(r.enlace, '_blank');
                this.esperar(45);
            },
            async esperar(vueltas) {
                const r = await this.post(@js(route('requerimientos.telegram.verificar')));
                this.admins.forEach(a => { if (!a.vinculado && (r.vinculados || []).includes(a.id)) { a.vinculado = true; a.enlace = null; this.recibir(a, true); this.aviso = '✔ ' + a.nombre + ' quedó vinculado: le llegarán las calificaciones malas.'; } });
                if (this.admins.some(a => a.enlace && !a.vinculado) && vueltas > 1) setTimeout(() => this.esperar(vueltas - 1), 4000);
            },
            async recibir(a, valor) { a.recibir = valor; await this.post(@js(url('encuestas/telegram')) + '/' + a.id + '/recibir', { recibir: valor }); },
            async probar(a) { const r = await this.post(@js(url('encuestas/telegram')) + '/' + a.id + '/probar'); this.aviso = r.mensaje; },
        }));
    });
</script>
@endpush
