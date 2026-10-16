{{-- Ventana "Ver demo": lleva a la empresa de demostración con su usuario y contraseña --}}
@php $demo = config('sitio.demo'); @endphp
<div class="modal fade" id="modalDemo" tabindex="-1" aria-labelledby="modalDemoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:1.25rem;overflow:hidden">
            <div class="p-4 text-white" style="background:linear-gradient(135deg,#1e3a8a,#4338ca)">
                <button type="button" class="btn-close btn-close-white float-end" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                <p class="mb-1 small text-uppercase fw-semibold" style="letter-spacing:.08em;opacity:.75">Sin registrarte</p>
                <h2 class="h4 fw-bold mb-1" id="modalDemoTitulo"><i class="fas fa-circle-play me-2"></i>Mira el sistema por dentro</h2>
                <p class="mb-0" style="opacity:.85">Entra a la demo con datos de ejemplo. Al ingresar eliges tu tipo de negocio: restaurante, farmacia, grifo, hotel, gimnasio y más.</p>
            </div>
            <div class="p-4">
                <div class="row g-3 mb-3">
                    @foreach (['Usuario' => $demo['usuario'], 'Contraseña' => $demo['clave']] as $etiqueta => $valor)
                        <div class="col-6">
                            <div class="border rounded-4 p-3 text-center h-100">
                                <div class="small text-muted">{{ $etiqueta }}</div>
                                <div class="fs-4 fw-bold font-monospace">{{ $valor }}</div>
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none"
                                        onclick="navigator.clipboard && navigator.clipboard.writeText(@js($valor)); this.textContent = '✔ Copiado'">Copiar</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ $demo['url'] }}" target="_blank" rel="noopener" class="btn btn-achiote btn-lg w-100"><i class="fas fa-arrow-up-right-from-square me-2"></i>Abrir la demo</a>
                <p class="small text-muted text-center mt-3 mb-0">Es una demo compartida: lo que registres ahí lo ven otros visitantes y se limpia cada cierto tiempo.
                    Para tu negocio, <a href="{{ route('sitio.registro') }}">crea tu sistema gratis</a>.</p>
            </div>
        </div>
    </div>
</div>
