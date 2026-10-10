@php
    $nombre = $empresa->NomEmpresa ?? config('app.name');
    // Clases completas (Tailwind no detecta nombres armados por partes)
    $colores = [
        'emerald' => ['caja' => 'bg-emerald-50 ring-emerald-200', 'badge' => 'bg-emerald-600', 'texto' => 'text-emerald-800', 'icono' => '✓'],
        'rose' => ['caja' => 'bg-rose-50 ring-rose-200', 'badge' => 'bg-rose-600', 'texto' => 'text-rose-800', 'icono' => '✕'],
        'amber' => ['caja' => 'bg-amber-50 ring-amber-200', 'badge' => 'bg-amber-500', 'texto' => 'text-amber-800', 'icono' => '⏳'],
        'slate' => ['caja' => 'bg-slate-50 ring-slate-200', 'badge' => 'bg-slate-500', 'texto' => 'text-slate-700', 'icono' => '?'],
    ];
    $c = $resultado ? ($colores[$resultado['color']] ?? $colores['slate']) : null;
    $campo = 'w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-slate-800';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Consulta de comprobantes · {{ $nombre }}</title>
    @if ($logo)<link rel="icon" href="{{ $logo }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-indigo-50 to-slate-100 text-slate-800 antialiased">
<main class="max-w-xl mx-auto px-4 py-8 sm:py-12">

    <header class="text-center mb-6">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $nombre }}" class="mx-auto h-20 w-20 rounded-2xl object-contain bg-white p-1.5 shadow ring-1 ring-slate-200">
        @endif
        <h1 class="mt-3 text-xl font-extrabold tracking-tight">{{ $nombre }}</h1>
        @if ($empresa)
            <p class="text-sm text-slate-500">RUC {{ $empresa->IdEmpresa }}@if ($empresa->DirEmpresa) · {{ $empresa->DirEmpresa }}@endif</p>
        @endif
    </header>

    <section class="bg-white rounded-3xl shadow-xl ring-1 ring-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-5 text-white">
            <h2 class="text-lg font-bold">Consulta tu comprobante electrónico</h2>
            <p class="text-sm text-indigo-100">Escribe los datos tal como figuran en tu boleta o factura.</p>
        </div>

        <form method="POST" action="{{ route('cpe.consultar') }}" class="p-6 space-y-4">
            @csrf
            @if ($errors->any())
                <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 p-3 text-sm text-rose-700">
                    @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
                </div>
            @endif

            <label class="block">
                <span class="text-sm font-semibold text-slate-600">Tipo de comprobante</span>
                <select name="tipo" class="{{ $campo }} mt-1" required>
                    @foreach ($tipos as $codigo => $tipo)
                        <option value="{{ $codigo }}" @selected(old('tipo', $datos['tipo'] ?? '03') === $codigo)>{{ $tipo }}</option>
                    @endforeach
                </select>
            </label>

            <div class="grid grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Serie</span>
                    <input name="serie" value="{{ old('serie', $datos['serie'] ?? '') }}" maxlength="4" placeholder="B001" required
                           class="{{ $campo }} mt-1 uppercase" oninput="this.value=this.value.toUpperCase()">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Número</span>
                    <input name="numero" type="number" min="1" value="{{ old('numero', $datos['numero'] ?? '') }}" placeholder="125" required class="{{ $campo }} mt-1">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Fecha de emisión</span>
                    <input name="fecha" type="date" max="{{ now()->toDateString() }}" value="{{ old('fecha', $datos['fecha'] ?? '') }}" required class="{{ $campo }} mt-1">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-600">Monto total (S/)</span>
                    <input name="total" type="number" step="0.01" min="0" value="{{ old('total', $datos['total'] ?? '') }}" placeholder="0.00" required class="{{ $campo }} mt-1">
                </label>
            </div>

            <button class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700 py-3 font-bold text-white shadow-lg shadow-indigo-600/30 transition">
                🔍 Consultar documento
            </button>
        </form>

        @if ($resultado)
            <div class="border-t border-slate-100 p-6" id="resultado">
                <div class="rounded-2xl ring-1 {{ $c['caja'] }} p-5 text-center">
                    <span class="inline-flex items-center gap-2 rounded-full {{ $c['badge'] }} px-4 py-1.5 text-white font-extrabold tracking-wide">
                        <span>{{ $c['icono'] }}</span> {{ $resultado['texto'] }}
                    </span>
                    <p class="mt-3 text-sm {{ $c['texto'] }}">{{ $resultado['explicacion'] }}</p>

                    @if ($resultado['cpe'])
                        <dl class="mt-4 grid grid-cols-2 gap-2 text-left text-sm bg-white/70 rounded-xl p-3">
                            <dt class="text-slate-500">Comprobante</dt>
                            <dd class="font-semibold">{{ $resultado['cpe']->serdoc }}-{{ $resultado['cpe']->numdoc }}</dd>
                            <dt class="text-slate-500">Fecha</dt>
                            <dd class="font-semibold">{{ \Carbon\Carbon::parse($resultado['cpe']->ccafem)->format('d/m/Y') }}</dd>
                            <dt class="text-slate-500">Total</dt>
                            <dd class="font-semibold">S/ {{ number_format($resultado['cpe']->ccaitv, 2) }}</dd>
                            @if ($resultado['cpe']->ccanom)
                                <dt class="text-slate-500">Cliente</dt>
                                <dd class="font-semibold truncate">{{ $resultado['cpe']->ccanom }}</dd>
                            @endif
                        </dl>
                    @endif

                    @if ($resultado['sunat'])
                        <p class="mt-3 text-xs text-slate-500">Verificado en SUNAT
                            @if ($resultado['sunat']['empresa_estado']) · Emisor {{ $resultado['sunat']['empresa_estado'] }} / {{ $resultado['sunat']['empresa_condicion'] }}@endif</p>
                    @endif
                </div>

                @if ($resultado['descargas'])
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach (['pdf' => ['PDF A4', '📄', 'bg-rose-600 hover:bg-rose-700'], 'xml' => ['XML', '🧾', 'bg-sky-600 hover:bg-sky-700'], 'cdr' => ['CDR', '✅', 'bg-emerald-600 hover:bg-emerald-700']] as $clave => [$etiqueta, $icono, $clase])
                            @if (isset($resultado['descargas'][$clave]))
                                <a href="{{ $resultado['descargas'][$clave] }}" target="_blank" rel="noopener"
                                   class="rounded-xl {{ $clase }} py-3 text-center text-white font-bold shadow transition">
                                    <div class="text-xl">{{ $icono }}</div>
                                    <div class="text-sm">{{ $etiqueta }}</div>
                                </a>
                            @else
                                <div class="rounded-xl bg-slate-100 py-3 text-center text-slate-400" title="Aún no disponible">
                                    <div class="text-xl">{{ $icono }}</div>
                                    <div class="text-sm">{{ $etiqueta }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-2 text-center text-xs text-slate-400">Los enlaces de descarga vencen en 30 minutos.</p>
                @endif
            </div>
        @endif
    </section>

    <footer class="mt-6 text-center text-xs text-slate-400">
        También puedes validar en <a href="https://e-consultaruc.sunat.gob.pe/cl-ti-itconsvalicpe/ConsValiCpe.htm" class="underline" target="_blank" rel="noopener">SUNAT</a>
        · Sistema TUSHPA
    </footer>
</main>
@if ($resultado)
    <script>document.getElementById('resultado')?.scrollIntoView({ behavior: 'smooth', block: 'start' });</script>
@endif
</body>
</html>
