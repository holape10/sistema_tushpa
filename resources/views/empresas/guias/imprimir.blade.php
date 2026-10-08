{{-- Guía A4 en el navegador: mismo diseño que el PDF (guias/partials/a4_cuerpo) y que el comprobante A4 --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía {{ $g->serie }}-{{ str_pad($g->numero, 8, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { size: A4; margin: 12mm 11mm; }
        body { background: #e5e7eb; margin: 0; padding: 20px; }
        .hoja { width: 210mm; max-width: 100%; margin: 0 auto; background: #fff; padding: 12mm 11mm; box-sizing: border-box; box-shadow: 0 4px 20px rgba(0,0,0,.12); }
        .acciones { text-align: center; margin: 18px 0 4px; font-family: Arial, sans-serif; }
        .acciones a, .acciones button { display: inline-block; padding: 10px 18px; margin: 4px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 13px; color: #fff; background: #007bff; }
        @media (max-width: 800px) { body { padding: 0; } .hoja { padding: 10px; } }
        @media print { body { background: #fff; padding: 0; } .hoja { box-shadow: none; width: auto; padding: 0; } .acciones { display: none; } }
    </style>
</head>
<body>
<div class="hoja">
    @include('empresas.guias.partials.a4_cuerpo', ['paraPdf' => false])
</div>
<div class="acciones">
    <button onclick="window.print()">IMPRIMIR</button>
    <a href="{{ route('guias.pdf', $g->gre_id) }}" target="_blank">DESCARGAR PDF</a>
</div>
@if (request('imprimir') == 1)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 600));</script>
@endif
</body>
</html>
