{{-- Datos del comprobante al cobrar un envío (Alpine: objeto "cobro"; $total = expresión JS con el monto) --}}
@unless ($turno)
    <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Debes <a href="{{ route('turnos.index') }}" class="font-bold underline">aperturar tu turno de caja</a> antes de cobrar.</p>
@endunless
<div class="grid sm:grid-cols-2 gap-3">
    <label class="text-xs font-semibold text-gray-500">Comprobante
        <select x-model="cobro.tdocod" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
            @foreach ($comprobantes as $t)<option value="{{ $t->tdocod }}">{{ $t->tdodes }}</option>@endforeach
        </select></label>
    <label class="text-xs font-semibold text-gray-500">Medio de pago
        <select x-model="cobro.id_med_pag" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm">
            @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
        </select></label>
    <label class="text-xs font-semibold text-gray-500">DNI o RUC de quien paga
        <input x-model="cobro.clinum" maxlength="15" inputmode="numeric" placeholder="El del cliente" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm"></label>
    <label class="text-xs font-semibold text-gray-500">Nombre o razón social
        <input x-model="cobro.clinom" maxlength="150" placeholder="El del cliente" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
    <label class="sm:col-span-2 text-xs font-semibold text-gray-500" x-show="cobro.tdocod === '01'">Dirección fiscal
        <input x-model="cobro.clidir" maxlength="200" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm uppercase"></label>
    <label class="text-xs font-semibold text-gray-500">Paga con (vuelto)
        <input type="number" step="any" min="0" x-model.number="cobro.paga" placeholder="0.00" class="mt-1 w-full h-10 rounded-lg border-gray-300 text-sm text-right">
        <span x-show="cobro.paga > {{ $total }}" class="text-emerald-700" x-text="'Vuelto S/ ' + (cobro.paga - {{ $total }}).toFixed(2)"></span></label>
    <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" x-model="cobro.imprimir" class="rounded"> Imprimir el comprobante</label>
</div>
