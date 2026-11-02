{{-- Vigencia del certificado digital: días que quedan; vencido en producción = no se puede enviar nada a SUNAT --}}
@php
    $diasCert = \App\Support\Sunat\Certificado::diasRestantes($empresa->fec_fin_cer);
    $fechaCert = $empresa->fec_fin_cer ? \Carbon\Carbon::parse($empresa->fec_fin_cer)->format('d/m/Y') : null;
    $urlCert = route('empresas.edit', $empresa->IdEmpresa).'#certificado';
@endphp
@if (! is_file(storage_path('app/certificados/'.$empresa->IdEmpresa.'.pem')))
    <div class="mb-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm flex flex-wrap items-center gap-3">
        <span class="text-2xl">🔐</span>
        <p class="flex-1"><b>Falta el certificado digital.</b> Sin él no se pueden enviar comprobantes a SUNAT.</p>
        <a href="{{ $urlCert }}" class="px-4 py-2 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700">Subir certificado</a>
    </div>
@elseif ($diasCert !== null && $diasCert < 0)
    <div class="mb-4 rounded-2xl bg-red-600 text-white px-5 py-4 shadow-sm flex flex-wrap items-center gap-4">
        <span class="text-4xl">⛔</span>
        <div class="flex-1 min-w-[220px]">
            <p class="text-lg font-extrabold">Tu certificado digital VENCIÓ el {{ $fechaCert }}</p>
            <p class="text-sm text-red-100">
                @if ((string) $empresa->produccion === '1')
                    No puedes enviar comprobantes, resúmenes ni bajas a SUNAT hasta que subas el nuevo. Tus comprobantes quedan pendientes para enviarlos después.
                @else
                    En producción ya no podrías enviar nada a SUNAT. Renuévalo y súbelo.
                @endif
            </p>
        </div>
        <a href="{{ $urlCert }}" class="px-4 py-2 rounded-xl bg-white text-red-700 font-extrabold hover:bg-red-50">Subir certificado nuevo</a>
    </div>
@elseif ($diasCert !== null && $diasCert <= 30)
    <div class="mb-4 rounded-2xl {{ $diasCert <= 7 ? 'bg-orange-50 border-orange-300 text-orange-900' : 'bg-amber-50 border-amber-200 text-amber-900' }} border px-5 py-4 flex flex-wrap items-center gap-4">
        <div class="text-center px-3 py-1 rounded-xl {{ $diasCert <= 7 ? 'bg-orange-500' : 'bg-amber-500' }} text-white">
            <p class="text-3xl font-extrabold leading-none">{{ $diasCert }}</p>
            <p class="text-[10px] font-bold uppercase">{{ $diasCert === 1 ? 'día' : 'días' }}</p>
        </div>
        <div class="flex-1 min-w-[220px]">
            <p class="text-lg font-extrabold">
                {{ $diasCert === 0 ? '¡Tu certificado digital vence HOY!' : 'Tu certificado digital vence en '.$diasCert.' '.($diasCert === 1 ? 'día' : 'días') }}
            </p>
            <p class="text-sm">Vence el <b>{{ $fechaCert }}</b>. Después de esa fecha no podrás enviar comprobantes ni resúmenes a SUNAT: renuévalo con tu proveedor y súbelo en Editar empresa.</p>
        </div>
        <a href="{{ $urlCert }}" class="px-4 py-2 rounded-xl {{ $diasCert <= 7 ? 'bg-orange-600 hover:bg-orange-700' : 'bg-amber-600 hover:bg-amber-700' }} text-white font-bold">Subir certificado</a>
    </div>
@elseif ($diasCert !== null)
    <div class="mb-4 text-sm">
        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 font-semibold">🔐 Certificado vigente hasta {{ $fechaCert }} · quedan {{ $diasCert }} días</span>
    </div>
@endif
