<?php
namespace App\Http\Controllers;

use App\Support\{Excel, Gastos};
use App\Support\Sunat\Sire;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class GastoController extends Controller
{
    private function ruc(bool $soloAdmin = false): string
    {
        abort_unless($soloAdmin ? Auth::user()->esAdmin() : Auth::user()->esAdminOCaja(), 403, 'No tienes permiso para los gastos.');
        return (string) Auth::user()->IdEmpresa;
    }

    private const REGLAS = [
        'fecha' => 'required|date', 'tdocod' => 'required|in:01,03,02,14,12,07,00', 'serie' => 'nullable|string|max:6', 'numero' => 'nullable|string|max:20',
        'prov_doc' => 'nullable|string|max:15', 'prov_nombre' => 'nullable|string|max:200', 'categoria_id' => 'required|integer',
        'descripcion' => 'nullable|string|max:255', 'moneda' => 'required|in:PEN,USD', 'tipo_cambio' => 'nullable|numeric|min:0',
        'base' => 'nullable|numeric|min:0', 'igv' => 'nullable|numeric|min:0', 'no_gravado' => 'nullable|numeric|min:0', 'retencion' => 'nullable|numeric|min:0',
        'credito_fiscal' => 'boolean', 'forma_pago' => 'required|in:CONTADO,CREDITO', 'id_med_pag' => 'nullable|integer',
    ];

    public function index(Request $request)
    {
        $ruc = $this->ruc();
        $mes = $request->get('mes', now()->format('Y-m'));
        [$desde, $hasta] = [Carbon::parse("$mes-01")->toDateString(), Carbon::parse("$mes-01")->endOfMonth()->toDateString()];
        $q = trim((string) $request->get('q'));

        $base = DB::table('gastos as g')->leftJoin('gasto_categorias as c', 'c.id', '=', 'g.categoria_id')
            ->where('g.IdEmpresa', $ruc)->whereBetween('g.fecha', [$desde, $hasta])
            ->when($request->filled('categoria'), fn($w) => $w->where('g.categoria_id', $request->categoria))
            ->when($request->get('estado', 'Registrado') !== 'todos', fn($w) => $w->where('g.estado', $request->get('estado', 'Registrado')))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('g.prov_nombre', 'like', "%$q%")->orWhere('g.prov_doc', 'like', "$q%")
                ->orWhere('g.descripcion', 'like', "%$q%")->orWhere(DB::raw("CONCAT(g.serie, '-', g.numero)"), 'like', "%$q%")));

        $gastos = (clone $base)->orderByDesc('g.fecha')->orderByDesc('g.id')->select('g.*', 'c.nombre as categoria', 'c.color')->get();

        if ($request->get('excel')) {
            $filas = $gastos->map(fn($g) => [Carbon::parse($g->fecha)->format('d/m/Y'), Gastos::DOCUMENTOS[$g->tdocod] ?? $g->tdocod,
                trim(($g->serie ? $g->serie . '-' : '') . $g->numero, '-'), (string) $g->prov_doc, $g->prov_nombre, $g->categoria, $g->descripcion, $g->moneda,
                (float) $g->base, (float) $g->igv, (float) $g->no_gravado, (float) $g->total, (float) $g->retencion, $g->credito_fiscal ? 'SÍ' : 'NO', $g->forma_pago, $g->estado])->all();
            $ruta = (new Excel())->hoja('Gastos ' . $mes, ['Fecha', 'Documento', 'Número', 'RUC/DNI', 'Proveedor', 'Categoría', 'Descripción', 'Moneda',
                'Base', 'IGV', 'No gravado', 'Total', 'Retención', 'Crédito fiscal', 'Pago', 'Estado'], $filas)->guardar();
            return response()->download($ruta, "gastos_{$mes}.xlsx")->deleteFileAfterSend();
        }

        $registrados = $gastos->where('estado', 'Registrado');
        return view('empresas.gastos.index', [
            'gastos' => $gastos, 'mes' => $mes, 'q' => $q,
            'categorias' => Gastos::categorias($ruc),
            'porCategoria' => $registrados->groupBy('categoria')->map(fn($g) => ['total' => $g->sum(fn($x) => Gastos::soles($x)), 'color' => $g->first()->color])->sortByDesc('total'),
            'totales' => ['total' => $registrados->sum(fn($x) => Gastos::soles($x)), 'igv' => $registrados->where('credito_fiscal', 1)->sum(fn($x) => Gastos::soles($x, 'igv')),
                'n' => $registrados->count(), 'retencion' => $registrados->sum(fn($x) => Gastos::soles($x, 'retencion'))],
            'medios' => DB::table('medios_pagos')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
            'documentos' => Gastos::DOCUMENTOS,
            'solicitudesSire' => DB::table('sire_solicitudes')->where('IdEmpresa', $ruc)->where('libro', Sire::COMPRAS)->whereNotNull('archivo')
                ->orderByDesc('periodo')->get(['id', 'periodo', 'filas']),
        ]);
    }

    public function guardar(Request $request, ?int $id = null)
    {
        $ruc = $this->ruc();
        $d = $request->validate(self::REGLAS, [], ['categoria_id' => 'categoría', 'tdocod' => 'tipo de documento']);
        if (!DB::table('gasto_categorias')->where('id', $d['categoria_id'])->where('IdEmpresa', $ruc)->exists()) {
            return response()->json(['success' => false, 'message' => 'Categoría no válida.']);
        }
        if ($id) {
            $g = DB::table('gastos')->where('id', $id)->where('IdEmpresa', $ruc)->first();
            abort_unless($g, 404);
            if ($g->estado !== 'Registrado') {
                return response()->json(['success' => false, 'message' => 'Un gasto anulado no se edita.']);
            }
        }
        try {
            Gastos::guardar(Auth::user(), $d + ['credito_fiscal' => $request->boolean('credito_fiscal')], $id);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
        return response()->json(['success' => true, 'mes' => substr($d['fecha'], 0, 7)]);
    }

    public function anular(int $id)
    {
        $ruc = $this->ruc();
        DB::table('gastos')->where('id', $id)->where('IdEmpresa', $ruc)->update(['estado' => 'Anulado', 'updated_at' => now()]);
        return back()->with('success', 'Gasto anulado.');
    }

    public function categoriaGuardar(Request $request, ?int $id = null)
    {
        $ruc = $this->ruc(true);
        $d = $request->validate(['nombre' => 'required|string|max:60', 'cuenta_contable' => 'nullable|string|max:12', 'color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/']);
        $datos = ['nombre' => mb_strtoupper(trim($d['nombre'])), 'cuenta_contable' => $d['cuenta_contable'] ?: null, 'color' => $d['color'] ?? '#6366f1', 'updated_at' => now()];
        if ($id) {
            DB::table('gasto_categorias')->where('id', $id)->where('IdEmpresa', $ruc)->update($datos);
        } else {
            DB::table('gasto_categorias')->insert($datos + ['IdEmpresa' => $ruc, 'created_at' => now()]);
        }
        return back()->with('success', 'Categoría guardada.');
    }

    /** Comprobantes de la propuesta RCE (SIRE) que faltan registrar */
    public function sire(Request $request)
    {
        $ruc = $this->ruc(true);
        try {
            $r = Gastos::pendientesSire($ruc, (int) $request->get('solicitud'));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
        return response()->json(['success' => true] + $r);
    }

    public function sireImportar(Request $request)
    {
        $ruc = $this->ruc(true);
        $request->validate(['solicitud' => 'required|integer', 'filas' => 'required|array|min:1', 'filas.*.clave' => 'required|string',
            'filas.*.categoria_id' => 'required|integer']);
        $pendientes = collect(Gastos::pendientesSire($ruc, (int) $request->solicitud)['filas'])->keyBy('clave');
        $categorias = DB::table('gasto_categorias')->where('IdEmpresa', $ruc)->pluck('id')->flip();
        $n = 0;
        $errores = [];
        foreach ($request->filas as $f) {
            $p = $pendientes[$f['clave']] ?? null;
            if (!$p || !isset($categorias[$f['categoria_id']])) {
                continue;
            }
            try {
                Gastos::guardar(Auth::user(), [
                    'fecha' => $p['fecha'] ?? now()->toDateString(), 'tdocod' => array_key_exists($p['tipo'], Gastos::DOCUMENTOS) ? $p['tipo'] : '00',
                    'serie' => $p['serie'], 'numero' => $p['numero'], 'prov_doc' => $p['prov_doc'], 'prov_nombre' => $p['prov_nombre'],
                    'categoria_id' => $f['categoria_id'], 'moneda' => $p['moneda'], 'tipo_cambio' => $p['tipo_cambio'],
                    'base' => $p['base'], 'igv' => $p['igv'], 'no_gravado' => $p['no_gravado'], 'credito_fiscal' => $p['tipo'] === '01' && $p['igv'] > 0,
                    'forma_pago' => 'CONTADO', 'descripcion' => 'IMPORTADO DEL SIRE (RCE)',
                ], null, 'SIRE');
                $n++;
            } catch (\RuntimeException $e) {
                $errores[] = "{$p['serie']}-{$p['numero']}: " . $e->getMessage();
            }
        }
        return response()->json(['success' => true, 'message' => "Se importaron {$n} comprobantes como gastos." . ($errores ? ' Omitidos: ' . implode(' | ', $errores) : '')]);
    }

    /** Reporte anual: categoría × mes */
    public function reporte(Request $request)
    {
        $ruc = $this->ruc();
        $anio = (int) $request->get('anio', now()->year);
        $s = "CASE WHEN g.tdocod='07' THEN -1 ELSE 1 END * CASE WHEN g.moneda='USD' THEN g.tipo_cambio ELSE 1 END";
        $datos = DB::table('gastos as g')->leftJoin('gasto_categorias as c', 'c.id', '=', 'g.categoria_id')
            ->where('g.IdEmpresa', $ruc)->where('g.estado', 'Registrado')->whereYear('g.fecha', $anio)
            ->groupBy('c.nombre', 'c.color', DB::raw('MONTH(g.fecha)'))
            ->select(DB::raw("COALESCE(c.nombre, 'SIN CATEGORÍA') as categoria"), 'c.color', DB::raw('MONTH(g.fecha) as mes'), DB::raw("SUM($s * g.total) as total"))->get();
        $matriz = [];
        foreach ($datos as $d) {
            $matriz[$d->categoria]['color'] = $d->color ?? '#9ca3af';
            $matriz[$d->categoria]['meses'][$d->mes] = round((float) $d->total, 2);
        }
        uasort($matriz, fn($a, $b) => array_sum($b['meses']) <=> array_sum($a['meses']));
        return view('empresas.gastos.reporte', compact('matriz', 'anio'));
    }
}
