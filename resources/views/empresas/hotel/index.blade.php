@extends('layouts.app')
@section('title', 'Hotel')
@section('content')
    @php $in = 'block w-full rounded-xl border-gray-300 text-base sm:text-sm focus:border-violet-500 focus:ring-violet-500'; @endphp
    <style>
        @keyframes hotel-parpadeo { 50% { opacity: .65; } }
        .hab-vencida { animation: hotel-parpadeo 1s infinite; }
    </style>

    <div x-data="hotel()" x-init="iniciar()" class="space-y-4 pb-24">
        {{-- ================= Cabecera ================= --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <h1 class="text-2xl font-extrabold text-gray-800">🛏️ Hotel · Habitaciones</h1>
                <p class="text-sm text-gray-500 font-mono" x-text="reloj"></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="abrirReservas()" class="relative px-4 py-2 rounded-xl bg-white border-2 border-violet-200 text-violet-700 text-sm font-bold hover:bg-violet-50">
                    📅 Reservas
                    <span x-show="datos.reservas_hoy" x-cloak class="absolute -top-2 -right-2 px-1.5 py-0.5 rounded-full bg-violet-600 text-white text-[10px]" x-text="datos.reservas_hoy + ' hoy'"></span>
                </button>
                @if ($verReporte)
                    <a href="{{ route('hotel.reporte') }}" class="px-4 py-2 rounded-xl bg-white border-2 border-gray-200 text-gray-700 text-sm font-bold hover:bg-gray-50">📊 Reporte</a>
                @endif
                @if ($esAdmin)
                    <button type="button" @click="abrirConfig()" class="px-4 py-2 rounded-xl bg-white border-2 border-gray-200 text-gray-700 text-sm font-bold hover:bg-gray-50">⚙️ Configurar</button>
                @endif
                @if ($puedeCobrar)
                    <a href="{{ route('cobros.directa', ['desde' => 'hotel']) }}" class="px-4 py-2 rounded-xl bg-orange-500 text-white text-sm font-bold hover:bg-orange-600 shadow">🛒 Punto de venta</a>
                @endif
            </div>
        </div>

        {{-- ================= Resumen = filtro ================= --}}
        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
            <template x-for="f in filtros" :key="f.clave">
                <button type="button" @click="filtro = filtro === f.clave ? 'todas' : f.clave"
                        class="shrink-0 px-4 py-2 rounded-2xl text-sm font-bold text-white shadow-sm transition"
                        :class="[f.color, filtro === f.clave ? 'ring-4 ring-offset-1 ring-violet-300 scale-105' : (filtro === 'todas' ? '' : 'opacity-60')]">
                    <span x-text="f.nombre"></span> <span class="ml-1 px-2 py-0.5 rounded-full bg-white/25" x-text="cuenta[f.clave] || 0"></span>
                </button>
            </template>
        </div>

        {{-- ================= Alertas de tiempo ================= --}}
        <div class="space-y-2" x-show="alertas.length" x-cloak>
            <template x-for="a in alertas" :key="a.hab_id">
                <button type="button" @click="abrirHabitacion(a.hab_id)" class="w-full flex items-center gap-3 rounded-2xl px-4 py-3 text-left font-semibold shadow-sm"
                        :class="a.vencido ? 'bg-rose-600 text-white' : 'bg-amber-100 text-amber-900'">
                    <span class="text-xl" x-text="a.vencido ? '⏰' : '⚠️'"></span>
                    <span class="flex-1" x-text="a.texto"></span>
                    <span class="text-xs font-bold underline whitespace-nowrap">Agregar horas »</span>
                </button>
            </template>
        </div>

        {{-- ================= Llegadas próximas (reservas) ================= --}}
        <div x-show="llegadas.length" x-cloak class="bg-white rounded-2xl shadow-sm p-3">
            <p class="text-xs font-bold uppercase text-violet-700 mb-2">📅 Llegan en las próximas 24 horas</p>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <template x-for="l in llegadas" :key="l.res_id">
                    <button type="button" @click="abrirHabitacion(l.hab_id)" class="shrink-0 rounded-xl border-2 px-3 py-2 text-left"
                            :class="l.tarde ? 'border-rose-300 bg-rose-50' : 'border-violet-200 bg-violet-50'">
                        <span class="block text-sm font-extrabold text-gray-800" x-text="cuando(fecha(l.llegada)) + ' · Hab. ' + l.hab_nom"></span>
                        <span class="block text-xs text-gray-600" x-text="l.cliente + (l.tarde ? ' · aún no llega' : '')"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- ================= Pisos ================= --}}
        <div x-show="pisos.length > 1" x-cloak class="flex gap-2 overflow-x-auto pb-1">
            <template x-for="p in ['TODOS', ...pisos]" :key="p">
                <button type="button" @click="elegirPiso(p)" class="shrink-0 px-5 py-2 rounded-xl text-sm font-bold"
                        :class="piso === p ? 'bg-violet-700 text-white shadow' : 'bg-white text-gray-600 border border-gray-200'" x-text="p"></button>
            </template>
        </div>

        {{-- ================= Habitaciones ================= --}}
        <div x-show="!cargado" class="py-16 text-center text-gray-400">Cargando habitaciones…</div>
        <div x-show="cargado && !datos.habitaciones.length" x-cloak class="bg-white rounded-2xl shadow-sm p-10 text-center">
            <p class="text-5xl">🛏️</p>
            <p class="mt-2 text-gray-500">Aún no hay habitaciones.</p>
            @if ($esAdmin)
                <button type="button" @click="abrirConfig()" class="mt-4 px-5 py-2.5 rounded-xl bg-violet-600 text-white font-bold">Crear habitaciones y servicios</button>
            @else
                <p class="text-sm text-gray-400">Pide al administrador que las cree.</p>
            @endif
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
            <template x-for="h in visibles" :key="h.hab_id">
                <button type="button" @click="abrirHabitacion(h.hab_id)"
                        class="relative rounded-2xl p-3 text-white text-left shadow-md hover:-translate-y-0.5 transition min-h-[132px] flex flex-col"
                        :class="[colorDe(claseDe(h)), claseDe(h) === 'vencido' ? 'hab-vencida' : '', h.reserva && !h.estadia ? 'ring-4 ring-violet-400' : '']">
                    {{-- Por cobrar / pagado --}}
                    <span x-show="h.estadia" class="absolute -top-2 -right-2 px-2 py-0.5 rounded-full bg-white text-[11px] font-black shadow"
                          :class="h.estadia && h.estadia.pendiente > 0.009 ? 'text-rose-600' : 'text-emerald-600'"
                          x-text="h.estadia ? (h.estadia.pendiente > 0.009 ? money(h.estadia.pendiente) : 'PAGADO') : ''"></span>
                    <span class="flex items-baseline justify-between gap-2">
                        <span class="text-2xl font-black leading-none" x-text="h.hab_nom"></span>
                        <span class="text-[10px] font-bold uppercase opacity-80 truncate" x-text="h.hab_tip || ''"></span>
                    </span>
                    <template x-if="h.estadia">
                        <span class="mt-2 block">
                            <span class="block font-mono text-xl font-black" x-text="restante(h)"></span>
                            <span class="block text-xs font-semibold truncate opacity-95" x-text="h.estadia.cliente"></span>
                            <span class="mt-2 block h-1.5 rounded-full bg-white/30 overflow-hidden"><span class="block h-full bg-white" :style="'width:' + avance(h) + '%'"></span></span>
                            <span class="mt-1 block text-[10px] opacity-90" x-text="'Sale ' + cuando(fecha(h.estadia.fin))"></span>
                        </span>
                    </template>
                    <template x-if="!h.estadia">
                        <span class="mt-auto block text-sm font-bold uppercase" x-text="({ Libre: '🔑 Libre', Limpieza: '🧹 Limpieza', Mantenimiento: '🛠️ Mantenimiento' })[h.hab_est] || h.hab_est"></span>
                    </template>
                    <span x-show="h.reserva && !h.estadia" class="mt-1 block text-[10px] font-bold rounded-md bg-black/25 px-1.5 py-0.5 truncate"
                          x-text="h.reserva ? '📅 ' + cuando(fecha(h.reserva.llegada)) + ' ' + h.reserva.cliente : ''"></span>
                </button>
            </template>
        </div>
        <p x-show="cargado && datos.habitaciones.length && !visibles.length" x-cloak class="text-center text-gray-400 py-8">No hay habitaciones con ese filtro.</p>

        {{-- ================= Ingreso (habitación libre) ================= --}}
        <div x-show="panel === 'ingreso'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center" @keydown.escape.window="cerrar()">
            <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl max-h-[94vh] overflow-y-auto" @click.outside="cerrar()">
                <template x-if="actual && panel === 'ingreso'">
                    <div>
                        <div class="px-5 py-4 bg-emerald-500 text-white rounded-t-3xl flex items-center justify-between">
                            <h3 class="text-lg font-extrabold" x-text="'🔑 Ingreso · Habitación ' + actual.hab_nom + (actual.hab_tip ? ' (' + actual.hab_tip + ')' : '')"></h3>
                            <button type="button" @click="cerrar()" class="text-3xl leading-none">&times;</button>
                        </div>
                        <div class="p-5 space-y-3">
                            <div x-show="actual.reserva" class="rounded-xl bg-violet-50 border border-violet-200 p-3 text-sm text-violet-900">
                                <p x-text="actual.reserva ? '📅 Reservada para ' + actual.reserva.cliente + ' · llega ' + cuando(fecha(actual.reserva.llegada)) + (actual.reserva.telefono ? ' · ☎ ' + actual.reserva.telefono : '') : ''"></p>
                                <button type="button" @click="ingresarReserva(actual.reserva.res_id)" class="mt-2 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-xs font-bold">Es él/ella: registrar su ingreso</button>
                            </div>
                            <label class="block text-xs font-semibold text-gray-500">Servicio
                                <select x-model="ing.servicio" @change="precioServicio(ing)" class="{{ $in }} mt-1">
                                    <template x-for="s in datos.servicios" :key="s.IdProducto">
                                        <option :value="String(s.IdProducto)" x-text="s.pronom + ' · ' + textoMin(s.minutos) + ' · ' + money(s.propun)"></option>
                                    </template>
                                </select></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block text-xs font-semibold text-gray-500">Cantidad
                                    <span class="mt-1 flex items-center gap-1">
                                        <button type="button" @click="ing.cantidad = Math.max(1, Number(ing.cantidad) - 1)" class="w-11 h-11 rounded-xl bg-gray-100 text-xl font-bold">−</button>
                                        <input type="number" min="1" x-model="ing.cantidad" class="{{ $in }} text-center font-bold">
                                        <button type="button" @click="ing.cantidad = Number(ing.cantidad) + 1" class="w-11 h-11 rounded-xl bg-gray-100 text-xl font-bold">+</button>
                                    </span></label>
                                <label class="block text-xs font-semibold text-gray-500">Precio S/
                                    <input type="number" min="0" step="0.10" x-model="ing.precio" class="{{ $in }} mt-1 text-right font-bold" @unless ($esAdmin) readonly title="Solo el administrador cambia el precio" @endunless></label>
                            </div>
                            <div class="rounded-xl bg-emerald-50 text-emerald-900 text-sm px-3 py-2" x-html="resumenIngreso()"></div>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="col-span-2 block text-xs font-semibold text-gray-500">DNI / RUC <span class="font-normal">(opcional)</span>
                                    <span class="mt-1 flex gap-1">
                                        <input x-model="ing.documento" @keydown.enter.prevent="buscarDoc()" maxlength="15" inputmode="numeric" class="{{ $in }}">
                                        <button type="button" @click="buscarDoc()" class="px-3 rounded-xl bg-violet-600 text-white">🔍</button>
                                    </span></label>
                                <label class="block text-xs font-semibold text-gray-500">Personas
                                    <input type="number" min="1" max="20" x-model="ing.personas" class="{{ $in }} mt-1 text-center"></label>
                            </div>
                            <p x-show="ing.msg" class="text-xs text-violet-700" x-text="ing.msg"></p>
                            <label class="block text-xs font-semibold text-gray-500">Huésped
                                <input x-model="ing.cliente" maxlength="150" placeholder="CLIENTE HOSPEDAJE" class="{{ $in }} mt-1 uppercase"></label>
                            <label class="block text-xs font-semibold text-gray-500">Celular <span class="font-normal">(para avisarle por WhatsApp cuando su tiempo termine)</span>
                                <input x-model="ing.telefono" maxlength="20" inputmode="tel" placeholder="987654321" class="{{ $in }} mt-1"></label>
                            @if ($puedeCobrar)
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700"><input type="checkbox" x-model="ing.cobrar" class="rounded text-violet-600"> Cobrar ahora</label>
                            @endif
                        </div>
                        <div class="px-5 pb-5 grid grid-cols-2 gap-2">
                            <button type="button" @click="registrarIngreso()" :disabled="ocupado" class="col-span-2 h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-lg font-extrabold disabled:opacity-50">🔑 REGISTRAR INGRESO</button>
                            <button type="button" @click="mantenimiento()" class="h-11 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">🛠️ Mantenimiento</button>
                            <button type="button" @click="cerrar()" class="h-11 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Cancelar</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ================= Habitación ocupada ================= --}}
        <div x-show="panel === 'ocupada'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center" @keydown.escape.window="panel === 'ocupada' && cerrar()">
            <div class="bg-gray-50 w-full sm:max-w-2xl rounded-t-3xl sm:rounded-3xl max-h-[94vh] overflow-y-auto" @click.outside="if (!sub && !$event.target.closest('[data-sub]')) cerrar()">
                <template x-if="actual && actual.estadia && panel === 'ocupada'">
                    <div>
                        <div class="px-5 py-4 text-white rounded-t-3xl" :class="colorDe(claseDe(actual))">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-lg font-extrabold" x-text="'🛏️ Habitación ' + actual.hab_nom"></h3>
                                    <p class="text-sm opacity-95" x-text="actual.estadia.cliente + (actual.estadia.personas > 1 ? ' · ' + actual.estadia.personas + ' personas' : '') + (actual.estadia.documento ? ' · ' + actual.estadia.documento : '')"></p>
                                </div>
                                <button type="button" @click="cerrar()" class="text-3xl leading-none">&times;</button>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-xl bg-white/15 py-2"><p class="text-[10px] uppercase opacity-80">Ingreso</p><p class="font-bold" x-text="cuando(fecha(actual.estadia.inicio))"></p></div>
                                <div class="rounded-xl bg-white/15 py-2"><p class="text-[10px] uppercase opacity-80">Salida</p><p class="font-bold" x-text="cuando(fecha(actual.estadia.fin))"></p></div>
                                <div class="rounded-xl bg-white/25 py-2"><p class="text-[10px] uppercase opacity-80" x-text="faltaMs(actual) > 0 ? 'Restante' : 'Excedido'"></p><p class="font-mono font-black text-lg" x-text="restante(actual)"></p></div>
                            </div>
                        </div>

                        <div class="p-4 space-y-3">
                            {{-- Agregar tiempo --}}
                            <div class="bg-white rounded-2xl shadow-sm p-4">
                                <p class="text-xs font-bold uppercase text-gray-500 mb-2">⏱️ Agregar tiempo</p>
                                <div class="grid grid-cols-12 gap-2">
                                    <select x-model="ext.servicio" @change="precioServicio(ext)" class="{{ $in }} col-span-12 sm:col-span-6">
                                        <template x-for="s in datos.servicios" :key="s.IdProducto">
                                            <option :value="String(s.IdProducto)" x-text="s.pronom + ' · ' + textoMin(s.minutos) + ' · ' + money(s.propun)"></option>
                                        </template>
                                    </select>
                                    <input type="number" min="1" x-model="ext.cantidad" class="{{ $in }} col-span-3 sm:col-span-2 text-center">
                                    <input type="number" min="0" step="0.10" x-model="ext.precio" class="{{ $in }} col-span-4 sm:col-span-2 text-right" @unless ($esAdmin) readonly title="Solo el administrador cambia el precio" @endunless>
                                    <button type="button" @click="agregarTiempo()" :disabled="ocupado" class="col-span-5 sm:col-span-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold disabled:opacity-50">+ Tiempo</button>
                                </div>
                            </div>

                            {{-- Consumo --}}
                            <div class="bg-white rounded-2xl shadow-sm p-4">
                                <p class="text-xs font-bold uppercase text-gray-500 mb-2">🍾 Consumo a la habitación</p>
                                <input type="search" x-model="con.q" @input.debounce.250ms="buscarProductos()" placeholder="Buscar: gaseosa, cerveza, desayuno…" class="{{ $in }}">
                                <div x-show="con.productos.length" class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-56 overflow-y-auto">
                                    <template x-for="p in con.productos" :key="p.id">
                                        <button type="button" @click="elegirConsumo(p)" class="rounded-xl border border-gray-200 px-3 py-2 text-left hover:border-violet-400 hover:bg-violet-50">
                                            <span class="block text-sm font-semibold leading-tight" x-text="p.nombre"></span>
                                            <span class="block text-sm font-extrabold text-violet-700" x-text="money(p.precio)"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Cuenta --}}
                            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-[11px] uppercase text-gray-500"><tr><th class="px-3 py-2 text-left">Cant.</th><th class="px-3 py-2 text-left">Descripción</th><th class="px-3 py-2 text-right">Importe</th></tr></thead>
                                    <tbody class="divide-y">
                                        <template x-for="i in detalle.items" :key="i.ped_det_id">
                                            <tr>
                                                <td class="px-3 py-2" x-text="Number(i.ped_det_can)"></td>
                                                <td class="px-3 py-2"><span x-text="i.descripcion"></span>
                                                    <span x-show="Number(i.item_facturado) >= Number(i.ped_det_can)" class="ml-1 text-[10px] font-bold text-emerald-600">✔ cobrado</span></td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap"><span x-text="money(i.ped_det_can * i.ped_det_pre)"></span>
                                                    <button type="button" x-show="i.se_quita" @click="abrirQuitar(i)" class="ml-1 text-rose-500 font-bold" title="Quitar (cargado por error)">✕</button></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="text-sm">
                                        <tr><td colspan="2" class="px-3 py-1.5 text-right font-semibold">Total</td><td class="px-3 py-1.5 text-right font-bold" x-text="money(detalle.total)"></td></tr>
                                        <tr><td colspan="2" class="px-3 py-1.5 text-right font-bold text-rose-600">Por cobrar</td><td class="px-3 py-1.5 text-right font-black text-rose-600" x-text="money(detalle.pendiente)"></td></tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Acciones --}}
                            <div class="grid grid-cols-2 gap-2">
                                @if ($puedeCobrar)
                                    <a :href="URL.cobrar + actual.estadia.ped_id" class="h-12 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold flex items-center justify-center">💵 Cobrar</a>
                                @endif
                                <button type="button" @click="darSalida()" class="h-12 rounded-xl bg-violet-700 hover:bg-violet-800 text-white font-extrabold">🚪 Dar salida</button>
                                <button type="button" @click="precuenta()" class="h-11 rounded-xl bg-white border-2 border-gray-200 text-gray-700 font-bold">🧾 Precuenta</button>
                                <button type="button" @click="abrirCambiar()" class="h-11 rounded-xl bg-white border-2 border-teal-200 text-teal-700 font-bold">🔁 Cambiar habitación</button>
                                <a x-show="whatsapp(actual)" :href="whatsapp(actual)" target="_blank" rel="noopener" class="h-11 rounded-xl bg-green-600 text-white font-bold flex items-center justify-center">💬 Avisar por WhatsApp</a>
                                @if ($esAdmin)
                                    <button type="button" x-show="sePuedeAnular()" @click="anularIngreso()" class="h-11 rounded-xl bg-gray-200 text-gray-700 font-bold">🚫 Anular ingreso</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ================= Cantidad del consumo ================= --}}
        <div x-show="sub === 'cantidad'" x-cloak data-sub class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4">
            <form @submit.prevent="agregarConsumo()" class="bg-white w-full max-w-sm rounded-3xl overflow-hidden shadow-2xl" @click.outside="sub = null">
                <div class="px-5 py-4 bg-violet-600 text-white"><p class="text-xs uppercase text-violet-200 font-bold">Consumo a la habitación</p>
                    <p class="text-lg font-extrabold" x-text="con.sel ? con.sel.nombre : ''"></p></div>
                <div class="p-5">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="con.cantidad = Math.max(1, Number(con.cantidad) - 1)" class="w-14 h-14 rounded-2xl bg-gray-100 text-2xl font-bold">−</button>
                        <input id="con_cantidad" type="number" min="1" step="1" x-model="con.cantidad" @focus="$event.target.select()" class="{{ $in }} h-14 text-center text-3xl font-black">
                        <button type="button" @click="con.cantidad = Number(con.cantidad) + 1" class="w-14 h-14 rounded-2xl bg-gray-100 text-2xl font-bold">+</button>
                    </div>
                    <p class="mt-3 text-right text-gray-500">Total <b class="text-xl text-gray-800" x-text="con.sel ? money(con.cantidad * con.sel.precio) : ''"></b></p>
                </div>
                <div class="px-5 pb-5 grid grid-cols-2 gap-2">
                    <button type="submit" :disabled="ocupado" class="h-12 rounded-xl bg-emerald-600 text-white font-extrabold disabled:opacity-50">✓ Agregar</button>
                    <button type="button" @click="sub = null" class="h-12 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                </div>
            </form>
        </div>

        {{-- ================= Quitar consumo (autoriza el administrador) ================= --}}
        <div x-show="sub === 'quitar'" x-cloak data-sub class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-sm rounded-3xl overflow-hidden shadow-2xl" @click.outside="sub = null">
                <div class="px-5 py-4 bg-rose-600 text-white font-extrabold">🛡️ Quitar consumo</div>
                <div class="p-5 space-y-3">
                    <p class="font-bold" x-text="quitar.texto"></p>
                    <label class="block text-xs font-semibold text-gray-500">Motivo<input x-model="quitar.motivo" maxlength="100" placeholder="Se cargó por error" class="{{ $in }} mt-1"></label>
                    @unless ($esAdmin)
                        <p class="text-xs text-gray-500">Necesita la autorización de un administrador:</p>
                        <input x-model="quitar.usuario" placeholder="Correo del administrador" autocomplete="off" class="{{ $in }}">
                        <input x-model="quitar.clave" type="password" placeholder="Contraseña" autocomplete="new-password" class="{{ $in }}">
                    @endunless
                    <p x-show="quitar.msg" class="text-sm text-rose-600" x-text="quitar.msg"></p>
                </div>
                <div class="px-5 pb-5 grid grid-cols-2 gap-2">
                    <button type="button" @click="confirmarQuitar()" :disabled="ocupado" class="h-11 rounded-xl bg-rose-600 text-white font-bold disabled:opacity-50">Quitar</button>
                    <button type="button" @click="sub = null" class="h-11 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                </div>
            </div>
        </div>

        {{-- ================= Cambiar de habitación ================= --}}
        <div x-show="sub === 'cambiar'" x-cloak data-sub class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-sm rounded-3xl overflow-hidden shadow-2xl" @click.outside="sub = null">
                <div class="px-5 py-4 bg-teal-600 text-white font-extrabold">🔁 Cambiar de habitación</div>
                <div class="p-5 space-y-3">
                    <p class="text-sm" x-text="actual && actual.estadia ? 'Pasar a ' + actual.estadia.cliente + ' de la ' + actual.hab_nom + ' a:' : ''"></p>
                    <div class="grid grid-cols-3 gap-2 max-h-56 overflow-y-auto">
                        <template x-for="h in libres" :key="h.hab_id">
                            <button type="button" @click="cam.destino = h.hab_id" class="rounded-xl border-2 px-2 py-2 text-center"
                                    :class="cam.destino === h.hab_id ? 'border-teal-600 bg-teal-50' : 'border-gray-200'">
                                <span class="block font-extrabold" x-text="h.hab_nom"></span>
                                <span class="block text-[10px] text-gray-500 truncate" x-text="(h.hab_tip || '') + (h.reserva ? ' · 📅' : '')"></span>
                            </button>
                        </template>
                    </div>
                    <input x-model="cam.motivo" maxlength="100" placeholder="Motivo (opcional): no funciona el aire…" class="{{ $in }}">
                    <p class="text-xs text-gray-500">El tiempo y lo cobrado se mantienen. La habitación anterior queda en limpieza.</p>
                </div>
                <div class="px-5 pb-5 grid grid-cols-2 gap-2">
                    <button type="button" @click="confirmarCambio()" :disabled="ocupado || !cam.destino" class="h-11 rounded-xl bg-teal-600 text-white font-bold disabled:opacity-50">Cambiar</button>
                    <button type="button" @click="sub = null" class="h-11 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                </div>
            </div>
        </div>

        {{-- ================= Limpieza / mantenimiento ================= --}}
        <div x-show="panel === 'estado'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center">
            <div class="bg-white w-full sm:max-w-sm rounded-t-3xl sm:rounded-3xl p-5 space-y-2" @click.outside="cerrar()">
                <h3 class="text-lg font-extrabold" x-text="actual ? 'Habitación ' + actual.hab_nom : ''"></h3>
                <p class="text-sm text-gray-500" x-text="actual && actual.hab_est === 'Limpieza' ? 'Cuando termine la limpieza márcala como disponible.' : 'Está fuera de servicio.'"></p>
                <button type="button" @click="cambiarEstado('Libre')" class="w-full h-14 rounded-2xl bg-emerald-600 text-white text-lg font-extrabold">✔ Lista / Disponible</button>
                <button type="button" @click="cambiarEstado('Limpieza')" class="w-full h-11 rounded-xl bg-gray-100 font-bold">🧹 En limpieza</button>
                <button type="button" @click="cambiarEstado('Mantenimiento')" class="w-full h-11 rounded-xl bg-gray-100 font-bold">🛠️ Mantenimiento</button>
                <button type="button" @click="cerrar()" class="w-full h-10 text-sm text-gray-500">Cerrar</button>
            </div>
        </div>

        {{-- ================= Reservas ================= --}}
        <div x-show="panel === 'reservas'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center">
            <div class="bg-white w-full sm:max-w-3xl rounded-t-3xl sm:rounded-3xl max-h-[94vh] overflow-y-auto" @click.outside="cerrar()">
                <div class="px-5 py-4 bg-violet-700 text-white rounded-t-3xl flex items-center justify-between">
                    <h3 class="text-lg font-extrabold">📅 Reservas</h3>
                    <button type="button" @click="cerrar()" class="text-3xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="guardarReserva()" class="m-4 p-4 rounded-2xl bg-violet-50 grid grid-cols-2 sm:grid-cols-6 gap-3" autocomplete="off">
                    <label class="col-span-1 sm:col-span-2 block text-xs font-semibold text-gray-600">Habitación
                        <select x-model="res.hab_id" required class="{{ $in }} mt-1">
                            <template x-for="h in datos.habitaciones" :key="h.hab_id"><option :value="String(h.hab_id)" x-text="h.hab_nom + (h.hab_tip ? ' · ' + h.hab_tip : '')"></option></template>
                        </select></label>
                    <label class="col-span-1 sm:col-span-2 block text-xs font-semibold text-gray-600">Llega el
                        <input type="datetime-local" x-model="res.llegada" required class="{{ $in }} mt-1"></label>
                    <label class="col-span-2 sm:col-span-2 block text-xs font-semibold text-gray-600">Servicio
                        <select x-model="res.servicio" required class="{{ $in }} mt-1">
                            <template x-for="s in datos.servicios" :key="s.IdProducto"><option :value="String(s.IdProducto)" x-text="s.pronom + ' · ' + money(s.propun)"></option></template>
                        </select></label>
                    <label class="col-span-2 sm:col-span-3 block text-xs font-semibold text-gray-600">Huésped<input x-model="res.cliente" required maxlength="150" class="{{ $in }} mt-1 uppercase"></label>
                    <label class="block text-xs font-semibold text-gray-600">Cant.<input type="number" min="1" x-model="res.cantidad" required class="{{ $in }} mt-1 text-center"></label>
                    <label class="block text-xs font-semibold text-gray-600">Pers.<input type="number" min="1" max="20" x-model="res.personas" class="{{ $in }} mt-1 text-center"></label>
                    <label class="col-span-2 sm:col-span-2 block text-xs font-semibold text-gray-600">DNI/RUC<input x-model="res.documento" maxlength="15" class="{{ $in }} mt-1"></label>
                    <label class="col-span-2 sm:col-span-2 block text-xs font-semibold text-gray-600">Teléfono<input x-model="res.telefono" maxlength="20" inputmode="tel" class="{{ $in }} mt-1"></label>
                    <label class="col-span-2 sm:col-span-2 block text-xs font-semibold text-gray-600">Nota<input x-model="res.nota" maxlength="200" placeholder="Llega tarde, cama extra…" class="{{ $in }} mt-1"></label>
                    <div class="col-span-2 sm:col-span-6 flex justify-end gap-2">
                        <button type="button" @click="limpiarReserva()" class="px-4 py-2 rounded-xl bg-white border text-sm font-semibold">Nueva</button>
                        <button class="px-5 py-2 rounded-xl bg-violet-700 text-white text-sm font-bold" x-text="res.res_id ? 'Guardar cambios' : 'Guardar reserva'"></button>
                    </div>
                </form>
                <div class="px-4 pb-5 space-y-2">
                    <p x-show="!reservas.length" class="text-center text-gray-400 py-6">No hay reservas pendientes.</p>
                    <template x-for="r in reservas" :key="r.res_id">
                        <div class="rounded-2xl border p-3 flex flex-wrap items-center gap-3" :class="esTarde(r) ? 'border-rose-200 bg-rose-50' : 'border-gray-200'">
                            <div class="w-24"><p class="font-extrabold text-gray-800" x-text="cuando(fecha(r.llegada))"></p>
                                <p x-show="esTarde(r)" class="text-[11px] font-bold text-rose-600">aún no llega</p></div>
                            <div class="w-20"><p class="font-extrabold" x-text="'Hab. ' + r.hab_nom"></p><p class="text-[11px] text-gray-500" x-text="r.hab_est"></p></div>
                            <div class="flex-1 min-w-[160px]"><p class="font-semibold" x-text="r.cliente"></p>
                                <p class="text-xs text-gray-500" x-text="(r.telefono ? '☎ ' + r.telefono + ' · ' : '') + Number(r.cantidad) + ' x ' + r.servicio + ' · ' + money(r.propun * r.cantidad)"></p>
                                <p x-show="r.nota" class="text-xs text-gray-400" x-text="r.nota"></p></div>
                            <div class="flex gap-1">
                                <button type="button" @click="ingresarReserva(r.res_id)" :disabled="r.hab_est !== 'Libre'" :title="r.hab_est !== 'Libre' ? 'La habitación no está libre' : ''"
                                        class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold disabled:opacity-40">🔑 Llegó</button>
                                <button type="button" @click="editarReserva(r)" class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs font-bold">✏️</button>
                                <button type="button" @click="cancelarReserva(r)" class="px-3 py-1.5 rounded-lg bg-rose-100 text-rose-700 text-xs font-bold">✕</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        @if ($esAdmin)
        {{-- ================= Configuración ================= --}}
        <div x-show="panel === 'config'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center">
            <div class="bg-white w-full sm:max-w-3xl rounded-t-3xl sm:rounded-3xl max-h-[94vh] overflow-y-auto" @click.outside="cerrar()">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h3 class="text-lg font-extrabold">⚙️ Configurar hotel</h3>
                    <button type="button" @click="cerrar()" class="text-3xl leading-none text-gray-400">&times;</button>
                </div>
                <div class="px-5 pt-4 flex gap-2">
                    <template x-for="t in [['hab', '🛏️ Habitaciones'], ['serv', '⏱️ Servicios de tiempo'], ['aviso', '🔔 Avisos']]" :key="t[0]">
                        <button type="button" @click="tabCfg = t[0]" class="px-4 py-2 rounded-xl text-sm font-bold" :class="tabCfg === t[0] ? 'bg-violet-700 text-white' : 'bg-gray-100 text-gray-600'" x-text="t[1]"></button>
                    </template>
                </div>
                <div class="p-5">
                    <div x-show="tabCfg === 'hab'" class="space-y-3">
                        <form @submit.prevent="guardarHabitacion()" class="grid grid-cols-2 sm:grid-cols-6 gap-2 items-end" autocomplete="off">
                            <label class="block text-xs font-semibold text-gray-500">N° / nombre<input x-model="cfgHab.hab_nom" required maxlength="50" placeholder="101" class="{{ $in }} mt-1"></label>
                            <label class="block text-xs font-semibold text-gray-500">Tipo<input x-model="cfgHab.hab_tip" maxlength="40" list="tipos_hab" placeholder="MATRIMONIAL" class="{{ $in }} mt-1 uppercase"></label>
                            <label class="block text-xs font-semibold text-gray-500">Piso<input x-model="cfgHab.hab_piso" required maxlength="30" class="{{ $in }} mt-1 uppercase"></label>
                            <label class="col-span-2 block text-xs font-semibold text-gray-500">Nota<input x-model="cfgHab.hab_obs" maxlength="150" placeholder="TV, jacuzzi" class="{{ $in }} mt-1"></label>
                            <button class="h-11 rounded-xl bg-violet-700 text-white font-bold" x-text="cfgHab.hab_id ? 'Guardar' : '+ Agregar'"></button>
                        </form>
                        <datalist id="tipos_hab"><option>SIMPLE</option><option>DOBLE</option><option>MATRIMONIAL</option><option>TRIPLE</option><option>SUITE</option></datalist>
                        <div class="divide-y border rounded-xl">
                            <template x-for="h in datos.habitaciones" :key="h.hab_id">
                                <div class="flex items-center gap-3 px-3 py-2 text-sm">
                                    <span class="w-16 font-extrabold" x-text="h.hab_nom"></span>
                                    <span class="flex-1 text-gray-500" x-text="[h.hab_tip, h.hab_piso, h.hab_obs].filter(Boolean).join(' · ')"></span>
                                    <span class="text-xs text-gray-400" x-text="h.hab_est"></span>
                                    <button type="button" @click="cfgHab = { hab_id: h.hab_id, hab_nom: h.hab_nom, hab_tip: h.hab_tip || '', hab_piso: h.hab_piso, hab_obs: h.hab_obs || '' }" class="px-2 py-1 rounded-lg bg-gray-100">✏️</button>
                                    <button type="button" @click="borrarHabitacion(h)" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600">🗑</button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div x-show="tabCfg === 'serv'" class="space-y-3">
                        <p class="text-sm text-gray-500">Cada servicio da un tiempo: <b>HABITACIÓN 2 HORAS</b> (2 h), <b>HABITACIÓN 1 DÍA</b> (24 h), <b>HORA EXTRA</b> (1 h). Se guardan como productos (categoría HOSPEDAJE) y se cobran con el IGV de la sucursal.</p>
                        <form @submit.prevent="guardarServicio()" class="grid grid-cols-2 sm:grid-cols-6 gap-2 items-end" autocomplete="off">
                            <label class="col-span-2 block text-xs font-semibold text-gray-500">Nombre<input x-model="cfgServ.pronom" required maxlength="150" placeholder="HABITACION 3 HORAS" class="{{ $in }} mt-1 uppercase"></label>
                            <label class="block text-xs font-semibold text-gray-500">Precio S/<input type="number" step="0.10" min="0" x-model="cfgServ.propun" required class="{{ $in }} mt-1 text-right"></label>
                            <label class="block text-xs font-semibold text-gray-500">Horas<input type="number" min="0" x-model="cfgServ.horas" required class="{{ $in }} mt-1 text-center"></label>
                            <label class="block text-xs font-semibold text-gray-500">Minutos<input type="number" min="0" max="59" x-model="cfgServ.min" class="{{ $in }} mt-1 text-center"></label>
                            <button class="h-11 rounded-xl bg-violet-700 text-white font-bold" x-text="cfgServ.IdProducto ? 'Guardar' : '+ Agregar'"></button>
                        </form>
                        <div class="divide-y border rounded-xl">
                            <template x-for="s in datos.servicios" :key="s.IdProducto">
                                <div class="flex items-center gap-3 px-3 py-2 text-sm">
                                    <span class="flex-1 font-semibold" x-text="s.pronom"></span>
                                    <span class="text-gray-500" x-text="textoMin(s.minutos)"></span>
                                    <span class="w-20 text-right font-bold" x-text="money(s.propun)"></span>
                                    <button type="button" @click="cfgServ = { IdProducto: s.IdProducto, pronom: s.pronom, propun: s.propun, horas: Math.floor(s.minutos / 60), min: s.minutos % 60 }" class="px-2 py-1 rounded-lg bg-gray-100">✏️</button>
                                    <button type="button" @click="quitarServicio(s)" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-600">✕</button>
                                </div>
                            </template>
                            <p x-show="!datos.servicios.length" class="px-3 py-3 text-sm text-gray-400">Crea por ejemplo: HABITACION 2 HORAS, HABITACION 1 DIA y HORA EXTRA.</p>
                        </div>
                    </div>
                    <div x-show="tabCfg === 'aviso'" class="space-y-4 max-w-sm">
                        <label class="block text-sm font-semibold text-gray-700">Avisar cuando falten (minutos)
                            <input type="number" min="1" max="240" x-model.number="aviso" @change="guardarPref('aviso', aviso = Math.max(1, aviso || 15))" class="{{ $in }} mt-1"></label>
                        <p class="text-xs text-gray-500 -mt-3">Se guarda en este equipo. La tarjeta se pone naranja y suena un aviso.</p>
                        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" x-model="sonido" @change="guardarPref('sonido', sonido); sonido && beep(1)" class="rounded"> Sonar alerta</label>
                        <label class="block text-sm font-semibold text-gray-700">Tolerancia después de la hora de salida (minutos)
                            <span class="mt-1 flex gap-2"><input type="number" min="0" max="180" x-model="tolerancia" class="{{ $in }}">
                                <button type="button" @click="guardarTolerancia()" class="px-4 rounded-xl bg-violet-700 text-white font-bold">Guardar</button></span></label>
                        <p class="text-xs text-gray-500 -mt-3">Pasado este tiempo no se puede dar salida sin cobrar las horas extra. Vale para todos los equipos.</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <script>
        function hotel() {
            const CSRF = '{{ csrf_token() }}';
            const URL = {
                estado: @js(route('hotel.estado')), detalle: @js(url('hotel/estadia')) + '/', ingresar: @js(route('hotel.ingresar')),
                extender: @js(route('hotel.extender')), consumo: @js(route('hotel.consumo')), salida: @js(route('hotel.salida')),
                anular: @js(route('hotel.anular')), cambiarEstado: @js(route('hotel.cambiar_estado')), habitacion: @js(route('hotel.habitacion')),
                habitaciones: @js(url('hotel/habitaciones')) + '/', servicio: @js(route('hotel.servicio')), servicios: @js(url('hotel/servicios')) + '/',
                cobrar: @js(url('cobrarmesa')) + '/', precuenta: @js(url('comandas/precuenta')) + '/', precuentaImp: @js(url('impresion/precuenta')) + '/',
                cliente: @js(url('cobros/cliente')) + '/', productos: @js(route('comandas.search_products')),
                exceso: @js(route('hotel.exceso')), quitar: @js(route('hotel.quitar')), cambiar: @js(route('hotel.cambiar')),
                reservas: @js(route('hotel.reservas')), reserva: @js(route('hotel.reserva')), cancelarReserva: @js(route('hotel.reserva.cancelar')),
                configurar: @js(route('hotel.configurar')),
            };
            const ES_ADMIN = @js($esAdmin), PUEDE_COBRAR = @js($puedeCobrar);
            const NEGOCIO = @js($negocio->nombre_comercial ?? '');
            const pref = (k, def) => { try { const v = localStorage.getItem('hotel_' + k); return v === null ? def : JSON.parse(v); } catch (e) { return def; } };
            const avisa = (t, ok = true) => window.tushpaAviso ? tushpaAviso(t, ok ? 'ok' : 'aviso') : alert(t);
            let audio;

            return {
                URL, datos: { habitaciones: [], servicios: [], reservas_hoy: 0 }, cargado: false, desfase: 0, ahoraMs: Date.now(),
                piso: pref('piso', 'TODOS'), filtro: 'todas', aviso: pref('aviso', 15), sonido: pref('sonido', true), tolerancia: @js($tolerancia),
                panel: null, sub: null, actual: null, ocupado: false, avisados: {},
                detalle: { items: [], total: 0, pendiente: 0 },
                ing: {}, ext: {}, con: { q: '', productos: [], sel: null, cantidad: 1 }, quitar: {}, cam: {}, res: {}, reservas: [],
                tabCfg: 'hab', cfgHab: { hab_piso: 'PISO 1' }, cfgServ: { horas: 1, min: 0 },
                filtros: [
                    { clave: 'libre', nombre: 'Libres', color: 'bg-emerald-500' }, { clave: 'ocupado', nombre: 'Ocupadas', color: 'bg-blue-600' },
                    { clave: 'por-vencer', nombre: 'Por vencer', color: 'bg-amber-500' }, { clave: 'vencido', nombre: 'Tiempo cumplido', color: 'bg-rose-600' },
                    { clave: 'limpieza', nombre: 'Limpieza / mant.', color: 'bg-slate-500' },
                ],

                iniciar() {
                    this.cargar();
                    setInterval(() => this.tick(), 1000);
                    // Con la pestaña oculta no se recarga (el reloj y las alarmas siguen); al volver se pone al día
                    setInterval(() => { if (!document.hidden) this.cargar(); }, 30000);
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.cargar(); });
                },

                // ---------- utilidades ----------
                money: n => 'S/ ' + Number(n || 0).toFixed(2),
                fecha: s => new Date(String(s).replace(' ', 'T')),
                ahora() { return this.ahoraMs + this.desfase; },
                hm: d => d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }),
                cuando(d) { return (d.toDateString() === new Date(this.ahora()).toDateString() ? '' : d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' }) + ' ') + this.hm(d); },
                duracion(ms) {
                    const s = Math.floor(Math.abs(ms) / 1000), h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), x = s % 60, dd = Math.floor(h / 24);
                    const reloj = `${String(h % 24).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(x).padStart(2, '0')}`;
                    return dd ? `${dd}d ${reloj}` : reloj;
                },
                textoMin: min => min % 1440 === 0 ? (min / 1440) + ' día(s)' : (min >= 60 ? (Math.floor(min / 60) + ' h' + (min % 60 ? ' ' + (min % 60) + ' min' : '')) : min + ' min'),
                guardarPref(k, v) { try { localStorage.setItem('hotel_' + k, JSON.stringify(v)); } catch (e) {} },
                async post(url, data, metodo = 'POST') {
                    try {
                        const r = await fetch(url, { method: metodo, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data || {}) });
                        const j = await r.json().catch(() => ({}));
                        if (r.status === 422) return { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] || 'Revisa los datos.' };
                        if (!r.ok) return { ok: false, mensaje: j.message || 'Error del servidor.' };
                        return j;
                    } catch (e) { return { ok: false, mensaje: 'Sin conexión.' }; }
                },
                beep(veces = 2) {
                    if (!this.sonido) return;
                    try {
                        audio = audio || new (window.AudioContext || window.webkitAudioContext)();
                        for (let i = 0; i < veces; i++) {
                            const o = audio.createOscillator(), g = audio.createGain(), t0 = audio.currentTime + i * 0.35;
                            o.frequency.value = 880; o.connect(g); g.connect(audio.destination);
                            g.gain.setValueAtTime(0.25, t0); g.gain.exponentialRampToValueAtTime(0.001, t0 + 0.3); o.start(t0); o.stop(t0 + 0.3);
                        }
                    } catch (e) {}
                },

                // ---------- estado ----------
                async cargar() {
                    try {
                        const d = await fetch(URL.estado, { headers: { Accept: 'application/json' } }).then(r => r.json());
                        this.desfase = this.fecha(d.ahora).getTime() - Date.now();
                        this.datos = d;
                        this.cargado = true;
                        if (this.piso !== 'TODOS' && !this.pisos.includes(this.piso)) this.piso = 'TODOS';
                        if (this.actual) {
                            const h = d.habitaciones.find(x => x.hab_id === this.actual.hab_id);
                            if (this.panel === 'ocupada') { if (h && h.estadia) this.actual = h; else this.cerrar(); }
                        }
                    } catch (e) { /* sin conexión: sigue el reloj */ }
                },
                get reloj() { const t = new Date(this.ahora()); return t.toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long' }) + ' · ' + t.toLocaleTimeString('es-PE'); },
                get pisos() { return [...new Set(this.datos.habitaciones.map(h => h.hab_piso))]; },
                elegirPiso(p) { this.piso = p; this.guardarPref('piso', p); },
                faltaMs(h) { return h.estadia ? this.fecha(h.estadia.fin).getTime() - this.ahora() : 0; },
                claseDe(h) {
                    if (h.hab_est === 'Ocupado' && h.estadia) { const r = this.faltaMs(h); return r <= 0 ? 'vencido' : (r <= this.aviso * 60000 ? 'por-vencer' : 'ocupado'); }
                    return { Libre: 'libre', Limpieza: 'limpieza', Mantenimiento: 'mantenimiento' }[h.hab_est] || 'ocupado';
                },
                colorDe(c) { return { libre: 'bg-emerald-500', ocupado: 'bg-blue-600', 'por-vencer': 'bg-amber-500', vencido: 'bg-rose-600', limpieza: 'bg-slate-400', mantenimiento: 'bg-slate-700' }[c]; },
                restante(h) { const r = this.faltaMs(h); return (r > 0 ? '' : '+') + this.duracion(r); },
                avance(h) {
                    const ini = this.fecha(h.estadia.inicio).getTime(), fin = this.fecha(h.estadia.fin).getTime();
                    return Math.min(100, Math.max(0, (this.ahora() - ini) / Math.max(1, fin - ini) * 100));
                },
                get cuenta() {
                    const c = { libre: 0, ocupado: 0, 'por-vencer': 0, vencido: 0, limpieza: 0 };
                    this.datos.habitaciones.forEach(h => { const k = this.claseDe(h); c[k === 'mantenimiento' ? 'limpieza' : k]++; });
                    return c;
                },
                get visibles() {
                    return this.datos.habitaciones.filter(h => (this.piso === 'TODOS' || h.hab_piso === this.piso)
                        && (this.filtro === 'todas' || (this.filtro === 'limpieza' ? ['limpieza', 'mantenimiento'].includes(this.claseDe(h)) : this.claseDe(h) === this.filtro)));
                },
                get alertas() {
                    return this.datos.habitaciones.filter(h => ['vencido', 'por-vencer'].includes(this.claseDe(h))).map(h => {
                        const vencido = this.claseDe(h) === 'vencido', r = this.faltaMs(h);
                        return { hab_id: h.hab_id, vencido, texto: vencido ? `Hab. ${h.hab_nom}: tiempo cumplido hace ${this.duracion(r)} — ${h.estadia.cliente}`
                            : `Hab. ${h.hab_nom}: faltan ${this.duracion(r)} — ${h.estadia.cliente}` };
                    });
                },
                get llegadas() {
                    return this.datos.habitaciones.filter(h => h.reserva && !h.estadia)
                        .map(h => ({ ...h.reserva, hab_id: h.hab_id, hab_nom: h.hab_nom, tarde: this.fecha(h.reserva.llegada).getTime() < this.ahora() - 30 * 60000 }))
                        .sort((a, b) => this.fecha(a.llegada) - this.fecha(b.llegada));
                },
                get libres() { return this.datos.habitaciones.filter(h => h.hab_est === 'Libre'); },
                // Cada segundo: el tiempo baja; al llegar al aviso suena una vez y al terminar suena otra vez
                tick() {
                    this.ahoraMs = Date.now();
                    this.datos.habitaciones.forEach(h => {
                        if (!h.estadia) return;
                        const clase = this.claseDe(h), hos = h.estadia.hos_id;
                        if (clase === 'vencido' && this.avisados[hos] !== 'vencido') { this.avisados[hos] = 'vencido'; this.beep(3); avisa(`⏰ Habitación ${h.hab_nom}: se terminó el tiempo`, false); }
                        else if (clase === 'por-vencer' && !this.avisados[hos]) { this.avisados[hos] = 'aviso'; this.beep(2); avisa(`⚠ Habitación ${h.hab_nom}: el tiempo está por terminar`, false); }
                        else if (clase === 'ocupado') delete this.avisados[hos];
                    });
                },

                // ---------- abrir ----------
                cerrar() { this.panel = null; this.sub = null; this.actual = null; },
                servicioDe(id) { return this.datos.servicios.find(s => String(s.IdProducto) === String(id)); },
                precioServicio(f) { const s = this.servicioDe(f.servicio); f.precio = s ? Number(s.propun).toFixed(2) : ''; },
                elegirServicio(f, extra) {
                    const s = this.datos.servicios.find(x => extra ? /extra/i.test(x.pronom) : !/extra/i.test(x.pronom)) || this.datos.servicios[0];
                    f.servicio = s ? String(s.IdProducto) : '';
                    this.precioServicio(f);
                },
                abrirHabitacion(id) {
                    const h = this.datos.habitaciones.find(x => x.hab_id === id);
                    if (!h) return;
                    this.actual = h;
                    this.sub = null;
                    if (h.hab_est === 'Libre') {
                        this.ing = { cantidad: 1, precio: '', documento: '', cliente: '', personas: 1, telefono: '', cobrar: false, msg: '' };
                        this.elegirServicio(this.ing, false);
                        this.panel = 'ingreso';
                    } else if (h.estadia) {
                        this.ext = { cantidad: 1 };
                        this.elegirServicio(this.ext, true);
                        this.con = { q: '', productos: [], sel: null, cantidad: 1 };
                        this.detalle = { items: [], total: 0, pendiente: 0 };
                        this.panel = 'ocupada';
                        this.cargarDetalle();
                    } else {
                        this.panel = 'estado';
                    }
                },

                // ---------- ingreso ----------
                resumenIngreso() {
                    const s = this.servicioDe(this.ing.servicio);
                    if (!s) return 'Primero crea los servicios de tiempo (Configurar).';
                    const cant = Math.max(1, Number(this.ing.cantidad) || 1), fin = new Date(this.ahora() + s.minutos * cant * 60000);
                    return `<b>${this.textoMin(s.minutos * cant)}</b> · sale a las <b>${this.cuando(fin)}</b> · Total <b>${this.money(cant * (Number(this.ing.precio) || 0))}</b>`;
                },
                async buscarDoc() {
                    const doc = String(this.ing.documento || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(doc)) { this.ing.msg = 'Escribe un DNI (8) o RUC (11).'; return; }
                    this.ing.msg = 'Buscando…';
                    try {
                        const d = await fetch(URL.cliente + encodeURIComponent(doc), { headers: { Accept: 'application/json' } }).then(r => r.json());
                        if (d.error || !d.nom) { this.ing.msg = d.error || 'No encontrado: escribe el nombre.'; return; }
                        this.ing.cliente = d.nom; this.ing.msg = '✔ Encontrado';
                        if (d.tel && !this.ing.telefono) this.ing.telefono = d.tel;
                    } catch (e) { this.ing.msg = 'No se pudo consultar.'; }
                },
                async registrarIngreso(extra = {}) {
                    if (!this.servicioDe(this.ing.servicio)) return avisa('Primero crea los servicios de tiempo.', false);
                    this.ocupado = true;
                    const r = await this.post(URL.ingresar, { hab_id: this.actual.hab_id, servicio: this.ing.servicio, cantidad: this.ing.cantidad, precio: this.ing.precio,
                        documento: String(this.ing.documento || '').trim(), cliente: String(this.ing.cliente || '').trim(), personas: this.ing.personas,
                        telefono: String(this.ing.telefono || '').trim(), ...extra });
                    this.ocupado = false;
                    if (!r.ok && r.reserva) {
                        if (confirm(r.mensaje + '\n\nAceptar = registrar igual en esta habitación · Cancelar = elegir otra')) return this.registrarIngreso({ forzar: 1 });
                        return;
                    }
                    if (!r.ok) return avisa(r.mensaje, false);
                    if (this.ing.cobrar) { window.location.href = URL.cobrar + r.ped_id; return; }
                    this.cerrar(); avisa('✔ Ingreso registrado: empieza a correr el tiempo'); this.cargar();
                },
                async ingresarReserva(resId) {
                    const r = await this.post(URL.ingresar, { res_id: resId });
                    if (!r.ok) return avisa(r.mensaje, false);
                    if (this.ing && this.ing.cobrar) { window.location.href = URL.cobrar + r.ped_id; return; }
                    this.cerrar(); avisa('✔ Llegó la reserva: empieza a correr el tiempo'); this.cargar();
                },
                async mantenimiento() {
                    if (!confirm(`¿Poner la habitación ${this.actual.hab_nom} en mantenimiento? No se podrá usar hasta marcarla como disponible.`)) return;
                    await this.cambiarEstado('Mantenimiento');
                },
                async cambiarEstado(estado) {
                    const r = await this.post(URL.cambiarEstado, { hab_id: this.actual.hab_id, estado });
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    if (r.ok) { this.cerrar(); this.cargar(); }
                },

                // ---------- ocupada ----------
                async cargarDetalle() {
                    const d = await fetch(URL.detalle + this.actual.estadia.hos_id, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (!d) return;
                    let total = 0, pend = 0;
                    d.items.forEach(i => { total += i.ped_det_can * i.ped_det_pre; pend += (i.ped_det_can - i.item_facturado) * i.ped_det_pre; });
                    this.detalle = { items: d.items, total, pendiente: pend };
                },
                // No se anula si ya pagó algo o si ya cumplió su tiempo
                sePuedeAnular() { return this.actual && this.actual.estadia && this.detalle.total - this.detalle.pendiente <= 0.009 && this.faltaMs(this.actual) > 0; },
                async agregarTiempo() {
                    const s = this.servicioDe(this.ext.servicio);
                    if (!s) return avisa('Primero crea los servicios de tiempo.', false);
                    this.ocupado = true;
                    const r = await this.post(URL.extender, { hos_id: this.actual.estadia.hos_id, servicio: s.IdProducto, cantidad: this.ext.cantidad, precio: this.ext.precio });
                    this.ocupado = false;
                    if (!r.ok) return avisa(r.mensaje, false);
                    this.actual.estadia.fin = r.fin;
                    delete this.avisados[this.actual.estadia.hos_id];
                    avisa(`✔ Se agregó ${this.textoMin(s.minutos * (Number(this.ext.cantidad) || 1))}. Nueva salida: ${this.cuando(this.fecha(r.fin))}`);
                    this.cargarDetalle(); this.cargar();
                },
                async buscarProductos() {
                    const q = this.con.q.trim();
                    if (q.length < 2) { this.con.productos = []; return; }
                    const d = await fetch(URL.productos + '?vista=pv&search_text=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (!d || this.con.q.trim() !== q) return;
                    const caja = document.createElement('div');
                    caja.innerHTML = d.vista || '';
                    this.con.productos = [...caja.querySelectorAll('.product-item-kiosko')].map(p => ({ id: Number(p.dataset.id), nombre: p.dataset.nombre, precio: Number(p.dataset.precio) }));
                },
                elegirConsumo(p) { this.con.sel = p; this.con.cantidad = 1; this.sub = 'cantidad'; this.$nextTick(() => setTimeout(() => document.getElementById('con_cantidad')?.focus(), 80)); },
                async agregarConsumo() {
                    const cant = Number(this.con.cantidad);
                    if (!(cant > 0)) return avisa('Cantidad no válida.', false);
                    this.ocupado = true;
                    const r = await this.post(URL.consumo, { hos_id: this.actual.estadia.hos_id, producto: this.con.sel.id, cantidad: cant });
                    this.ocupado = false;
                    if (!r.ok) return avisa(r.mensaje, false);
                    avisa(`✔ ${cant} x ${this.con.sel.nombre} agregado a la habitación`);
                    this.sub = null; this.con.q = ''; this.con.productos = [];
                    this.cargarDetalle(); this.cargar();
                },
                abrirQuitar(i) { this.quitar = { id: i.ped_det_id, texto: '¿Quitar ' + Number(i.ped_det_can) + ' x ' + i.descripcion + '?', motivo: '', usuario: '', clave: '', msg: '' }; this.sub = 'quitar'; },
                async confirmarQuitar() {
                    if (!this.quitar.motivo.trim()) { this.quitar.msg = 'Escribe el motivo.'; return; }
                    this.ocupado = true;
                    const r = await this.post(URL.quitar, { ped_det_id: this.quitar.id, motivo: this.quitar.motivo.trim(),
                        ...(ES_ADMIN ? {} : { auth_user: this.quitar.usuario.trim(), auth_password: this.quitar.clave }) });
                    this.ocupado = false;
                    if (!r.ok) { this.quitar.msg = r.mensaje; return; }
                    this.sub = null; avisa('✔ ' + r.mensaje); this.cargarDetalle(); this.cargar();
                },
                abrirCambiar() {
                    if (!this.libres.length) return avisa('No hay habitaciones libres.', false);
                    this.cam = { destino: null, motivo: '' }; this.sub = 'cambiar';
                },
                async confirmarCambio() {
                    this.ocupado = true;
                    const r = await this.post(URL.cambiar, { hos_id: this.actual.estadia.hos_id, hab_id: this.cam.destino, motivo: this.cam.motivo.trim() });
                    this.ocupado = false;
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    if (r.ok) { this.cerrar(); this.cargar(); }
                },
                // Precuenta: a la ticketera; sin impresora configurada, por el navegador
                async precuenta() {
                    const ped = this.actual.estadia.ped_id;
                    const r = await this.post(URL.precuentaImp + ped, {});
                    if (r.directa) { avisa('🧾 ' + r.mensaje); return; }
                    window.open(URL.precuenta + ped, '_blank');
                },
                whatsapp(h) {
                    const cel = String(h && h.estadia && h.estadia.telefono || '').replace(/\D/g, '');
                    if (!cel) return '';
                    const fin = this.fecha(h.estadia.fin), r = this.faltaMs(h);
                    const texto = r > 0 ? `Hola ${h.estadia.cliente}, le saludamos de ${NEGOCIO}. Le recordamos que su tiempo en la habitación ${h.hab_nom} termina a las ${this.hm(fin)}. ¿Desea agregar más tiempo?`
                        : `Hola ${h.estadia.cliente}, le saludamos de ${NEGOCIO}. Su tiempo en la habitación ${h.hab_nom} terminó a las ${this.hm(fin)}. ¿Desea agregar más tiempo?`;
                    return 'https://wa.me/' + (cel.length === 9 ? '51' + cel : cel) + '?text=' + encodeURIComponent(texto);
                },
                async darSalida(extra = {}) {
                    if (!extra.sin_exceso && !confirm(`¿Dar salida a la habitación ${this.actual.hab_nom}? Quedará en limpieza.`)) return;
                    const hos = this.actual.estadia.hos_id;
                    const r = await this.post(URL.salida, { hos_id: hos, ...extra });
                    // Se pasó de la hora: con un clic se agregan las horas extra y se va a cobrarlas
                    if (!r.ok && r.exceso) {
                        const oferta = r.horas_extra ? `\n\n¿Agregar ${r.horas_extra} x ${r.servicio_extra} (${this.money(r.monto_extra)}) e ir a cobrar?` : '';
                        if (oferta && confirm(r.mensaje + oferta)) {
                            const x = await this.post(URL.exceso, { hos_id: hos });
                            if (!x.ok) return avisa(x.mensaje, false);
                            avisa('✔ ' + x.mensaje);
                            if (PUEDE_COBRAR) { window.location.href = URL.cobrar + x.ped_id; return; }
                            this.cargarDetalle(); this.cargar(); return;
                        }
                        if (ES_ADMIN) {
                            const motivo = prompt('¿Dar salida SIN cobrar ese tiempo? Escribe el motivo (queda en la bitácora del reporte):');
                            if (motivo) return this.darSalida({ sin_exceso: 1, motivo });
                        }
                        return;
                    }
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    if (r.ok) { this.cerrar(); this.cargar(); }
                },
                async anularIngreso() {
                    const motivo = prompt('Motivo de la anulación del ingreso:');
                    if (!motivo) return;
                    const r = await this.post(URL.anular, { hos_id: this.actual.estadia.hos_id, motivo });
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    if (r.ok) { this.cerrar(); this.cargar(); }
                },

                // ---------- reservas ----------
                esTarde(r) { return this.fecha(r.llegada).getTime() < this.ahora() - 30 * 60000; },
                limpiarReserva() {
                    const d = new Date(this.ahora() + 3600000); d.setMinutes(0, 0, 0);
                    const s = this.datos.servicios.find(x => !/extra/i.test(x.pronom)) || this.datos.servicios[0];
                    this.res = { res_id: '', hab_id: this.datos.habitaciones[0] ? String(this.datos.habitaciones[0].hab_id) : '', servicio: s ? String(s.IdProducto) : '',
                        cantidad: 1, cliente: '', documento: '', telefono: '', personas: 1, nota: '',
                        llegada: new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16) };
                },
                abrirReservas() { this.limpiarReserva(); this.cargarReservas(); this.actual = null; this.panel = 'reservas'; },
                async cargarReservas() { this.reservas = await fetch(URL.reservas, { headers: { Accept: 'application/json' } }).then(r => r.json()).catch(() => []); },
                editarReserva(r) {
                    this.res = { res_id: r.res_id, hab_id: String(r.hab_id), servicio: String(r.IdProducto), cantidad: Number(r.cantidad), cliente: r.cliente || '',
                        documento: r.documento || '', telefono: r.telefono || '', personas: r.personas || 1, nota: r.nota || '', llegada: r.llegada.replace(' ', 'T').slice(0, 16) };
                },
                async guardarReserva() {
                    const r = await this.post(URL.reserva, this.res);
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    if (r.ok) { this.limpiarReserva(); this.cargarReservas(); this.cargar(); }
                },
                async cancelarReserva(res) {
                    const motivo = prompt('¿Cancelar la reserva? Motivo (opcional):', '');
                    if (motivo === null) return;
                    const r = await this.post(URL.cancelarReserva, { res_id: res.res_id, motivo });
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    this.cargarReservas(); this.cargar();
                },

                // ---------- configuración ----------
                abrirConfig() { this.actual = null; this.panel = 'config'; },
                async guardarHabitacion() {
                    const r = await this.post(URL.habitacion, this.cfgHab);
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    // Queda el piso para crear varias seguidas
                    if (r.ok) { this.cfgHab = { hab_piso: this.cfgHab.hab_piso }; this.cargar(); }
                },
                async borrarHabitacion(h) {
                    if (!confirm(`¿Eliminar la habitación ${h.hab_nom}?`)) return;
                    const r = await this.post(URL.habitaciones + h.hab_id, {}, 'DELETE');
                    avisa(r.mensaje, r.ok !== false); this.cargar();
                },
                async guardarServicio() {
                    const r = await this.post(URL.servicio, this.cfgServ);
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                    if (r.ok) { this.cfgServ = { horas: 1, min: 0 }; this.cargar(); }
                },
                async quitarServicio(s) {
                    if (!confirm(`¿Quitar el servicio ${s.pronom}?`)) return;
                    const r = await this.post(URL.servicios + s.IdProducto, {}, 'DELETE');
                    avisa(r.mensaje, r.ok !== false); this.cargar();
                },
                async guardarTolerancia() {
                    const r = await this.post(URL.configurar, { tolerancia: this.tolerancia });
                    avisa(r.ok ? '✔ ' + r.mensaje : r.mensaje, r.ok);
                },
            };
        }
    </script>
@endsection
