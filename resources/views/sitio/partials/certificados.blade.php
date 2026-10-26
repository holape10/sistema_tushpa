{{-- Certificados digitales para facturación electrónica: precios y textos en admin.tushpa.app › Certificados --}}
@php $certificados = \App\Support\Certificados::activos(); @endphp
@if ($certificados->isNotEmpty())
    <section id="certificados" class="seccion">
        <div class="container">
            <div class="seccion-titulo revelar">
                <span class="etiqueta">Certificado digital</span>
                <h2>Tu certificado digital para facturar electrónicamente</h2>
                <p>Lo necesitas para firmar tus facturas y boletas electrónicas ante SUNAT. Somos <b>partner de LLAMA.PE</b>:
                    te lo damos a sus mismos precios y te ayudamos a instalarlo en tu sistema.</p>
            </div>
            <div class="row g-4 justify-content-center align-items-stretch pt-2">
                @foreach ($certificados as $c)
                    <div class="col-md-6 col-lg-4 revelar">
                        <div class="plan {{ $c->destacado ? 'destacado' : '' }}">
                            @if ($c->destacado)<span class="cinta">⭐ El más elegido</span>@endif
                            <h3 class="fs-4 mb-1"><i class="fas fa-shield-halved me-1" style="color:#2563eb"></i> {{ $c->nombre }}</h3>
                            @if ($c->detalle)<p class="texto-gris small mb-3">{{ $c->detalle }}</p>@endif
                            @if ($c->precio_antes)<p class="small texto-gris mb-0"><s>S/ {{ number_format((float) $c->precio_antes, 2) }}</s></p>@endif
                            <div class="precio mb-1">S/ {{ number_format((float) $c->precio, 2) }}</div>
                            <p class="small mb-4">
                                @if ($c->anios > 1)
                                    <b>S/ {{ number_format(\App\Support\Certificados::porAnio($c), 2) }} por año</b>
                                    @if (($primero = $certificados->firstWhere('anios', 1)) && \App\Support\Certificados::porAnio($c) < (float) $primero->precio)
                                        <span class="badge rounded-pill text-bg-success ms-1">Ahorras S/ {{ number_format((float) $primero->precio * $c->anios - (float) $c->precio, 2) }}</span>
                                    @endif
                                @else
                                    <b>Válido por 1 año</b>
                                @endif
                            </p>
                            <ul class="lista-check mb-4 flex-grow-1">
                                <li><i class="fas fa-circle-check"></i><span>Válido {{ (int) $c->anios === 1 ? 'por 1 año' : 'por '.$c->anios.' años' }} ante SUNAT</span></li>
                                <li><i class="fas fa-circle-check"></i><span>Firma tus facturas, boletas y notas electrónicas</span></li>
                                <li><i class="fas fa-circle-check"></i><span>Para tu RUC, persona natural o empresa</span></li>
                                <li><i class="fas fa-circle-check"></i><span>Te ayudamos a instalarlo en TUSHPA</span></li>
                            </ul>
                            <a href="{{ \App\Support\Certificados::whatsapp($c) }}" target="_blank" rel="noopener" class="btn {{ $c->destacado ? 'btn-achiote' : 'btn-selva' }} btn-lg w-100">
                                <i class="fab fa-whatsapp me-1"></i> Lo quiero
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-center texto-gris small mt-4 mb-0">Precios en soles. Te guiamos paso a paso por WhatsApp para emitirlo e instalarlo.</p>
        </div>
    </section>
@endif
