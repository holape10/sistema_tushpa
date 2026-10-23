<?php

namespace App\Http\Controllers;

use App\Support\Excel;
use App\Support\Rentabilidad;
use App\Support\Unidades;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Rentabilidad y food cost (administrador): ingeniería de menú, food cost real, teórico vs. real de los insumos,
 * lista de compras e historial de precios, cada uno con su Excel.
 */
class RentabilidadController extends Controller
{
    public const VISTAS = [
        'menu' => ['Ingeniería de menú', '⭐'], 'foodcost' => ['Food cost real', '📈'], 'teorico' => ['Teórico vs. real', '🔍'],
        'compras' => ['Lista de compras', '🛒'], 'precios' => ['Historial de precios', '🏷'],
    ];

    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador ve la rentabilidad del negocio.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    /** @return array{suc: int, ver: string, desde: string, hasta: string, categoria: ?int, dias: int, cubrir: int} */
    private function filtros(Request $request): array
    {
        $suc = $this->sucursal();
        try {
            $desde = Carbon::parse((string) $request->get('desde', now()->startOfMonth()->toDateString()))->toDateString();
            $hasta = Carbon::parse((string) $request->get('hasta', now()->toDateString()))->toDateString();
        } catch (\Throwable) {
            [$desde, $hasta] = [now()->startOfMonth()->toDateString(), now()->toDateString()];
        }
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return ['suc' => $suc, 'ver' => array_key_exists($request->get('ver'), self::VISTAS) ? $request->get('ver') : 'menu',
            'desde' => $desde, 'hasta' => $hasta, 'categoria' => (int) $request->get('categoria') ?: null,
            'dias' => min(90, max(3, (int) $request->get('dias', 14))), 'cubrir' => min(60, max(1, (int) $request->get('cubrir', 7)))];
    }

    private function datos(array $f): array
    {
        return match ($f['ver']) {
            'menu' => Rentabilidad::ingenieriaMenu($f['suc'], $f['desde'], $f['hasta'], $f['categoria']),
            'foodcost' => Rentabilidad::foodCost($f['suc'], $f['desde'], $f['hasta']),
            'teorico' => Rentabilidad::teoricoVsReal($f['suc'], $f['desde'], $f['hasta']),
            'compras' => ['lista' => Rentabilidad::listaCompras($f['suc'], $f['dias'], $f['cubrir'])],
            'precios' => ['lista' => Rentabilidad::historialPrecios($f['suc'], $f['desde'], $f['hasta'])],
        };
    }

    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $resumen = Rentabilidad::foodCost($f['suc'], $f['desde'], $f['hasta'], 1)['total'];

        return view('empresas.recetas.rentabilidad', $f + $this->datos($f) + [
            'resumen' => $resumen,
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', $f['suc'])->orderBy('cat_nom')->pluck('cat_nom', 'cat_id'),
        ]);
    }

    public function excel(Request $request)
    {
        $f = $this->filtros($request);
        $d = $this->datos($f);
        $cant = fn ($o, $n) => Unidades::texto((float) $n, $o->umecod, $o->ume_equivalente, (float) $o->factor);
        [$titulos, $filas] = match ($f['ver']) {
            'menu' => [['Producto', 'Categoría', 'Clase', 'Vendidos', '% de lo vendido', 'Ventas S/', 'Costo S/', 'Ganancia S/', 'Ganancia por unidad S/', 'Food cost %'],
                $d['platos']->map(fn ($p) => [$p->nombre, $p->categoria, Rentabilidad::CLASES[$p->clase][0], $p->cantidad, $p->participacion, $p->ventas, $p->costo,
                    $p->ganancia, $p->ganancia_unidad, $p->food_cost])->all()],
            'foodcost' => [['Mes / categoría', 'Ventas S/', 'Costo S/', 'Ganancia S/', 'Food cost %', 'Vendido sin costo S/'],
                $d['meses']->concat([(object) ['nombre' => '', 'ventas' => '', 'costo' => '', 'ganancia' => '', 'food_cost' => '', 'sin_costo' => '']])->concat($d['categorias'])
                    ->map(fn ($m) => [$m->nombre, $m->ventas, $m->costo, $m->ganancia, $m->food_cost, $m->sin_costo])->all()],
            'teorico' => [['Insumo', 'Debió gastarse (recetas)', 'Mermas anotadas', 'Faltó (+) / sobró (-) en conteos', 'Gasto real', 'Desviación %', 'Pérdida S/'],
                $d['insumos']->map(fn ($i) => [$i->nombre, $cant($i, $i->teorico), $cant($i, $i->mermas), $cant($i, $i->diferencia), $cant($i, $i->real),
                    $i->desviacion, $i->perdida])->all()],
            'compras' => [['Producto', 'Tipo', 'Stock', 'Consumo por día', 'Alcanza (días)', 'Comprar', 'Costo unit. S/', 'Importe S/', 'Último proveedor'],
                $d['lista']->map(fn ($p) => [$p->nombre, $p->insumo ? 'Insumo' : 'Producto', $cant($p, $p->stock), $cant($p, $p->por_dia), $p->dias,
                    $p->comprar.' '.Unidades::nombre($p->umecod, $p->comprar), $p->costo, $p->importe, $p->proveedor])->all()],
            'precios' => [['Insumo', 'Costo antes S/', 'Costo ahora S/', 'Variación %', 'Cambios', 'Platos afectados'],
                $d['lista']->map(fn ($c) => [$c->nombre, $c->antes, $c->ahora, $c->variacion, $c->cambios->count(), $c->platos])->all()],
        };
        $nombre = self::VISTAS[$f['ver']][0];
        $ruta = (new Excel)->hoja(mb_substr($nombre, 0, 31), [[$nombre.' · del '.Carbon::parse($f['desde'])->format('d/m/Y').' al '.Carbon::parse($f['hasta'])->format('d/m/Y')], $titulos], $filas)->guardar();

        return response()->download($ruta, 'rentabilidad_'.$f['ver'].'_'.str_replace('-', '', $f['desde']).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
