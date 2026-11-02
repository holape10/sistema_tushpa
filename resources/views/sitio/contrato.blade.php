@extends('sitio.base')
@section('titulo', 'Contrato '.$numero)
@section('robots', 'noindex, nofollow')

@php
    $estado = $contrato->estado;
    $empresa = $cliente?->nombre_comercial ?: $cliente?->razon_social;
    $archivo = 'image/jpeg,image/png,image/webp,application/pdf';
@endphp

@push('cabecera')
    <style>
        .contrato-texto { max-height: 340px; overflow-y: auto; font-size: 13px; line-height: 1.5; background: #f8fafc; border-radius: 1rem; padding: 1rem 1.2rem; }
        .contrato-texto .titulo { font-size: 16px; font-weight: 800; text-align: center; margin: 0; }
        .contrato-texto .sub { text-align: center; color: #64748b; font-size: 12px; }
        .contrato-texto h2 { font-size: 13px; font-weight: 800; margin: .8rem 0 .2rem; }
        .contrato-texto ul { padding-left: 1.1rem; margin: 0; }
        #lienzo { width: 100%; height: 190px; border: 2px dashed #94a3b8; border-radius: 1rem; background: #fff; touch-action: none; display: block; }
        .paso-n { width: 28px; height: 28px; border-radius: 50%; background: #2563eb; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; }
    </style>
@endpush

@section('contenido')
    <div class="row justify-content-center mt-2">
        <div class="col-lg-9">
            <div class="card tarjeta p-4 p-md-5">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <img src="{{ asset('imagenes/holape-logo.webp') }}" alt="" width="56" height="56">
                    <div class="flex-grow-1">
                        <p class="text-uppercase small fw-bold text-primary mb-0">Contrato de servicio · {{ $numero }}</p>
                        <h1 class="h4 fw-bold mb-0">{{ $empresa }}</h1>
                        <p class="text-muted small mb-0">RUC {{ $cliente?->ruc }} · Plan {{ $contrato->plan ?: '—' }} · {{ $periodo }} · S/ {{ number_format((float) $contrato->monto, 2) }}</p>
                    </div>
                    <a href="{{ route('sitio.contrato.pdf', $token) }}" class="btn btn-outline-primary rounded-pill fw-semibold"><i class="fas fa-file-pdf me-1"></i> Descargar PDF</a>
                </div>

                @if (session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif

                @if ($estado === 'APROBADO')
                    <div class="alert alert-success mb-0"><b>✅ Contrato aprobado.</b> Gracias por confiar en {{ $proveedor['marca'] }}. Puedes descargar tu copia firmada con el botón de arriba.</div>
                @elseif ($estado === 'ENVIADO')
                    <div class="alert alert-info mb-0"><b>📨 Recibimos tu contrato firmado.</b> Estamos revisando tus documentos; te avisaremos cuando quede aprobado.
                        Ya puedes descargar tu copia con el botón de arriba.</div>
                @else
                    @if ($estado === 'OBSERVADO')
                        <div class="alert alert-warning"><b>⚠️ Necesitamos que corrijas algo:</b> {{ $contrato->observacion }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
                    @endif

                    <form method="POST" action="{{ route('sitio.contrato.enviar', $token) }}" enctype="multipart/form-data" id="form-contrato">
                        @csrf
                        {{-- 1. Quién firma --}}
                        <h2 class="h6 fw-bold mt-2"><span class="paso-n me-2">1</span>¿Quién firma por la empresa?</h2>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><label class="form-label" for="firmante">Nombre completo</label>
                                <input id="firmante" name="firmante" value="{{ old('firmante', $contrato->firmante) }}" required maxlength="150" class="form-control text-uppercase"></div>
                            <div class="col-md-3"><label class="form-label" for="firmante_dni">DNI</label>
                                <input id="firmante_dni" name="firmante_dni" value="{{ old('firmante_dni', $contrato->firmante_dni) }}" required maxlength="12" inputmode="numeric" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label" for="firmante_cargo">Cargo <span class="text-muted fw-normal">(opcional)</span></label>
                                <input id="firmante_cargo" name="firmante_cargo" value="{{ old('firmante_cargo', $contrato->firmante_cargo) }}" maxlength="60" placeholder="Gerente, titular…" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label" for="correo">Correo</label>
                                <input id="correo" type="email" name="correo" value="{{ old('correo', $contrato->correo) }}" maxlength="120" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label" for="telefono">Celular</label>
                                <input id="telefono" name="telefono" value="{{ old('telefono', $contrato->telefono) }}" maxlength="20" inputmode="tel" class="form-control"></div>
                        </div>

                        {{-- 2. DNI por las dos caras --}}
                        <h2 class="h6 fw-bold"><span class="paso-n me-2">2</span>Copia de tu DNI <span class="text-muted fw-normal small">(foto o PDF, que se lea bien)</span></h2>
                        <div class="row g-3 mb-4">
                            @foreach (['dni_frente' => 'Cara delantera', 'dni_reverso' => 'Cara posterior'] as $campo => $texto)
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $campo }}">{{ $texto }}</label>
                                    <input id="{{ $campo }}" type="file" name="{{ $campo }}" accept="{{ $archivo }}" @required(! $contrato->{$campo}) class="form-control">
                                    @if ($contrato->{$campo})<div class="form-text text-success"><i class="fas fa-circle-check"></i> Ya la subiste; sube otra solo si quieres cambiarla.</div>@endif
                                </div>
                            @endforeach
                        </div>

                        {{-- 3. Leer y firmar --}}
                        <h2 class="h6 fw-bold"><span class="paso-n me-2">3</span>Lee y firma el contrato</h2>
                        <div class="contrato-texto mb-3">@include('contratos.cuerpo')</div>

                        <div class="btn-group w-100 mb-3" role="group">
                            <input type="radio" class="btn-check" name="forma" id="forma-pantalla" value="pantalla" @checked(old('forma', 'pantalla') === 'pantalla')>
                            <label class="btn btn-outline-primary" for="forma-pantalla">✍️ Firmar aquí (dedo, lápiz o mouse)</label>
                            <input type="radio" class="btn-check" name="forma" id="forma-escaneado" value="escaneado" @checked(old('forma') === 'escaneado')>
                            <label class="btn btn-outline-primary" for="forma-escaneado">📄 Subir el contrato firmado a mano</label>
                        </div>
                        <div id="zona-pantalla">
                            <canvas id="lienzo"></canvas>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="small text-muted">Firma dentro del recuadro.</span>
                                <button type="button" id="borrar" class="btn btn-sm btn-light">Borrar</button>
                            </div>
                            <input type="hidden" name="firma" id="firma">
                        </div>
                        <div id="zona-escaneado" class="d-none">
                            <p class="small text-muted mb-2">Descarga el PDF (botón de arriba), imprímelo, fírmalo y sube una foto nítida o el escaneo en PDF.</p>
                            <input type="file" name="escaneado" accept="{{ $archivo }}" class="form-control">
                        </div>

                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="acepto" value="1" id="acepto" required>
                            <label class="form-check-label" for="acepto">Leí el contrato y lo acepto. Declaro que los datos y documentos que envío son verdaderos.</label>
                        </div>
                        <button class="btn btn-primary btn-lg w-100 rounded-pill fw-semibold mt-3"><i class="fas fa-paper-plane me-1"></i> Enviar contrato firmado</button>
                    </form>
                @endif
            </div>
            <p class="text-center text-white-50 small mt-3"><i class="fas fa-shield-halved"></i> Tus documentos se guardan de forma privada y solo los revisa {{ $proveedor['razon_social'] }}.</p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('form-contrato');
            if (!form) return;
            // Recuadro para firmar con el dedo, lápiz o mouse
            const c = document.getElementById('lienzo'), ctx = c.getContext('2d');
            let dibujando = false, firmado = false;
            const preparar = () => {
                const r = window.devicePixelRatio || 1;
                c.width = c.offsetWidth * r; c.height = c.offsetHeight * r;
                ctx.setTransform(r, 0, 0, r, 0, 0); ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
                firmado = false;
            };
            const punto = (e) => { const b = c.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; };
            c.onpointerdown = (e) => { e.preventDefault(); dibujando = true; c.setPointerCapture(e.pointerId); const [x, y] = punto(e); ctx.beginPath(); ctx.moveTo(x, y); };
            c.onpointermove = (e) => { if (!dibujando) return; e.preventDefault(); const [x, y] = punto(e); ctx.lineTo(x, y); ctx.stroke(); firmado = true; };
            c.onpointerup = c.onpointercancel = () => { dibujando = false; };
            document.getElementById('borrar').onclick = preparar;

            const zonas = () => {
                const pantalla = document.getElementById('forma-pantalla').checked;
                document.getElementById('zona-pantalla').classList.toggle('d-none', !pantalla);
                document.getElementById('zona-escaneado').classList.toggle('d-none', pantalla);
                if (pantalla) preparar();
            };
            document.querySelectorAll('input[name=forma]').forEach((r) => r.addEventListener('change', zonas));
            zonas();

            form.addEventListener('submit', (e) => {
                if (document.getElementById('forma-pantalla').checked) {
                    if (!firmado) { e.preventDefault(); alert('Firma dentro del recuadro.'); return; }
                    document.getElementById('firma').value = c.toDataURL('image/png');
                }
            });
        })();
    </script>
@endpush
