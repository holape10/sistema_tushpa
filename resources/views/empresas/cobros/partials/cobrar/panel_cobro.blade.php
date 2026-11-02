{{-- Columna 1: lo que se cobra. Mesa: el detalle de la cuenta (o cuentas separadas); punto de venta: catálogo y carrito (va dentro de cobrar.blade.php) --}}
        <div class="col-cuenta">
            <div class="tarjeta">
                <div class="tarjeta-h">
                    @if ($directa)
                        <h2>🛒 Venta directa</h2>
                        <label class="top-btn" style="background:#eef2ff; color:#3730a3; border-color:#c7d2fe;" title="Manda los platos a la pantalla e impresora de cocina">
                            <input type="checkbox" id="enviar_cocina" checked> Enviar a cocina</label>
                    @else
                        <h2>🍽️ {{ $mesa ? trim(($piso->pis_nom ?? '').' / '.$mesa->mes_nom, ' /') : strtoupper($pedido->ped_tip).' · '.strtoupper($pedido->ped_cli_nom) }}</h2>
                        @if ($separadas)<span class="chip chip-morado">Cuenta separada</span>@endif
                    @endif
                </div>
                @if ($directa)
                    <div class="tarjeta-b">
                        <div style="margin-bottom:6px;">
                            @foreach ($categorias as $cat)
                                <button type="button" class="cat-btn" data-cat-id="{{ $cat->cat_id }}" style="background: {{ $cat->color ?? '#4f46e5' }};">{{ $cat->cat_nom }}</button>
                            @endforeach
                        </div>
                        <input type="text" id="buscar_producto" class="form-control input-sm" placeholder="🔎 Buscar producto..." style="margin-bottom:8px;" autocomplete="off">
                        <div id="productos_grid" class="grid-productos"><p class="text-muted">Cargando...</p></div>
                    </div>
                    <table class="table tbl-det">
                        <thead><tr><th>Producto</th><th style="width:118px" class="text-center">Cant.</th><th style="width:70px" class="text-right">Precio</th><th style="width:78px" class="text-right">Total</th><th style="width:{{ $descuentos->cortesias ? 74 : 30 }}px"></th></tr></thead>
                        <tbody id="carrito"><tr><td colspan="5" class="text-center text-muted">Toca un producto para agregarlo.</td></tr></tbody>
                    </table>
                @else
                    @if ($separadas)
                        <div style="display:flex; align-items:center; gap:6px; padding:10px 16px; background:#f5f3ff; border-bottom:1px solid #ddd6fe;">
                            <span style="font-size:13px; color:#5b21b6; flex:1;">👥 Elige qué productos paga <strong>esta</strong> persona</span>
                            <button type="button" class="btn btn-default btn-xs" id="sep_todo">Todo</button>
                            <button type="button" class="btn btn-default btn-xs" id="sep_nada">Nada</button>
                        </div>
                    @endif
                    <table class="table tbl-det">
                        <thead>
                            <tr><th>Producto</th><th style="width:64px" class="text-center">{{ $separadas ? 'Pend.' : 'Cant.' }}</th>
                                @if ($separadas)<th style="width:120px" class="text-center">Cobrar</th>@endif
                                <th style="width:72px" class="text-right">Precio</th><th style="width:80px" class="text-right">Total</th>
                                @if ($descuentos->cortesias)<th style="width:44px" class="text-center" title="Cortesía">🎁</th>@endif</tr>
                        </thead>
                        <tbody>
                            @foreach ($detalle as $d)
                                <tr data-clave="{{ $d->clave }}" data-precio="{{ $d->ped_det_pre }}" data-max="{{ $d->cantidad_pendiente }}" data-producto="{{ $d->IdProducto }}" data-base="{{ $d->id_presentacion ? 0 : 1 }}">
                                    <td>{{ $d->descripcion }}@if ($d->item_obs)<br><small class="text-muted">{{ $d->item_obs }}</small>@endif<small class="cort-info"></small></td>
                                    <td class="text-center"><span class="cant">{{ rtrim(rtrim(number_format($d->cantidad_pendiente, 2), '0'), '.') }}</span></td>
                                    @if ($separadas)
                                        <td><div class="sep-ctrl">
                                            <button type="button" class="sep-menos">−</button>
                                            <input type="number" class="sep-cant" min="0" max="{{ $d->cantidad_pendiente }}" step="1" value="0">
                                            <button type="button" class="sep-mas">+</button>
                                        </div></td>
                                    @endif
                                    @if (auth()->user()->esAdmin())
                                        {{-- El administrador cambia el precio ahí mismo (adelantos, un precio mal puesto) --}}
                                        <td class="text-right"><input type="number" step="0.01" min="0.01" class="precio-admin" value="{{ number_format($d->ped_det_pre, 2, '.', '') }}"
                                                                      data-original="{{ number_format($d->ped_det_pre, 2, '.', '') }}" title="Solo el administrador cambia el precio"></td>
                                    @else
                                        <td class="text-right text-muted">{{ number_format($d->ped_det_pre, 2) }}</td>
                                    @endif
                                    <td class="text-right"><strong class="linea-total">{{ number_format($separadas ? 0 : $d->cantidad_pendiente * $d->ped_det_pre, 2) }}</strong></td>
                                    @if ($descuentos->cortesias)
                                        <td class="text-center"><button type="button" class="btn-cortesia btn-cortesia-mesa" title="Dar de cortesía (gratis)">🎁</button></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="cuenta-pie">
                        <span class="text-muted">{{ $detalle->count() }} {{ $detalle->count() === 1 ? 'producto' : 'productos' }} · {{ rtrim(rtrim(number_format($detalle->sum('cantidad_pendiente'), 2), '0'), '.') }} unid.</span>
                        <span>{{ $separadas ? 'Pendiente en la mesa' : 'Consumo' }}: <b @unless ($separadas) id="consumo_pie" @endunless>S/ {{ number_format($total, 2) }}</b></span>
                    </div>
                    @if ($cuentasPrevias->isNotEmpty())
                        <div class="previas">
                            <strong>✔ Ya cobrado de esta mesa:</strong>
                            @foreach ($cuentasPrevias as $cp)
                                <a href="{{ route('cobros.voucher', $cp->IdCpe_cabecera) }}" target="_blank">{{ $cp->serdoc }}-{{ $cp->numdoc }}</a> ({{ $cp->ccanom }} · S/ {{ number_format($cp->ccaitv, 2) }}){{ $loop->last ? '' : ',' }}
                            @endforeach
                        </div>
                    @endif
                    <p class="ayuda" style="padding:10px 16px 12px; margin:0;">Para agregar o quitar productos vuelve a la comanda (botón EDITAR de la mesa).</p>
                @endif
            </div>
            @include('empresas.cobros.partials.cobrar.panel_atencion')
        </div>
