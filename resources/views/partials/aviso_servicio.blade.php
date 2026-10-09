{{--
    Advertencia de pago que deja el panel admin.tushpa.app (antes de suspender): barra fija abajo en todas las pantallas
    del sistema de la empresa, con los días que faltan y los datos para pagar. Se puede ocultar por un rato; vuelve al recargar.
--}}
@php
    $clienteTenancy = \App\Support\Tenancy\Tenancy::cliente();
    $pago = config('soporte.pago');
    $soporteWa = config('soporte.whatsapp');
@endphp
@if ($clienteTenancy && $clienteTenancy->aviso_mensaje)
    @php
        $limite = $clienteTenancy->aviso_fecha ? \Illuminate\Support\Carbon::parse($clienteTenancy->aviso_fecha) : null;
        $dias = $limite ? (int) now()->startOfDay()->diffInDays($limite, false) : null;
    @endphp
    <div id="aviso-servicio" style="position:fixed;left:12px;right:12px;bottom:12px;z-index:2147481000;font-family:system-ui,-apple-system,'Segoe UI',sans-serif">
        <div style="max-width:880px;margin:0 auto;background:#7c2d12;color:#fff;border-radius:18px;box-shadow:0 18px 40px -10px rgba(0,0,0,.45);padding:14px 16px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
            <div style="flex:0 0 42px;height:42px;border-radius:50%;background:#f59e0b;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:900">!</div>
            <div style="flex:1;min-width:220px">
                <p style="margin:0;font-weight:800;font-size:15px">
                    @if ($dias === null) Aviso importante sobre tu servicio
                    @elseif ($dias > 1) Tu servicio se suspenderá en {{ $dias }} días ({{ $limite->format('d/m/Y') }})
                    @elseif ($dias === 1) Tu servicio se suspenderá MAÑANA ({{ $limite->format('d/m/Y') }})
                    @elseif ($dias === 0) Tu servicio se suspenderá HOY
                    @else Fecha límite vencida ({{ $limite->format('d/m/Y') }})
                    @endif
                </p>
                <p style="margin:4px 0 0;font-size:14px;line-height:1.45;color:#fde68a">{{ $clienteTenancy->aviso_mensaje }}</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button type="button" onclick="document.getElementById('pago-servicio').style.display='flex'" style="border:0;border-radius:12px;background:#f59e0b;color:#451a03;font-weight:800;padding:10px 14px;cursor:pointer">Ver cómo pagar</button>
                <button type="button" onclick="document.getElementById('aviso-servicio').style.display='none'" title="Ocultar" style="border:0;background:transparent;color:#fed7aa;font-size:24px;cursor:pointer;line-height:1">&times;</button>
            </div>
        </div>
    </div>
    <div id="pago-servicio" onclick="if (event.target === this) this.style.display='none'" style="display:none;position:fixed;inset:0;z-index:2147481500;background:rgba(15,23,42,.65);align-items:center;justify-content:center;padding:16px;font-family:system-ui,-apple-system,'Segoe UI',sans-serif">
        <div style="background:#fff;border-radius:22px;max-width:380px;width:100%;padding:20px;text-align:center;max-height:92vh;overflow:auto">
            <p style="margin:0 0 4px;font-weight:800;font-size:18px;color:#0f172a">Paga tu servicio TUSHPA</p>
            <p style="margin:0 0 12px;font-size:13px;color:#64748b">Yape, Plin o transferencia a {{ $pago['titular'] }}</p>
            @if (is_file(public_path($pago['qr'])))<img src="{{ asset($pago['qr']) }}" alt="QR de pago" style="width:100%;border-radius:14px">@endif
            <p style="margin:12px 0 2px;font-size:13px;color:#334155">Cuenta {{ $pago['banco'] }}: <b>{{ $pago['cuenta'] }}</b></p>
            <p style="margin:0 0 12px;font-size:13px;color:#334155">CCI: <b>{{ $pago['cci'] }}</b></p>
            <a href="https://wa.me/{{ $soporteWa }}?text={{ rawurlencode('Hola, soy de '.($clienteTenancy->nombre_comercial ?: $clienteTenancy->razon_social).' (RUC '.$clienteTenancy->ruc.'). Ya pagué el servicio TUSHPA, les envío el comprobante.') }}"
               target="_blank" rel="noopener" style="display:block;background:#16a34a;color:#fff;font-weight:800;border-radius:14px;padding:12px;text-decoration:none">Ya pagué · enviar comprobante</a>
            <button type="button" onclick="document.getElementById('pago-servicio').style.display='none'" style="margin-top:8px;border:0;background:#f1f5f9;border-radius:12px;padding:10px;width:100%;font-weight:700;color:#475569;cursor:pointer">Cerrar</button>
        </div>
    </div>
@endif
