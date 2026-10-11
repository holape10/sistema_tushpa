{{-- Planes: los crea y edita el dueño en admin.tushpa.app › Planes (solo se muestran los activos) --}}
@if ($planes->isNotEmpty())
    <section id="planes" class="seccion seccion-arena">
        <div class="container">
            <div class="seccion-titulo revelar">
                <span class="etiqueta">Planes</span>
                <h2>Elige el plan para tu negocio</h2>
                <p>Todos incluyen <b>{{ config('sitio.dias_prueba') }} días gratis</b>, sin tarjeta. Tu sistema queda listo en segundos.</p>
            </div>
            <div class="row g-4 justify-content-center align-items-stretch pt-2">
                @foreach ($planes as $plan)
                    <div class="col-md-6 col-lg-4 revelar">
                        <div class="plan {{ $plan->destacado ? 'destacado' : '' }}">
                            @if ($plan->destacado)<span class="cinta">⭐ El más elegido</span>@endif
                            <h3 class="fs-4 mb-1">{{ $plan->nombre }}</h3>
                            @if ($plan->descripcion)<p class="texto-gris small mb-3">{{ $plan->descripcion }}</p>@endif
                            <div class="precio mb-4">S/ {{ rtrim(rtrim(number_format($plan->precio, 2), '0'), '.') }} <small>/ mes</small></div>
                            <ul class="lista-check mb-4 flex-grow-1">
                                @foreach ($plan->listaCaracteristicas() as $caracteristica)
                                    <li><i class="fas fa-circle-check"></i><span>{{ $caracteristica }}</span></li>
                                @endforeach
                            </ul>
                            <a href="{{ route('sitio.registro', ['plan' => $plan->id]) }}" class="btn {{ $plan->destacado ? 'btn-achiote' : 'btn-selva' }} btn-lg w-100">
                                Probar gratis {{ config('sitio.dias_prueba') }} días
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-center texto-gris small mt-4 mb-0">
                ¿Necesitas algo a medida? <a href="https://wa.me/{{ config('soporte.whatsapp') }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a>.
            </p>
        </div>
    </section>
@endif
