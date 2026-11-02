@extends('layouts.app')
@section('title', 'Editar empresa')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $input = 'block w-full mt-1 rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $secciones = ['datos' => ['Datos', 'fa-building'], 'logo' => ['Logo', 'fa-image'], 'sunat' => ['SUNAT', 'fa-file-code'],
                      'sire' => ['SIRE / API', 'fa-book'], 'certificado' => ['Certificado', 'fa-key']];
        $sireListo = \App\Support\Sunat\Sire::configurado($empresa);
        $logo = $empresa->LogEmpresa && is_file(public_path($empresa->LogEmpresa)) ? $empresa->LogEmpresa : null;
        $venceCert = $empresa->fec_fin_cer ? \Carbon\Carbon::parse($empresa->fec_fin_cer) : null;
        $ojo = fn ($id) => '<button type="button" onclick="verClave(\''.$id.'\', this)" class="absolute right-2 top-1/2 -translate-y-1/2 mt-0.5 text-gray-400 hover:text-gray-600"><i class="fas fa-eye"></i></button>';
    @endphp

    @if ($errors->any())
        <div class="max-w-6xl mx-auto mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc list-inside space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('empresas.update', $empresa->IdEmpresa) }}" method="POST" enctype="multipart/form-data" class="max-w-6xl mx-auto pb-24">
        @csrf @method('PATCH')

        {{-- Encabezado --}}
        <div class="rounded-2xl bg-gradient-to-r from-indigo-700 via-indigo-600 to-blue-600 text-white p-5 sm:p-6 mb-5 shadow-sm relative isolate overflow-hidden">
            <x-kene-adorno patron="meandro" />
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center shrink-0 overflow-hidden">
                    @if ($logo)<img src="{{ asset($logo) }}" alt="" class="w-full h-full object-contain p-1">@else<i class="fas fa-building text-2xl text-indigo-600"></i>@endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs uppercase tracking-wider text-indigo-200">Empresa · RUC {{ $empresa->IdEmpresa }}</p>
                    <h2 class="text-xl sm:text-2xl font-extrabold truncate">{{ $empresa->NomEmpresa }}</h2>
                    <p class="text-sm text-indigo-100 truncate"><i class="fas fa-location-dot mr-1"></i>{{ $empresa->DirEmpresa }}</p>
                </div>
                <span class="self-start sm:self-center px-3 py-1 rounded-full text-xs font-bold {{ $empresa->produccion == '1' ? 'bg-emerald-400 text-emerald-950' : 'bg-amber-300 text-amber-950' }}">
                    SUNAT {{ $empresa->produccion == '1' ? 'PRODUCCIÓN' : 'BETA (pruebas)' }}</span>
            </div>
        </div>

        <div class="lg:grid lg:grid-cols-[200px_1fr] gap-6">
            {{-- Índice de secciones --}}
            <nav class="mb-4 lg:mb-0">
                <div class="flex lg:flex-col gap-1 overflow-x-auto lg:sticky lg:top-4 bg-white lg:bg-transparent rounded-2xl p-1 shadow-sm lg:shadow-none">
                    @foreach ($secciones as $id => [$nombre, $icono])
                        <a href="#{{ $id }}" class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 whitespace-nowrap">
                            <i class="fas {{ $icono }} w-4 text-center"></i>{{ $nombre }}</a>
                    @endforeach
                    <a href="{{ route('sucursales.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold text-indigo-600 hover:bg-indigo-50 whitespace-nowrap">
                        <i class="fas fa-store w-4 text-center"></i>Sucursales →</a>
                </div>
            </nav>

            <div class="space-y-5 min-w-0">
                {{-- Datos --}}
                <section id="datos" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fas fa-building"></i></span>
                        <div><h3 class="font-bold text-gray-800">Datos de la empresa</h3><p class="text-xs text-gray-400">Como figura en SUNAT.</p></div>
                    </header>
                    <div class="grid sm:grid-cols-6 gap-4 p-5">
                        <label class="text-sm font-medium text-gray-600 sm:col-span-2">RUC
                            <input value="{{ $empresa->IdEmpresa }}" readonly class="{{ $input }} bg-gray-100 text-gray-500 font-mono font-bold cursor-not-allowed"></label>
                        <label class="text-sm font-medium text-gray-600 sm:col-span-4">Razón social <span class="text-red-500">*</span>
                            <input name="nomEmpresa" value="{{ old('nomEmpresa', $empresa->NomEmpresa) }}" required maxlength="255" class="{{ $input }}"></label>
                        <label class="text-sm font-medium text-gray-600 sm:col-span-6">Dirección fiscal <span class="text-red-500">*</span>
                            <input name="dirEmpresa" value="{{ old('dirEmpresa', $empresa->DirEmpresa) }}" required maxlength="255" class="{{ $input }}"></label>
                    </div>
                </section>

                {{-- Logo --}}
                <section id="logo" class="bg-white rounded-2xl shadow-sm scroll-mt-4" x-data="{ vista: @js($logo ? asset($logo) : null) }">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center"><i class="fas fa-image"></i></span>
                        <div><h3 class="font-bold text-gray-800">Logo de la empresa</h3>
                            <p class="text-xs text-gray-400">Sale en tickets, comprobantes, la carta y la tienda. Las sucursales con logo propio usan el suyo
                                (<a href="{{ route('sucursales.index') }}" class="text-indigo-600 font-semibold">Sucursales → Editar</a>).</p></div>
                    </header>
                    <div class="p-5 flex flex-col sm:flex-row gap-5 sm:items-center">
                        <div class="w-32 h-32 shrink-0 rounded-2xl bg-gray-50 ring-1 ring-gray-200 flex items-center justify-center overflow-hidden">
                            <template x-if="vista"><img :src="vista" alt="" class="w-full h-full object-contain p-2"></template>
                            <template x-if="!vista"><span class="text-xs text-gray-400 text-center px-2">Sin logo</span></template>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="file" name="logologin" accept="image/png,image/jpeg,image/webp"
                                   @change="vista = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : vista"
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="text-xs text-gray-400">PNG, JPG o WebP de hasta 2 MB. Mejor con fondo blanco o transparente y en blanco y negro para la ticketera.</p>
                            @if ($logo)
                                <label class="inline-flex items-center gap-2 text-sm text-red-600 cursor-pointer">
                                    <input type="checkbox" name="quitar_logo" value="1" class="rounded text-red-600"> Quitar el logo</label>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- SUNAT --}}
                <section id="sunat" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fas fa-file-code"></i></span>
                        <div><h3 class="font-bold text-gray-800">Facturación electrónica</h3><p class="text-xs text-gray-400">Conexión con SUNAT para enviar los comprobantes.</p></div>
                    </header>
                    <div class="grid sm:grid-cols-3 gap-4 p-5">
                        <label class="text-sm font-medium text-gray-600">Tipo de envío
                            <select name="tip_env_fac" class="{{ $input }}">
                                @forelse ($tip_env_fac as $tef)
                                    <option value="{{ $tef->tip_env_fac_id }}" @selected($empresa->tip_env_fac_id == $tef->tip_env_fac_id)>{{ $tef->tip_env_fac_des }}</option>
                                @empty
                                    <option value="1" @selected($empresa->tip_env_fac_id == 1)>SUNAT</option>
                                    <option value="2" @selected($empresa->tip_env_fac_id == 2)>OSE</option>
                                @endforelse
                            </select></label>
                        <label class="text-sm font-medium text-gray-600">Ambiente
                            <select name="produccion" class="{{ $input }}">
                                <option value="0" @selected($empresa->produccion == '0')>BETA (pruebas)</option>
                                <option value="1" @selected($empresa->produccion == '1')>PRODUCCIÓN</option>
                            </select></label>
                        <label class="text-sm font-medium text-gray-600">Envío a SUNAT
                            <select name="envio" class="{{ $input }}">
                                <option value="1" @selected($empresa->tipo_envio == '1')>Automático</option>
                                <option value="0" @selected($empresa->tipo_envio == '0')>Manual</option>
                            </select></label>
                        <label class="text-sm font-medium text-gray-600">Usuario SOL
                            <input name="txtWsUsuario" value="{{ old('txtWsUsuario', $empresa->wsusuario) }}" class="{{ $input }} uppercase"></label>
                        <label class="text-sm font-medium text-gray-600">Clave SOL
                            <span class="relative block"><input type="password" name="txtWsContrasena" id="txtWsContrasena" value="{{ old('txtWsContrasena', $empresa->claveSunat) }}" class="{{ $input }} pr-9">{!! $ojo('txtWsContrasena') !!}</span></label>
                        <label class="text-sm font-medium text-gray-600">ICBPER (bolsas) S/
                            <input type="number" step="0.01" min="0" name="icbper" value="{{ old('icbper', $empresa->icbper) }}" class="{{ $input }}"></label>
                    </div>
                </section>

                {{-- SIRE / API SUNAT --}}
                <section id="sire" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fas fa-book"></i></span>
                        <div class="flex-1"><h3 class="font-bold text-gray-800">Credenciales de API SUNAT</h3><p class="text-xs text-gray-400">Para el SIRE y las guías de remisión.</p></div>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $sireListo ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ $sireListo ? 'Configurado' : 'Sin configurar' }}</span>
                    </header>
                    <div class="grid sm:grid-cols-2 gap-4 p-5">
                        <label class="text-sm font-medium text-gray-600">ID <span class="text-gray-400 font-normal">(client_id)</span>
                            <input name="sire_client_id" value="{{ old('sire_client_id', $empresa->client_id) }}" autocomplete="off" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="{{ $input }} font-mono"></label>
                        <label class="text-sm font-medium text-gray-600">CLAVE <span class="text-gray-400 font-normal">(client_secret)</span>
                            <span class="relative block"><input type="password" name="sire_client_secret" id="sire_client_secret" autocomplete="new-password"
                                placeholder="{{ $empresa->client_secret ? '•••••••• guardada (vacío = no cambiar)' : 'Pega la CLAVE de SUNAT' }}" class="{{ $input }} font-mono pr-9">{!! $ojo('sire_client_secret') !!}</span></label>
                        <label class="text-sm font-medium text-gray-600">Usuario SOL para el SIRE
                            <input name="sire_usuario" value="{{ old('sire_usuario', $empresa->sire_usuario) }}" maxlength="30" autocomplete="off"
                                   placeholder="Vacío = {{ $empresa->wsusuario ?: 'el de facturación' }}" class="{{ $input }} uppercase"></label>
                        <label class="text-sm font-medium text-gray-600">Clave SOL para el SIRE
                            <span class="relative block"><input type="password" name="sire_clave" id="sire_clave" autocomplete="new-password"
                                placeholder="{{ $empresa->sire_clave ? '•••••••• guardada (vacío = no cambiar)' : 'Vacío = la de facturación' }}" class="{{ $input }} pr-9">{!! $ojo('sire_clave') !!}</span></label>
                        <p class="sm:col-span-2 text-xs text-gray-500 bg-gray-50 rounded-xl px-4 py-3 leading-relaxed">
                            <i class="fas fa-circle-info mr-1 text-emerald-600"></i>En SUNAT SOL: <strong>Empresas → Credenciales de API SUNAT → Gestión Credenciales de API SUNAT</strong>;
                            copia el <strong>ID</strong> y la <strong>CLAVE</strong> y marca los permisos del SIRE. Al guardar se prueba la conexión. La CLAVE y la clave SOL se guardan cifradas.
                            @if ($sireListo)<a href="{{ route('sire.index', 'ventas') }}" class="ml-1 font-semibold text-emerald-700 hover:underline">Ir al SIRE Ventas →</a>@endif
                        </p>
                    </div>
                </section>

                {{-- Certificado digital --}}
                <section id="certificado" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fas fa-key"></i></span>
                        <div class="flex-1"><h3 class="font-bold text-gray-800">Certificado digital</h3><p class="text-xs text-gray-400">Firma los comprobantes. La vigencia se lee sola del certificado.</p></div>
                        @if ($venceCert)
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $venceCert->isPast() ? 'bg-red-100 text-red-700' : ($venceCert->diffInDays(now()) < 30 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">
                                {{ $venceCert->isPast() ? 'Venció' : 'Vigente hasta' }} {{ $venceCert->format('d/m/Y') }}</span>
                        @endif
                    </header>
                    <div class="grid sm:grid-cols-2 gap-4 p-5">
                        <div class="text-sm font-medium text-gray-600">Archivo .pfx o .p12
                            <input type="file" name="txtCertificado" accept=".pfx,.p12"
                                   class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                            @if ($empresa->certificado)<p class="text-xs text-emerald-600 mt-1"><i class="fas fa-circle-check"></i> Cargado: {{ $empresa->certificado }}</p>@endif
                        </div>
                        <label class="text-sm font-medium text-gray-600">Contraseña del certificado
                            <span class="relative block"><input type="password" name="txtPassCert" id="txtPassCert" value="{{ old('txtPassCert', $empresa->passcert) }}" class="{{ $input }} pr-9">{!! $ojo('txtPassCert') !!}</span></label>
                    </div>
                </section>
            </div>
        </div>

        {{-- Barra de guardar fija --}}
        <div class="fixed bottom-0 inset-x-0 z-30 bg-white/90 backdrop-blur border-t border-gray-200">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-end gap-3">
                <a href="{{ route('empresas.index') }}" class="px-5 py-2.5 rounded-xl bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Cancelar</a>
                <button class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 shadow"><i class="fas fa-floppy-disk mr-1"></i> Guardar cambios</button>
            </div>
        </div>
    </form>

    <script>
        function verClave(id, boton) {
            const campo = document.getElementById(id);
            campo.type = campo.type === 'password' ? 'text' : 'password';
            boton.querySelector('i').className = 'fas ' + (campo.type === 'password' ? 'fa-eye' : 'fa-eye-slash');
        }
    </script>
@endsection
