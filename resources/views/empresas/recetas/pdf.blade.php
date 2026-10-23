{{-- Recetas en PDF (A4), explicadas en simple: qué lleva cada ingrediente, a cuánto se compra y cuánto cuesta en el plato --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; }
        .cabecera { border-bottom: 2px solid #4338ca; padding-bottom: 6px; margin-bottom: 14px; }
        .cabecera h1 { margin: 0; font-size: 16px; color: #312e81; }
        .cabecera p { margin: 2px 0 0; color: #64748b; font-size: 9px; }
        .ficha { page-break-inside: avoid; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 16px; }
        .titulo { background: #eef2ff; padding: 8px 10px; }
        .titulo b { font-size: 13px; color: #1e1b4b; }
        .titulo span { color: #64748b; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; padding: 6px 10px; }
        td { padding: 5px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .d { text-align: right; white-space: nowrap; }
        .n { color: #94a3b8; width: 14px; }
        .chico { display: block; color: #94a3b8; font-size: 8px; }
        .rojo { color: #b91c1c; font-weight: bold; }
        .barra { height: 4px; background: #e0e7ff; margin-top: 2px; }
        .barra i { display: block; height: 4px; background: #6366f1; }
        .cajas td { border: 0; padding: 8px 10px; width: 33%; }
        .caja { border-radius: 6px; padding: 7px 9px; background: #f8fafc; }
        .caja small { display: block; color: #64748b; font-size: 8.5px; }
        .caja b { font-size: 14px; }
        .fc { padding: 0 10px 9px; font-size: 9.5px; color: #475569; }
    </style>
</head>
<body>
    <div class="cabecera">
        <h1>{{ $fichas->count() === 1 ? 'Receta' : 'Recetas' }} · {{ $negocio }}</h1>
        <p>{{ $fichas->count() }} {{ $fichas->count() === 1 ? 'plato' : 'platos' }} · precios de compra al {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @foreach ($fichas as $f)
        @php $lineas = \App\Support\FichaReceta::lineas($f); $r = \App\Support\FichaReceta::resumen($f); @endphp
        <div class="ficha">
            <div class="titulo"><b>{{ mb_strtoupper($f->pronom) }}</b> <span>· {{ $f->cat_nom ?: 'SIN CATEGORÍA' }} · {{ count($lineas) }} ingredientes</span></div>
            <table>
                <thead><tr><th></th><th>Ingrediente</th><th>Lleva</th><th>Se compra a</th><th class="d">Cuesta en el plato</th><th class="d" style="width:70px">Parte del costo</th></tr></thead>
                <tbody>
                    @foreach ($lineas as $n => $l)
                        <tr>
                            <td class="n">{{ $n + 1 }}</td>
                            <td><b>{{ $l['nombre'] }}</b></td>
                            <td>{{ $l['lleva'] }}</td>
                            <td>@if ($l['compra']){{ $l['compra'] }}@else<span class="rojo">Sin precio de compra</span>@endif
                                @if ($l['equivale'])<span class="chico">{{ $l['equivale'] }}</span>@endif</td>
                            <td class="d"><b>S/ {{ number_format($l['costo'], 2) }}</b></td>
                            <td class="d">{{ $l['parte'] !== null ? $l['parte'].'%' : '' }}
                                @if ($l['parte'])<div class="barra"><i style="width: {{ min(100, $l['parte']) }}%"></i></div>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <table class="cajas">
                <tr>
                    <td><div class="caja"><small>Te cuesta prepararlo</small><b>{{ $r['cuesta'] }}</b></div></td>
                    <td><div class="caja"><small>Lo vendes a</small><b>{{ $r['vende'] }}</b></div></td>
                    <td><div class="caja" style="background:#ecfdf5"><small>Ganas por cada uno</small><b style="color:#047857">{{ $r['ganas'] }}</b></div></td>
                </tr>
            </table>
            <div class="fc">Food cost: <b style="color: {{ $f->estado['color'] }}">{{ $r['food_cost'] }}</b>@if ($r['explica']) — {{ $r['explica'] }}@endif</div>
        </div>
    @endforeach
</body>
</html>
