<?php

namespace App\Http\Controllers;

use App\Support\ConsumoCajas;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reporte "Consumo por Caja y Barra" (solo el administrador) */
class ConsumoCajasController extends Controller
{
    /** @return array{0: int, 1: Carbon, 2: Carbon, 3: ?string} */
    private function filtros(Request $request): array
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador ve el consumo por caja.');
        // Un bar trabaja de noche: por defecto, desde ayer hasta hoy
        $desde = Carbon::parse($request->get('desde', now()->subDay()->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->endOfDay();
        $ver = preg_match('/^(caja|barra):.{1,60}$/u', (string) $request->get('ver')) ? (string) $request->get('ver') : null;

        return [(int) Auth::user()->id_empresa_negocio, $desde, $hasta, $ver];
    }

    public function index(Request $request): View
    {
        [$suc, $desde, $hasta, $ver] = $this->filtros($request);

        return view('empresas.reportes.consumo_cajas', ConsumoCajas::datos($suc, $desde, $hasta, $ver) + [
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'ver' => $ver,
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        [$suc, $desde, $hasta, $ver] = $this->filtros($request);
        $d = ConsumoCajas::datos($suc, $desde, $hasta, $ver);
        $titulo = ($ver ? str_replace(['caja:', 'barra:'], ['Caja ', 'Barra '], $ver) : 'Todas las cajas').' · del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y');

        $ruta = (new Excel)
            ->hoja('Cajas', [[$titulo], ['Barra', 'Caja', 'Comprobantes', 'Ventas S/', 'Cortesías S/']],
                $d['cajas']->map(fn ($c) => [$c->barra, $c->caja, $c->comprobantes, $c->ventas, $c->cortesias])->all())
            ->hoja('Productos', [[$titulo], ['Producto', 'Vendidos', 'Total S/', 'Cortesías', 'Valor cortesías S/']],
                $d['productos']->map(fn ($p) => [$p->nombre, $p->vendidos, $p->total, $p->cortesias, $p->valor_cortesias])->all())
            ->hoja('Insumos usados', [[$titulo], ['Insumo / producto', 'Salió del almacén', 'Cantidad', 'Costo S/']],
                $d['insumos']->map(fn ($i) => [$i->nombre, $i->texto, $i->cantidad, $i->costo])->all())
            ->guardar();

        return response()->download($ruta, 'consumo_cajas_'.$desde->format('Ymd').'_'.$hasta->format('Ymd').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
