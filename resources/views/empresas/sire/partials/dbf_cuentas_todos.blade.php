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
    <button class="h-10 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold">Poner a todos</button>
</form>
{{-- Ayuda: al escribir una cuenta se filtra el plan contable (por número o por nombre) --}}
<datalist id="plan_cuentas">
    @foreach (\App\Support\Contabilidad\CuentasCompras::plan((string) auth()->user()->IdEmpresa) as $numero => $nombre)
        <option value="{{ $numero }}">{{ $numero }} · {{ $nombre }}</option>
    @endforeach
</datalist>
