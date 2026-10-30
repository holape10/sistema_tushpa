@extends('admin.layout')
@section('title', 'Mis cuentas y QR')

@section('content')
    @php $in = 'mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp
    <h1 class="text-xl font-bold">Mis cuentas y QR de cobro</h1>
    <p class="text-sm text-slate-500 mb-4">Si una de tus empresas queda suspendida, en su pantalla de pago verá <b>todas</b> tus cuentas y tus QR para que elija cómo pagarte.
        @if ($yo->es_dueno) Si no pones ninguno, ve los datos de pago de la configuración del sistema.
        @else Mientras no pongas ninguno, ve los datos de cobro de TUSHPA. @endif</p>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="mb-5 rounded-xl px-4 py-3 text-sm font-semibold {{ $propios ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-50 text-slate-600 border border-slate-200' }}">
        {{ $propios ? '✔ Tus empresas suspendidas ven tus '.$cuentas->count().' cuenta(s) y '.$qrs->count().' QR.' : 'Todavía no agregaste cuentas ni QR.' }}
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
        {{-- ================= Cuentas bancarias ================= --}}
        <section class="space-y-3" x-data="{ nueva: {{ $cuentas->isEmpty() || old('_form') === 'cuenta' ? 'true' : 'false' }} }">
            <div class="flex items-center gap-2">
                <h2 class="flex-1 font-bold text-slate-700">🏦 Cuentas bancarias</h2>
                <button type="button" @click="nueva = !nueva" class="px-3 h-9 rounded-xl bg-indigo-600 text-white text-sm font-bold" x-text="nueva ? 'Cerrar' : '+ Agregar cuenta'"></button>
            </div>

            <form x-show="nueva" x-cloak method="POST" action="{{ route('admin.cobros.cuenta') }}" class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-2 gap-3 ring-2 ring-indigo-100">
                @csrf <input type="hidden" name="_form" value="cuenta">
                <label class="block text-sm font-semibold text-slate-600">Banco
                    <input name="banco" value="{{ old('banco') }}" required maxlength="60" list="bancos" placeholder="BCP, BBVA, Interbank…" class="{{ $in }}"></label>
                <label class="block text-sm font-semibold text-slate-600">Titular
                    <input name="titular" value="{{ old('titular', $yo->nombre) }}" maxlength="120" class="{{ $in }}"></label>
                <label class="block text-sm font-semibold text-slate-600">Número de cuenta
                    <input name="cuenta" value="{{ old('cuenta') }}" maxlength="40" inputmode="numeric" placeholder="191-12345678-0-12" class="{{ $in }}"></label>
                <label class="block text-sm font-semibold text-slate-600">CCI <span class="font-normal text-slate-400">(otros bancos)</span>
                    <input name="cci" value="{{ old('cci') }}" maxlength="40" inputmode="numeric" placeholder="002-191-112345678012-55" class="{{ $in }}"></label>
                <div class="sm:col-span-2 flex justify-end"><button class="px-5 h-10 rounded-xl bg-indigo-600 text-white text-sm font-bold">Guardar cuenta</button></div>
            </form>

            @forelse ($cuentas as $c)
                <div class="bg-white rounded-2xl shadow-sm p-4" x-data="{ editar: false }">
                    <div x-show="!editar" class="flex items-start gap-3">
                        <span class="w-11 h-11 shrink-0 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xl">🏦</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-slate-800">{{ $c->banco }}</p>
                            <p class="text-xs text-slate-500">{{ $c->titular ?: $yo->nombre }}</p>
                            @if ($c->cuenta)<p class="mt-1 font-mono text-sm"><span class="text-slate-400 text-xs font-sans">Cuenta</span> {{ $c->cuenta }}</p>@endif
                            @if ($c->cci)<p class="font-mono text-sm"><span class="text-slate-400 text-xs font-sans">CCI</span> {{ $c->cci }}</p>@endif
                        </div>
                        <div class="flex gap-1">
                            <button type="button" @click="editar = true" class="px-2.5 h-8 rounded-lg bg-slate-100 text-xs font-semibold" title="Corregir">✏️</button>
                            <form method="POST" action="{{ route('admin.cobros.eliminar', $c->id) }}" onsubmit="return confirm('¿Quitar la cuenta {{ $c->banco }}?')">@csrf
                                <button class="px-2.5 h-8 rounded-lg bg-rose-50 text-xs" title="Quitar">🗑</button></form>
                        </div>
                    </div>
                    <form x-show="editar" x-cloak method="POST" action="{{ route('admin.cobros.cuenta', $c->id) }}" class="grid sm:grid-cols-2 gap-3">
                        @csrf
                        <label class="block text-sm font-semibold text-slate-600">Banco<input name="banco" value="{{ $c->banco }}" required maxlength="60" list="bancos" class="{{ $in }}"></label>
                        <label class="block text-sm font-semibold text-slate-600">Titular<input name="titular" value="{{ $c->titular }}" maxlength="120" class="{{ $in }}"></label>
                        <label class="block text-sm font-semibold text-slate-600">Número de cuenta<input name="cuenta" value="{{ $c->cuenta }}" maxlength="40" inputmode="numeric" class="{{ $in }}"></label>
                        <label class="block text-sm font-semibold text-slate-600">CCI<input name="cci" value="{{ $c->cci }}" maxlength="40" inputmode="numeric" class="{{ $in }}"></label>
                        <div class="sm:col-span-2 flex justify-end gap-2">
                            <button type="button" @click="editar = false" class="px-4 h-9 rounded-xl bg-slate-100 text-sm font-semibold">Cancelar</button>
                            <button class="px-4 h-9 rounded-xl bg-indigo-600 text-white text-sm font-bold">Guardar</button>
                        </div>
                    </form>
                </div>
            @empty
                <p class="text-sm text-slate-400">Aún no agregaste cuentas.</p>
            @endforelse
        </section>

        {{-- ================= QR (Yape, Plin, bancos) ================= --}}
        <section class="space-y-3" x-data="{ nuevo: {{ $qrs->isEmpty() || old('_form') === 'qr' ? 'true' : 'false' }}, previa: null }">
            <div class="flex items-center gap-2">
                <h2 class="flex-1 font-bold text-slate-700">📱 QR de pago <span class="font-normal text-sm text-slate-400">(Yape, Plin, bancos)</span></h2>
                <button type="button" @click="nuevo = !nuevo" class="px-3 h-9 rounded-xl bg-indigo-600 text-white text-sm font-bold" x-text="nuevo ? 'Cerrar' : '+ Agregar QR'"></button>
            </div>

            <form x-show="nuevo" x-cloak method="POST" action="{{ route('admin.cobros.qr') }}" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-4 space-y-3 ring-2 ring-indigo-100">
                @csrf <input type="hidden" name="_form" value="qr">
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-sm font-semibold text-slate-600">Nombre
                        <input name="banco" value="{{ old('banco') }}" required maxlength="60" list="billeteras" placeholder="Yape, Plin, BCP…" class="{{ $in }}"></label>
                    <label class="block text-sm font-semibold text-slate-600">Titular
                        <input name="titular" value="{{ old('titular', $yo->nombre) }}" maxlength="120" class="{{ $in }}"></label>
                </div>
                <label class="block rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-center cursor-pointer hover:border-indigo-400">
                    <template x-if="previa"><img :src="previa" class="mx-auto max-h-48 rounded-xl" alt="QR nuevo"></template>
                    <span x-show="!previa" class="block text-sm text-slate-500">📷 Toca para elegir la imagen del QR<br><span class="text-xs text-slate-400">Una captura del QR de tu Yape, Plin o banco</span></span>
                    <input type="file" name="qr" accept="image/png,image/jpeg,image/webp" required class="sr-only"
                           @change="previa = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                </label>
                <div class="flex justify-end"><button class="px-5 h-10 rounded-xl bg-indigo-600 text-white text-sm font-bold">Guardar QR</button></div>
            </form>

            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ($qrs as $q)
                    <div class="bg-white rounded-2xl shadow-sm p-3" x-data="{ editar: false, previa: null }">
                        <img src="{{ asset($q->qr) }}?v={{ @filemtime(public_path($q->qr)) }}" alt="QR {{ $q->banco }}" class="w-full aspect-square object-contain rounded-xl bg-slate-50">
                        <div x-show="!editar" class="mt-2 flex items-start gap-2">
                            <div class="flex-1 min-w-0"><p class="font-bold text-slate-800 truncate">{{ $q->banco }}</p><p class="text-xs text-slate-500 truncate">{{ $q->titular ?: $yo->nombre }}</p></div>
                            <button type="button" @click="editar = true" class="px-2.5 h-8 rounded-lg bg-slate-100 text-xs" title="Cambiar">✏️</button>
                            <form method="POST" action="{{ route('admin.cobros.eliminar', $q->id) }}" onsubmit="return confirm('¿Quitar el QR {{ $q->banco }}?')">@csrf
                                <button class="px-2.5 h-8 rounded-lg bg-rose-50 text-xs" title="Quitar">🗑</button></form>
                        </div>
                        <form x-show="editar" x-cloak method="POST" action="{{ route('admin.cobros.qr', $q->id) }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                            @csrf
                            <input name="banco" value="{{ $q->banco }}" required maxlength="60" list="billeteras" class="{{ $in }}">
                            <input name="titular" value="{{ $q->titular }}" maxlength="120" placeholder="Titular" class="{{ $in }}">
                            <label class="block text-xs text-indigo-700 font-semibold cursor-pointer">📷 Cambiar imagen (opcional)
                                <input type="file" name="qr" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-xs"></label>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="editar = false" class="px-3 h-8 rounded-lg bg-slate-100 text-xs font-semibold">Cancelar</button>
                                <button class="px-3 h-8 rounded-lg bg-indigo-600 text-white text-xs font-bold">Guardar</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
            @if ($qrs->isEmpty())<p class="text-sm text-slate-400">Aún no agregaste QR.</p>@endif
        </section>
    </div>

    {{-- ================= WhatsApp para los comprobantes ================= --}}
    <form method="POST" action="{{ route('admin.cobros.whatsapp') }}" class="mt-6 bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        @csrf
        <label class="block flex-1 min-w-[220px] text-sm font-semibold text-slate-600">💬 WhatsApp donde te envían el comprobante de pago
            <input name="cobro_whatsapp" value="{{ old('cobro_whatsapp', $yo->cobro_whatsapp) }}" maxlength="20" inputmode="tel" placeholder="987654321" class="{{ $in }}">
            <span class="block mt-1 text-xs font-normal text-slate-400">Si lo dejas vacío, el comprobante llega al WhatsApp de soporte de TUSHPA.</span></label>
        <button class="px-5 h-10 rounded-xl bg-emerald-600 text-white text-sm font-bold">Guardar WhatsApp</button>
    </form>

    <datalist id="bancos">@foreach (['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Banco de la Nación', 'BanBif', 'Caja Arequipa', 'Caja Huancayo', 'Caja Piura', 'Caja Maynas'] as $b)<option value="{{ $b }}">@endforeach</datalist>
    <datalist id="billeteras">@foreach (['Yape', 'Plin', 'BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Tunki', 'Agora'] as $b)<option value="{{ $b }}">@endforeach</datalist>
@endsection
