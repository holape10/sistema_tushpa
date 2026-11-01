{{-- Izquierda: comprobante, cliente, estado de pago, delivery, mozo y promotor (va dentro de cobrar.blade.php) --}}
        <!-- IZQUIERDA: datos del comprobante -->
        <div class="col-lg-5">
            <div class="box-x">
                <div class="box-x-h" style="flex-wrap:wrap; gap:6px;">
                    <span>Datos del comprobante <small style="font-weight:normal; text-transform:none; background:#27ae60; padding:2px 8px; border-radius:10px; margin-left:6px;">Turno N° {{ $turno->turno }} abierto</small></span>
                    <label style="margin:0; font-weight:bold;">IMPRIMIR
                        <input type="checkbox" id="imprimir" checked>
                    </label>
                </div>
                <div class="box-x-b">
                    <div class="row">
                        <div class="col-xs-6 col-sm-4">
                            <div class="form-group">
                                <label>Comprobante</label>
                                <select id="tdocod" class="form-control input-sm">
                                    @foreach ($comprobantes as $c)
                                        <option value="{{ $c->tdocod }}" @selected($c->tdocod == ($negocio->tdocod_pred ?? '13'))>{{ $c->tdodes }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-4">
                            <div class="form-group">
                                <label>Estado pago</label>
                                <select id="estadopago" class="form-control input-sm">
                                    @foreach ($estadopagos as $e)
                                        <option value="{{ $e->cre_dia_id }}" data-tipo="{{ $e->cre_dia_tip }}" data-dias="{{ $e->cre_dia_fac }}">{{ $e->cre_dia_nom }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-4">
                            <div class="form-group">
                                <label>X consumo</label>
                                <select id="consumo" class="form-control input-sm">
                                    <option value="0">NO</option>
                                    <option value="1">SI</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-6 col-sm-6">
                            <div class="form-group">
                                <label>F. emisión</label>
                                <input type="date" id="fecEmi" class="form-control input-sm" value="{{ now()->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-6" id="div_fecVen" style="display:none;">
                            <div class="form-group">
                                <label>F. vencimiento</label>
                                <input type="date" id="fecVen" class="form-control input-sm" value="{{ now()->addDay()->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-6 col-sm-3">
                            <div class="form-group">
                                <label>Tipo</label>
                                <select id="tdicod" class="form-control input-sm">
                                    @foreach ($documentos as $d)
                                        <option value="{{ $d->tdicod }}" @selected($d->tdicod == '1')>{{ $d->tdides }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-4">
                            <div class="form-group">
                                <label>DNI / RUC</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="clinum" class="form-control" value="{{ ($pedido->ped_num_doc ?? null) ?: '00000000' }}" maxlength="15">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-primary" id="btn_buscar"><i class="fas fa-search"></i></button>
                                    </span>
                                </div>
                                <small id="msg_cliente" class="text-danger"></small>
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <div class="form-group">
                                <label>Nombre o razón social <small class="text-muted">(escribe para buscar)</small></label>
                                <div class="sug-wrap">
                                <input type="text" id="clinom" class="form-control input-sm" autocomplete="off" value="{{ !empty($pedido->ped_cli_nom) && !in_array($pedido->ped_cli_nom, ['CONSUMO EN SALON', 'PARA LLEVAR', 'CLIENTE HOSPEDAJE']) ? $pedido->ped_cli_nom : 'VENTA AL PORTADOR' }}">
                                <ul id="sug_clientes" class="sug-list"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" id="clidir" class="form-control input-sm" value="--" list="dirs_cliente">
                                <datalist id="dirs_cliente"></datalist>
                                <small id="dirs_aviso" style="display:none;color:#4f46e5"></small>
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-4">
                            <div class="form-group">
                                <label>Correo</label>
                                <input type="text" id="clicor" class="form-control input-sm">
                            </div>
                        </div>
                        <div class="col-xs-6 col-sm-4">
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" id="telefono" class="form-control input-sm">
                            </div>
                        </div>
                    </div>

                    @if (($pedido->ped_tip ?? null) === 'Delivery')
                        {{-- Delivery: quién lo lleva (se guarda al elegirlo) --}}
                        <div class="form-group">
                            <label><i class="fa fa-motorcycle"></i> Motorizado</label>
                            <select id="motorizado" class="form-control input-sm"
                                    onchange="fetch('{{ route('motorizados.asignar') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ ped_id: {{ (int) $pedido->ped_id }}, mot_id: this.value || null }) })">
                                <option value="">— Sin asignar —</option>
                                @foreach (\App\Http\Controllers\MotorizadoController::activos((int) $pedido->id_empresa_negocio) as $m)
                                    <option value="{{ $m->mot_id }}" @selected((int) ($pedido->mot_id ?? 0) === (int) $m->mot_id)>{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div data-fidelizacion data-doc-input="#clinum" data-total-input="#total_comp"></div>
                    @if ($mozos->isNotEmpty())
                        <div class="form-group">
                            <label><i class="fas fa-user-tie"></i> Mozo que atendió (opcional)</label>
                            <select id="mozo_id" class="form-control input-sm">
                                <option value="">— {{ $pedido ? 'El del pedido' : 'Sin mozo' }} —</option>
                                @foreach ($mozos as $mz)<option value="{{ $mz->IdUsuario }}" @selected((int) ($pedido->mozo ?? 0) === (int) $mz->IdUsuario)>{{ $mz->nombre }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if ($promotores->isNotEmpty())
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Promotor (opcional)</label>
                            <select id="promotor_id" class="form-control input-sm">
                                <option value="">— Sin promotor —</option>
                                @foreach ($promotores as $pr)<option value="{{ $pr->IdUsuario }}" @selected((int) ($pedido->promotor_id ?? 0) === (int) $pr->IdUsuario)>{{ $pr->nombre }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea id="observaciones" rows="2" class="form-control" maxlength="100"></textarea>
                    </div>
                </div>
            </div>
        </div>
