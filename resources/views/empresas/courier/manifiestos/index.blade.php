@extends('layouts.app')
@section('title', 'Manifiestos de carga')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $campo = 'mt-1 w-full h-10 rounded-lg border-gray-300 text-sm';
        $etiqueta = 'text-xs font-semibold text-gray-500';
        $colores = ['ABIERTO' => 'bg-amber-100 text-amber-800', 'EN_VIAJE' => 'bg-sky-100 text-sky-700', 'RECIBIDO' => 'bg-emerald-100 text-emerald-700'];
        $estados = \App\Support\CourierManifiestos::ESTADOS;
    @endphp

    <div class="space-y-5" x-data="manifiestos()">
        @include('empresas.courier.partials.nav')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-gray-800"><i class="fas fa-clipboard-list text-orange-600"></i> Manifiestos de carga</h1>
                <p class="text-sm text-gray-500">Cada viaje con los envíos que lleva: se carga y se recibe escaneando las etiquetas.</p>
            </div>
            <button type="button" @click="modal = true" class="h-11 px-5 rounded-xl bg-orange-600 text-white font-extrabold"><i class="fas fa-plus"></i> Nuevo viaje</button>
        </div>

        <div class="bg-white rounded-2xl shadow-sm divide-y divide-gray-100">
            @forelse ($manifiestos as $m)
                <a href="{{ route('courier.manifiestos.ver', $m->man_id) }}" class="flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-orange-50/40">
                    <span class="h-11 w-11 shrink-0 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center text-lg"><i class="fas {{ $medios[$m->medio][1] }}"></i></span>
                    <div class="flex-1 min-w-56">
                        <p class="font-black text-gray-800">Manifiesto N° {{ $m->numero }}
                            <span class="ml-1 text-[11px] font-bold rounded-full px-2 py-0.5 {{ $colores[$m->estado] ?? 'bg-gray-100' }}">{{ $estados[$m->estado] }}</span>
                            @if ($m->llega && ! $m->sale)<span class="text-[11px] font-bold rounded-full px-2 py-0.5 bg-indigo-100 text-indigo-700">Viene hacia ti</span>@endif</p>
                        <p class="text-sm text-gray-600"><b>{{ $m->origen->ciudad ?? '?' }}</b> <i class="fas fa-arrow-right text-xs text-gray-400"></i> <b>{{ $m->destino->ciudad ?? 'Varias agencias' }}</b>
                            · {{ $m->transportista ?: $medios[$m->medio][0] }}{{ $m->vehiculo ? ' · '.$m->vehiculo : '' }}</p>
                        <p class="text-xs text-gray-400">{{ $m->salida ? 'Salida '.\Carbon\Carbon::parse($m->salida)->format('d/m H:i') : 'Creado '.\Carbon\Carbon::parse($m->creado)->format('d/m H:i') }}</p>
                    </div>
                    <div class="text-right text-sm"><p class="font-black text-gray-800">{{ $m->envios }} envío(s)</p>
                        <p class="text-xs text-gray-400">{{ $m->piezas }} bulto(s) · {{ rtrim(rtrim(number_format($m->peso, 2), '0'), '.') }} kg</p></div>
                </a>
            @empty
                <p class="p-10 text-center text-gray-400">Aún no hay manifiestos. Crea uno con «Nuevo viaje» y carga los envíos escaneando sus etiquetas.</p>
            @endforelse
        </div>

        <div x-show="modal" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
            <form @submit.prevent="crear()" class="bg-white rounded-3xl shadow-2xl w-full max-w-xl my-4" @click.outside="modal = false">
                <div class="px-6 py-4 border-b"><h2 class="text-lg font-black text-gray-800"><i class="fas fa-truck text-orange-600"></i> Nuevo viaje</h2></div>
                <div class="p-6 grid sm:grid-cols-2 gap-3">
                    <label class="{{ $etiqueta }}">Sale de
                        <select x-model.number="f.origen_id" class="{{ $campo }}">
                            @foreach ($propias as $a)<option value="{{ $a->age_id }}">{{ \App\Support\Courier::etiquetaAgencia($a) }}</option>@endforeach
                        </select></label>
                    <label class="{{ $etiqueta }}">Va a
                        <select x-model="f.destino_id" class="{{ $campo }}">
                            <option value="">Varias agencias (el bus deja carga en el camino)</option>
                            @foreach ($agencias as $a)<option value="{{ $a->age_id }}">{{ \App\Support\Courier::etiquetaAgencia($a) }}</option>@endforeach
                        </select></label>
                    <div class="sm:col-span-2 grid grid-cols-3 gap-2">
                        @foreach ($medios as $clave => [$texto, $icono])
                            <label class="flex flex-col items-center gap-1 rounded-xl ring-2 p-3 cursor-pointer text-sm text-center" :class="f.medio === '{{ $clave }}' ? 'ring-orange-400 bg-orange-50' : 'ring-gray-200'">
                                <input type="radio" value="{{ $clave }}" x-model="f.medio" class="sr-only"><i class="fas {{ $icono }} text-xl text-orange-600"></i> <b>{{ $texto }}</b></label>
                        @endforeach
                    </div>
                    <label class="{{ $etiqueta }}">Empresa de transporte, aerolínea o embarcación<input x-model="f.transportista" maxlength="150" class="{{ $campo }} uppercase"></label>
                    <label class="{{ $etiqueta }}">Placa, N° de vuelo o nombre de la nave<input x-model="f.vehiculo" maxlength="60" class="{{ $campo }} uppercase"></label>
                    <label class="{{ $etiqueta }}">Conductor o encargado<input x-model="f.conductor" maxlength="150" class="{{ $campo }} uppercase"></label>
                    <label class="{{ $etiqueta }}">Salida<input type="datetime-local" x-model="f.salida" class="{{ $campo }}"></label>
                    <p x-show="error" class="sm:col-span-2 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700" x-text="error"></p>
                </div>
                <div class="px-6 py-4 border-t flex gap-3 justify-end">
                    <button type="button" @click="modal = false" class="px-5 h-11 rounded-xl bg-gray-100 font-bold text-gray-700">Cancelar</button>
                    <button :disabled="ocupado" class="px-6 h-11 rounded-xl bg-orange-600 text-white font-extrabold disabled:opacity-60">Crear y cargar envíos</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function manifiestos() {
            return {
                modal: false, ocupado: false, error: '',
                f: { origen_id: {{ (int) $propias->first()->age_id }}, destino_id: '', medio: 'TERRESTRE', transportista: '', vehiculo: '', conductor: '', salida: '' },
                async crear() {
                    this.ocupado = true; this.error = '';
                    try {
                        const d = await fetch(@json(route('courier.manifiestos.crear')), { method: 'POST', body: JSON.stringify(this.f),
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
                        if (d.errors) { this.error = Object.values(d.errors).flat()[0]; return; }
                        if (!d.ok) { this.error = d.mensaje; return; }
                        location.href = d.url;
                    } catch (e) { this.error = 'Error de conexión.'; } finally { this.ocupado = false; }
                },
            };
        }
    </script>
@endsection
