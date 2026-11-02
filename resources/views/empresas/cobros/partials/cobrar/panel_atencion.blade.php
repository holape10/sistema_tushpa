{{-- Delivery (motorizado), mozo, promotor y observaciones: va en la columna de la cuenta, debajo del detalle --}}
            <div class="tarjeta">
                <div class="tarjeta-h"><h2>📝 Atención</h2></div>
                <div class="tarjeta-b">
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
                    <div class="fila-2">
                        @if ($mozos->isNotEmpty())
                            <div class="form-group">
                                <label><i class="fas fa-user-tie"></i> Mozo que atendió</label>
                                <select id="mozo_id" class="form-control input-sm">
                                    <option value="">— {{ $pedido ? 'El del pedido' : 'Sin mozo' }} —</option>
                                    @foreach ($mozos as $mz)<option value="{{ $mz->IdUsuario }}" @selected((int) ($pedido->mozo ?? 0) === (int) $mz->IdUsuario)>{{ $mz->nombre }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                        @if ($promotores->isNotEmpty())
                            <div class="form-group">
                                <label><i class="fas fa-star"></i> Promotor</label>
                                <select id="promotor_id" class="form-control input-sm">
                                    <option value="">— Sin promotor —</option>
                                    @foreach ($promotores as $pr)<option value="{{ $pr->IdUsuario }}" @selected((int) ($pedido->promotor_id ?? 0) === (int) $pr->IdUsuario)>{{ $pr->nombre }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Observaciones</label>
                        <textarea id="observaciones" rows="2" class="form-control" maxlength="100" placeholder="Sale impresa en el comprobante"></textarea>
                    </div>
                </div>
            </div>
