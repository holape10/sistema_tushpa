{{--
    Lo que necesita una empresa del estudio: Clave SOL principal, API SIRE y cuentas por defecto. Lo usan "Nueva
    empresa" y "Configurar". $e = la empresa (null al crear) y $cta = sus cuentas (concar_config). Las claves nunca se
    muestran: vacías = se conserva la guardada.
--}}
@php
    $e ??= null;
    $cta ??= null;
    // Lo escrito antes (si falló el registro) solo vuelve en "Nueva empresa", nunca en el "Configurar" de otra empresa
    $valor = fn (string $campo) => $e ? ($e->{$campo} ?? '') : old($campo, '');
@endphp
<fieldset class="space-y-2">
    <legend class="text-xs font-bold uppercase text-slate-500">1 · Clave SOL principal</legend>
    <input name="sol_usuario" value="{{ $valor('sol_usuario') }}" placeholder="Usuario SOL principal" autocomplete="off" class="w-full h-9 rounded-lg border-slate-300 text-sm uppercase">
    <input type="password" name="sol_clave" placeholder="{{ ($e->sol_usuario ?? null) ? 'Clave guardada · escribe para cambiar' : 'Clave SOL principal' }}" autocomplete="new-password" class="w-full h-9 rounded-lg border-slate-300 text-sm">
    <p class="text-xs text-slate-400">Con ella se traen los XML de compras y se entra al SIRE. Se guarda cifrada.</p>
</fieldset>
<fieldset class="space-y-2">
    <legend class="text-xs font-bold uppercase text-slate-500">2 · Credenciales API SUNAT (SIRE)</legend>
    <input name="client_id" value="{{ $valor('client_id') }}" placeholder="ID (client_id)" autocomplete="off" class="w-full h-9 rounded-lg border-slate-300 text-sm">
    <input type="password" name="client_secret" placeholder="{{ ($e->client_id ?? null) ? 'CLAVE guardada · escribe para cambiar' : 'CLAVE (client_secret)' }}" autocomplete="new-password" class="w-full h-9 rounded-lg border-slate-300 text-sm">
    <details class="text-xs text-slate-500" @if ($e->sire_usuario ?? null) open @endif>
        <summary class="cursor-pointer font-semibold text-indigo-700">¿Otro usuario SOL para el SIRE? (opcional)</summary>
        <p class="mt-1">Vacío = se entra con la Clave SOL principal. Pon aquí un usuario secundario solo si la empresa creó uno con permiso para el SIRE.</p>
        <input name="sire_usuario" value="{{ $valor('sire_usuario') }}" placeholder="Usuario SOL secundario" autocomplete="off" class="mt-1 w-full h-9 rounded-lg border-slate-300 text-sm uppercase">
        <input type="password" name="sire_clave" placeholder="{{ ($e->sire_usuario ?? null) ? 'Clave guardada · escribe para cambiar' : 'Su clave SOL' }}" autocomplete="new-password" class="mt-2 w-full h-9 rounded-lg border-slate-300 text-sm">
    </details>
    <p class="text-xs text-slate-400">El ID y la CLAVE se generan en SUNAT SOL → "Credenciales de API SUNAT".</p>
</fieldset>
<fieldset class="grid grid-cols-2 gap-2 content-start">
    <legend class="col-span-2 text-xs font-bold uppercase text-slate-500">3 · Cuentas por defecto (asientos y .dbf)</legend>
    @foreach (['cta_compras' => 'Compras (cargo)', 'cta_por_pagar' => 'Por pagar (abono)', 'cta_por_cobrar' => 'Por cobrar (cargo)', 'cta_ventas' => 'Ventas gravadas', 'cta_ventas_exo' => 'Ventas exoneradas'] as $campo => $texto)
        <label class="text-xs font-semibold text-slate-500">{{ $texto }}
            <input name="{{ $campo }}" value="{{ $e ? ($cta->{$campo} ?? '') : old($campo, '') }}" maxlength="12" inputmode="numeric" placeholder="{{ \App\Support\Concar::DEFECTO[$campo] }}" class="mt-0.5 w-full h-9 rounded-lg border-slate-300 text-sm font-mono"></label>
    @endforeach
</fieldset>
