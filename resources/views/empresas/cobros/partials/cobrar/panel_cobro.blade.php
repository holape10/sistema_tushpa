{{-- Derecha: detalle de la cuenta (o carrito del punto de venta), descuentos, propina y medios de pago (va dentro de cobrar.blade.php) --}}
        <!-- DERECHA: detalle + cobro -->
        <div class="col-lg-7">
            <div class="box-x">
                <div class="box-x-h">
                    <span>
                        @if ($directa)
                            <span style="background:#e67e22; padding:2px 8px; border-radius:10px; margin-right:6px;">PUNTO DE VENTA</span> Venta directa
                        @else
                        @if ($separadas)<span style="background:#8e44ad; padding:2px 8px; border-radius:10px; margin-right:6px;">CUENTA SEPARADA</span>@endif
                        Detalle:
                        @if ($mesa)
                            {{ $piso->pis_nom ?? '' }} / {{ $mesa->mes_nom }}
                        @else
                            {{ strtoupper($pedido->ped_tip) }} - {{ strtoupper($pedido->ped_cli_nom) }}
                        @endif
                        @endif
                    </span>
                    @if ($directa)
                        <label style="margin:0; font-weight:bold; text-transform:none;" title="Manda los platos a la pantalla e impresora de cocina">
                            <input type="checkbox" id="enviar_cocina" checked> Enviar a cocina</label>
                    @elseif ($separadas)
                        <a href="{{ route('cobros.cobrar', $pedido->ped_id) }}" class="btn btn-default btn-xs">Cobrar todo junto</a>
                    @else
                        <a href="{{ route('cobros.separadas', $pedido->ped_id) }}" class="btn btn-primary btn-xs" style="background:#8e44ad; border-color:#8e44ad;">Cuentas Separadas</a>
                    @endif
                </div>
                <div class="box-x-b">
                    <div class="row">
                        <div class="col-md-7">
                            @if ($directa)
                                <div style="margin-bottom:6px;">
                                    @foreach ($categorias as $cat)
                                        <button type="button" class="cat-btn" data-cat-id="{{ $cat->cat_id }}" style="background: {{ $cat->color ?? '#3f4aee' }};">{{ $cat->cat_nom }}</button>
                                    @endforeach
                                </div>
                                <input type="text" id="buscar_producto" class="form-control input-sm" placeholder="Buscar producto..." style="margin-bottom:6px;" autocomplete="off">
                                <div id="productos_grid" class="grid-productos"><p class="text-muted">Cargando...</p></div>
                                <table class="table table-bordered table-condensed tbl-det" style="margin-top:10px;">
                                    <thead><tr><th>PRODUCTO</th><th style="width:110px">CANT.</th><th style="width:70px">PRECIO</th><th style="width:75px">TOTAL</th><th style="width:{{ $descuentos->cortesias ? 70 : 28 }}px"></th></tr></thead>
                                    <tbody id="carrito"><tr><td colspan="5" class="text-center text-muted">Toca un producto para agregarlo.</td></tr></tbody>
                                </table>
                            @else
                            @if ($separadas)
                                <div style="display:flex; gap:6px; margin-bottom:6px;">
                                    <span style="font-size:12px; color:#555; flex:1;">Elige qué productos paga <strong>esta</strong> persona:</span>
                                    <button type="button" class="btn btn-default btn-xs" id="sep_todo">Todo</button>
                                    <button type="button" class="btn btn-default btn-xs" id="sep_nada">Nada</button>
                                </div>
                            @endif
                            <table class="table table-bordered table-condensed tbl-det">
                                <thead>
                                    <tr><th>PRODUCTO</th><th style="width:60px">{{ $separadas ? 'PEND.' : 'CANT.' }}</th>
                                        @if ($separadas)<th style="width:110px">COBRAR</th>@endif
                                        <th style="width:70px">PRECIO</th><th style="width:75px">TOTAL</th>
                                        @if ($descuentos->cortesias)<th style="width:40px" title="Cortesía">🎁</th>@endif</tr>
                                </thead>
                                <tbody>
                                    @foreach ($detalle as $d)
                                        <tr data-clave="{{ $d->clave }}" data-precio="{{ $d->ped_det_pre }}" data-max="{{ $d->cantidad_pendiente }}" data-producto="{{ $d->IdProducto }}" data-base="{{ $d->id_presentacion ? 0 : 1 }}">
                                            <td>{{ $d->descripcion }}@if ($d->item_obs)<br><small class="text-muted">{{ $d->item_obs }}</small>@endif<small class="cort-info"></small></td>
                                            <td class="text-center">{{ rtrim(rtrim(number_format($d->cantidad_pendiente, 2), '0'), '.') }}</td>
                                            @if ($separadas)
                                                <td><div class="sep-ctrl">
                                                    <button type="button" class="sep-menos">−</button>
                                                    <input type="number" class="sep-cant" min="0" max="{{ $d->cantidad_pendiente }}" step="1" value="0">
                                                    <button type="button" class="sep-mas">+</button>
                                                </div></td>
                                            @endif
                                            <td class="text-right">{{ number_format($d->ped_det_pre, 2) }}</td>
                                            <td class="text-right"><strong class="linea-total">{{ number_format($separadas ? 0 : $d->cantidad_pendiente * $d->ped_det_pre, 2) }}</strong></td>
                                            @if ($descuentos->cortesias)
                                                <td class="text-center"><button type="button" class="btn-cortesia btn-cortesia-mesa" title="Dar de cortesía (gratis)">🎁</button></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($cuentasPrevias->isNotEmpty())
                                <div style="font-size:11px; color:#555; background:#f7f7f7; padding:6px 8px; border-radius:5px; margin-bottom:6px;">
                                    <strong>Ya cobrado de esta mesa:</strong>
                                    @foreach ($cuentasPrevias as $cp)
                                        <a href="{{ route('cobros.voucher', $cp->IdCpe_cabecera) }}" target="_blank">{{ $cp->serdoc }}-{{ $cp->numdoc }}</a> ({{ $cp->ccanom }} · S/ {{ number_format($cp->ccaitv, 2) }}){{ $loop->last ? '' : ',' }}
                                    @endforeach
                                </div>
                            @endif
                            <p class="text-muted" style="font-size:11px;">Para agregar o quitar productos vuelve a la comanda (botón EDITAR de la mesa).</p>
                            @endif
                        </div>

                        <div class="col-md-5 panel-pago">
                            <div class="totales">
                                <div style="font-size:11pt; color:#d33;"><b>Por cobrar:</b> S/ <span id="lbl_total">{{ number_format($separadas ? 0 : $total, 2) }}</span>
                                    @if ($separadas)<div style="font-size:9pt; color:#666;">Pendiente en la mesa: S/ {{ number_format($total, 2) }}</div>@endif</div>
                                <div id="lbl_desglose"></div>
                            </div>

                            @if ($descuentos->descuentos || $descuentos->cupones)
                                {{-- Descuento a la cuenta: el cajero hasta el máximo de la sucursal; más que eso, con la clave del administrador --}}
                                <div class="dscto-box">
                                    @if ($descuentos->cupones)
                                        {{-- Cupón: el descuento lo pone el cupón (ya autorizado por el administrador) --}}
                                        <label>🎟️ CUPÓN DE DESCUENTO</label>
                                        <div class="input-group input-group-sm" id="cupon_box">
                                            <input type="text" id="cupon_codigo" maxlength="30" class="form-control" placeholder="Escribe el código" autocomplete="off" style="text-transform:uppercase;">
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-primary" id="btn_cupon">Aplicar</button>
                                            </span>
                                        </div>
                                        <div id="cupon_info" style="display:none; margin-top:4px; font-size:12px; font-weight:bold; color:#16a34a;">
                                            <span id="cupon_texto"></span> <button type="button" id="btn_cupon_quitar" class="btn btn-default btn-xs">Quitar</button>
                                        </div>
                                        <small id="cupon_error" class="text-danger" style="display:none;"></small>
                                    @endif
                                    @if ($descuentos->descuentos)
                                    <div id="dscto_manual" @if ($descuentos->cupones) style="margin-top:8px;" @endif>
                                    <label>DESCUENTO A LA CUENTA</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-btn">
                                            <select id="dscto_tipo" class="form-control input-sm" style="width:64px;">
                                                <option value="S">S/</option>
                                                <option value="P">%</option>
                                            </select>
                                        </span>
                                        <input type="number" step="any" min="0" id="dscto_valor" class="form-control" placeholder="0.00" autocomplete="off">
                                    </div>
                                    <input type="text" id="dscto_motivo" maxlength="80" class="form-control input-sm" placeholder="Motivo (opcional): cliente frecuente, promo…" style="margin-top:5px;">
                                    <div id="dscto_clave_box" style="display:none; margin-top:5px;">
                                        <input type="password" id="clave_admin" maxlength="100" autocomplete="new-password" class="form-control input-sm" placeholder="Clave del administrador">
                                        <small class="text-danger">Pasa el {{ rtrim(rtrim(number_format($descuentos->max, 2), '0'), '.') }}% permitido a caja: necesita la clave de un administrador.</small>
                                    </div>
                                    </div>
                                    @endif
                                    <div id="dscto_info" style="font-size:11px; font-weight:bold; color:#2c6fa3; margin-top:3px;"></div>
                                </div>
                            @endif

                            @if ($descuentos->propina)
                                {{-- Propina: la deja el cliente para el mozo. No va en el comprobante ni suma a la venta --}}
                                <div class="propina-box">
                                    <label>💝 PROPINA <small style="font-weight:normal; color:#777;">(no va en el comprobante)</small></label>
                                    <div style="display:flex; gap:4px; margin-bottom:5px;">
                                        @foreach ([5, 10, 15] as $pct)
                                            <button type="button" class="btn btn-default btn-sm btn-propina" data-pct="{{ $pct }}" style="flex:1;">{{ $pct }}%</button>
                                        @endforeach
                                        <button type="button" class="btn btn-default btn-sm" id="propina_quitar" style="flex:1;">Sin</button>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-addon">S/</span>
                                        <input type="number" step="any" min="0" id="propina" class="form-control" placeholder="0.00" autocomplete="off">
                                        <span class="input-group-btn">
                                            <select id="propina_medio" class="form-control input-sm" style="width:120px;" title="¿Cómo pagó la propina?">
                                                @foreach ($mediospagos as $m)
                                                    <option value="{{ $m->id_med_pag }}" @selected($m->predeterminado == '1')>{{ $m->nom_med_pag }}</option>
                                                @endforeach
                                            </select>
                                        </span>
                                    </div>
                                    <div id="propina_info" style="font-size:11px; font-weight:bold; color:#be185d; margin-top:3px;"></div>
                                </div>
                            @endif

                            <div class="row">
                                <div class="col-xs-6">
                                    <label>PAGA CON <span id="paga_moneda">S/</span>:
                                        @if ($descuentos->tipoCambio)
                                            <label style="font-weight:normal; font-size:11px; margin-left:4px; cursor:pointer;"><input type="checkbox" id="paga_usd"> en US$</label>
                                        @endif
                                    </label>
                                    <input type="number" step="any" id="paga" class="form-control big" value="0.00" style="color:#555;">
                                    <small id="paga_equiv" style="display:none; color:#15803d; font-weight:bold;"></small>
                                </div>
                                <div class="col-xs-6">
                                    <label>VUELTO S/:</label>
                                    <input type="text" id="vuelto" class="form-control big" value="0.00" readonly style="color:#d33; background:#f4f4f4;">
                                </div>
                            </div>

                            <div class="form-group" style="margin-top:10px;">
                                <label>TOTAL COMP.</label>
                                <input type="text" id="total_comp" class="form-control big" value="{{ number_format($separadas ? 0 : $total, 2, '.', '') }}" readonly style="color:#000; background:#f4f4f4;">
                            </div>

                            <hr style="margin:8px 0;">

                            <div id="bloque_medios">
                                <label>MEDIOS DE PAGO</label>
                                <select id="med_pag" class="form-control input-sm" style="margin-bottom:5px;">
                                    @foreach ($mediospagos as $m)
                                        <option value="{{ $m->id_med_pag }}" data-nom="{{ $m->nom_med_pag }}" @selected($m->predeterminado == '1')>{{ $m->nom_med_pag }}</option>
                                    @endforeach
                                </select>
                                @if ($negocio->exigir_operacion ?? false)
                                    {{-- Tarjeta, Yape, transferencia...: número de operación obligatorio --}}
                                    <input type="text" id="op_med_pag" maxlength="30" class="form-control input-sm" style="margin-bottom:5px; display:none;"
                                           placeholder="N° de operación (obligatorio)">
                                @endif
                                @if ($descuentos->tipoCambio)
                                    {{-- Pago en dólares: el monto sigue en soles; se anota cuántos dólares recibe la caja --}}
                                    <label style="font-weight:normal; font-size:12px; cursor:pointer; margin-bottom:5px;">
                                        <input type="checkbox" id="med_usd"> 💵 Paga en dólares <small class="text-muted">(T.C. {{ rtrim(rtrim(number_format($descuentos->tipoCambio, 3), '0'), '.') }})</small>
                                        <b id="med_usd_equiv" style="color:#15803d; margin-left:4px;"></b>
                                    </label>
                                @endif
                                <div class="input-group input-group-sm">
                                    <input type="number" step="any" id="mon_med_pag" class="form-control" value="{{ number_format($separadas ? 0 : $total, 2, '.', '') }}">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-primary" id="btn_agregar_medio"><i class="fas fa-plus-square"></i> AGREGAR</button>
                                    </span>
                                </div>
                                <table class="table table-condensed" style="margin:8px 0 0; font-size:12px;"><tbody id="tbody_med_pag"></tbody></table>
                                <small class="text-muted">Restante: S/ <span id="lbl_restante">{{ number_format($separadas ? 0 : $total, 2) }}</span>. Si no agregas ninguno, se cobra todo con el medio seleccionado arriba.</small>
                            </div>

                            <hr style="margin:10px 0;">

                            <button type="button" id="btnRegistrar" class="btn btn-success btn-block" style="height:45px; font-size:11pt; font-weight:bold;">
                                {{ $directa ? 'REGISTRAR VENTA' : ($separadas ? 'COBRAR ESTA CUENTA' : 'REGISTRAR Y COBRAR') }}
                            </button>
                            <a href="{{ $volver ?? route('comandas.seleccion') }}" class="btn btn-default btn-block" style="margin-top:8px; font-weight:bold;">SALIR</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
