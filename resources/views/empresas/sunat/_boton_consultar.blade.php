{{--
    Botón "Consultar" en SUNAT de una compra o gasto: muestra lo último que respondió SUNAT y permite volver a consultar.
    Variables: $url (POST que consulta), $estado (lo guardado o null), $consultado (fecha o null)
--}}
@php
    $color = fn (?string $e) => match (true) {
        ! $e => 'bg-gray-100 text-gray-500',
        str_contains($e, 'ACEPTADO') => 'bg-emerald-100 text-emerald-800',
        str_contains($e, 'ANULADO') || str_contains($e, 'BAJA') => 'bg-rose-100 text-rose-800',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp
<span class="inline-flex flex-col items-end gap-0.5 align-middle" x-data="{ estado: @js($estado), clase: @js($color($estado)), mensaje: @js($consultado ? 'Consultado el '.\Carbon\Carbon::parse($consultado)->format('d/m/Y H:i') : 'Aún no se consultó en SUNAT'), cargando: false }">
    <span class="inline-flex items-center gap-1.5">
        <span x-show="estado" class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="clase" :title="mensaje" x-text="estado"></span>
        <button type="button" :disabled="cargando" :title="mensaje" class="text-xs font-semibold text-emerald-700 hover:underline disabled:opacity-50"
                @click="cargando = true; fetch(@js($url), { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), Accept: 'application/json' } })
                    .then(r => r.status === 429 ? { ok: false, mensaje: 'Muchas consultas seguidas: espera un minuto.' } : r.json())
                    .then(d => { mensaje = d.mensaje; if (d.ok) { estado = d.estado; clase = { emerald: 'bg-emerald-100 text-emerald-800', rose: 'bg-rose-100 text-rose-800' }[d.color] || 'bg-amber-100 text-amber-800'; } else { alert(d.mensaje); } })
                    .catch(() => alert('Sin conexión con el servidor.')).finally(() => cargando = false)"
                x-text="cargando ? 'Consultando…' : '🔎 Consultar'"></button>
    </span>
    <span x-show="estado && mensaje" class="text-[10px] text-gray-400 max-w-[180px] truncate" x-text="mensaje"></span>
</span>
