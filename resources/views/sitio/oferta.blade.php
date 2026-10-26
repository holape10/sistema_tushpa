@extends('sitio.base')
@section('titulo', $p->titulo)

@push('cabecera')
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) ($p->texto ?: $p->contenido)), 155) }}">
    <meta property="og:title" content="{{ $p->titulo }}">
    @if ($p->imagen)<meta property="og:image" content="{{ asset($p->imagen) }}">@endif
@endpush

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <article class="card tarjeta overflow-hidden">
                @if ($p->imagen)<img src="{{ asset($p->imagen) }}" alt="{{ $p->titulo }}" class="w-100 d-block"
                     style="max-height: {{ \App\Support\Promociones::imagenCompleta($p) ? '80vh' : '420px' }}; {{ \App\Support\Promociones::estiloImagen($p) }}">@endif
                <div class="p-4 p-md-5">
                    <span class="badge rounded-pill mb-2" style="background: {{ $p->color }}">OFERTA</span>
                    <h1 class="fw-bold">{{ $p->titulo }}</h1>
                    @if ($p->texto)<p class="lead text-muted">{{ $p->texto }}</p>@endif
                    @if ($p->contenido)<div class="mt-3" style="white-space: pre-line">{{ $p->contenido }}</div>@endif
                    @if ($p->hasta)<p class="small text-danger fw-semibold mt-3"><i class="far fa-clock me-1"></i>Válido hasta el {{ \Carbon\Carbon::parse($p->hasta)->format('d/m/Y') }}</p>@endif
                    <a href="{{ route('sitio.promo.clic', $p->id) }}" class="btn btn-lg text-white mt-3 px-4" style="background: {{ $p->color }}; border-radius: 14px">{{ $p->boton_texto ?: 'Lo quiero' }}</a>
                    <a href="https://wa.me/{{ config('soporte.whatsapp') }}?text={{ rawurlencode('Hola, quiero la oferta: '.$p->titulo) }}" target="_blank" rel="noopener" class="btn btn-lg btn-outline-success mt-3 ms-sm-2" style="border-radius: 14px">
                        <i class="fab fa-whatsapp me-1"></i>Consultar por WhatsApp</a>
                </div>
            </article>
        </div>
    </div>
@endsection
