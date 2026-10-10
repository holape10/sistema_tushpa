@extends('layouts.app')
@section('title', 'Estacionamiento')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        .placa { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .08em; }
        .placa-franja { background: linear-gradient(90deg, #1d4ed8, #2563eb); }
        .mantenimiento { background-image: repeating-linear-gradient(45deg, #f3f4f6 0 8px, #e5e7eb 8px 16px); }
        @keyframes latido { 0%, 100% { box-shadow: 0 0 0 0 rgba(225, 29, 72, .45); } 50% { box-shadow: 0 0 0 8px rgba(225, 29, 72, 0); } }
        .latido { animation: latido 1.6s infinite; }
    </style>

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $btn = 'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition';
    @endphp

    <div x-data="estacionamiento()" x-init="iniciar()" class="space-y-5">
        {{-- Cabecera --}}
        <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-800 via-indigo-700 to-blue-700 text-white p-5 sm:p-7 shadow-lg">
            <x-kene-adorno patron="escalera" />
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black flex items-center gap-3"><i class="fas fa-square-parking"></i> Estacionamiento</h1>
                    <p class="text-indigo-100 text-sm mt-1">Entradas, salidas, valet y cobro con comprobante · se actualiza solo</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if (auth()->user()->tieneModulo('/estacionamiento/abonados'))
                        <a href="{{ route('estacionamiento.abonados') }}" class="{{ $btn }} bg-white/15 hover:bg-white/25"><i class="fas fa-id-card"></i> Abonados</a>
                    @endif
                    @if (auth()->user()->tieneModulo('/estacionamiento/reporte') || $esAdmin)
                        <a href="{{ route('estacionamiento.reporte') }}" class="{{ $btn }} bg-white/15 hover:bg-white/25"><i class="fas fa-chart-column"></i> Reporte</a>
                    @endif
                    @if ($esAdmin)
                        <button type="button" @click="abrirConfig('tarifas')" class="{{ $btn }} bg-white text-indigo-800 hover:bg-indigo-50"><i class="fas fa-gear"></i> Configurar</button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                <div class="rounded-2xl bg-white/10 p-4">
                    <p class="text-xs text-indigo-200">Dentro ahora</p>
                    <p class="text-3xl font-black" x-text="dentro.length"></p>
                </div>
                <div class="rounded-2xl bg-white/10 p-4">
                    <p class="text-xs text-indigo-200">Espacios libres</p>
                    <p class="text-3xl font-black"><span x-text="libres"></span><span class="text-base font-semibold text-indigo-200" x-text="' / ' + activos"></span></p>
                </div>
                <div class="rounded-2xl bg-white/10 p-4">
                    <p class="text-xs text-indigo-200">Ingresaron hoy</p>
                    <p class="text-3xl font-black" x-text="resumen.ingresos"></p>
                </div>
                <div class="rounded-2xl bg-white/10 p-4">
                    <p class="text-xs text-indigo-200">Recaudado hoy <span x-text="'(' + resumen.salidas + ' salidas)'"></span></p>
                    <p class="text-3xl font-black" x-text="soles(resumen.recaudado)"></p>
                </div>
            </div>
        </section>

        @if (!$turno)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                <i class="fas fa-triangle-exclamation"></i> No tienes un turno de caja abierto: puedes registrar entradas, pero para cobrar salidas
                <a href="{{ route('turnos.index') }}" class="font-bold underline">apertura tu turno</a>.
            </div>
        @endif
        @if ($tarifas->where('activo', 1)->isEmpty())
            <div class="rounded-2xl border-2 border-dashed border-indigo-300 bg-indigo-50 p-5 text-sm text-indigo-900">
                <p class="font-bold text-base"><i class="fas fa-flag-checkered"></i> Primer paso: crea tus tarifas y espacios</p>
                <p class="mt-1">Por ejemplo: <b>AUTO</b> S/ 4.00 la hora con fracción de 15 min y 10 min de tolerancia, <b>MOTO</b> S/ 2.00 la hora. Luego genera tus espacios: A-01 al A-30.</p>
                @if ($esAdmin)<button type="button" @click="abrirConfig('tarifas')" class="mt-3 {{ $btn }} bg-indigo-600 text-white hover:bg-indigo-700">Configurar ahora</button>@endif
            </div>
        @endif

        {{-- Valet: autos que el cliente pidió --}}
        <template x-if="solicitados.length">
            <div class="rounded-2xl bg-rose-50 ring-1 ring-rose-200 p-4">
                <p class="font-black text-rose-800 mb-3"><i class="fas fa-bell"></i> Autos solicitados · llévalos a la salida</p>
                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
                    <template x-for="t in solicitados" :key="t.tic_id">
                        <button type="button" @click="abrirTicket(t.tic_id)" class="latido text-left rounded-xl bg-white ring-2 ring-rose-400 p-3 flex items-center gap-3 hover:bg-rose-50">
                            <span class="w-11 h-11 rounded-xl bg-rose-500 text-white flex items-center justify-center text-lg"><i class="fas" :class="t.icono"></i></span>
                            <span class="flex-1 min-w-0">
                                <span class="placa block font-black text-gray-900 text-lg" x-text="t.placa"></span>
                                <span class="block text-xs text-gray-600">
                                    <span x-show="t.llavero" x-text="'Llavero ' + t.llavero + ' · '"></span>
                                    <span x-text="t.espacio ? 'Espacio ' + t.espacio : 'Sin espacio'"></span>
                                    · pidió hace <b x-text="hace(t.solicitado)"></b>
                                </span>
                            </span>
                            <span class="text-xs font-bold text-rose-700">Atender <i class="fas fa-chevron-right"></i></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- Buscar ticket --}}
        <form @submit.prevent="buscar()" class="bg-white rounded-2xl shadow-sm p-3 flex gap-2">
            <div class="relative flex-1">
                <i class="fas fa-qrcode absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input x-model="q" x-ref="buscador" placeholder="Escanea el QR del ticket o escribe la placa o el N° de ticket" class="{{ $in }} pl-10 h-11 text-base">
            </div>
            <button class="{{ $btn }} bg-indigo-600 text-white hover:bg-indigo-700 px-6"><i class="fas fa-right-from-bracket"></i> <span class="hidden sm:inline">Dar salida</span></button>
        </form>

        <div class="grid lg:grid-cols-3 gap-5 items-start">
            {{-- Nueva entrada --}}
            <form @submit.prevent="registrarEntrada()" class="bg-white rounded-2xl shadow-sm p-5 space-y-4 lg:sticky lg:top-4">
                <h2 class="font-black text-gray-800 flex items-center gap-2"><i class="fas fa-right-to-bracket text-emerald-600"></i> Nueva entrada</h2>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-500">Placa</span>
                    <span class="mt-1 block rounded-xl border-2 border-gray-800 overflow-hidden bg-white shadow-sm">
                        <span class="placa-franja block text-center text-[10px] font-black tracking-[.4em] text-white py-0.5">PERÚ</span>
                        <input x-model="entrada.placa" x-ref="placa" @input="entrada.placa = entrada.placa.toUpperCase().replace(/[^A-Z0-9-]/g, '')" @change="revisarPlaca()"
                               maxlength="8" placeholder="ABC-123" autocomplete="off" required
                               class="placa w-full border-0 text-center text-3xl font-black text-gray-900 py-2 focus:ring-0 placeholder:text-gray-300">
                    </span>
                </label>

                <div>
                    <span class="text-xs font-semibold text-gray-500">Tipo de vehículo</span>
                    <div class="mt-1 grid grid-cols-3 gap-2">
                        <template x-for="t in tarifasActivas" :key="t.tar_id">
                            <button type="button" @click="entrada.tar_id = t.tar_id; sugerirEspacio()"
                                    class="rounded-xl border-2 py-2 px-1 text-center transition"
                                    :class="entrada.tar_id === t.tar_id ? 'border-indigo-600 bg-indigo-50 text-indigo-800' : 'border-gray-200 text-gray-600 hover:border-indigo-300'">
                                <i class="fas text-xl" :class="t.fa"></i>
                                <span class="block text-[11px] font-bold mt-0.5 truncate" x-text="t.nombre"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-500">Espacio <span class="font-normal text-gray-400">(o tócalo en el mapa)</span></span>
                    <select x-model.number="entrada.esp_id" class="{{ $in }} mt-1">
                        <option value="">Sin espacio asignado</option>
                        <template x-for="e in espaciosLibres(entrada.tar_id)" :key="e.esp_id">
                            <option :value="e.esp_id" x-text="e.codigo + (e.zona ? ' · ' + e.zona : '')" :selected="e.esp_id === entrada.esp_id"></option>
                        </template>
                    </select>
                </label>

                <label class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2.5 cursor-pointer">
                    <span class="text-sm font-bold text-gray-700"><i class="fas fa-key text-amber-500"></i> Valet parking <span class="font-normal text-gray-500 text-xs">(dejan la llave)</span></span>
                    <input type="checkbox" x-model="entrada.valet" class="rounded text-indigo-600 w-5 h-5">
                </label>
                <div x-show="entrada.valet" x-collapse class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs font-semibold text-gray-500">N° de llavero<input x-model="entrada.llavero" maxlength="10" class="{{ $in }} mt-1"></label>
                        <label class="text-xs font-semibold text-gray-500">Lo estaciona
                            <select x-model="entrada.IdUsuario_valet" class="{{ $in }} mt-1">
                                <option value="">—</option>
                                @foreach ($valets as $v)<option value="{{ $v->IdUsuario }}">{{ \Illuminate\Support\Str::limit(preg_replace('/^\d{8,11}\s+/', '', $v->apeusu), 22) }}</option>@endforeach
                            </select></label>
                    </div>
                </div>

                <details class="group" :open="entrada.valet">
                    <summary class="text-xs font-semibold text-indigo-700 cursor-pointer select-none">Datos del vehículo y del cliente (opcional)</summary>
                    <div class="mt-3 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <input x-model="entrada.marca" maxlength="40" placeholder="Marca / modelo" class="{{ $in }}">
                            <input x-model="entrada.color" maxlength="20" placeholder="Color" class="{{ $in }}">
                        </div>
                        <textarea x-model="entrada.observaciones" maxlength="200" rows="2" placeholder="Daños visibles, objetos de valor…" class="{{ $in }}"></textarea>
                        <div class="grid grid-cols-2 gap-2">
                            <input x-model="entrada.cliente" maxlength="120" placeholder="Nombre del cliente" class="{{ $in }}">
                            <input x-model="entrada.telefono" maxlength="20" placeholder="Celular" class="{{ $in }}">
                        </div>
                    </div>
                </details>

                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" x-model="entrada.imprimir" class="rounded"> Imprimir ticket</label>
                <button :disabled="ocupado" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-black hover:bg-emerald-700 disabled:opacity-50">
                    <i class="fas fa-ticket"></i> <span x-text="ocupado ? 'Registrando…' : 'REGISTRAR ENTRADA'"></span>
                </button>
            </form>

            {{-- Mapa y lista --}}
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm">
                <div class="flex items-center gap-1 border-b px-3 pt-3">
                    <button type="button" @click="vista = 'mapa'" class="px-4 py-2 text-sm font-bold rounded-t-lg border-b-2 -mb-px"
                            :class="vista === 'mapa' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"><i class="fas fa-table-cells"></i> Mapa</button>
                    <button type="button" @click="vista = 'lista'" class="px-4 py-2 text-sm font-bold rounded-t-lg border-b-2 -mb-px"
                            :class="vista === 'lista' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"><i class="fas fa-list"></i> Vehículos dentro <span class="ml-1 px-1.5 rounded-full bg-gray-100 text-xs" x-text="dentro.length"></span></button>
                    <div class="ml-auto hidden sm:flex items-center gap-3 text-[11px] text-gray-500 pb-2">
                        <span><span class="inline-block w-3 h-3 rounded border-2 border-emerald-400 align-middle"></span> Libre</span>
                        <span><span class="inline-block w-3 h-3 rounded bg-indigo-600 align-middle"></span> Ocupado</span>
                        <span><span class="inline-block w-3 h-3 rounded border-2 border-dashed border-amber-400 align-middle"></span> Reservado</span>
                        <span><span class="inline-block w-3 h-3 rounded mantenimiento align-middle"></span> Mantenimiento</span>
                    </div>
                </div>

                {{-- Mapa de espacios --}}
                <div x-show="vista === 'mapa'" class="p-4 space-y-5">
                    <template x-if="!espacios.length">
                        <div class="text-center text-gray-500 py-10">
                            <i class="fas fa-border-all text-4xl text-gray-300"></i>
                            <p class="mt-2 text-sm">Aún no hay espacios. {{ $esAdmin ? 'Créalos en Configurar → Espacios.' : 'Pide al administrador que los cree.' }}</p>
                            <p class="text-xs text-gray-400 mt-1">Igual puedes registrar entradas sin espacio.</p>
                        </div>
                    </template>
                    <template x-for="z in zonas" :key="z.nombre">
                        <div>
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">
                                <span x-text="z.nombre"></span>
                                <span class="normal-case font-semibold text-gray-400" x-text="z.libres + ' libre(s) de ' + z.total"></span>
                                <span class="kene kene-franja flex-1 h-3 text-gray-200" aria-hidden="true"></span>
                            </h3>
                            <div class="grid grid-cols-3 sm:grid-cols-5 xl:grid-cols-7 gap-2">
                                <template x-for="e in z.espacios" :key="e.esp_id">
                                    <button type="button" @click="tocarEspacio(e)"
                                            class="relative h-20 rounded-xl p-2 text-left transition flex flex-col justify-between"
                                            :class="claseEspacio(e)" :title="e.estado === 'MANTENIMIENTO' ? 'En mantenimiento' : (e.placa ? 'Ver ticket' : 'Registrar entrada aquí')">
                                        <span class="flex items-center justify-between text-[11px] font-black">
                                            <span x-text="e.codigo"></span>
                                            <i class="fas text-xs opacity-70" :class="iconoTarifa(e.tar_id)"></i>
                                        </span>
                                        <template x-if="ticketEn(e)">
                                            <span>
                                                <span class="placa block font-black text-sm leading-tight truncate" x-text="ticketEn(e).placa"></span>
                                                <span class="block text-[10px] opacity-80" x-text="tiempo(ticketEn(e).entrada)"></span>
                                            </span>
                                        </template>
                                        <template x-if="!ticketEn(e)">
                                            <span class="text-[10px] font-semibold" x-text="e.estado === 'MANTENIMIENTO' ? 'Mantenimiento' : (e.reservado ? 'Reservado ' + e.reservado : (entrada.esp_id === e.esp_id ? 'Elegido' : 'Libre'))"></span>
                                        </template>
                                        <span x-show="ticketEn(e)?.estado === 'SOLICITADO'" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center latido"><i class="fas fa-bell"></i></span>
                                        <span x-show="ticketEn(e)?.valet" class="absolute -top-1.5 -left-1.5 w-5 h-5 rounded-full bg-amber-400 text-amber-950 text-[10px] flex items-center justify-center" title="Valet"><i class="fas fa-key"></i></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="sinEspacio.length">
                        <div>
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Sin espacio asignado <span class="kene kene-franja flex-1 h-3 text-gray-200" aria-hidden="true"></span></h3>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="t in sinEspacio" :key="t.tic_id">
                                    <button type="button" @click="abrirTicket(t.tic_id)" class="rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-3 py-2 text-left hover:bg-indigo-100">
                                        <span class="placa block font-black text-indigo-900 text-sm" x-text="t.placa"></span>
                                        <span class="block text-[10px] text-indigo-700" x-text="t.tipo + ' · ' + tiempo(t.entrada)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Lista --}}
                <div x-show="vista === 'lista'" x-cloak>
                    <div class="p-3 border-b">
                        <input x-model="filtro" placeholder="Filtrar por placa, llavero, espacio o cliente" class="{{ $in }}">
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-500 bg-gray-50">
                                <tr><th class="px-3 py-2 text-left">Ticket</th><th class="px-3 py-2 text-left">Placa</th><th class="px-3 py-2 text-left">Espacio</th>
                                    <th class="px-3 py-2 text-left">Entrada</th><th class="px-3 py-2 text-left">Tiempo</th><th class="px-3 py-2 text-right">Debe</th><th class="px-3 py-2"></th></tr>
                            </thead>
                            <tbody class="divide-y">
                                <template x-for="t in dentroFiltrado" :key="t.tic_id">
                                    <tr class="hover:bg-indigo-50/40" :class="t.estado === 'SOLICITADO' ? 'bg-rose-50' : ''">
                                        <td class="px-3 py-2 font-semibold text-gray-500" x-text="'N° ' + t.numero"></td>
                                        <td class="px-3 py-2">
                                            <span class="placa font-black text-gray-900" x-text="t.placa"></span>
                                            <span class="block text-[11px] text-gray-500"><i class="fas" :class="t.icono"></i> <span x-text="t.tipo"></span>
                                                <span x-show="t.marca || t.color" x-text="' · ' + [t.marca, t.color].filter(Boolean).join(' ')"></span></span>
                                            <span x-show="t.valet" class="inline-block mt-0.5 text-[10px] font-bold px-1.5 rounded bg-amber-100 text-amber-800"><i class="fas fa-key"></i> <span x-text="t.llavero ? 'Llavero ' + t.llavero : 'Valet'"></span></span>
                                            <span x-show="t.abonado" class="inline-block mt-0.5 text-[10px] font-bold px-1.5 rounded bg-emerald-100 text-emerald-800">ABONADO</span>
                                            <span x-show="t.estado === 'SOLICITADO'" class="inline-block mt-0.5 text-[10px] font-bold px-1.5 rounded bg-rose-100 text-rose-700">SOLICITADO</span>
                                        </td>
                                        <td class="px-3 py-2" x-text="t.espacio || '—'"></td>
                                        <td class="px-3 py-2" x-text="hora(t.entrada)"></td>
                                        <td class="px-3 py-2 font-semibold" x-text="tiempo(t.entrada)"></td>
                                        <td class="px-3 py-2 text-right font-black text-gray-800" x-text="t.abonado ? '—' : soles(t.importe)"></td>
                                        <td class="px-3 py-2 text-right"><button type="button" @click="abrirTicket(t.tic_id)" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">Salida</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p x-show="!dentroFiltrado.length" class="text-center text-gray-400 py-10 text-sm">No hay vehículos dentro.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: ticket (detalle y salida) --}}
        <div x-show="modal === 'ticket'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start sm:items-center justify-center p-3 overflow-y-auto" @keydown.escape.window="cerrarTicket()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-6" @click.outside="cerrarTicket()" x-show="tk">
                <template x-if="tk">
                    <div>
                        <div class="relative isolate overflow-hidden rounded-t-2xl bg-gradient-to-r from-indigo-800 to-blue-700 text-white px-5 py-4 flex items-center gap-4">
                            <x-kene-adorno patron="rombos" :franja="false" />
                            <span class="rounded-lg border-2 border-white/80 overflow-hidden bg-white text-gray-900 shrink-0">
                                <span class="placa-franja block text-center text-[8px] font-black tracking-[.4em] text-white">PERÚ</span>
                                <span class="placa block px-3 text-2xl font-black" x-text="tk.ticket.placa"></span>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-lg" x-text="'Ticket N° ' + tk.ticket.numero + ' · ' + tk.ticket.tipo"></p>
                                <p class="text-xs text-indigo-100" x-text="'Entró ' + fechaHora(tk.ticket.entrada) + (tk.ticket.usuario_entrada ? ' · registró ' + nombreCorto(tk.ticket.usuario_entrada) : '')"></p>
                            </div>
                            <button type="button" @click="cerrarTicket()" class="w-9 h-9 rounded-full hover:bg-white/15" aria-label="Cerrar"><i class="fas fa-xmark text-lg"></i></button>
                        </div>

                        <div class="grid md:grid-cols-2 gap-0 md:divide-x">
                            {{-- Datos --}}
                            <div class="p-5 space-y-4 text-sm">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-gray-50 p-3"><p class="text-xs text-gray-500">Tiempo</p><p class="font-black text-xl text-gray-800" x-text="tk.cobro.duracion"></p></div>
                                    <div class="rounded-xl bg-gray-50 p-3"><p class="text-xs text-gray-500">Espacio</p>
                                        <template x-if="abierto">
                                            <select @change="moverEspacio($event.target.value)" class="mt-0.5 w-full rounded-lg border-gray-300 text-sm py-1">
                                                <option value="">Sin espacio</option>
                                                <template x-if="tk.ticket.esp_id"><option :value="tk.ticket.esp_id" selected x-text="tk.ticket.espacio"></option></template>
                                                <template x-for="e in espaciosLibres(tk.ticket.tar_id)" :key="e.esp_id"><option :value="e.esp_id" x-text="e.codigo"></option></template>
                                            </select>
                                        </template>
                                        <p x-show="!abierto" class="font-black text-xl text-gray-800" x-text="tk.ticket.espacio || '—'"></p>
                                    </div>
                                </div>

                                <template x-if="tk.ticket.valet">
                                    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-3 space-y-1">
                                        <p class="font-bold text-amber-900"><i class="fas fa-key"></i> Valet parking <span x-show="tk.ticket.llavero" x-text="'· Llavero ' + tk.ticket.llavero"></span></p>
                                        <p x-show="tk.ticket.usuario_valet" class="text-amber-800 text-xs" x-text="'Lo estacionó: ' + nombreCorto(tk.ticket.usuario_valet)"></p>
                                        <p x-show="tk.ticket.solicitado" class="text-rose-700 text-xs font-bold" x-text="'El cliente lo pidió a las ' + hora(tk.ticket.solicitado)"></p>
                                    </div>
                                </template>
                                <dl class="grid grid-cols-[110px_1fr] gap-y-1.5">
                                    <template x-if="tk.ticket.marca || tk.ticket.color"><dt class="text-gray-500">Vehículo</dt></template>
                                    <template x-if="tk.ticket.marca || tk.ticket.color"><dd class="font-semibold" x-text="[tk.ticket.marca, tk.ticket.color].filter(Boolean).join(' · ')"></dd></template>
                                    <template x-if="tk.ticket.cliente || tk.ticket.telefono"><dt class="text-gray-500">Cliente</dt></template>
                                    <template x-if="tk.ticket.cliente || tk.ticket.telefono"><dd class="font-semibold" x-text="[tk.ticket.cliente, tk.ticket.telefono].filter(Boolean).join(' · ')"></dd></template>
                                    <template x-if="tk.ticket.observaciones"><dt class="text-gray-500">Observaciones</dt></template>
                                    <template x-if="tk.ticket.observaciones"><dd class="font-semibold text-rose-700" x-text="tk.ticket.observaciones"></dd></template>
                                    <template x-if="tk.ticket.salida"><dt class="text-gray-500">Salió</dt></template>
                                    <template x-if="tk.ticket.salida"><dd class="font-semibold" x-text="fechaHora(tk.ticket.salida) + (tk.ticket.usuario_salida ? ' · ' + nombreCorto(tk.ticket.usuario_salida) : '')"></dd></template>
                                    <template x-if="tk.ticket.motivo"><dt class="text-gray-500">Nota</dt></template>
                                    <template x-if="tk.ticket.motivo"><dd class="font-semibold" x-text="tk.ticket.motivo"></dd></template>
                                </dl>

                                <div class="flex flex-wrap gap-2 pt-2 border-t">
                                    <a :href="RUTA + '/tickets/' + tk.ticket.tic_id + '/imprimir'" target="_blank" class="{{ $btn }} bg-gray-100 text-gray-700 hover:bg-gray-200 px-3"><i class="fas fa-print"></i> Ticket</a>
                                    <template x-if="abierto && tk.ticket.valet">
                                        <button type="button" @click="solicitar()" class="{{ $btn }} px-3" :class="tk.ticket.estado === 'SOLICITADO' ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-amber-100 text-amber-800 hover:bg-amber-200'">
                                            <i class="fas fa-bell"></i> <span x-text="tk.ticket.estado === 'SOLICITADO' ? 'Cancelar pedido' : 'Pidió su auto'"></span></button>
                                    </template>
                                    @if ($esAdmin)
                                        <template x-if="abierto"><button type="button" @click="anular()" class="{{ $btn }} bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 px-3"><i class="fas fa-ban"></i> Anular</button></template>
                                    @endif
                                </div>
                            </div>

                            {{-- Cobro --}}
                            <div class="p-5 space-y-3 text-sm bg-gray-50/60 md:rounded-br-2xl">
                                <template x-if="!abierto">
                                    <div class="text-center py-6">
                                        <p class="text-5xl" x-text="tk.ticket.estado === 'ANULADO' ? '🚫' : '✅'"></p>
                                        <p class="mt-2 font-black text-gray-800 text-lg" x-text="tk.ticket.estado === 'ANULADO' ? 'Ticket anulado' : 'El vehículo ya salió'"></p>
                                        <p x-show="tk.ticket.estado === 'SALIO'" class="text-3xl font-black text-indigo-700 mt-2" x-text="soles(tk.ticket.total)"></p>
                                        <a x-show="tk.ticket.IdCpe_cabecera" :href="'{{ url('voucher') }}/' + tk.ticket.IdCpe_cabecera" target="_blank" class="text-indigo-600 underline text-sm" x-text="tk.ticket.comprobante"></a>
                                    </div>
                                </template>
                                <template x-if="abierto">
                                    <div class="space-y-3">
                                        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-3">
                                            <p class="text-xs text-gray-500" x-text="tk.cobro.detalle"></p>
                                            <div class="flex justify-between mt-1"><span>Por el tiempo</span><b x-text="soles(tk.cobro.importe)"></b></div>
                                            <div x-show="tk.cobro.penalidad > 0" class="flex justify-between text-rose-700"><span>Ticket perdido</span><b x-text="'+ ' + soles(tk.cobro.penalidad)"></b></div>
                                            <div x-show="cobro.descuento > 0" class="flex justify-between text-emerald-700"><span>Descuento</span><b x-text="'- ' + soles(Math.min(cobro.descuento, bruto))"></b></div>
                                            <div class="flex justify-between items-end border-t mt-2 pt-2"><span class="font-bold text-gray-700">Total a cobrar</span><span class="text-3xl font-black text-indigo-700" x-text="soles(totalCobro)"></span></div>
                                        </div>
                                        <p x-show="tk.cobro.abonado" class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-xs font-semibold"><i class="fas fa-id-card"></i> <span x-text="tk.cobro.abonado ? tk.cobro.abonado.clinom : ''"></span></p>

                                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                                            <label class="flex items-center gap-2"><input type="checkbox" x-model="cobro.perdido" @change="recotizar()" class="rounded"> Ticket perdido</label>
                                            @if (auth()->user()->esAdminOCaja())
                                                <label class="flex items-center gap-2"><input type="checkbox" x-model="cobro.conDescuento" @change="if (!cobro.conDescuento) { cobro.descuento = 0; cobro.motivo = '' }" class="rounded"> Descuento / cortesía</label>
                                            @endif
                                        </div>
                                        <div x-show="cobro.conDescuento" class="grid grid-cols-[110px_1fr] gap-2">
                                            <input type="number" step="0.10" min="0" x-model.number="cobro.descuento" placeholder="S/" class="{{ $in }}">
                                            <input x-model="cobro.motivo" maxlength="200" placeholder="Motivo (obligatorio)" class="{{ $in }}">
                                        </div>

                                        <template x-if="totalCobro > 0">
                                            <div class="space-y-3">
                                                <div class="grid grid-cols-3 gap-1 rounded-xl bg-white ring-1 ring-gray-200 p-1">
                                                    <template x-for="[cod, nom] in [['03', 'Boleta'], ['01', 'Factura'], ['13', 'Nota de venta']]" :key="cod">
                                                        <button type="button" @click="cobro.tdocod = cod" class="py-1.5 rounded-lg text-xs font-bold" :class="cobro.tdocod === cod ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" x-text="nom"></button>
                                                    </template>
                                                </div>
                                                <div class="grid grid-cols-[120px_1fr] gap-2">
                                                    <input x-model="cobro.clinum" @change="buscarDoc()" maxlength="15" :placeholder="cobro.tdocod === '01' ? 'RUC' : 'DNI (opcional)'" class="{{ $in }}">
                                                    <input x-model="cobro.clinom" maxlength="150" :placeholder="cobro.tdocod === '01' ? 'Razón social' : 'Nombre (opcional)'" class="{{ $in }}">
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <select x-model="cobro.medio" class="{{ $in }}">
                                                        @foreach ($mediospagos as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                                                    </select>
                                                    <input type="number" step="0.10" min="0" x-model.number="cobro.paga" placeholder="Paga con S/" class="{{ $in }}">
                                                </div>
                                                <p x-show="cobro.paga > totalCobro" class="text-sm">Vuelto: <b class="text-emerald-700 text-base" x-text="soles(cobro.paga - totalCobro)"></b></p>
                                                <label class="flex items-center gap-2"><input type="checkbox" x-model="cobro.imprimir" class="rounded"> Imprimir comprobante</label>
                                            </div>
                                        </template>

                                        <button type="button" @click="darSalida()" :disabled="ocupado || (totalCobro > 0 && {{ $turno ? 'false' : 'true' }})"
                                                class="w-full py-3.5 rounded-xl text-white font-black disabled:opacity-50"
                                                :class="totalCobro > 0 ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                                                x-text="ocupado ? 'Registrando…' : (totalCobro > 0 ? 'COBRAR ' + soles(totalCobro) + ' Y DAR SALIDA' : 'DAR SALIDA SIN COBRO')"></button>
                                        @if (!$turno)<p x-show="totalCobro > 0" class="text-xs text-amber-700">Apertura tu turno de caja para cobrar.</p>@endif
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        @if ($esAdmin)
            {{-- Modal: configuración --}}
            <div x-show="modal === 'config'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-3 overflow-y-auto">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-6" @click.outside="modal = null">
                    <div class="flex items-center gap-1 border-b px-4 pt-3">
                        <button type="button" @click="tabConfig = 'tarifas'" class="px-4 py-2 text-sm font-bold border-b-2 -mb-px" :class="tabConfig === 'tarifas' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500'"><i class="fas fa-tags"></i> Tarifas</button>
                        <button type="button" @click="tabConfig = 'espacios'" class="px-4 py-2 text-sm font-bold border-b-2 -mb-px" :class="tabConfig === 'espacios' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500'"><i class="fas fa-border-all"></i> Espacios</button>
                        <button type="button" @click="modal = null" class="ml-auto mb-2 w-9 h-9 rounded-full hover:bg-gray-100" aria-label="Cerrar"><i class="fas fa-xmark"></i></button>
                    </div>

                    {{-- Tarifas --}}
                    <div x-show="tabConfig === 'tarifas'" class="p-5 grid md:grid-cols-2 gap-5 text-sm">
                        <div class="space-y-2">
                            <template x-for="t in tarifas" :key="t.tar_id">
                                <button type="button" @click="editarTarifa(t)" class="w-full text-left rounded-xl border-2 p-3 flex items-center gap-3 transition"
                                        :class="tarifa.tar_id === t.tar_id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-300'" :style="t.activo ? '' : 'opacity:.5'">
                                    <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center"><i class="fas" :class="t.fa"></i></span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block font-black text-gray-800" x-text="t.nombre + (t.activo ? '' : ' (inactiva)')"></span>
                                        <span class="block text-xs text-gray-500" x-text="t.texto"></span>
                                        <span class="block text-[11px] text-gray-400" x-text="'Tolerancia ' + t.tolerancia_min + ' min' + (Number(t.perdido) ? ' · ticket perdido ' + soles(t.perdido) : '') + (Number(t.pension) ? ' · pensión ' + soles(t.pension) : '')"></span>
                                    </span>
                                </button>
                            </template>
                            <p x-show="!tarifas.length" class="text-gray-400">Aún no hay tarifas.</p>
                            <button type="button" @click="tarifa = tarifaVacia()" class="text-indigo-600 font-bold text-sm"><i class="fas fa-plus"></i> Nueva tarifa</button>
                        </div>
                        <form @submit.prevent="guardarTarifa()" class="rounded-2xl bg-gray-50 p-4 space-y-3">
                            <p class="font-black text-gray-800" x-text="tarifa.tar_id ? 'Editar tarifa' : 'Nueva tarifa'"></p>
                            <div class="grid grid-cols-[1fr_auto] gap-2">
                                <label>Tipo de vehículo<input x-model="tarifa.nombre" maxlength="40" placeholder="AUTO" required class="{{ $in }} mt-1"></label>
                                <label>Ícono
                                    <div class="mt-1 flex gap-1">
                                        @foreach ($iconos as $clave => $fa)
                                            <button type="button" @click="tarifa.icono = '{{ $clave }}'" class="w-9 h-9 rounded-lg border-2" :class="tarifa.icono === '{{ $clave }}' ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-gray-500'"><i class="fas {{ $fa }}"></i></button>
                                        @endforeach
                                    </div></label>
                            </div>
                            <label class="block">Forma de cobro
                                <select x-model="tarifa.modo" class="{{ $in }} mt-1">
                                    @foreach ($modos as $clave => $nombre)<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                                </select></label>
                            <div class="grid grid-cols-2 gap-2">
                                <label><span x-text="tarifa.modo === 'FIJO' ? 'Precio por día S/' : 'Precio por hora S/'"></span><input type="number" step="0.10" min="0.1" x-model.number="tarifa.precio" required class="{{ $in }} mt-1"></label>
                                <label x-show="tarifa.modo === 'FRACCION'">Fracción (min)<input type="number" min="1" max="60" x-model.number="tarifa.fraccion_min" class="{{ $in }} mt-1"></label>
                                <label>Tolerancia (min)<input type="number" min="0" max="120" x-model.number="tarifa.tolerancia_min" class="{{ $in }} mt-1"></label>
                                <label>Tope por día S/<input type="number" step="0.10" min="0" x-model.number="tarifa.tope_dia" placeholder="0 = sin tope" class="{{ $in }} mt-1"></label>
                                <label>Ticket perdido S/<input type="number" step="0.10" min="0" x-model.number="tarifa.perdido" class="{{ $in }} mt-1"></label>
                                <label>Pensión mensual S/<input type="number" step="0.10" min="0" x-model.number="tarifa.pension" class="{{ $in }} mt-1"></label>
                            </div>
                            <p class="rounded-lg bg-white px-3 py-2 text-xs text-gray-600" x-text="ejemploTarifa()"></p>
                            <label class="flex items-center gap-2"><input type="checkbox" x-model="tarifa.activo" class="rounded"> Activa</label>
                            <button :disabled="ocupado" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 disabled:opacity-50">Guardar tarifa</button>
                        </form>
                    </div>

                    {{-- Espacios --}}
                    <div x-show="tabConfig === 'espacios'" class="p-5 space-y-5 text-sm">
                        <form @submit.prevent="generarEspacios()" class="rounded-2xl bg-gray-50 p-4">
                            <p class="font-black text-gray-800 mb-2">Crear espacios</p>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 items-end">
                                <label>Prefijo<input x-model="gen.prefijo" maxlength="4" placeholder="A" required class="{{ $in }} mt-1 uppercase"></label>
                                <label>Del N°<input type="number" min="1" x-model.number="gen.desde" required class="{{ $in }} mt-1"></label>
                                <label>Al N°<input type="number" min="1" x-model.number="gen.hasta" required class="{{ $in }} mt-1"></label>
                                <label>Zona<input x-model="gen.zona" maxlength="30" placeholder="SÓTANO 1" class="{{ $in }} mt-1"></label>
                                <label>Para
                                    <select x-model="gen.tar_id" class="{{ $in }} mt-1">
                                        <option value="">Cualquier vehículo</option>
                                        <template x-for="t in tarifas" :key="t.tar_id"><option :value="t.tar_id" x-text="t.nombre"></option></template>
                                    </select></label>
                            </div>
                            <div class="flex items-center justify-between mt-3">
                                <p class="text-xs text-gray-500" x-text="gen.prefijo && gen.desde && gen.hasta >= gen.desde ? 'Se crearán ' + (gen.hasta - gen.desde + 1) + ': ' + codigoGen(gen.desde) + ' … ' + codigoGen(gen.hasta) : ''"></p>
                                <button :disabled="ocupado" class="{{ $btn }} bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"><i class="fas fa-plus"></i> Crear</button>
                            </div>
                        </form>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="text-xs text-gray-500 bg-gray-50"><tr><th class="px-2 py-2 text-left">Código</th><th class="px-2 py-2 text-left">Zona</th><th class="px-2 py-2 text-left">Para</th><th class="px-2 py-2 text-left">Estado</th><th></th></tr></thead>
                                <tbody class="divide-y">
                                    <template x-for="e in espacios" :key="e.esp_id">
                                        <tr>
                                            <td class="px-2 py-1"><input x-model="e.codigo" maxlength="10" class="w-24 rounded-lg border-gray-300 text-sm py-1 uppercase"></td>
                                            <td class="px-2 py-1"><input x-model="e.zona" maxlength="30" class="w-32 rounded-lg border-gray-300 text-sm py-1"></td>
                                            <td class="px-2 py-1"><select x-model="e.tar_id" class="rounded-lg border-gray-300 text-sm py-1">
                                                <option value="">Cualquiera</option>
                                                <template x-for="t in tarifas" :key="t.tar_id"><option :value="t.tar_id" :selected="t.tar_id == e.tar_id" x-text="t.nombre"></option></template>
                                            </select></td>
                                            <td class="px-2 py-1"><select x-model="e.estado" class="rounded-lg border-gray-300 text-sm py-1"><option value="ACTIVO">Activo</option><option value="MANTENIMIENTO">Mantenimiento</option></select></td>
                                            <td class="px-2 py-1 text-right whitespace-nowrap">
                                                <button type="button" @click="guardarEspacio(e)" class="px-2 py-1 rounded-lg text-indigo-600 hover:bg-indigo-50" title="Guardar"><i class="fas fa-floppy-disk"></i></button>
                                                <button type="button" @click="quitarEspacio(e)" class="px-2 py-1 rounded-lg text-rose-600 hover:bg-rose-50" title="Eliminar"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <p x-show="!espacios.length" class="text-center text-gray-400 py-6">Aún no hay espacios.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Aviso --}}
        <div x-show="aviso.visible" x-cloak x-transition class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-2xl text-white text-sm font-semibold max-w-md text-center"
             :class="aviso.ok ? 'bg-emerald-600' : 'bg-rose-600'" x-text="aviso.texto"></div>
    </div>

    <script>
        function estacionamiento() {
            const RUTA = @json(url('estacionamiento'));
            const CSRF = @json(csrf_token());
            const MEDIO = @json((string) ($mediospagos->first()->id_med_pag ?? ''));
            const entradaVacia = (tar) => ({ placa: '', tar_id: tar, esp_id: '', valet: false, llavero: '', IdUsuario_valet: '', marca: '', color: '', observaciones: '', cliente: '', telefono: '', imprimir: true });
            const tarifaVacia = () => ({ tar_id: null, nombre: '', icono: 'auto', modo: 'FRACCION', precio: null, fraccion_min: 15, tolerancia_min: 10, tope_dia: 0, perdido: 0, pension: 0, activo: true });
            return {
                RUTA, tarifas: @js($tarifas), espacios: @js($espacios), dentro: @js($dentro), resumen: @js($resumen),
                entrada: {}, tk: null, cobro: {}, modal: null, vista: 'mapa', tabConfig: 'tarifas', q: '', filtro: '', ocupado: false,
                tarifa: tarifaVacia(), tarifaVacia, gen: { prefijo: 'A', desde: 1, hasta: 20, zona: '', tar_id: '' },
                ahora: Date.now(), desfase: new Date(@json(now()->format('Y-m-d\TH:i:s'))).getTime() - Date.now(), avisados: new Set(),
                aviso: { visible: false, ok: true, texto: '' },

                iniciar() {
                    this.entrada = entradaVacia(this.tarifasActivas[0]?.tar_id ?? null);
                    this.sugerirEspacio();
                    this.dentro.filter(t => t.estado === 'SOLICITADO').forEach(t => this.avisados.add(t.tic_id));
                    setInterval(() => this.ahora = Date.now(), 1000);
                    setInterval(() => this.refrescar(), 15000);
                    this.$nextTick(() => this.$refs.placa?.focus({ preventScroll: true }));
                },
                async refrescar() {
                    const d = await fetch(RUTA + '/estado', { headers: { 'Accept': 'application/json' } }).then(r => r.ok ? r.json() : null).catch(() => null);
                    if (!d) return;
                    this.dentro = d.dentro; this.resumen = d.resumen;
                    if (this.modal !== 'config') this.espacios = d.espacios;
                    // Valet: suena cuando un cliente pide su auto desde el QR
                    const nuevos = d.dentro.filter(t => t.estado === 'SOLICITADO' && !this.avisados.has(t.tic_id));
                    if (nuevos.length) { nuevos.forEach(t => this.avisados.add(t.tic_id)); this.timbre(); this.avisar('🔔 Piden el auto ' + nuevos.map(t => t.placa).join(', '), true); }
                },
                timbre() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        [0, .25].forEach(t => { const o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
                            g.gain.setValueAtTime(.25, ctx.currentTime + t); g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + t + .2); o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + .2); });
                    } catch (e) {}
                },

                // ---- datos derivados
                get tarifasActivas() { return this.tarifas.filter(t => Number(t.activo)); },
                get activos() { return this.espacios.filter(e => e.estado === 'ACTIVO').length; },
                get libres() { return this.espacios.filter(e => e.estado === 'ACTIVO' && !this.ticketEn(e) && !e.reservado).length; },
                get solicitados() { return this.dentro.filter(t => t.estado === 'SOLICITADO'); },
                get sinEspacio() { return this.dentro.filter(t => !t.esp_id); },
                get zonas() {
                    const grupos = {};
                    this.espacios.forEach(e => (grupos[e.zona || 'General'] ??= []).push(e));
                    return Object.entries(grupos).map(([nombre, espacios]) => ({ nombre, espacios, total: espacios.length,
                        libres: espacios.filter(e => e.estado === 'ACTIVO' && !this.ticketEn(e) && !e.reservado).length }));
                },
                get dentroFiltrado() {
                    const q = this.filtro.trim().toUpperCase();
                    return this.dentro.filter(t => !q || [t.placa, t.llavero, t.espacio, t.cliente, String(t.numero)].some(v => (v || '').toUpperCase().includes(q)));
                },
                get abierto() { return this.tk && ['DENTRO', 'SOLICITADO'].includes(this.tk.ticket.estado); },
                get bruto() { return this.tk ? Number(this.tk.cobro.importe) + Number(this.tk.cobro.penalidad) : 0; },
                get totalCobro() { return Math.max(0, Math.round((this.bruto - Math.min(Number(this.cobro.descuento) || 0, this.bruto)) * 100) / 100); },
                ticketEn(e) { return this.dentro.find(t => t.esp_id === e.esp_id) || null; },
                espaciosLibres(tarId) { return this.espacios.filter(e => e.estado === 'ACTIVO' && !this.ticketEn(e) && !e.reservado && (!e.tar_id || !tarId || e.tar_id == tarId)); },
                iconoTarifa(tarId) { return this.tarifas.find(t => t.tar_id == tarId)?.fa || 'fa-car-side'; },
                claseEspacio(e) {
                    const t = this.ticketEn(e);
                    if (t) return t.estado === 'SOLICITADO' ? 'bg-rose-600 text-white shadow-md' : 'bg-indigo-600 text-white shadow-md hover:bg-indigo-700';
                    if (e.estado === 'MANTENIMIENTO') return 'mantenimiento text-gray-500 cursor-not-allowed';
                    if (e.reservado) return 'border-2 border-dashed border-amber-400 bg-amber-50 text-amber-800';
                    return this.entrada.esp_id === e.esp_id ? 'border-2 border-emerald-500 bg-emerald-100 text-emerald-800 ring-4 ring-emerald-200' : 'border-2 border-emerald-300 bg-emerald-50/50 text-emerald-800 hover:bg-emerald-100';
                },

                // ---- formato
                soles(n) { return 'S/ ' + Number(n || 0).toFixed(2); },
                fecha(s) { if (!s) return ''; const [a, m, d] = s.slice(0, 10).split('-'); return `${d}/${m}/${a}`; },
                hora(s) { return s ? s.slice(11, 16) : ''; },
                fechaHora(s) { return s ? this.fecha(s) + ' ' + this.hora(s) : ''; },
                nombreCorto(n) { return (n || '').replace(/^\d{8,11}\s+/, '').trim(); },
                minutosDesde(s) { return Math.max(0, Math.floor((this.ahora + this.desfase - new Date(s.replace(' ', 'T')).getTime()) / 60000)); },
                tiempo(s) { const m = this.minutosDesde(s), d = Math.floor(m / 1440), h = Math.floor(m % 1440 / 60); return (d ? d + 'd ' : '') + (h || d ? h + 'h ' : '') + (m % 60) + 'min'; },
                hace(s) { return s ? this.tiempo(s) : ''; },
                avisar(texto, ok = true) {
                    if (window.tushpaAviso) return window.tushpaAviso(texto, ok);
                    this.aviso = { visible: true, ok, texto };
                    clearTimeout(this._t); this._t = setTimeout(() => this.aviso.visible = false, ok ? 3500 : 6000);
                },
                async post(url, datos = {}) {
                    try {
                        const r = await fetch(url, { method: 'POST', body: JSON.stringify(datos),
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' } });
                        const d = await r.json();
                        if (r.status === 422) return { ok: false, mensaje: Object.values(d.errors || {})[0]?.[0] || d.message };
                        if (!r.ok) return { ok: false, mensaje: d.message || 'No se pudo completar.' };
                        return d;
                    } catch (e) { return { ok: false, mensaje: 'Sin conexión con el servidor.' }; }
                },

                // ---- entrada
                sugerirEspacio() {
                    const actual = this.espacios.find(e => e.esp_id === this.entrada.esp_id);
                    if (actual && this.espaciosLibres(this.entrada.tar_id).includes(actual)) return;
                    this.entrada.esp_id = this.espaciosLibres(this.entrada.tar_id)[0]?.esp_id ?? '';
                },
                tocarEspacio(e) {
                    const t = this.ticketEn(e);
                    if (t) return this.abrirTicket(t.tic_id);
                    if (e.estado !== 'ACTIVO') return;
                    if (e.reservado) return this.avisar('Espacio reservado para el abonado ' + e.reservado + '.', false);
                    this.entrada.esp_id = e.esp_id;
                    if (e.tar_id) this.entrada.tar_id = Number(e.tar_id);
                    this.$refs.placa.focus();
                },
                revisarPlaca() {
                    const placa = this.entrada.placa.replace(/[^A-Z0-9]/g, '');
                    if (placa.length < 5) return;
                    const ya = this.dentro.find(t => t.placa === placa);
                    if (ya) return this.avisar('La placa ' + placa + ' ya está dentro (ticket N° ' + ya.numero + ').', false);
                },
                async registrarEntrada() {
                    if (!this.entrada.tar_id) return this.avisar('Elige el tipo de vehículo.', false);
                    this.ocupado = true;
                    const imprimir = this.entrada.imprimir;
                    const r = await this.post(RUTA + '/entrada', { ...this.entrada, esp_id: this.entrada.esp_id || null, IdUsuario_valet: this.entrada.IdUsuario_valet || null });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (!r.ok) return;
                    if (imprimir) window.open(r.ticket + '?imprimir=1', '_blank', 'width=420,height=640');
                    const tar = this.entrada.tar_id;
                    await this.refrescar();
                    this.entrada = entradaVacia(tar);
                    this.sugerirEspacio();
                    this.$refs.placa.focus({ preventScroll: true });
                },

                // ---- ticket y salida
                async buscar() {
                    const q = this.q.trim();
                    if (!q) return;
                    const r = await fetch(RUTA + '/buscar?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({ ok: false, mensaje: 'Sin conexión.' }));
                    if (!r.ok) return this.avisar(r.mensaje, false);
                    this.q = '';
                    this.abrirTicket(r.tic_id);
                },
                async abrirTicket(id) {
                    const d = await fetch(RUTA + '/tickets/' + id, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (!d) return this.avisar('No se pudo abrir el ticket.', false);
                    this.tk = d;
                    this.cobro = { perdido: false, conDescuento: false, descuento: 0, motivo: '', tdocod: '03', clinum: '', clinom: d.ticket.cliente || '', clidir: '', medio: MEDIO, paga: null, imprimir: true };
                    this.modal = 'ticket';
                },
                cerrarTicket() { if (this.modal === 'ticket') { this.modal = null; this.tk = null; this.$nextTick(() => this.$refs.buscador?.focus()); } },
                async recotizar() {
                    const d = await fetch(RUTA + '/tickets/' + this.tk.ticket.tic_id + '?perdido=' + (this.cobro.perdido ? 1 : 0), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (d) this.tk.cobro = d.cobro;
                },
                async buscarDoc() {
                    const doc = (this.cobro.clinum || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(doc)) return;
                    const d = await fetch('{{ url('cobros/cliente') }}/' + doc, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({}));
                    if (d && d.nom) { this.cobro.clinom = d.nom; this.cobro.clidir = d.dir && d.dir !== '--' ? d.dir : ''; if (doc.length === 11) this.cobro.tdocod = '01'; }
                },
                async darSalida() {
                    const c = this.cobro, total = this.totalCobro;
                    if (total > 0 && c.tdocod === '01' && !/^\d{11}$/.test(c.clinum)) return this.avisar('Para factura escribe el RUC.', false);
                    if (c.descuento > 0 && !c.motivo.trim()) return this.avisar('Escribe el motivo del descuento.', false);
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/tickets/' + this.tk.ticket.tic_id + '/cobrar', {
                        tdocod: c.tdocod, clinum: c.clinum, clinom: c.clinom, clidir: c.clidir, perdido: c.perdido ? 1 : 0,
                        descuento: c.conDescuento ? c.descuento || 0 : 0, motivo: c.motivo,
                        id_med_pag: [c.medio], mon_med_pag: [total], paga: c.paga || total, imprimir: c.imprimir ? 1 : 0,
                    });
                    this.ocupado = false;
                    this.avisar(r.ok ? r.mensaje + (r.numero ? ' ' + r.numero : '') + (r.vuelto > 0 ? ' · vuelto ' + this.soles(r.vuelto) : '') : r.mensaje, r.ok);
                    if (!r.ok) return;
                    if (r.id && c.imprimir && !r.impreso) window.open('{{ url('voucher') }}/' + r.id + '?imprimir=1', '_blank');
                    this.cerrarTicket();
                    this.refrescar();
                },
                async solicitar() {
                    const r = await this.post(RUTA + '/tickets/' + this.tk.ticket.tic_id + '/solicitar');
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.avisados.add(this.tk.ticket.tic_id); await this.abrirTicket(this.tk.ticket.tic_id); this.refrescar(); }
                },
                async moverEspacio(espId) {
                    const r = await this.post(RUTA + '/tickets/' + this.tk.ticket.tic_id + '/espacio', { esp_id: espId || null });
                    this.avisar(r.mensaje, r.ok);
                    await this.refrescar();
                    await this.abrirTicket(this.tk.ticket.tic_id);
                },
                async anular() {
                    const motivo = prompt('¿Por qué se anula el ticket N° ' + this.tk.ticket.numero + '? (sale sin cobro)');
                    if (!motivo || motivo.trim().length < 3) return;
                    const r = await this.post(RUTA + '/tickets/' + this.tk.ticket.tic_id + '/anular', { motivo });
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.cerrarTicket(); this.refrescar(); }
                },

                // ---- configuración
                abrirConfig(tab) { this.tabConfig = tab; this.tarifa = tarifaVacia(); this.modal = 'config'; },
                editarTarifa(t) { this.tarifa = { ...t, precio: Number(t.precio), tope_dia: Number(t.tope_dia), perdido: Number(t.perdido), pension: Number(t.pension), activo: !!Number(t.activo) }; },
                ejemploTarifa() {
                    const t = this.tarifa, p = Number(t.precio) || 0;
                    if (!p) return 'Escribe el precio para ver un ejemplo.';
                    if (t.modo === 'FIJO') return `Ejemplo: entra y sale el mismo día → ${this.soles(p)}.`;
                    if (t.modo === 'HORA') return `Ejemplo: 1 h 20 min → 2 horas = ${this.soles(p * 2)}.`;
                    const f = Number(t.fraccion_min) || 15, extra = Math.ceil(20 / f) * Math.round(p * f / 60 * 100) / 100;
                    return `Ejemplo: 1 h 20 min → ${this.soles(p)} + ${Math.ceil(20 / f)} fracción(es) de ${f} min = ${this.soles(p + extra)}.`;
                },
                async guardarTarifa() {
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/tarifas', { ...this.tarifa, activo: this.tarifa.activo ? 1 : 0 });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (!r.ok) return;
                    this.tarifas = r.tarifas; this.tarifa = tarifaVacia();
                    if (!this.entrada.tar_id) this.entrada.tar_id = this.tarifasActivas[0]?.tar_id ?? null;
                },
                codigoGen(n) { return (this.gen.prefijo || '').toUpperCase() + '-' + String(n).padStart(2, '0'); },
                async generarEspacios() {
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/espacios', { ...this.gen, tar_id: this.gen.tar_id || null });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.espacios = r.espacios; this.sugerirEspacio(); }
                },
                async guardarEspacio(e) {
                    const r = await this.post(RUTA + '/espacios/' + e.esp_id, { codigo: e.codigo, zona: e.zona, tar_id: e.tar_id || null, estado: e.estado });
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) this.espacios = r.espacios;
                },
                async quitarEspacio(e) {
                    if (!confirm('¿Eliminar el espacio ' + e.codigo + '?')) return;
                    const r = await this.post(RUTA + '/espacios/' + e.esp_id + '/quitar');
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) this.espacios = r.espacios;
                },
            };
        }
    </script>
@endsection
