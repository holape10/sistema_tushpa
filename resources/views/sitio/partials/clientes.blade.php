{{-- Logos de clientes (admin.tushpa.app › Logos web): filas de hasta 10 que se desplazan, alternando el sentido --}}
@php
    $logos = \App\Support\LogosClientes::todos();
    $filas = $logos ? array_chunk($logos, (int) ceil(count($logos) / max(1, (int) ceil(count($logos) / 10)))) : [];
@endphp
@foreach ($filas as $fila)
    <div class="clients-row" data-aos="fade-up" @if ($loop->index) data-aos-delay="{{ $loop->index * 100 }}" @endif>
        <div class="clients-marquee">
            {{-- La fila va dos veces seguidas para que el desplazamiento no tenga cortes --}}
            @foreach ([1, 2] as $copia)
                <div class="clients-track {{ $loop->parent->even ? 'track-left' : 'track-right' }}" @if ($copia === 2) aria-hidden="true" @endif>
                    @foreach ($fila as $archivo)
                        <div class="client-marquee-item">
                            <div class="client-card p-3 bg-white rounded-3 text-center">
                                <img src="{{ \App\Support\LogosClientes::url($archivo) }}" loading="lazy" alt="Cliente de TUSHPA" class="img-fluid client-logo">
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endforeach
