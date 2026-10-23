@extends('admin.layout')
@section('title', 'Mis datos de cobro')

@section('content')
    @php $in = 'mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp
    <h1 class="text-xl font-bold">Mis datos de cobro</h1>
    <p class="text-sm text-slate-500 mb-4">Si una de tus empresas queda suspendida, en su pantalla de pago verá <b>tu</b> QR y <b>tu</b> cuenta.
        Mientras no pongas tu cuenta o tu QR, verá los datos de TUSHPA.</p>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="mb-4 rounded-xl px-4 py-3 text-sm font-semibold {{ $propios ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-50 text-slate-600 border border-slate-200' }}">
        {{ $propios ? '✔ Tus empresas suspendidas ven tus datos de cobro.' : 'Ahora tus empresas suspendidas ven los datos de cobro de TUSHPA.' }}
    </div>

    <form method="POST" action="{{ route('admin.cobros.guardar') }}" enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-4"
          x-data="{ previa: null, enviando: false }" @submit="enviando = true">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
            <p class="font-bold">Cuenta bancaria</p>
            <label class="block text-sm font-semibold text-slate-600">Titular de la cuenta
                <input name="cobro_titular" value="{{ old('cobro_titular', $yo->cobro_titular) }}" maxlength="120" placeholder="Tu nombre o el de tu empresa" class="{{ $in }}"></label>
            <label class="block text-sm font-semibold text-slate-600">Banco
                <input name="cobro_banco" value="{{ old('cobro_banco', $yo->cobro_banco) }}" maxlength="60" placeholder="BCP, BBVA, Interbank…" class="{{ $in }}"></label>
            <label class="block text-sm font-semibold text-slate-600">Número de cuenta
                <input name="cobro_cuenta" value="{{ old('cobro_cuenta', $yo->cobro_cuenta) }}" maxlength="40" inputmode="numeric" placeholder="191-12345678-0-12" class="{{ $in }}"></label>
            <label class="block text-sm font-semibold text-slate-600">CCI <span class="font-normal text-slate-400">(para transferencias desde otros bancos)</span>
                <input name="cobro_cci" value="{{ old('cobro_cci', $yo->cobro_cci) }}" maxlength="40" inputmode="numeric" placeholder="002-191-112345678012-55" class="{{ $in }}"></label>
            <label class="block text-sm font-semibold text-slate-600">WhatsApp para recibir el comprobante de pago
                <input name="cobro_whatsapp" value="{{ old('cobro_whatsapp', $yo->cobro_whatsapp) }}" maxlength="20" inputmode="tel" placeholder="987654321" class="{{ $in }}">
                <span class="block mt-1 text-xs font-normal text-slate-400">Si lo dejas vacío, el comprobante llega al WhatsApp de soporte de TUSHPA.</span></label>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
            <p class="font-bold">QR de pago <span class="font-normal text-sm text-slate-400">(Yape, Plin o el de tu banco)</span></p>
            <div class="rounded-2xl bg-slate-50 border border-dashed border-slate-300 p-4 flex items-center justify-center min-h-[220px]">
                <template x-if="previa"><img :src="previa" class="max-h-64 rounded-xl" alt="QR nuevo"></template>
                @if ($yo->cobro_qr)
                    <img x-show="!previa" src="{{ asset($yo->cobro_qr) }}?v={{ @filemtime(public_path($yo->cobro_qr)) }}" class="max-h-64 rounded-xl" alt="Tu QR de pago">
                @else
                    <p x-show="!previa" class="text-sm text-slate-400 text-center">Aún no subiste tu QR.<br>Toma una captura del QR de tu Yape, Plin o banco y súbela.</p>
                @endif
            </div>
            <input type="file" name="qr" accept="image/png,image/jpeg,image/webp" class="text-sm"
                   @change="previa = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
            @if ($yo->cobro_qr)
                <button form="quitar-qr" class="text-sm text-rose-600 hover:underline">Quitar mi QR</button>
            @endif
        </div>

        <div class="lg:col-span-2 flex justify-end">
            <button class="px-6 h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold disabled:opacity-50" :disabled="enviando"
                    x-text="enviando ? 'Guardando…' : 'Guardar mis datos de cobro'"></button>
        </div>
    </form>
    <form id="quitar-qr" method="POST" action="{{ route('admin.cobros.qr.quitar') }}" onsubmit="return confirm('¿Quitar tu QR de pago?')">@csrf</form>
@endsection
