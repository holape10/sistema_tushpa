{{-- Filtros propios de cada reporte --}}
@if (in_array('vendedor', $usa, true))
    <label class="text-sm">Vendedor / cajero
        <select name="vendedor" class="block rounded-lg border-gray-300 text-sm">
            <option value="">Todos</option>
            @foreach ($vendedores as $v)<option value="{{ $v->IdUsuario }}" @selected((string) request('vendedor') === (string) $v->IdUsuario)>{{ $v->apeusu ?: $v->name }}{{ $v->estusu ? '' : ' (inactivo)' }}</option>@endforeach
        </select></label>
@endif
@if (in_array('medio', $usa, true))
    <label class="text-sm">Medio de pago
        <select name="medio" class="block rounded-lg border-gray-300 text-sm">
            <option value="">Todos</option>
            @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}" @selected((string) request('medio') === (string) $m->id_med_pag)>{{ $m->nom_med_pag }}</option>@endforeach
        </select></label>
@endif
@if (in_array('cliente', $usa, true))
    <label class="text-sm">Cliente
        <input name="cliente" value="{{ request('cliente') }}" placeholder="DNI/RUC o nombre" class="block rounded-lg border-gray-300 text-sm w-44"></label>
@endif
@if (in_array('motorizado', $usa, true))
    <label class="text-sm">Motorizado
        <select name="motorizado" class="block rounded-lg border-gray-300 text-sm">
            <option value="">Todos</option>
            <option value="sin" @selected(request('motorizado') === 'sin')>Sin motorizado</option>
            @foreach ($motorizados as $m)<option value="{{ $m->mot_id }}" @selected((string) request('motorizado') === (string) $m->mot_id)>{{ $m->nombre }}</option>@endforeach
        </select></label>
@endif
@if (in_array('estado_sunat', $usa, true))
    <label class="text-sm">Estado SUNAT
        <select name="estado_sunat" class="block rounded-lg border-gray-300 text-sm">
            @foreach (['' => 'Todos', 'ACEPTADO' => 'Aceptados', 'OBSERVADO' => 'Observados', 'pendientes' => 'Pendientes / con error', 'RECHAZADO' => 'Rechazados'] as $k => $n)<option value="{{ $k }}" @selected((string) request('estado_sunat') === $k)>{{ $n }}</option>@endforeach
        </select></label>
@endif
@if (in_array('tipo', $usa, true))
    <label class="text-sm">Comprobante
        <select name="tipo" class="block rounded-lg border-gray-300 text-sm">
            <option value="">Todos</option>
            @foreach ($tipos as $c => $n)<option value="{{ $c }}" @selected(request('tipo') === $c)>{{ $n }}</option>@endforeach
        </select></label>
@endif
@if (in_array('estado', $usa, true))
    <label class="text-sm">Estado
        <select name="estado" class="block rounded-lg border-gray-300 text-sm">
            @foreach (['vigentes' => 'Vigentes', 'anuladas' => 'Anuladas', 'todas' => 'Todas'] as $v => $n)<option value="{{ $v }}" @selected(request('estado', 'vigentes') === $v)>{{ $n }}</option>@endforeach
        </select></label>
@endif
@if (in_array('agrupar', $usa, true))
    <label class="text-sm">Agrupar por
        <select name="agrupar" class="block rounded-lg border-gray-300 text-sm">
            <option value="vendedor" @selected(request('agrupar', 'vendedor') === 'vendedor')>Vendedor / cajero</option>
            <option value="mozo" @selected(request('agrupar') === 'mozo')>Mozo (ventas en mesa)</option>
        </select></label>
@endif
@if (in_array('agrupar_rent', $usa, true))
    <label class="text-sm">Ver por
        <select name="agrupar" class="block rounded-lg border-gray-300 text-sm">
            @foreach (['producto' => 'Producto', 'categoria' => 'Categoría', 'dia' => 'Día', 'mes' => 'Mes'] as $v => $n)<option value="{{ $v }}" @selected(request('agrupar', 'producto') === $v)>{{ $n }}</option>@endforeach
        </select></label>
@endif
@if (in_array('orden', $usa, true))
    <label class="text-sm">Mostrar
        <select name="orden" class="block rounded-lg border-gray-300 text-sm">
            <option value="mas" @selected(request('orden', 'mas') === 'mas')>Más vendidos</option>
            <option value="menos" @selected(request('orden') === 'menos')>Menos vendidos</option>
        </select></label>
@endif
@if (in_array('limite', $usa, true))
    <label class="text-sm">Cantidad
        <select name="limite" class="block rounded-lg border-gray-300 text-sm">
            @foreach ([10, 20, 50, 100] as $l)<option value="{{ $l }}" @selected((int) request('limite', 20) === $l)>Top {{ $l }}</option>@endforeach
        </select></label>
@endif
@if (in_array('categoria', $usa, true))
    <label class="text-sm">Categoría
        <select name="categoria" class="block rounded-lg border-gray-300 text-sm">
            <option value="">Todas</option>
            @foreach ($categorias as $c)<option value="{{ $c->cat_id }}" @selected(request('categoria') == $c->cat_id)>{{ $c->cat_nom }}</option>@endforeach
        </select></label>
@endif
