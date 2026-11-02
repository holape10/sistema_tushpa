{{-- Columna 2: comprobante, cliente (con su cumpleaños para la fidelización), delivery, mozo y promotor (va dentro de cobrar.blade.php) --}}
        <div class="col-cliente">
            <div class="tarjeta">
                <div class="tarjeta-h"><h2>🧾 Comprobante</h2></div>
                <div class="tarjeta-b">
                    <div class="fila-2">
                        <div class="form-group">
                            <label>Comprobante</label>
                            <select id="tdocod" class="form-control input-sm">
                                @foreach ($comprobantes as $c)
                                    <option value="{{ $c->tdocod }}" @selected($c->tdocod == ($negocio->tdocod_pred ?? '13'))>{{ $c->tdodes }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estado de pago</label>
                            <select id="estadopago" class="form-control input-sm">
                                @foreach ($estadopagos as $e)
                                    <option value="{{ $e->cre_dia_id }}" data-tipo="{{ $e->cre_dia_tip }}" data-dias="{{ $e->cre_dia_fac }}">{{ $e->cre_dia_nom }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="fila-2">
                        <div class="form-group" title="SÍ: el comprobante sale con una sola línea POR CONSUMO y el total (también ante SUNAT)">
                            <label>Por consumo</label>
                            <select id="consumo" class="form-control input-sm">
                                <option value="0">NO (detallado)</option>
                                <option value="1">SÍ: "POR CONSUMO"</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fecha de emisión</label>
                            <input type="date" id="fecEmi" class="form-control input-sm" value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="fila-2">
                        <div class="form-group" id="div_fecVen" style="display:none;">
                            <label>Vence</label>
                            <input type="date" id="fecVen" class="form-control input-sm" value="{{ now()->addDay()->format('Y-m-d') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="tarjeta">
                <div class="tarjeta-h"><h2>👤 Cliente</h2><small id="msg_cliente" class="text-danger"></small></div>
                <div class="tarjeta-b">
                    <div style="display:grid; grid-template-columns: 110px 1fr; gap: 0 10px;">
                        <div class="form-group">
                            <label>Tipo</label>
                            <select id="tdicod" class="form-control input-sm">
                                @foreach ($documentos as $d)
                                    <option value="{{ $d->tdicod }}" @selected($d->tdicod == '1')>{{ $d->tdides }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>DNI / RUC</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="clinum" class="form-control" value="{{ ($pedido->ped_num_doc ?? null) ?: '00000000' }}" maxlength="15" inputmode="numeric">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-primary" id="btn_buscar" title="Buscar (SUNAT / RENIEC)"><i class="fas fa-search"></i></button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nombre o razón social <span class="ayuda">(escribe para buscar)</span></label>
                        <div class="sug-wrap">
                            <input type="text" id="clinom" class="form-control input-sm" autocomplete="off" value="{{ !empty($pedido->ped_cli_nom) && !in_array($pedido->ped_cli_nom, ['CONSUMO EN SALON', 'PARA LLEVAR', 'CLIENTE HOSPEDAJE']) ? $pedido->ped_cli_nom : 'VENTA AL PORTADOR' }}">
                            <ul id="sug_clientes" class="sug-list"></ul>
                        </div>
                    </div>
                    <div class="fila-2">
                        <div class="form-group">
                            <label>Teléfono / celular</label>
                            <input type="text" id="telefono" class="form-control input-sm" inputmode="tel">
                        </div>
                        {{-- Solo con DNI: su cumpleaños (puntos dobles en su mes y saludo del dueño) --}}
                        <div class="form-group" id="box_nac">
                            <label>🎂 Fecha de nacimiento <span class="ayuda">(opcional)</span></label>
                            <input type="date" id="fecha_nac" class="form-control input-sm" max="{{ now()->subYears(3)->toDateString() }}">
                            <div id="cumple_aviso" class="cumple"></div>
                        </div>
                    </div>
                    <div class="fila-2">
                        <div class="form-group">
                            <label>Correo</label>
                            <input type="text" id="clicor" class="form-control input-sm" inputmode="email">
                        </div>
                        <div class="form-group">
                            <label>Dirección</label>
                            <input type="text" id="clidir" class="form-control input-sm" value="--" list="dirs_cliente">
                            <datalist id="dirs_cliente"></datalist>
                            <small id="dirs_aviso" style="display:none;color:#4f46e5"></small>
                        </div>
                    </div>

                    <div data-fidelizacion data-doc-input="#clinum" data-total-input="#total_comp"></div>
                </div>
            </div>

        </div>
