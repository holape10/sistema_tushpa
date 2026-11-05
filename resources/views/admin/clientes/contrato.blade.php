@extends('admin.layout')
@section('title', 'Contrato · '.$cliente->razon_social)

@section('content')
    @use('App\Support\Tenancy\Contratos')
    @php
        $in = 'mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $colores = ['PENDIENTE' => 'bg-slate-100 text-slate-700', 'ENVIADO' => 'bg-sky-100 text-sky-800', 'APROBADO' => 'bg-emerald-100 text-emerald-800',
            'OBSERVADO' => 'bg-amber-100 text-amber-900', 'ANULADO' => 'bg-rose-100 text-rose-700'];
        $enlace = $contrato ? route('sitio.contrato', $contrato->token) : null;
        $telefono = preg_replace('/\D/', '', (string) ($contrato->telefono ?? $cliente->contacto_telefono));
        $mensaje = $contrato ? 'Hola, te envío el contrato de servicio de '.config('contrato.proveedor.marca').' de '.($cliente->nombre_comercial ?: $cliente->razon_social)
            .'. En este enlace subes tu DNI (ambas caras), descargas el contrato y lo firmas desde tu celular o PC: '.$enlace : '';
        $esImagen = fn ($ruta) => (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) $ruta);
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.clientes.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Clientes</a>
        <div>
            <h1 class="text-xl font-bold">Contrato de servicio</h1>
            <p class="text-sm text-slate-500">{{ $cliente->nombre_comercial ?: $cliente->razon_social }} · RUC {{ $cliente->ruc }}</p>
        </div>
    </div>

    @if (session('ok'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-semibold">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>@endif

    @if (! $cobraElDueno)
        <p class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600">Esta empresa le paga a su revendedor: el contrato lo hace él con su cliente.</p>
    @else
        <div class="grid lg:grid-cols-3 gap-5 items-start">
            {{-- Generar --}}
            <form method="POST" action="{{ route('admin.clientes.contrato.generar', $cliente->id) }}" class="bg-white rounded-2xl shadow-sm p-4 space-y-3"
                  x-data="{ periodo: 'MENSUAL', sugeridos: @js($sugeridos), monto: @js($sugeridos['MENSUAL'] ?: '') }">
                @csrf
                <h2 class="font-bold text-slate-700">{{ $contrato ? '🔁 Generar uno nuevo' : '📄 Generar contrato' }}</h2>
                <label class="block text-sm font-semibold text-slate-600">Modalidad
                    <select name="periodo" x-model="periodo" @change="monto = sugeridos[periodo] || monto" class="{{ $in }}">
                        @foreach (Contratos::PERIODOS as $v => $t)<option value="{{ $v }}">{{ $t }}</option>@endforeach
                    </select></label>
                <label class="block text-sm font-semibold text-slate-600">Monto (S/)
                    <input type="number" step="0.01" min="1" name="monto" x-model="monto" required class="{{ $in }}">
                    <span class="text-xs font-normal text-slate-400">Sale del plan {{ $cliente->planContratado?->nombre ?? $cliente->plan ?? '—' }}; puedes cambiarlo.</span></label>
                <label class="block text-sm font-semibold text-slate-600">Implementación (S/, pago único)
                    <input type="number" step="0.01" min="0" name="implementacion" value="{{ $implementacion ?: '' }}" placeholder="Sin implementación" class="{{ $in }}"></label>
                <button class="w-full h-10 rounded-xl bg-indigo-600 text-white text-sm font-bold"
                        @if ($contrato) onclick="return confirm('El contrato actual quedará anulado y la empresa tendrá que firmar el nuevo. ¿Seguir?')" @endif>
                    {{ $contrato ? 'Generar nuevo (reemplaza al actual)' : 'Generar contrato' }}</button>
            </form>

            {{-- El contrato actual --}}
            <div class="lg:col-span-2 space-y-4">
                @if (! $contrato)
                    <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-400">Esta empresa todavía no tiene contrato. Genéralo y mándale el enlace.</p>
                @else
                    <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3" x-data="{ copiado: false }">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-slate-800 flex-1">{{ Contratos::numero($contrato) }} · {{ Contratos::PERIODOS[$contrato->periodo] }} · S/ {{ number_format((float) $contrato->monto, 2) }}@if ((float) ($contrato->implementacion ?? 0) > 0) · implementación S/ {{ number_format((float) $contrato->implementacion, 2) }}@endif</h2>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $colores[$contrato->estado] }}">{{ Contratos::ESTADOS[$contrato->estado] }}</span>
                        </div>
                        @if ($contrato->estado === 'OBSERVADO')<p class="text-sm text-amber-800 bg-amber-50 rounded-xl px-3 py-2">Observación: {{ $contrato->observacion }}</p>@endif
                        <div class="flex flex-wrap gap-2">
                            <input value="{{ $enlace }}" readonly class="flex-1 min-w-60 rounded-xl border-slate-300 text-xs font-mono bg-slate-50">
                            <button type="button" @click="navigator.clipboard.writeText(@js($enlace)); copiado = true" class="px-3 h-10 rounded-xl bg-slate-100 text-sm font-semibold"
                                    x-text="copiado ? '✔ Copiado' : 'Copiar enlace'"></button>
                            <a href="https://wa.me/{{ strlen($telefono) === 9 ? '51'.$telefono : $telefono }}?text={{ rawurlencode($mensaje) }}" target="_blank" rel="noopener"
                               class="px-3 h-10 leading-10 rounded-xl bg-green-600 text-white text-sm font-bold">WhatsApp</a>
                            <a href="{{ route('admin.clientes.contrato.pdf', [$cliente->id, $contrato->id]) }}" target="_blank" class="px-3 h-10 leading-10 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-bold">📄 PDF</a>
                        </div>
                        <p class="text-xs text-slate-400">La empresa también ve un aviso para firmarlo dentro de su sistema mientras esté pendiente u observado.</p>
                    </section>

                    @if ($contrato->firmado_el)
                        <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                            <h3 class="font-bold text-slate-700">✍️ Lo que envió la empresa</h3>
                            <div class="grid sm:grid-cols-2 gap-2 text-sm">
                                <p>Firmante: <b>{{ $contrato->firmante }}</b>{{ $contrato->firmante_cargo ? ' ('.$contrato->firmante_cargo.')' : '' }}</p>
                                <p>DNI: <b>{{ $contrato->firmante_dni }}</b></p>
                                <p>Correo: {{ $contrato->correo ?: '—' }}</p>
                                <p>Celular: {{ $contrato->telefono ?: '—' }}</p>
                                <p class="sm:col-span-2 text-xs text-slate-500">Enviado el {{ \Carbon\Carbon::parse($contrato->firmado_el)->format('d/m/Y H:i') }} desde la IP {{ $contrato->firmado_ip }}
                                    · huella {{ \Illuminate\Support\Str::limit((string) $contrato->huella, 16, '…') }}</p>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                @foreach (['dni_frente' => 'DNI delantero', 'dni_reverso' => 'DNI posterior', 'firma' => 'Firma en pantalla', 'escaneado' => 'Contrato firmado a mano'] as $campo => $texto)
                                    @if ($contrato->{$campo})
                                        @php $url = route('admin.clientes.contrato.archivo', [$cliente->id, $contrato->id, $campo]); @endphp
                                        <a href="{{ $url }}" target="_blank" class="block rounded-xl ring-1 ring-slate-200 p-2 text-center hover:ring-indigo-300">
                                            @if ($esImagen($contrato->{$campo}))<img src="{{ $url }}" alt="{{ $texto }}" class="w-full h-24 object-contain bg-slate-50 rounded-lg">
                                            @else<span class="flex h-24 items-center justify-center text-3xl bg-slate-50 rounded-lg">📄</span>@endif
                                            <span class="block mt-1 text-xs font-semibold text-slate-600">{{ $texto }}</span>
                                        </a>
                                    @endif
                                @endforeach
                            </div>

                            @if (in_array($contrato->estado, ['ENVIADO', 'APROBADO'], true))
                                <form method="POST" action="{{ route('admin.clientes.contrato.revisar', [$cliente->id, $contrato->id]) }}" class="border-t border-slate-100 pt-3 space-y-2" x-data="{ observar: false }">
                                    @csrf
                                    <div class="flex flex-wrap gap-2">
                                        @if ($contrato->estado === 'ENVIADO')
                                            <button name="accion" value="aprobar" class="px-4 h-10 rounded-xl bg-emerald-600 text-white text-sm font-bold">✔ Aprobar</button>
                                        @endif
                                        <button type="button" @click="observar = !observar" class="px-4 h-10 rounded-xl bg-amber-100 text-amber-900 text-sm font-bold">⚠️ Observar</button>
                                    </div>
                                    <div x-show="observar" x-cloak class="flex flex-wrap gap-2">
                                        <input name="observacion" maxlength="255" placeholder="Qué debe corregir (ej. la foto del DNI no se lee)" class="flex-1 min-w-60 rounded-xl border-slate-300 text-sm">
                                        <button name="accion" value="observar" class="px-4 h-10 rounded-xl bg-amber-600 text-white text-sm font-bold">Enviar observación</button>
                                    </div>
                                </form>
                            @endif
                        </section>
                    @endif
                @endif

                @if ($anteriores->isNotEmpty())
                    <p class="text-xs text-slate-400">Contratos anteriores anulados: {{ $anteriores->map(fn ($a) => Contratos::numero($a).' ('.\Carbon\Carbon::parse($a->created_at)->format('d/m/Y').')')->implode(' · ') }}</p>
                @endif
            </div>
        </div>
    @endif
@endsection
