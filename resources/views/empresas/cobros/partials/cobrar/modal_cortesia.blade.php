{{-- Modal: dar un producto de cortesía (gratis) con su motivo (va dentro de cobrar.blade.php) --}}
@if ($descuentos->cortesias)
{{-- Modal: dar un producto de cortesía (gratis) con su motivo --}}
<div id="modal_cortesia" class="mc-fondo" style="display:none;">
    <div class="mc-caja">
        <h4>🎁 Cortesía</h4>
        <div class="mc-prod" id="mc_producto"></div>
        <div id="mc_cant_box">
            <label>¿Cuántos van gratis?</label>
            <input type="number" id="mc_cant" class="form-control big" min="1" step="1">
        </div>
        <label style="margin-top:8px;">Motivo</label>
        <div class="mc-motivos">
            @foreach (['CUMPLEAÑOS', 'CLIENTE FRECUENTE', 'RECLAMO DEL CLIENTE', 'PROMOCIÓN', 'CORTESÍA DE LA CASA'] as $motivo)
                <button type="button" class="mc-motivo" data-motivo="{{ $motivo }}">{{ $motivo }}</button>
            @endforeach
        </div>
        <input type="text" id="mc_motivo" maxlength="80" class="form-control" placeholder="O escribe el motivo">
        <div class="mc-botones">
            <button type="button" id="mc_ok" class="btn btn-warning">🎁 Dar cortesía</button>
            <button type="button" id="mc_cancelar" class="btn btn-default">Cancelar</button>
        </div>
    </div>
</div>
@endif
