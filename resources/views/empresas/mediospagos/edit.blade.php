@extends('layouts.app')
@section('title', 'Editar Medio de Pago')
@section('content')
    @include('empresas.partials.alert')
    <form action="{{ route('mediospagos.update', $medioPago->id_med_pag) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 max-w-md space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
            <input type="text" name="nom_med_pag" value="{{ $medioPago->nom_med_pag }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        {{-- QR de cobro (Yape, Plin…): sale en el Punto Venta al elegir este medio, para que el cliente lo escanee --}}
        <div x-data="{ vista: @js(isset($medioPago) && $medioPago->qr ? asset($medioPago->qr) : null) }">
            <label class="block text-sm font-medium text-gray-700 mb-1">QR de cobro <span class="font-normal text-gray-400">(opcional: Yape, Plin, etc.)</span></label>
            <input type="file" name="qr" accept="image/png,image/jpeg,image/webp"
                   @change="const f = $event.target.files[0]; vista = f ? URL.createObjectURL(f) : vista"
                   class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700">
            <p class="mt-1 text-xs text-gray-400">Sube la imagen del QR que te da la app (captura de «Mi QR»). Al cobrar con este medio, el Punto Venta lo muestra con el monto.</p>
            <template x-if="vista"><img :src="vista" alt="QR" class="mt-3 h-40 w-40 rounded-xl object-contain ring-1 ring-gray-200 bg-white"></template>
            @if (isset($medioPago) && $medioPago->qr)
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="quitar_qr" value="1" class="rounded border-gray-300 text-rose-600"> Quitar el QR</label>
            @endif
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('mediospagos.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancelar</a>
            <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Actualizar</button>
        </div>
    </form>
@endsection