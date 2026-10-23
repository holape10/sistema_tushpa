<?php

namespace App\Http\Controllers;

use App\Support\Excel;
use App\Support\FichaReceta;
use App\Support\Impresion\Escpos;
use App\Support\Impresion\Impresion;
use App\Support\Recetas;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recetas para la cocina o para guardar: una (botón de cada plato) o varias (las que se ven en la lista) en ticket,
 * PDF o Excel, con sus ingredientes, cantidades y costos.
 */
class RecetaExportarController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador ve los costos y arma las recetas.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    /** Platos pedidos (?ids=1,2,3; vacío = todos los que tienen receta) */
    private function fichas(Request $request): Collection
    {
        $ids = collect(explode(',', (string) $request->input('ids')))->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
        $fichas = Recetas::fichas($this->sucursal(), $ids);
        abort_if($fichas->isEmpty(), 404, 'Esos platos aún no tienen receta.');

        return $fichas;
    }

    private function nombreArchivo(Collection $fichas, string $extension): string
    {
        $base = $fichas->count() === 1 ? 'receta_'.preg_replace('/[^A-Za-z0-9]+/', '_', $fichas->first()->pronom) : 'recetas_'.now()->format('Ymd');

        return trim($base, '_').'.'.$extension;
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $fichas = $this->fichas($request);
        $resumen = $fichas->map(fn ($p) => [mb_strtoupper($p->pronom), $p->cat_nom ?: 'SIN CATEGORÍA', $p->costo, $p->precio, $p->ganancia, $p->food_cost,
            $p->estado['texto'], FichaReceta::resumen($p)['explica'], $p->lineas->count()])->all();
        $ingredientes = $fichas->flatMap(fn ($p) => collect(FichaReceta::lineas($p))->map(fn ($l) => [mb_strtoupper($p->pronom), $l['nombre'],
            $l['cantidad'], $l['unidad'], $l['sin_precio'] ? 'SIN PRECIO' : $l['precio_compra'], $l['unidad_compra'], $l['equivale'], $l['costo'], $l['parte']]))->all();

        $ruta = (new Excel)
            ->hoja('Recetas', ['Plato', 'Categoría', "Te cuesta\nprepararlo S/", "Lo vendes\na S/", "Ganas por\ncada uno S/", 'Food cost %', 'Cómo está', '¿Qué significa?', 'Ingredientes'], $resumen)
            ->hoja('Ingredientes', ['Plato', 'Ingrediente', 'Lleva', 'Unidad', "Se compra\na S/", 'Por cada', 'Equivalencia', "Cuesta en\nel plato S/", "Parte del\ncosto %"], $ingredientes)
            ->guardar();

        return response()->download($ruta, $this->nombreArchivo($fichas, 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    public function pdf(Request $request): Response
    {
        $fichas = $this->fichas($request);
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('empresas.recetas.pdf', ['fichas' => $fichas, 'negocio' => $this->negocio()])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->nombreArchivo($fichas, 'pdf').'"']);
    }

    /** A la ticketera de caja (por el agente). Sin ticketera, la pantalla abre la versión para imprimir desde el navegador. */
    public function ticket(Request $request): JsonResponse
    {
        $sucursal = $this->sucursal();
        $fichas = $this->fichas($request);
        $imp = Impresion::impresoraCaja($sucursal);
        if (! $imp || $imp->tip_conex_imp === 'WINDOWS') {
            return response()->json(['directa' => false, 'mensaje' => 'No hay ticketera configurada: se abrirá para imprimir desde el navegador.']);
        }

        $p = new Escpos((int) $imp->columnas);
        foreach ($fichas as $f) {
            $r = FichaReceta::resumen($f);
            $p->alinear('centro')->negrita()->texto('RECETA')->negrita(false)
                ->tamano(1, 2)->negrita()->parrafo(mb_strtoupper($f->pronom))->negrita(false)->tamano()
                ->texto($f->cat_nom ?: 'SIN CATEGORÍA')->alinear('izq')->linea('=')
                ->negrita()->texto('INGREDIENTES ('.$f->lineas->count().')')->negrita(false)->linea();
            foreach (FichaReceta::lineas($f) as $n => $l) {
                $p->negrita()->parrafo(($n + 1).'. '.$l['nombre'])->negrita(false)
                    ->dosColumnas('   Lleva: '.$l['lleva'], 'S/ '.number_format($l['costo'], 2))
                    ->parrafo('   '.($l['compra'] ? 'Se compra a '.$l['compra'] : 'SIN PRECIO DE COMPRA'));
                if ($l['equivale']) {
                    $p->parrafo('   ('.$l['equivale'].')');
                }
            }
            $p->linea()
                ->dosColumnas('Te cuesta prepararlo', $r['cuesta'])
                ->dosColumnas('Lo vendes a', $r['vende'])
                ->negrita()->dosColumnas('GANAS POR CADA UNO', $r['ganas'])->negrita(false)
                ->linea()->texto('Food cost: '.$r['food_cost']);
            if ($r['explica']) {
                $p->parrafo($r['explica']);
            }
            // Varias recetas: cada una en su propio ticket (se cortan por separado para la cocina)
            $p->avanzar(3)->cortar();
        }
        Impresion::encolar($imp, $p->bytes(), 'RECETA', $fichas->count() === 1 ? 'Receta '.$fichas->first()->pronom : $fichas->count().' recetas');

        return response()->json(['directa' => true, 'mensaje' => ($fichas->count() === 1 ? 'Receta' : $fichas->count().' recetas').': '
            .(Impresion::agenteConectado($sucursal) ? 'enviada(s) a la impresora.' : 'en cola, saldrán apenas se conecte el agente de impresión.')]);
    }

    /** El ticket en 80 mm: vista previa (?vista=1, dentro de la pantalla) o para imprimir desde el navegador cuando no hay ticketera */
    public function ticketNavegador(Request $request): View
    {
        return view('empresas.recetas.ticket', ['fichas' => $this->fichas($request), 'vistaPrevia' => $request->boolean('vista')]);
    }

    private function negocio(): string
    {
        $n = DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->first(['nombre_comercial', 'IdEmpresa']);

        return (string) ($n->nombre_comercial ?: DB::table('empresa')->where('IdEmpresa', $n->IdEmpresa)->value('NomEmpresa'));
    }
}
