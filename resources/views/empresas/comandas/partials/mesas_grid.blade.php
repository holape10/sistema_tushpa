@forelse ($mesas as $m)
    @include('empresas.comandas.partials.mesa_boton', ['m' => $m, 'plano' => false])
@empty
    <p class="col-span-full py-8 text-center text-gray-400">Sin mesas en este piso</p>
@endforelse
