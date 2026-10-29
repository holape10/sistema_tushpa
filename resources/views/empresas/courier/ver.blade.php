@extends('layouts.app')
@section('title', 'Envío '.$e->codigo)
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $kg = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        [$textoEstado, $iconoEstado, $color] = \App\Support\Courier::ESTADOS[$e->estado];
        $colores = ['slate' => 'bg-slate-100 text-slate-700', 'sky' => 'bg-sky-100 text-sky-700', 'indigo' => 'bg-indigo-100 text-indigo-700',
            'amber' => 'bg-amber-100 text-amber-800', 'emerald' => 'bg-emerald-100 text-emerald-700', 'rose' => 'bg-rose-100 text-rose-700', 'gray' => 'bg-gray-100 text-gray-500'];
        $porEntregar = $lado['destino'] && in_array($e->estado, ['EN_DESTINO', 'EN_REPARTO'], true);
        $cobraAlEntregar = $e->pago === 'DESTINO' && ! $e->IdCpe_cabecera;
        // Siguiente paso de este envío desde esta agencia
        $paso = match (true) {
            $e->estado === 'REGISTRADO' && $lado['origen'] => ['EN_TRANSITO', 'Despachar', 'fa-truck-fast'],
            $e->estado === 'EN_TRANSITO' && $lado['destino'] => ['EN_DESTINO', 'Recibir en mi agencia', 'fa-warehouse'],
            $e->estado === 'EN_DESTINO' && $lado['destino'] && $e->entrega === 'DOMICILIO' => ['EN_REPARTO', 'Salió a reparto', 'fa-motorcycle'],
            default => null,
        };
        $wa = ($n = preg_replace('/\D/', '', (string) $e->des_telefono)) ? 'https://wa.me/'.(strlen($n) === 9 ? '51'.$n : $n).'?text='.rawurlencode(
            'Hola '.\App\Support\Courier::enmascarar($e->des_nombre).', tienes un envío de '.$e->rem_nombre.' con N° de guía '.$e->codigo.'. '
            .($e->estado === 'EN_DESTINO' && $e->entrega === 'AGENCIA' ? 'Ya llegó: recógelo en '.($destino->nombre ?? 'la agencia').($destino->direccion ? ' ('.$destino->direccion.')' : '').' con tu DNI y la clave que te dará quien te lo envió. ' : '')
            .($cobraAlEntregar ? 'Monto a pagar al recoger: S/ '.number_format((float) $e->total, 2).'. ' : '')
            .'Síguelo aquí: '.$rastreo) : null;
    @endphp

    <div class="space-y-5" x-data="fichaEnvio()">
        @include('empresas.courier.partials.nav')
        @include('empresas.partials.alert')

        <div class="bg-white rounded-3xl shadow-sm p-5 sm:p-6">
            <a href="{{ route('courier.index') }}" class="text-sm text-gray-500">← Envíos</a>
            <div class="mt-2 flex flex-wrap items-start gap-4">
                <span class="h-16 w-16 shrink-0 rounded-2xl bg-gradient-to-br from-orange-500 to-rose-600 text-white flex items-center justify-center text-2xl"><i class="fas {{ $iconoEstado }}"></i></span>
                <div class="flex-1 min-w-60">
                    <p class="text-xs text-gray-400">N° de guía</p>
                    <h1 class="text-3xl font-black text-gray-800 font-mono tracking-widest leading-tight">{{ $e->codigo }}</h1>
                    <p class="mt-1"><span class="text-xs font-bold rounded-full px-2.5 py-1 {{ $colores[$color] }}">{{ $textoEstado }}</span>
                        @if ($e->entrega === 'DOMICILIO')<span class="text-xs font-bold rounded-full px-2.5 py-1 bg-amber-50 text-amber-700"><i class="fas fa-house"></i> Reparto a domicilio</span>@endif</p>
                    <p class="mt-2 text-lg text-gray-700"><b>{{ \App\Support\Courier::etiquetaAgencia($origen) }}</b> <i class="fas fa-arrow-right text-gray-400"></i> <b>{{ \App\Support\Courier::etiquetaAgencia($destino) }}</b></p>
                    @if ($e->llegada_estimada && ! in_array($e->estado, ['ENTREGADO', 'ANULADO', 'DEVUELTO'], true))
                        <p class="text-sm text-gray-500">Llegada estimada: {{ \Carbon\Carbon::parse($e->llegada_estimada)->format('d/m/Y') }}</p>
                    @endif
                    <p class="text-sm text-gray-500 space-x-2">
                        @if ($manifiesto)<a href="{{ route('courier.manifiestos.ver', $manifiesto->man_id) }}" class="text-orange-700 font-semibold"><i class="fas fa-clipboard-list"></i> Manifiesto N° {{ $manifiesto->numero }}</a>@endif
                        @if ($motorizado)<span><i class="fas fa-motorcycle"></i> {{ $motorizado }}</span>@endif
                        @if ($e->intentos)<span class="text-rose-600 font-semibold">{{ $e->intentos }} intento(s) sin entregar</span>@endif
                        @if ($e->bultos_recibidos !== null && $e->bultos_recibidos < $e->piezas)<span class="text-rose-600 font-semibold">Llegó incompleto: {{ $e->bultos_recibidos }} de {{ $e->piezas }} bultos</span>@endif
                        @if ($devolucion)<a href="{{ route('courier.ver', $devolucion->env_id) }}" class="text-rose-700 font-semibold"><i class="fas fa-rotate-left"></i> Se devuelve con la guía {{ $devolucion->codigo }}</a>@endif
                        @if ($original)<a href="{{ route('courier.ver', $original->env_id) }}" class="text-rose-700 font-semibold"><i class="fas fa-rotate-left"></i> Devolución de la guía {{ $original->codigo }}</a>@endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($paso)
                        <button type="button" @click="avanzar(@js($paso[0]))" class="h-10 px-4 rounded-xl bg-orange-600 text-white text-sm font-bold"><i class="fas {{ $paso[2] }}"></i> {{ $paso[1] }}</button>
                    @endif
                    @if ($porEntregar)
                        <button type="button" @click="modal = 'entregar'" class="h-10 px-4 rounded-xl bg-emerald-600 text-white text-sm font-bold"><i class="fas fa-hand-holding"></i> Entregar</button>
                    @endif
                    @if ($puedeCobrar && ! ($porEntregar && $cobraAlEntregar))
                        <button type="button" @click="modal = 'cobrar'" class="h-10 px-4 rounded-xl bg-emerald-600 text-white text-sm font-bold"><i class="fas fa-cash-register"></i> Cobrar {{ $soles($e->total) }}</button>
                    @endif
                    <button type="button" @click="imprimir(@js(route('courier.guia', $e->env_id).'?imprimir=1'))" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-print"></i> Guía</button>
                    @if ($wa)<a href="{{ $wa }}" target="_blank" class="h-10 px-3 rounded-xl bg-white ring-1 ring-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-1"><i class="fab fa-whatsapp"></i> Avisar</a>@endif
                    <button type="button" @click="navigator.clipboard?.writeText(@js($rastreo)); (window.tushpaAviso || alert)('Enlace de rastreo copiado', 'ok')" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-link"></i> Rastreo</button>
                    @if ($e->estado === 'EN_DESTINO' && $lado['destino'])
                        <button type="button" @click="devolver()" class="h-10 px-3 rounded-xl bg-white ring-1 ring-rose-200 text-rose-600 text-sm font-semibold"><i class="fas fa-rotate-left"></i> Devolver</button>
                    @endif
                    <button type="button" @click="modal = 'reclamo'" class="h-10 px-3 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold"><i class="fas fa-comment-dots"></i> Reclamo</button>
                    @if ($e->estado === 'REGISTRADO' && $lado['origen'])
                        <button type="button" @click="anular()" class="h-10 px-3 rounded-xl bg-white ring-1 ring-rose-200 text-rose-600 text-sm font-semibold"><i class="fas fa-ban"></i> Anular</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-5 items-start">
            <div class="lg:col-span-2 space-y-5">
                <div class="grid sm:grid-cols-2 gap-5">
                    @foreach ([['Remitente', $e->rem_nombre, $e->rem_doc !== '00000000' ? $e->rem_doc : null, $e->rem_telefono, null],
                        ['Destinatario', $e->des_nombre, $e->des_doc, $e->des_telefono, $e->direccion_entrega]] as [$titulo, $nombre, $doc, $tel, $dir])
                        <div class="bg-white rounded-2xl shadow-sm p-5">
                            <p class="text-xs font-semibold uppercase text-gray-400">{{ $titulo }}</p>
                            <p class="font-black text-gray-800 mt-1">{{ $nombre }}</p>
                            <p class="text-sm text-gray-500">{{ $doc ? 'Doc. '.$doc : 'Sin documento' }}{{ $tel ? ' · '.$tel : '' }}</p>
                            @if ($dir)<p class="text-sm text-gray-600 mt-1"><i class="fas fa-location-dot text-amber-500"></i> {{ $dir }}</p>@endif
                        </div>
                    @endforeach
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <p class="text-xs font-semibold uppercase text-gray-400 mb-2">Lo que envía</p>
                    <div class="divide-y divide-gray-100 text-sm">
                        @foreach ($paquetes as $p)
                            <p class="py-2 flex flex-wrap gap-x-3"><span><i class="fas {{ $p->tipo === 'SOBRE' ? 'fa-envelope' : 'fa-box' }} text-orange-500"></i> <b>{{ $p->cantidad }} {{ $p->tipo === 'SOBRE' ? 'sobre(s)' : 'paquete(s)' }}</b></span>
                                @if ($p->tipo === 'PAQUETE')<span class="text-gray-500">{{ $kg($p->peso) }} kg c/u{{ $p->largo ? ' · '.$kg($p->largo).'×'.$kg($p->ancho).'×'.$kg($p->alto).' cm' : '' }}</span>@endif
                                @if ($p->descripcion)<span class="text-gray-600">{{ $p->descripcion }}</span>@endif</p>
                        @endforeach
                    </div>
                    <div class="mt-3 grid sm:grid-cols-3 gap-3 text-sm">
                        <p><span class="text-gray-400">Contenido</span><br><b>{{ $e->contenido ?: '—' }}</b></p>
                        <p><span class="text-gray-400">Valor declarado</span><br><b>{{ $soles($e->valor_declarado) }}</b></p>
                        <p><span class="text-gray-400">Peso cobrado</span><br><b>{{ $kg($e->peso_cobrado) }} kg</b> <span class="text-gray-400">(real {{ $kg($e->peso_real) }})</span></p>
                    </div>
                    @if ($e->observacion)<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $e->observacion }}</p>@endif
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <p class="text-xs font-semibold uppercase text-gray-400 mb-3">Seguimiento</p>
                    <ol class="relative border-l-2 border-orange-100 ml-2 space-y-4">
                        @foreach ($eventos as $v)
                            @php [$t, $i, $c] = \App\Support\Courier::ESTADOS[$v->estado] ?? [$v->estado, 'fa-circle', 'slate']; @endphp
                            <li class="ml-5">
                                <span class="absolute -left-[13px] h-6 w-6 rounded-full flex items-center justify-center text-[11px] {{ $colores[$c] }}"><i class="fas {{ $i }}"></i></span>
                                <p class="font-bold text-gray-800">{{ $v->detalle ?: $t }}</p>
                                <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($v->fecha)->format('d/m/Y h:i a') }}{{ $v->ciudad ? ' · '.$v->ciudad : '' }}{{ $v->usuario ? ' · '.$v->usuario : '' }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="space-y-5">
                <div class="bg-white rounded-2xl shadow-sm p-5 text-sm space-y-1">
                    <p class="text-xs font-semibold uppercase text-gray-400 mb-2">Cobro</p>
                    <p class="flex justify-between"><span>Flete</span><b>{{ $soles($e->flete) }}</b></p>
                    @if ((float) $e->domicilio > 0)<p class="flex justify-between"><span>Reparto a domicilio</span><b>{{ $soles($e->domicilio) }}</b></p>@endif
                    @if ((float) $e->seguro > 0)<p class="flex justify-between"><span>Seguro</span><b>{{ $soles($e->seguro) }}</b></p>@endif
                    <p class="flex justify-between border-t pt-2 text-lg"><b>Total</b><b>{{ $soles($e->total) }}</b></p>
                    @if ($comprobante)
                        <a href="{{ route('cobros.voucher', $comprobante->IdCpe_cabecera) }}" target="_blank" class="block mt-2 rounded-xl bg-emerald-50 p-3 font-bold text-emerald-700">
                            <i class="fas fa-circle-check"></i> {{ $e->pago === 'CREDITO' ? 'Facturado' : 'Pagado' }} · {{ $comprobante->serdoc }}-{{ $comprobante->numdoc }}</a>
                    @elseif ($e->pago === 'CREDITO')
                        <a href="{{ $e->rem_clicod ? route('courier.creditos.cuenta', $e->rem_clicod) : '#' }}" class="block mt-2 rounded-xl bg-violet-50 p-3 font-bold text-violet-700"><i class="fas fa-handshake"></i> A crédito: va en su estado de cuenta</a>
                    @elseif ((float) $e->total <= 0)
                        <p class="mt-2 rounded-xl bg-gray-50 p-3 font-bold text-gray-500">Sin costo</p>
                    @elseif ($e->cobrado_rep)
                        <p class="mt-2 rounded-xl bg-amber-50 p-3 font-bold text-amber-800"><i class="fas fa-motorcycle"></i> El motorizado cobró {{ $e->medio_rep }} {{ $soles($e->cobrado_rep) }}: por liquidar en Reparto</p>
                    @elseif (! in_array($e->estado, ['ANULADO', 'DEVUELTO'], true))
                        <p class="mt-2 rounded-xl bg-rose-50 p-3 font-bold text-rose-700"><i class="fas fa-clock"></i> {{ $e->pago === 'DESTINO' && $e->estado !== 'ENTREGADO' ? 'Paga al recoger' : 'Por cobrar' }}</p>
                    @endif
                </div>
                @if ($lado['origen'] && ! in_array($e->estado, ['ENTREGADO', 'ANULADO', 'DEVUELTO'], true))
                    <div class="bg-white rounded-2xl shadow-sm p-5 text-sm">
                        <p class="text-xs font-semibold uppercase text-gray-400">Clave de recojo</p>
                        <p class="text-3xl font-black font-mono tracking-[.4em] text-gray-800 mt-1">{{ $e->clave }}</p>
                        <p class="text-xs text-gray-400 mt-1">Va en la copia del remitente. Se la da a quien recogerá.</p>
                    </div>
                @endif
                @if ($e->estado === 'ENTREGADO')
                    <div class="bg-emerald-50 rounded-2xl p-5 text-sm text-emerald-900">
                        <p class="font-bold"><i class="fas fa-circle-check"></i> Entregado</p>
                        <p>{{ \Carbon\Carbon::parse($e->entregado)->format('d/m/Y h:i a') }}</p>
                        <p>A {{ $e->entregado_a }}{{ $e->entregado_doc ? ' · Doc. '.$e->entregado_doc : '' }}</p>
                        @if ($e->foto_entrega || $e->firma_entrega)
                            <div class="mt-2 flex gap-2">
                                @if ($e->foto_entrega)<a href="{{ asset($e->foto_entrega) }}" target="_blank"><img src="{{ asset($e->foto_entrega) }}" alt="Foto de la entrega" class="h-24 w-24 rounded-xl object-cover ring-1 ring-emerald-200"></a>@endif
                                @if ($e->firma_entrega)<a href="{{ asset($e->firma_entrega) }}" target="_blank"><img src="{{ asset($e->firma_entrega) }}" alt="Firma" class="h-24 w-36 rounded-xl object-contain bg-white ring-1 ring-emerald-200"></a>@endif
                            </div>
                        @endif
                    </div>
                @endif
                @foreach ($reclamos as $r)
                    <a href="{{ route('courier.reclamos') }}?estado={{ $r->estado }}" class="block bg-white rounded-2xl shadow-sm p-4 text-sm">
                        <p class="font-bold"><i class="fas fa-comment-dots text-orange-500"></i> Reclamo N° {{ $r->numero }} · {{ $tiposReclamo[$r->tipo] ?? $r->tipo }}</p>
                        <p class="text-xs text-gray-500">{{ \App\Support\CourierWeb::ESTADOS_RECLAMO[$r->estado] }} · {{ \Carbon\Carbon::parse($r->creado)->format('d/m/Y') }}</p>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Entregar --}}
        @if ($porEntregar)
            <div x-show="modal === 'entregar'" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
                <form @submit.prevent="entregar()" class="bg-white rounded-3xl shadow-2xl w-full max-w-xl my-4" @click.outside="modal = null">
                    <div class="px-6 py-4 border-b"><h2 class="text-lg font-black text-gray-800"><i class="fas fa-hand-holding text-emerald-600"></i> Entregar envío</h2></div>
                    <div class="p-6 space-y-4">
                        <p class="rounded-xl bg-gray-50 p-3 text-sm">Para <b>{{ $e->des_nombre }}</b>{{ $e->des_doc ? ' · DNI '.$e->des_doc : '' }}. Verifica su documento y pídele la clave.</p>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <label class="text-xs font-semibold text-gray-500">Clave (4 dígitos)
                                <input x-model="ent.clave" maxlength="4" inputmode="numeric" autocomplete="off" class="mt-1 w-full h-12 rounded-lg border-gray-300 text-2xl font-black tracking-[.4em] text-center"></label>
                            <label class="sm:col-span-2 text-xs font-semibold text-gray-500">Quien recoge
                                <input x-model="ent.recibe_nombre" maxlength="150" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase">
                                <input x-model="ent.recibe_doc" maxlength="15" inputmode="numeric" placeholder="DNI de quien recoge" class="mt-2 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                        </div>
                        @if ($esAdmin)
                            <label class="block text-xs font-semibold text-gray-500">¿No tiene la clave? (solo administrador) Motivo de la entrega sin clave
                                <input x-model="ent.motivo_sin_clave" maxlength="120" placeholder="Ej. remitente confirmó por teléfono" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
                        @endif
                        @if ($cobraAlEntregar)
                            <div class="rounded-2xl ring-1 ring-emerald-200 p-4 space-y-3">
                                <p class="font-black text-emerald-800">Cobrar al entregar: {{ $soles($e->total) }}</p>
                                @include('empresas.courier.partials.cobro', ['total' => (float) $e->total])
                            </div>
                        @endif
                        <p x-show="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700" x-text="error"></p>
                    </div>
                    <div class="px-6 py-4 border-t flex gap-3 justify-end">
                        <button type="button" @click="modal = null" class="px-5 h-11 rounded-xl bg-gray-100 font-bold text-gray-700">Cancelar</button>
                        <button :disabled="ocupado" class="px-6 h-11 rounded-xl bg-emerald-600 text-white font-extrabold disabled:opacity-60"
                                x-text="ocupado ? 'Guardando…' : '{{ $cobraAlEntregar ? '✓ Cobrar '.$soles($e->total).' y entregar' : '✓ Entregar' }}'"></button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Cobrar --}}
        @if ($puedeCobrar)
            <div x-show="modal === 'cobrar'" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
                <form @submit.prevent="cobrar()" class="bg-white rounded-3xl shadow-2xl w-full max-w-xl my-4" @click.outside="modal = null">
                    <div class="px-6 py-4 border-b"><h2 class="text-lg font-black text-gray-800"><i class="fas fa-cash-register text-emerald-600"></i> Cobrar {{ $soles($e->total) }}</h2></div>
                    <div class="p-6 space-y-4">
                        @include('empresas.courier.partials.cobro', ['total' => (float) $e->total])
                        <p x-show="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700" x-text="error"></p>
                    </div>
                    <div class="px-6 py-4 border-t flex gap-3 justify-end">
                        <button type="button" @click="modal = null" class="px-5 h-11 rounded-xl bg-gray-100 font-bold text-gray-700">Cancelar</button>
                        <button :disabled="ocupado" class="px-6 h-11 rounded-xl bg-emerald-600 text-white font-extrabold disabled:opacity-60" x-text="ocupado ? 'Cobrando…' : '✓ Cobrar'"></button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Reclamo en la agencia o por teléfono --}}
        <div x-show="modal === 'reclamo'" x-cloak class="fixed inset-0 z-[70] bg-slate-900/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
            <form @submit.prevent="enviar(@js(route('courier.reclamar', $e->env_id)), rec)" class="bg-white rounded-3xl shadow-2xl w-full max-w-lg my-4 p-6 space-y-3" @click.outside="modal = null">
                <h2 class="text-lg font-black text-gray-800"><i class="fas fa-comment-dots text-orange-600"></i> Registrar reclamo</h2>
                <select x-model="rec.tipo" class="w-full h-10 rounded-lg border-gray-300 text-sm">
                    @foreach ($tiposReclamo as $clave => $texto)<option value="{{ $clave }}">{{ $texto }}</option>@endforeach
                </select>
                <textarea x-model="rec.detalle" required rows="3" maxlength="2000" placeholder="¿Qué pasó?" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                <input x-model="rec.pedido" maxlength="255" placeholder="¿Qué pide el cliente? (opcional)" class="w-full h-10 rounded-lg border-gray-300 text-sm">
                <div class="grid grid-cols-3 gap-2">
                    <input x-model="rec.nombre" required maxlength="150" placeholder="Quién reclama" class="col-span-3 h-10 rounded-lg border-gray-300 text-sm uppercase">
                    <input x-model="rec.documento" maxlength="15" placeholder="DNI" class="h-10 rounded-lg border-gray-300 text-sm">
                    <input x-model="rec.telefono" maxlength="20" placeholder="Celular" class="col-span-2 h-10 rounded-lg border-gray-300 text-sm">
                </div>
                <p x-show="error" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700" x-text="error"></p>
                <div class="flex gap-3 justify-end"><button type="button" @click="modal = null" class="px-4 h-10 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                    <button :disabled="ocupado" class="px-5 h-10 rounded-xl bg-orange-600 text-white font-bold">Registrar</button></div>
            </form>
        </div>

        <iframe x-ref="imprimir" class="hidden"></iframe>
    </div>

    <script>
        function fichaEnvio() {
            const post = (url, data) => fetch(url, { method: 'POST', body: JSON.stringify(data || {}),
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) } }).then(r => r.json());
            const aviso = (m, t) => (window.tushpaAviso || alert)(m, t);
            // A nombre de quien paga: el remitente si paga al enviar, el destinatario si paga al recoger
            const pagador = @json($e->pago === 'ORIGEN' ? ['doc' => $e->rem_doc !== '00000000' ? $e->rem_doc : '', 'nombre' => $e->rem_nombre] : ['doc' => $e->des_doc ?? '', 'nombre' => $e->des_nombre]);
            return {
                modal: new URLSearchParams(location.search).has('entregar') && @json($porEntregar) ? 'entregar' : null, ocupado: false, error: '',
                ent: { clave: '', recibe_nombre: @json($e->des_nombre), recibe_doc: @json($e->des_doc ?? ''), motivo_sin_clave: '' },
                cobro: { tdocod: String(pagador.doc).length === 11 ? '01' : '03', id_med_pag: @json((string) ($medios->first()->id_med_pag ?? '')),
                         clinum: pagador.doc, clinom: pagador.nombre, clidir: '', paga: null, imprimir: true },
                imprimir(url) { this.$refs.imprimir.src = url; },
                // Después de cobrar: si no salió por la impresora directa, el comprobante se imprime con el navegador
                terminar(d) {
                    const c = d.comprobante;
                    aviso('✔ ' + (d.mensaje || '') + (c ? ' ' + c.numero : '') + (c && c.vuelto > 0 ? ' · vuelto S/ ' + c.vuelto.toFixed(2) : ''), 'ok');
                    if (c && this.cobro.imprimir && !c.impreso) this.imprimir(c.ticket + '&imprimir=1');
                    setTimeout(() => location.href = location.pathname, c && this.cobro.imprimir && !c.impreso ? 2500 : 900);
                },
                async enviar(url, datos) {
                    this.ocupado = true; this.error = '';
                    try {
                        const d = await post(url, datos);
                        if (d.errors) { this.error = Object.values(d.errors).flat()[0]; return; }
                        if (!d.ok) { this.error = d.mensaje; if (!this.modal) aviso(d.mensaje, 'error'); return; }
                        this.terminar(d);
                    } catch (e) { this.error = 'Error de conexión. Actualiza la pantalla para ver si se guardó.'; } finally { this.ocupado = false; }
                },
                entregar() { this.enviar(@json(route('courier.entregar', $e->env_id)), { ...this.ent, cobro: this.cobro, imprimir: this.cobro.imprimir }); },
                cobrar() { this.enviar(@json(route('courier.cobrar', $e->env_id)), { cobro: this.cobro, imprimir: this.cobro.imprimir }); },
                avanzar(estado) { this.enviar(@json(route('courier.avanzar')), { ids: [{{ $e->env_id }}], estado }); },
                anular() {
                    const motivo = prompt('¿Por qué se anula el envío?');
                    if (motivo) this.enviar(@json(route('courier.anular', $e->env_id)), { motivo });
                },
                rec: { tipo: 'DANO', detalle: '', pedido: '', nombre: @json($e->rem_nombre), documento: @json($e->rem_doc !== '00000000' ? $e->rem_doc : ''), telefono: @json($e->rem_telefono ?? '') },
                // La vuelta sale como un envío nuevo hacia la agencia de origen: se imprime su guía al toque
                async devolver() {
                    const motivo = prompt('¿Por qué se devuelve al remitente? (nadie lo recogió, lo rechazó…)');
                    if (!motivo) return;
                    const cobrar = confirm('¿Se le cobra la devuelta al remitente cuando la recoja?\n\nAceptar = sí, se cobra · Cancelar = sin costo');
                    const d = await post(@json(route('courier.devolver', $e->env_id)), { motivo, cobrar });
                    if (!d.ok) return aviso(d.mensaje || 'Error', 'error');
                    aviso(d.mensaje, 'ok'); this.imprimir(d.guia + '?imprimir=1');
                    setTimeout(() => location.href = d.url, 2500);
                },
            };
        }
    </script>
@endsection
