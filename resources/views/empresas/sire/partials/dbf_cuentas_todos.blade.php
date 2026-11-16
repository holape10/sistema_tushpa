{{-- Las mismas cuentas a todos los comprobantes que se llevan. Variables: $libro, $periodo, $compras --}}
<form method="POST" action="{{ route('sire.dbf.aplicar') }}" class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3 text-sm">
    @csrf
    <input type="hidden" name="libro" value="{{ $libro }}"><input type="hidden" name="periodo" value="{{ $periodo }}">
    <p class="w-full sm:w-auto sm:flex-1 text-slate-600"><strong class="text-slate-800">Mismas cuentas a todos</strong>
        <span class="block text-xs text-slate-500">{{ $compras ? 'Luego cambias las de cada compra según lo que se compró. El sistema recuerda la cuenta de cada proveedor para el próximo mes.' : 'Por ejemplo: cargo 1212 y abono 70001 para todas las ventas.' }}</span></p>
    <label class="text-xs font-semibold text-slate-500">Cta. cargo
        <input name="ctacargo" list="plan_cuentas" maxlength="12" class="block mt-1 w-28 h-10 rounded-xl border-slate-300 text-sm font-mono" placeholder="{{ $compras ? '63141' : '1212' }}"></label>
    <label class="text-xs font-semibold text-slate-500">Cta. abono
        <input name="ctaabono" list="plan_cuentas" maxlength="12" class="block mt-1 w-28 h-10 rounded-xl border-slate-300 text-sm font-mono" placeholder="{{ $compras ? '42121' : '70001' }}"></label>
    <label class="text-xs font-semibold text-slate-500">Cta. cancelación
        <input name="ctacance" list="plan_cuentas" maxlength="12" class="block mt-1 w-28 h-10 rounded-xl border-slate-300 text-sm font-mono" placeholder="1041"></label>
    @if ($compras)
        <label class="text-xs font-semibold text-slate-500">Medio de pago
            <select name="mpago" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
                <option value="">—</option>
                @foreach (\App\Support\Contabilidad\SireDbf::MEDIOS_PAGO as $codigo => $nombre)<option value="{{ $codigo }}">{{ $codigo }} {{ $nombre }}</option>@endforeach
            </select></label>
    @endif
    <button class="h-10 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold">Poner a todos</button>
</form>
@if ($compras)
    {{-- La cuenta contable de cada medio de pago: al elegir el medio en una compra, su cuenta de cancelación se pone sola --}}
    <details class="bg-white rounded-2xl shadow-sm px-4 py-3 text-sm">
        <summary class="cursor-pointer font-bold text-slate-700">💳 Medios de pago y su cuenta contable
            <span class="font-normal text-xs text-slate-500">· al elegir el medio en una compra, la Cta. cancelación se pone sola</span></summary>
        <div class="mt-3 grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach (\App\Support\Contabilidad\SireDbf::mediosPago((string) auth()->user()->IdEmpresa) as $codigo => $m)
                <label class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2">
                    <span class="flex-1 text-xs font-semibold text-slate-600">{{ $codigo }} {{ $m['nombre'] }}</span>
                    <input type="text" maxlength="12" list="plan_cuentas" value="{{ $m['cuenta'] }}" placeholder="Cuenta"
                           @change="fetch(@js(route('sire.dbf.medio_pago')), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                               body: JSON.stringify({ codigo: @js($codigo), cuenta: $event.target.value.trim() }) }).then(r => tushpaAviso(r.ok ? 'Cuenta del medio de pago guardada.' : 'Revisa la cuenta: solo números.', r.ok))"
                           class="w-28 h-9 rounded-lg border-slate-300 text-sm font-mono"></label>
            @endforeach
        </div>
    </details>
@endif
{{-- Ayuda: al escribir una cuenta se filtra el plan contable (por número o por nombre) --}}
<datalist id="plan_cuentas">
    @foreach (\App\Support\Contabilidad\CuentasCompras::plan((string) auth()->user()->IdEmpresa) as $numero => $nombre)
        <option value="{{ $numero }}">{{ $numero }} · {{ $nombre }}</option>
    @endforeach
</datalist>
