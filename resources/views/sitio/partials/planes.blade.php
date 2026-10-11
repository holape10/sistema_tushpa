{{-- Planes: los crea y edita el dueño en admin.tushpa.app › Planes (solo se muestran los activos) --}}
@if ($planes->isNotEmpty())
    <section id="planes" class="py-5" style="background:linear-gradient(180deg,#f8fafc 0%,#eef2ff 100%)">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h6 class="text-primary text-uppercase mb-2">Planes</h6>
                <h2 class="display-5 fw-bold">Elige el plan para tu negocio</h2>
                <p class="lead text-muted mt-3">
                    Todos incluyen <b>{{ config('sitio.dias_prueba') }} días gratis</b>, sin tarjeta. Tu sistema queda listo en segundos.
                </p>
            </div>

            <div class="row g-4 justify-content-center">
                @foreach ($planes as $plan)
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                        <div class="h-100 bg-white rounded-4 p-4 d-flex flex-column position-relative {{ $plan->destacado ? 'shadow-lg border border-2 border-primary' : 'shadow-sm' }}">
                            @if ($plan->destacado)
                                <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-primary px-3 py-2">⭐ Más popular</span>
                            @endif
                            <h4 class="fw-bold mb-1 text-center mt-2">{{ $plan->nombre }}</h4>
                            @if ($plan->descripcion)
                                <p class="text-muted text-center small mb-3">{{ $plan->descripcion }}</p>
                            @endif
                            <div class="text-center mb-4">
                                <span class="display-5 fw-bold text-primary">S/ {{ rtrim(rtrim(number_format($plan->precio, 2), '0'), '.') }}</span>
                                <span class="text-muted">/ mes</span>
                            </div>
                            <ul class="list-unstyled mb-4 flex-grow-1">
                                @foreach ($plan->listaCaracteristicas() as $caracteristica)
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>{{ $caracteristica }}</li>
                                @endforeach
                            </ul>
                            <a href="{{ route('sitio.registro', ['plan' => $plan->id]) }}"
                               class="btn {{ $plan->destacado ? 'btn-primary' : 'btn-outline-primary' }} btn-lg w-100 rounded-pill fw-semibold">
                                Probar gratis {{ config('sitio.dias_prueba') }} días
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-center text-muted small mt-4 mb-0">
                ¿Necesitas algo a medida? <a href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a>.
            </p>
        </div>
    </section>
@endif
