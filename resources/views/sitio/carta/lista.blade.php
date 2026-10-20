@extends('sitio.base')
@section('titulo', 'Tu carta está lista')
@section('robots', 'noindex, nofollow')

@push('cabecera')
    <style>
        .qr-caja svg { width: 100%; height: auto; display: block; }
        @media print {
            body { background: #fff !important; }
            nav, .no-imprimir, body > p { display: none !important; }
            .afiche { box-shadow: none !important; max-width: 520px; margin: 0 auto; }
        }
    </style>
@endpush

@section('contenido')
    @php $d = $disenos[$carta->diseno] ?? reset($disenos); @endphp
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="text-center text-white mb-4 no-imprimir">
                @if (session('ok'))<div class="alert alert-success rounded-4 d-inline-block">{{ session('ok') }}</div>@endif
                <h1 class="fw-bold">🎉 ¡Tu carta digital está lista!</h1>
                <p class="mb-0 text-white-50">Imprime el QR y ponlo en tus mesas, o comparte el enlace por WhatsApp e Instagram.</p>
            </div>

            <div class="row g-4">
                {{-- Afiche con el QR (se imprime) --}}
                <div class="col-md-6">
                    <div class="card tarjeta afiche text-center overflow-hidden h-100" style="background: {{ $d[1] }}; color: {{ $d[3] }}">
                        <div class="p-4 p-md-5">
                            <p class="text-uppercase small fw-bold mb-1" style="letter-spacing: .2em; opacity: .7">Nuestra carta</p>
                            <h2 class="fw-bold mb-3" style="font-family: {{ $d[4] }}">{{ $carta->nombre }}</h2>
                            <div class="qr-caja bg-white p-3 rounded-4 mx-auto" style="max-width: 280px">{!! $qr !!}</div>
                            <p class="mt-3 mb-0 fw-semibold">Escanea con la cámara de tu celular</p>
                            <p class="small mb-0" style="opacity: .7">{{ preg_replace('~^https?://~', '', $url) }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 no-imprimir">
                    <div class="card tarjeta p-4 h-100">
                        <label class="form-label">Tu enlace</label>
                        <div class="input-group mb-3">
                            <input id="enlace" value="{{ $url }}" readonly class="form-control">
                            <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('enlace').value); this.textContent = '✔'">Copiar</button>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="{{ $url }}" target="_blank" class="btn btn-outline-primary rounded-pill"><i class="fas fa-eye me-2"></i>Ver mi carta</a>
                            <button type="button" onclick="window.print()" class="btn btn-outline-dark rounded-pill"><i class="fas fa-print me-2"></i>Imprimir afiche con QR</button>
                            <button type="button" id="bajar_qr" class="btn btn-outline-dark rounded-pill"><i class="fas fa-download me-2"></i>Descargar QR (imagen)</button>
                            <a href="https://wa.me/?text={{ rawurlencode('Mira nuestra carta 👉 '.$url) }}" target="_blank" rel="noopener" class="btn btn-success rounded-pill"><i class="fab fa-whatsapp me-2"></i>Compartir por WhatsApp</a>
                        </div>
                        <hr>
                        <p class="fw-semibold mb-1"><i class="fas fa-key me-1 text-warning"></i>Enlace para editar (guárdalo)</p>
                        <p class="small text-muted mb-2">Con este enlace cambias tus productos, precios o el diseño cuando quieras. También te lo enviamos a <b>{{ $carta->email }}</b>.</p>
                        <div class="input-group">
                            <input id="editar" value="{{ $urlEditar }}" readonly class="form-control form-control-sm">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('editar').value); this.textContent = '✔'">Copiar</button>
                        </div>
                        <a href="{{ $urlEditar }}" class="btn btn-link mt-2">Editar mi carta</a>
                        <div class="mt-auto rounded-4 p-3 small" style="background: #eff6ff">
                            <b>¿Quieres más?</b> Con el sistema TUSHPA tus clientes ven cada plato con foto, piden desde la mesa y tú facturas a SUNAT.
                            <a href="{{ route('sitio.registro') }}">Pruébalo gratis</a>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Convierte el QR (SVG) en una imagen PNG para descargar
        document.getElementById('bajar_qr').addEventListener('click', () => {
            const svg = document.querySelector('.qr-caja svg');
            const datos = new XMLSerializer().serializeToString(svg);
            const img = new Image();
            img.onload = () => {
                const c = document.createElement('canvas');
                c.width = c.height = 1000;
                const x = c.getContext('2d');
                x.fillStyle = '#fff'; x.fillRect(0, 0, 1000, 1000);
                x.drawImage(img, 40, 40, 920, 920);
                const a = document.createElement('a');
                a.download = 'qr-{{ $carta->slug }}.png'; a.href = c.toDataURL('image/png'); a.click();
            };
            img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(datos)));
        });
    </script>
@endpush
