{{-- Columna 3: el total, descuento o cupón, propina, con cuánto paga y los medios de pago (va dentro de cobrar.blade.php) --}}
        <div class="col-pago">
            <div class="tarjeta">
                <div class="tarjeta-b">
                    <div class="total-box">
                        <div class="etq">Por cobrar</div>
                        <div class="monto">S/ <span id="lbl_total">{{ number_format($separadas ? 0 : $total, 2) }}</span></div>
                        @if ($separadas)<div class="pendiente">Pendiente en la mesa: S/ {{ number_format($total, 2) }}</div>@endif
                        <div id="lbl_desglose"></div>
                    </div>

                    @if ($descuentos->descuentos || $descuentos->cupones)
                        <details class="extra dscto">
                            <summary>🏷️ Descuento o cupón <span id="dscto_info" style="font-size:11px; color:#1d4ed8;"></span></summary>
                            <div>
                                @if ($descuentos->cupones)
                                    <label>🎟️ Cupón</label>
                                    <div class="input-group input-group-sm" id="cupon_box">
                                        <input type="text" id="cupon_codigo" maxlength="30" class="form-control" placeholder="Código" autocomplete="off" style="text-transform:uppercase;">
                                        <span class="input-group-btn"><button type="button" class="btn btn-primary" id="btn_cupon">Aplicar</button></span>
                                    </div>
                                    <div id="cupon_info" style="display:none; margin-top:4px; font-size:12px; font-weight:bold; color:#16a34a;">
                                        <span id="cupon_texto"></span> <button type="button" id="btn_cupon_quitar" class="btn btn-default btn-xs">Quitar</button>
                                    </div>
                                    <small id="cupon_error" class="text-danger" style="display:none;"></small>
                                @endif
                                @if ($descuentos->descuentos)
                                    <div id="dscto_manual" @if ($descuentos->cupones) style="margin-top:8px;" @endif>
                                        <label>Descuento a la cuenta</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-btn">
                                                <select id="dscto_tipo" class="form-control input-sm" style="width:64px;"><option value="S">S/</option><option value="P">%</option></select>
                                            </span>
                                            <input type="number" step="any" min="0" id="dscto_valor" class="form-control" placeholder="0.00" autocomplete="off">
                                        </div>
                                        <input type="text" id="dscto_motivo" maxlength="80" class="form-control input-sm" placeholder="Motivo (opcional)" style="margin-top:5px;">
                                        <div id="dscto_clave_box" style="display:none; margin-top:5px;">
                                            <input type="password" id="clave_admin" maxlength="100" autocomplete="new-password" class="form-control input-sm" placeholder="Clave del administrador">
                                            <small class="text-danger">Pasa el {{ rtrim(rtrim(number_format($descuentos->max, 2), '0'), '.') }}% permitido a caja: necesita la clave de un administrador.</small>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif

                    @if ($descuentos->propina)
                        <details class="extra propina">
                            <summary>💝 Propina <small style="font-weight:normal; color:#9d174d;">(no va en el comprobante)</small></summary>
                            <div>
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
                                        <select id="propina_medio" class="form-control input-sm" style="width:110px;" title="¿Cómo pagó la propina?">
                                            @foreach ($mediospagos as $m)<option value="{{ $m->id_med_pag }}" @selected($m->predeterminado == '1')>{{ $m->nom_med_pag }}</option>@endforeach
                                        </select>
                                    </span>
                                </div>
                                <div id="propina_info" style="font-size:11px; font-weight:bold; color:#be185d; margin-top:3px;"></div>
                            </div>
                        </details>
                    @endif

                    <div class="fila-2">
                        <div class="form-group">
                            <label>Paga con <span id="paga_moneda">S/</span>
                                @if ($descuentos->tipoCambio)<label style="font-weight:normal; font-size:11px; margin-left:4px; cursor:pointer;"><input type="checkbox" id="paga_usd"> en US$</label>@endif
                            </label>
                            <input type="number" step="any" id="paga" class="form-control big" value="0.00">
                            <small id="paga_equiv" style="display:none; color:#15803d; font-weight:bold;"></small>
                        </div>
                        <div class="form-group">
                            <label>Vuelto S/</label>
                            <input type="text" id="vuelto" class="form-control big vuelto" value="0.00" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Total del comprobante</label>
                        <input type="text" id="total_comp" class="form-control input-sm text-center" value="{{ number_format($separadas ? 0 : $total, 2, '.', '') }}" readonly style="font-weight:800; background:#f8fafc;">
                    </div>

                    <div id="bloque_medios">
                        <label>Medio de pago</label>
                        <select id="med_pag" class="form-control input-sm" style="margin-bottom:5px;">
                            @foreach ($mediospagos as $m)
                                <option value="{{ $m->id_med_pag }}" data-nom="{{ $m->nom_med_pag }}" @selected($m->predeterminado == '1')>{{ $m->nom_med_pag }}</option>
                            @endforeach
                        </select>
                        @if ($negocio->exigir_operacion ?? false)
                            <input type="text" id="op_med_pag" maxlength="30" class="form-control input-sm" style="margin-bottom:5px; display:none;" placeholder="N° de operación (obligatorio)">
                        @endif
                        @if ($descuentos->tipoCambio)
                            <label style="font-weight:normal; font-size:12px; cursor:pointer; margin-bottom:5px;">
                                <input type="checkbox" id="med_usd"> 💵 Paga en dólares <small class="text-muted">(T.C. {{ rtrim(rtrim(number_format($descuentos->tipoCambio, 3), '0'), '.') }})</small>
                                <b id="med_usd_equiv" style="color:#15803d; margin-left:4px;"></b>
                            </label>
                        @endif
                        <div class="input-group input-group-sm">
                            <input type="number" step="any" id="mon_med_pag" class="form-control" value="{{ number_format($separadas ? 0 : $total, 2, '.', '') }}">
                            <span class="input-group-btn"><button type="button" class="btn btn-primary" id="btn_agregar_medio"><i class="fas fa-plus"></i> Pago mixto</button></span>
                        </div>
                        <table class="table table-condensed" style="margin:8px 0 0; font-size:12px;"><tbody id="tbody_med_pag"></tbody></table>
                        <small class="ayuda">Restante: S/ <span id="lbl_restante">{{ number_format($separadas ? 0 : $total, 2) }}</span>. Si no agregas ninguno, se cobra todo con el medio elegido.</small>
                    </div>

                    <button type="button" id="btnRegistrar" class="btn btn-success btn-block btn-cobrar" style="margin-top:12px;">
                        {{ $directa ? 'REGISTRAR VENTA' : ($separadas ? 'COBRAR ESTA CUENTA' : 'REGISTRAR Y COBRAR') }}
                    </button>
                    <a href="{{ $volver ?? route('comandas.seleccion') }}" class="btn btn-default btn-block btn-salir" style="margin-top:8px;">Salir</a>
                </div>
            </div>
        </div>
