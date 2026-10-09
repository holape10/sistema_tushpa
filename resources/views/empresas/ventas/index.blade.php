@extends('layouts.app')
@section('title', 'Panel de Ventas')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $colorTipo = ['01' => 'bg-blue-600', '03' => 'bg-emerald-600', '07' => 'bg-orange-500', '08' => 'bg-purple-600', '13' => 'bg-gray-600'];
        $abrev = ['01' => 'FACTURA', '03' => 'BOLETA', '07' => 'N. CRÉDITO', '08' => 'N. DÉBITO', '13' => 'N. VENTA'];
    @endphp

    <div x-data="panelVentas()" @keydown.escape.window="cerrar()">
        {{-- Filtros --}}
        <form class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <label class="text-sm">Sucursal
                    <select name="sucursal" class="{{ $in }}">
                        @foreach ($sucursales as $s)
                            <option value="{{ $s->id_empresa_negocio }}" @selected($sucursal == $s->id_empresa_negocio)>{{ $s->IdEmpresa }} - {{ $s->nombre_comercial }}</option>
                        @endforeach
                    </select></label>
                <label class="text-sm">Desde<input type="date" name="desde" value="{{ $desde }}" class="{{ $in }}"></label>
                <label class="text-sm">Hasta<input type="date" name="hasta" value="{{ $hasta }}" class="{{ $in }}"></label>
                <label class="text-sm">Cliente<input name="cliente" value="{{ $cliente }}" placeholder="Nombre o DNI/RUC" class="{{ $in }}"></label>
                <label class="text-sm">Comprobante<input name="comprobante" value="{{ $comprobante }}" placeholder="Ej: F001-123 o 123" class="{{ $in }}"></label>
                <label class="text-sm">Tipo
                    <select name="tipo" class="{{ $in }}">
                        <option value="">Todos</option>
                        @foreach ($tipos as $t)<option value="{{ $t->tdocod }}" @selected(request('tipo') === $t->tdocod)>{{ $t->tdodes }}</option>@endforeach
                    </select></label>
                <label class="text-sm">Estado
                    <select name="estado" class="{{ $in }}">
                        <option value="">Todas</option>
                        <option value="vigentes" @selected(request('estado') === 'vigentes')>Vigentes</option>
                        <option value="anuladas" @selected(request('estado') === 'anuladas')>Anuladas</option>
                    </select></label>
                <label class="text-sm">SUNAT
                    <select name="sunat" class="{{ $in }}">
                        <option value="">Todos</option>
                        @foreach (['PENDIENTE', 'ACEPTADO', 'OBSERVADO', 'RECHAZADO', 'ERROR', 'EN RESUMEN'] as $e)
                            <option @selected(request('sunat') === $e)>{{ $e }}</option>
                        @endforeach
                    </select></label>
                <label class="text-sm">Medio de pago
                    <select name="medio" class="{{ $in }}">
                        <option value="">Todos</option>
                        @foreach ($mediosPago as $mp)<option value="{{ $mp->id_med_pag }}" @selected(request('medio') == $mp->id_med_pag)>{{ $mp->nom_med_pag }}</option>@endforeach
                    </select></label>
                <div class="flex items-end gap-2 sm:col-span-3 lg:col-span-3">
                    <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700"><i class="fas fa-search"></i> Buscar</button>
                    <a href="{{ route('ventas.exportar', request()->query()) }}" class="px-5 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('ventas.index') }}" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200">Limpiar</a>
                </div>
            </div>
        </form>

        {{-- Resumen --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Total vendido</p><p class="text-xl font-bold text-indigo-700">S/ {{ number_format($resumen['total'], 2) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Comprobantes</p><p class="text-xl font-bold text-gray-800">{{ $resumen['cantidad'] }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Al crédito</p><p class="text-xl font-bold text-amber-600">S/ {{ number_format($resumen['credito'], 2) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Anuladas</p><p class="text-xl font-bold text-red-600">{{ $resumen['anuladas'] }}</p></div>
        </div>
        <div class="flex flex-wrap gap-2 mb-4 text-xs">
            @foreach ($resumen['porTipo'] as $t)
                <span class="px-2 py-1 rounded-lg bg-white shadow-sm text-gray-600">{{ $t->tdodes }}: <strong>{{ $t->n }}</strong> · S/ {{ number_format($t->total, 2) }}</span>
            @endforeach
            @foreach ($resumen['porMedio'] as $m)
                <span class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700">{{ $m->nom_med_pag ?? 'SIN MEDIO' }}: <strong>S/ {{ number_format($m->total, 2) }}</strong></span>
            @endforeach
        </div>

        {{-- Registro de ventas --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left">Fecha y hora</th>
                        <th class="px-3 py-3 text-left">Comprobante</th>
                        <th class="px-3 py-3 text-left">Cliente</th>
                        <th class="px-3 py-3 text-right">Total</th>
                        <th class="px-3 py-3 text-left">Medio de pago</th>
                        <th class="px-3 py-3 text-center">SUNAT</th>
                        <th class="px-3 py-3 text-center">Opciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($ventas as $v)
                        @php
                            $anulada = (bool) $v->ccabaj;
                            $tachado = $anulada || $v->anulado_nc;
                            $electronico = in_array($v->tdocod, ['01', '03', '07', '08'], true);
                            $turnoAbierto = in_array($v->id_turno, $turnosAbiertos);
                            $numero = $v->serdoc . '-' . str_pad($v->numdoc, 8, '0', STR_PAD_LEFT);
                        @endphp
                        <tr class="hover:bg-gray-50 {{ $anulada ? 'bg-red-50/40' : '' }}">
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($v->fecha_hora)->format('d/m/Y') }}
                                <span class="block text-xs text-gray-400">{{ \Carbon\Carbon::parse($v->fecha_hora)->format('H:i:s') }}</span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold text-white {{ $colorTipo[$v->tdocod] ?? 'bg-gray-500' }}">{{ $abrev[$v->tdocod] ?? $v->tdocod }}</span>
                                <span class="block font-bold {{ $tachado ? 'line-through text-gray-400' : 'text-gray-800' }}">{{ $numero }}</span>
                                @if ($v->tdocod_ref)<span class="block text-[10px] text-rose-600 font-semibold">Modifica {{ $v->serie_ref }}-{{ $v->num_ref }}</span>@endif
                                <span class="block text-xs text-gray-400">{{ $v->mes_nom ?? ($v->ped_tip ? strtoupper($v->ped_tip) : '') }} · {{ $v->usuario }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="font-semibold text-gray-700">{{ $v->ccanom }}</span>
                                <span class="block text-xs text-gray-400">{{ $v->ccandi }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-bold whitespace-nowrap {{ $anulada ? 'line-through text-gray-400' : '' }}">
                                {{ $v->tdocod === '07' ? '-' : '' }}{{ number_format($v->ccaitv, 2) }}
                                @if ($v->estadopago === 'CREDITO')<span class="block text-[10px] font-semibold text-amber-600">CRÉDITO</span>@endif
                            </td>
                            <td class="px-3 py-2 text-xs whitespace-nowrap">
                                @forelse ($medios[$v->IdCpe_cabecera] ?? [] as $m)
                                    <span class="block">{{ $m->nom_med_pag }}: {{ number_format($m->monto, 2) }}</span>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if ($v->anulado_nc)
                                    @include('empresas.sunat._estado', ['estado' => $v->est_sunat])
                                    <span class="block mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-600 text-white">ANULADO CON NC</span>
                                    <span class="block text-[10px] text-gray-500 mt-0.5">{{ $v->anulado_nc }}</span>
                                @elseif ($anulada)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">ANULADO</span>
                                    <span class="block text-[10px] text-gray-500 mt-0.5">{{ $v->motivo_baja }}</span>
                                @elseif ($electronico)
                                    @include('empresas.sunat._estado', ['estado' => $v->est_sunat])
                                    @if ($v->res_id_baja)
                                        <a href="{{ route('sunat.resumenes') }}" class="block mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 hover:bg-amber-200">BAJA EN PROCESO</a>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">Interno</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('cobros.voucher', $v->IdCpe_cabecera) }}" target="_blank" title="Ticket" class="btn-ico text-red-600"><i class="fas fa-file-pdf"></i></a>
                                    <button type="button" onclick="TushpaWhatsApp.abrir({{ $v->IdCpe_cabecera }})" title="Enviar el comprobante por WhatsApp" class="btn-ico text-green-600"><i class="fab fa-whatsapp"></i></button>
                                    @if ($electronico)
                                        <a href="{{ route('sunat.descargar', [$v->IdCpe_cabecera, 'xml']) }}" title="XML" class="btn-ico text-sky-700"><i class="fas fa-file-code"></i></a>
                                        <a href="{{ route('sunat.descargar', [$v->IdCpe_cabecera, 'cdr']) }}" title="CDR (constancia SUNAT)" class="btn-ico text-amber-700"><i class="fas fa-file-zipper"></i></a>
                                    @endif
                                    <button type="button" @click="verDetalle({{ $v->IdCpe_cabecera }})" title="Detalle" class="btn-ico text-emerald-700"><i class="fas fa-eye"></i></button>
                                    @if (!$anulada && $v->estadopago === 'CONTADO' && $turnoAbierto)
                                        <button type="button" @click="editarMedios({{ $v->IdCpe_cabecera }})" title="Editar medio de pago" class="btn-ico text-indigo-600"><i class="fas fa-money-bill-transfer"></i></button>
                                    @endif
                                    @if (!$anulada && $v->tdocod === '13' && $turnoAbierto)
                                        <button type="button" @click="abrirAnular({{ $v->IdCpe_cabecera }}, @js($numero))" title="Anular" class="btn-ico text-red-600"><i class="fas fa-xmark"></i></button>
                                    @endif
                                    {{-- Más opciones: notas de crédito/débito y otro formato de impresión --}}
                                    <button type="button" title="Más opciones" class="btn-ico text-gray-600"
                                            @click.stop="abrirMenu($event, @js([
                                                'id' => $v->IdCpe_cabecera, 'numero' => $numero,
                                                'notas' => in_array($v->tdocod, ['01', '03'], true) && !$anulada && !$v->anulado_nc,
                                                'aceptado' => in_array($v->est_sunat, ['ACEPTADO', 'OBSERVADO'], true),
                                                'estado' => $v->est_sunat ?: 'PENDIENTE',
                                                'guia' => !$anulada,
                                                'baja' => in_array($v->tdocod, ['01', '03'], true) && !$anulada && !$v->anulado_nc && !$v->res_id_baja && $turnoAbierto
                                                    && in_array($v->est_sunat, ['ACEPTADO', 'OBSERVADO'], true)
                                                    && $v->ccafem >= now()->subDays(\App\Support\Sunat\SunatService::PLAZO_BAJA_DIAS)->toDateString(),
                                            ]))"><i class="fas fa-ellipsis-vertical"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay ventas con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $ventas->links() }}</div>
        <p class="text-xs text-gray-400 mt-2">Solo se pueden anular y cambiar el medio de pago de ventas cuyo <strong>turno sigue abierto</strong>. Las facturas y boletas aceptadas por SUNAT se anulan con <strong>Comunicación de baja</strong> (hasta 7 días después de emitidas) o con <a href="{{ route('notas.index') }}" class="text-rose-600 font-semibold hover:underline">nota de crédito</a>, desde el botón <i class="fas fa-ellipsis-vertical"></i> de cada venta.</p>

        {{-- Menú de opciones (fuera de la tabla para que no se corte con el scroll) --}}
        <div x-show="menu.abierto" x-cloak @click.outside="menu.abierto = false" @scroll.window="menu.abierto = false"
             class="fixed z-40 w-64 bg-white rounded-xl shadow-2xl ring-1 ring-black/5 py-1 text-sm"
             :style="`top: ${menu.y}px; left: ${menu.x}px`">
            <p class="px-3 py-1.5 text-[11px] font-bold uppercase text-gray-400" x-text="menu.venta?.numero"></p>
            <template x-if="menu.venta?.notas">
                <div>
                    <button type="button" @click="abrirNota('07')" :disabled="!menu.venta.aceptado"
                            class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-rose-50 disabled:opacity-40 disabled:hover:bg-transparent">
                        <i class="fas fa-file-circle-minus text-rose-600 w-4"></i>
                        <span><span class="font-semibold">Nota de crédito</span><span class="block text-[11px] text-gray-400">Anular, devolver o rebajar</span></span></button>
                    <button type="button" @click="abrirNota('08')" :disabled="!menu.venta.aceptado"
                            class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-amber-50 disabled:opacity-40 disabled:hover:bg-transparent">
                        <i class="fas fa-file-circle-plus text-amber-600 w-4"></i>
                        <span><span class="font-semibold">Nota de débito</span><span class="block text-[11px] text-gray-400">Intereses, penalidad, aumento</span></span></button>
                    <button type="button" x-show="menu.venta.baja" @click="menu.abierto = false; abrirAnular(menu.venta.id, menu.venta.numero, true)"
                            class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-red-50">
                        <i class="fas fa-ban text-red-600 w-4"></i>
                        <span><span class="font-semibold">Comunicación de baja</span><span class="block text-[11px] text-gray-400">Anular ante SUNAT sin nota de crédito</span></span></button>
                    <p x-show="!menu.venta.aceptado" class="px-3 py-1.5 text-[11px] text-amber-700 bg-amber-50"
                       x-text="'SUNAT: ' + menu.venta.estado + '. Las notas se habilitan cuando el comprobante esté ACEPTADO.'"></p>
                    <div class="border-t border-gray-100 my-1"></div>
                </div>
            </template>
            <a x-show="menu.venta?.guia" :href="`{{ route('guias.crear') }}?venta=${menu.venta?.id}`" class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50">
                <i class="fas fa-truck-fast text-indigo-600 w-4"></i>
                <span><span class="font-semibold">Guía de remisión</span><span class="block text-[11px] text-gray-400">Ya trae el cliente y los productos</span></span></a>
            <a :href="`{{ url('voucher') }}/${menu.venta?.id}?formato=a4`" target="_blank" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50">
                <i class="fas fa-file-lines text-indigo-600 w-4"></i> Ver / imprimir en A4</a>
            <a :href="`{{ url('voucher') }}/${menu.venta?.id}?formato=ticket`" target="_blank" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50">
                <i class="fas fa-receipt text-gray-600 w-4"></i> Ver / imprimir en ticket</a>
        </div>

        {{-- Modal: nota de crédito / débito --}}
        <div x-show="modal === 'nota'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center sm:p-4" @click.self="cerrar()">
            <div class="bg-white w-full sm:max-w-3xl rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[95vh] flex flex-col">
                <div class="px-5 py-3 border-b flex items-center gap-3" :class="nota.tdocod === '07' ? 'bg-rose-50' : 'bg-amber-50'">
                    <i class="fas text-lg" :class="nota.tdocod === '07' ? 'fa-file-circle-minus text-rose-600' : 'fa-file-circle-plus text-amber-600'"></i>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-gray-800" x-text="(nota.tdocod === '07' ? 'Nota de crédito ' : 'Nota de débito ') + (nota.datos ? nota.datos.proximo[nota.tdocod] : '')"></h3>
                        <p class="text-xs text-gray-500 truncate" x-show="nota.datos" x-text="nota.datos ? 'Modifica ' + nota.datos.ref.tipo + ' ' + nota.datos.ref.numero + ' · ' + nota.datos.ref.cliente : ''"></p>
                    </div>
                    <button @click="cerrar()" class="text-gray-400 text-2xl leading-none">&times;</button>
                </div>

                <div class="p-5 overflow-y-auto space-y-4 text-sm">
                    <p x-show="!nota.datos" class="text-center text-gray-400 py-8">Cargando…</p>
                    <template x-if="nota.datos && nota.datos.error">
                        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3" x-text="nota.datos.error"></div>
                    </template>
                    <template x-if="nota.datos && !nota.datos.error">
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-xl bg-gray-50 p-2"><p class="text-[10px] uppercase text-gray-400">Emisión</p><p class="font-semibold" x-text="nota.datos.ref.fecha"></p></div>
                                <div class="rounded-xl bg-gray-50 p-2"><p class="text-[10px] uppercase text-gray-400">Total</p><p class="font-semibold" x-text="'S/ ' + num2(nota.datos.ref.total)"></p></div>
                                <div class="rounded-xl bg-gray-50 p-2"><p class="text-[10px] uppercase text-gray-400">Saldo p/ NC</p><p class="font-bold text-rose-600" x-text="'S/ ' + num2(nota.datos.ref.saldo)"></p></div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-xl">
                                <button type="button" @click="tipoNota('07')" :class="nota.tdocod === '07' ? 'bg-white text-rose-700 shadow' : 'text-gray-500'" class="h-10 rounded-lg font-bold">Nota de crédito</button>
                                <button type="button" @click="tipoNota('08')" :class="nota.tdocod === '08' ? 'bg-white text-amber-700 shadow' : 'text-gray-500'" class="h-10 rounded-lg font-bold">Nota de débito</button>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-3">
                                <label>Tipo de nota (motivo SUNAT)
                                    <select x-model="nota.tipnot" @change="lineasNota()" class="block w-full mt-1 rounded-lg border-gray-300 text-sm font-semibold">
                                        <template x-for="(des, cod) in nota.datos.motivos[nota.tdocod]" :key="nota.tdocod + cod">
                                            <option :value="cod" :selected="cod === nota.tipnot" :disabled="nota.tdocod === '07' && nota.datos.tieneNotas && ['01','02','06'].includes(cod)" x-text="cod + ' - ' + des"></option>
                                        </template>
                                    </select></label>
                                <label>Sustento (se envía a SUNAT)
                                    <input x-model="nota.motivo" maxlength="100" placeholder="Ej. Cliente devolvió la mercadería" class="block w-full mt-1 rounded-lg border-gray-300 text-sm"></label>
                            </div>

                            <div class="rounded-xl px-4 py-2" :class="nota.tdocod === '07' ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800'" x-text="ayudaNota()"></div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                                        <th class="px-2 py-2 text-left">Descripción</th>
                                        <th class="px-2 py-2 text-right" x-show="modoNota() === 'devolucion'">Vendido</th>
                                        <th class="px-2 py-2 w-24">Cant.</th>
                                        <th class="px-2 py-2 w-28" x-text="modoNota() === 'descuento_item' ? 'Descuento' : 'P. unit.'"></th>
                                        <th class="px-2 py-2 text-right w-24">Total</th><th class="w-6" x-show="modoNota() === 'libre'"></th></tr></thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(it, i) in nota.items" :key="i">
                                            <tr>
                                                <td class="px-2 py-1.5">
                                                    <input x-show="modoNota() === 'libre'" x-model="it.descripcion" maxlength="150" class="w-full rounded-lg border-gray-300 text-sm uppercase">
                                                    <span x-show="modoNota() !== 'libre'" class="font-semibold text-gray-700" x-text="it.descripcion"></span>
                                                    <span x-show="it.lotes" class="block text-[11px] text-teal-700" x-text="'Lote: ' + it.lotes"></span>
                                                </td>
                                                <td class="px-2 py-1.5 text-right text-xs text-gray-500" x-show="modoNota() === 'devolucion'" x-text="num(it.vendido) + (it.devuelto ? ' (dev. ' + num(it.devuelto) + ')' : '')"></td>
                                                <td class="px-2 py-1.5"><input type="number" step="any" min="0" x-model.number="it.cantidad" :readonly="['total','descuento_item'].includes(modoNota())" class="w-full rounded-lg border-gray-300 text-sm text-right read-only:bg-gray-50"></td>
                                                <td class="px-2 py-1.5"><input type="number" step="0.01" min="0" x-model.number="it.precio" :readonly="['total','devolucion'].includes(modoNota())" class="w-full rounded-lg border-gray-300 text-sm text-right read-only:bg-gray-50"></td>
                                                <td class="px-2 py-1.5 text-right font-semibold" x-text="num2(r2(it.cantidad * it.precio))"></td>
                                                <td x-show="modoNota() === 'libre'" class="text-center"><button type="button" @click="nota.items.splice(i, 1)" class="text-rose-500 font-bold">✕</button></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" x-show="modoNota() === 'libre'" @click="nota.items.push({ ref: null, descripcion: '', cantidad: 1, precio: 0 })"
                                    class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200">+ Agregar línea</button>
                        </div>
                    </template>
                </div>

                <div class="px-5 py-3 border-t flex flex-col sm:flex-row items-center gap-3" x-show="nota.datos && !nota.datos.error">
                    <p class="text-sm text-gray-600 sm:mr-auto">Total de la nota: <strong class="text-xl" :class="nota.tdocod === '07' ? 'text-rose-600' : 'text-amber-600'" x-text="'S/ ' + num2(totalNota())"></strong></p>
                    <p class="text-xs text-red-600" x-text="error"></p>
                    <button type="button" @click="emitirNota()" :disabled="enviando" class="w-full sm:w-auto px-6 py-2.5 rounded-xl text-white font-bold disabled:opacity-50"
                            :class="nota.tdocod === '07' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700'"
                            x-text="enviando ? 'Emitiendo…' : (nota.tdocod === '07' ? 'EMITIR NOTA DE CRÉDITO' : 'EMITIR NOTA DE DÉBITO')"></button>
                </div>
            </div>
        </div>

        {{-- Modal: detalle --}}
        <div x-show="modal === 'detalle'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="cerrar()">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="px-5 py-3 border-b flex justify-between items-center">
                    <h3 class="font-bold text-gray-800" x-text="det ? det.titulo : 'Cargando...'"></h3>
                    <button @click="cerrar()" class="text-gray-400 text-xl">&times;</button>
                </div>
                <template x-if="det">
                    <div class="p-5 text-sm space-y-3">
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                            <div><strong>Cliente:</strong> <span x-text="det.cabecera.ccanom"></span></div>
                            <div><strong>Doc.:</strong> <span x-text="det.cabecera.ccandi"></span></div>
                            <div><strong>Fecha:</strong> <span x-text="det.cabecera.fecha_hora"></span></div>
                            <div><strong>Usuario:</strong> <span x-text="det.usuario"></span></div>
                            <div><strong>Condición:</strong> <span x-text="det.cabecera.estadopago"></span></div>
                            <div x-show="det.cabecera.ccaobs"><strong>Obs.:</strong> <span x-text="det.cabecera.ccaobs"></span></div>
                        </div>
                        <table class="w-full">
                            <thead class="text-xs text-gray-500 uppercase"><tr><th class="text-left">Producto</th><th class="text-right">Cant.</th><th class="text-right">P.U.</th><th class="text-right">Total</th></tr></thead>
                            <tbody>
                                <template x-for="d in det.detalle">
                                    <tr class="border-t border-gray-100"><td class="py-1" x-text="d.cdedes"></td><td class="text-right" x-text="parseFloat(d.cdecan)"></td>
                                        <td class="text-right" x-text="Number(d.cdepuni).toFixed(2)"></td><td class="text-right font-semibold" x-text="Number(d.cdevve).toFixed(2)"></td></tr>
                                </template>
                            </tbody>
                        </table>
                        <div class="flex justify-between font-bold text-base border-t pt-2"><span>TOTAL</span><span x-text="'S/ ' + Number(det.cabecera.ccaitv).toFixed(2)"></span></div>
                        <div class="text-xs text-gray-600">
                            <template x-for="m in det.medios"><span class="inline-block mr-3" x-text="m.nom_med_pag + ': S/ ' + Number(m.monto).toFixed(2)"></span></template>
                        </div>
                        <div x-show="det.cabecera.ccabaj" class="rounded-lg bg-red-50 text-red-700 p-2 text-xs">
                            <strong x-text="det.cabecera.ccabaj"></strong> · <span x-text="det.cabecera.motivo_baja"></span>
                            <span x-show="det.anuladoPor" x-text="' · por ' + det.anuladoPor"></span>
                        </div>
                        <div x-show="det.cabecera.ccadessun" class="rounded-lg bg-gray-50 text-gray-600 p-2 text-xs" x-text="'SUNAT: ' + det.cabecera.ccadessun"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Modal: medios de pago --}}
        <div x-show="modal === 'medios'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="cerrar()">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                <div class="px-5 py-3 border-b flex justify-between items-center">
                    <h3 class="font-bold text-gray-800">Editar medio de pago <span class="text-gray-400 font-normal" x-text="det ? det.titulo : ''"></span></h3>
                    <button @click="cerrar()" class="text-gray-400 text-xl">&times;</button>
                </div>
                <div class="p-5 space-y-3 text-sm" x-show="det">
                    <template x-for="(l, i) in lineas" :key="i">
                        <div class="flex gap-2">
                            <select x-model.number="l.id_med_pag" class="flex-1 rounded-lg border-gray-300 text-sm">
                                @foreach ($mediosPago as $mp)<option value="{{ $mp->id_med_pag }}">{{ $mp->nom_med_pag }}</option>@endforeach
                            </select>
                            <input type="number" step="0.01" min="0.01" x-model.number="l.monto" class="w-28 rounded-lg border-gray-300 text-sm text-right">
                            <button type="button" @click="lineas.splice(i, 1)" x-show="lineas.length > 1" class="px-2 text-red-600">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="lineas.push({ id_med_pag: {{ $mediosPago->first()->id_med_pag ?? 0 }}, monto: Math.max(0, restante()) })" class="text-xs text-indigo-600 hover:underline">+ Agregar otro medio</button>
                    <div class="flex justify-between text-xs">
                        <span>Total de la venta: <strong x-text="det ? 'S/ ' + Number(det.cabecera.ccaitv).toFixed(2) : ''"></strong></span>
                        <span :class="Math.abs(restante()) > 0.009 ? 'text-red-600 font-bold' : 'text-green-700'" x-text="'Diferencia: S/ ' + restante().toFixed(2)"></span>
                    </div>
                    <p class="text-xs text-red-600" x-text="error"></p>
                    <button type="button" @click="guardarMedios()" :disabled="enviando" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 disabled:opacity-50">Guardar</button>
                </div>
            </div>
        </div>

        {{-- Modal: anular --}}
        <div x-show="modal === 'anular'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="cerrar()">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                <div class="px-5 py-3 border-b flex justify-between items-center">
                    <h3 class="font-bold text-red-700"><span x-text="esBaja ? 'Comunicación de baja' : 'Anular'"></span> <span x-text="anularNumero"></span></h3>
                    <button @click="cerrar()" class="text-gray-400 text-xl">&times;</button>
                </div>
                <div class="p-5 space-y-3 text-sm">
                    <p class="text-gray-600">La venta dejará de contar en la caja y lo vendido volverá al stock. Esta acción no se puede deshacer.</p>
                    <p x-show="esBaja" class="text-xs text-amber-800 bg-amber-50 rounded-lg px-3 py-2">Se informa a SUNAT que el comprobante queda <strong>sin efecto</strong> (hasta {{ \App\Support\Sunat\SunatService::PLAZO_BAJA_DIAS }} días después de emitido). La venta se anula cuando SUNAT acepta la baja; suele tardar unos segundos.</p>
                    <label class="block">Motivo *
                        <input x-model="motivo" maxlength="70" placeholder="Ej. cliente desistió de la compra" class="block w-full rounded-lg border-gray-300 text-sm"></label>
                    <p class="text-xs text-red-600" x-text="error"></p>
                    <button type="button" @click="anular()" :disabled="enviando" class="w-full py-2.5 rounded-xl bg-red-600 text-white font-semibold hover:bg-red-700 disabled:opacity-50"
                            x-text="enviando ? (esBaja ? 'Enviando a SUNAT…' : 'Anulando…') : (esBaja ? 'ENVIAR BAJA A SUNAT' : 'ANULAR VENTA')"></button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .btn-ico { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; background: #f3f4f6; font-size: 15px; }
        .btn-ico:hover { background: #e5e7eb; }
    </style>

    <script>
        function panelVentas() {
            const CSRF = '{{ csrf_token() }}';
            const URL = "{{ url('ventas') }}/";
            const post = (url, data) => fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(data)
            }).then(async r => {
                const res = await r.json().catch(() => ({ success: false, message: 'Error del servidor.' }));
                if (r.status === 422 && res.errors) res.message = Object.values(res.errors)[0][0];
                return res;
            });

            return {
                modal: null, det: null, lineas: [], error: '', enviando: false, idActual: null, motivo: '', anularNumero: '', esBaja: false,
                menu: { abierto: false, x: 0, y: 0, venta: null },
                nota: { datos: null, tdocod: '07', tipnot: '01', motivo: '', items: [] },

                cerrar() { this.modal = null; this.error = ''; },

                // ---------- Menú de opciones ----------
                abrirMenu(e, venta) {
                    const r = e.currentTarget.getBoundingClientRect();
                    const ancho = 256, alto = venta.notas ? 250 : 110;
                    this.menu = {
                        abierto: true, venta,
                        x: Math.max(8, Math.min(r.right - ancho, window.innerWidth - ancho - 8)),
                        y: r.bottom + alto > window.innerHeight ? Math.max(8, r.top - alto) : r.bottom + 4,
                    };
                },

                // ---------- Nota de crédito / débito ----------
                r2(n) { return Math.round((Number(n) || 0) * 100) / 100; },
                num(n) { return String(this.r2(n)); },
                num2(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

                async abrirNota(tdocod) {
                    const id = this.menu.venta.id;
                    this.menu.abierto = false;
                    this.error = '';
                    this.nota = { datos: null, tdocod, tipnot: '01', motivo: '', items: [] };
                    this.modal = 'nota';
                    try {
                        const d = await (await fetch("{{ url('notas/datos') }}/" + id, { headers: { Accept: 'application/json' } })).json();
                        this.nota.datos = d;
                        this.tipoNota(tdocod);
                    } catch (e) {
                        this.nota.datos = { error: 'No se pudo cargar el comprobante.' };
                    }
                },
                tipoNota(t) {
                    this.nota.tdocod = t;
                    this.nota.tipnot = t === '07' && this.nota.datos?.tieneNotas ? '07' : '01';
                    this.lineasNota();
                },
                modoNota() {
                    if (this.nota.tdocod === '08') return 'libre';
                    if (['01', '02', '06'].includes(this.nota.tipnot)) return 'total';
                    if (this.nota.tipnot === '07') return 'devolucion';
                    if (this.nota.tipnot === '05') return 'descuento_item';
                    return 'libre';
                },
                ayudaNota() {
                    return {
                        total: 'Anula todo el comprobante: el stock vuelve al almacén, se anula la cuenta por cobrar y el comprobante queda marcado como anulado.',
                        devolucion: 'Escribe cuánto devuelve el cliente de cada producto. Ese stock vuelve al almacén (al mismo lote).',
                        descuento_item: 'Escribe el descuento (importe con IGV) de cada producto. No mueve stock.',
                        libre: this.nota.tdocod === '08' ? 'La nota de débito AUMENTA el importe del comprobante.' : 'Escribe el importe a rebajar (con IGV). No mueve stock.',
                    }[this.modoNota()];
                },
                lineasNota() {
                    const lineas = this.nota.datos?.lineas || [];
                    const m = this.modoNota();
                    if (m === 'total') {
                        this.nota.items = lineas.map(l => ({ ref: l.id, descripcion: l.descripcion, cantidad: l.cantidad, precio: l.precio, lotes: l.lotes }));
                    } else if (m === 'devolucion') {
                        this.nota.items = lineas.filter(l => l.producto && l.cantidad - l.devuelto > 0)
                            .map(l => ({ ref: l.id, descripcion: l.descripcion, cantidad: 0, precio: l.precio, vendido: l.cantidad, devuelto: l.devuelto, lotes: l.lotes }));
                    } else if (m === 'descuento_item') {
                        this.nota.items = lineas.map(l => ({ ref: l.id, descripcion: 'DESCUENTO: ' + l.descripcion, cantidad: 1, precio: 0 }));
                    } else {
                        const desc = this.nota.tdocod === '08'
                            ? { '01': 'INTERESES POR MORA', '02': 'AUMENTO EN EL VALOR', '03': 'PENALIDAD' }[this.nota.tipnot]
                            : (this.nota.tipnot === '04' ? 'DESCUENTO GLOBAL' : 'DISMINUCION EN EL VALOR');
                        this.nota.items = [{ ref: null, descripcion: desc, cantidad: 1, precio: 0 }];
                    }
                },
                totalNota() { return this.r2(this.nota.items.reduce((s, it) => s + this.r2(it.cantidad * it.precio), 0)); },

                async emitirNota() {
                    const total = this.totalNota();
                    if (this.nota.motivo.trim().length < 5) { this.error = 'Escribe el sustento (mínimo 5 letras).'; return; }
                    if (!(total > 0)) { this.error = 'La nota debe tener un importe mayor a 0.'; return; }
                    if (this.nota.tdocod === '07' && total > this.nota.datos.ref.saldo + 0.009) { this.error = 'Supera el saldo del comprobante.'; return; }
                    const tipo = this.nota.tdocod === '07' ? 'nota de crédito' : 'nota de débito';
                    if (!confirm(`Se emitirá la ${tipo} por S/ ${this.num2(total)}. Es un comprobante electrónico y no se puede borrar. ¿Continuar?`)) return;

                    this.enviando = true; this.error = '';
                    const res = await post("{{ route('notas.store') }}", {
                        ref: this.nota.datos.ref.id, tdocod: this.nota.tdocod, tipnot: this.nota.tipnot, motivo: this.nota.motivo.trim(),
                        items: this.nota.items.map(it => ({ IdCpe_detalle: it.ref, descripcion: it.descripcion, cantidad: it.cantidad, precio: it.precio })),
                    });
                    this.enviando = false;
                    if (!res.success) { this.error = res.message; return; }
                    alert(res.message);
                    if (res.ver) window.open(res.ver, '_blank');
                    location.reload();
                },

                async cargar(id) {
                    this.det = null;
                    const d = await (await fetch(URL + id + '/detalle', { headers: { 'Accept': 'application/json' } })).json();
                    d.titulo = d.cabecera.serdoc + '-' + String(d.cabecera.numdoc).padStart(8, '0');
                    this.det = d;
                    return d;
                },

                async verDetalle(id) { this.modal = 'detalle'; await this.cargar(id); },

                async editarMedios(id) {
                    this.idActual = id; this.error = ''; this.modal = 'medios';
                    const d = await this.cargar(id);
                    this.lineas = d.medios.length
                        ? d.medios.map(m => ({ id_med_pag: Number(m.id_med_pag), monto: Number(m.monto) }))
                        : [{ id_med_pag: {{ $mediosPago->first()->id_med_pag ?? 0 }}, monto: Number(d.cabecera.ccaitv) }];
                },

                restante() {
                    if (!this.det) return 0;
                    return Math.round((Number(this.det.cabecera.ccaitv) - this.lineas.reduce((a, l) => a + (Number(l.monto) || 0), 0)) * 100) / 100;
                },

                async guardarMedios() {
                    if (Math.abs(this.restante()) > 0.009) { this.error = 'Los medios de pago deben sumar el total de la venta.'; return; }
                    this.enviando = true;
                    const res = await post(URL + this.idActual + '/medios', { medios: this.lineas });
                    this.enviando = false;
                    if (!res.success) { this.error = res.message; return; }
                    location.reload();
                },

                abrirAnular(id, numero, esBaja = false) { this.idActual = id; this.anularNumero = numero; this.esBaja = esBaja; this.motivo = ''; this.error = ''; this.modal = 'anular'; },

                async anular() {
                    if (this.motivo.trim().length < 5) { this.error = 'Escribe el motivo (mínimo 5 letras).'; return; }
                    this.enviando = true;
                    const res = await post(URL + this.idActual + '/anular', { motivo: this.motivo });
                    this.enviando = false;
                    if (!res.success) { this.error = res.message; return; }
                    alert(res.message);
                    location.reload();
                },
            };
        }
    </script>
    <script src="{{ asset('js/whatsapp-cpe.js') }}?v={{ filemtime(public_path('js/whatsapp-cpe.js')) }}" data-base="{{ url('/') }}" data-csrf="{{ csrf_token() }}"></script>
@endsection
