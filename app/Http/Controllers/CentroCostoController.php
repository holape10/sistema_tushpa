<?php

namespace App\Http\Controllers;

use App\Support\Bitacora;
use App\Support\CentrosCosto;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Centros de costo y el resultado de cada uno (el administrador o quien tenga la opción en su menú) */
class CentroCostoController extends Controller
{
    public function index(Request $request): View
    {
        $ruc = $this->autorizar();
        $editar = $request->query('editar') ? DB::table('centros_costo')->where('id', (int) $request->query('editar'))->where('IdEmpresa', $ruc)->first() : null;

        return view('empresas.centros_costo.index', [
            'centros' => CentrosCosto::lista($ruc), 'editar' => $editar,
            'sucursales' => DB::table('empresa_negocios')->where('IdEmpresa', $ruc)->pluck('nombre_comercial', 'id_empresa_negocio'),
            'gastosPorCentro' => DB::table('gastos')->where('IdEmpresa', $ruc)->where('estado', 'Registrado')->whereNotNull('centro_costo_id')
                ->groupBy('centro_costo_id')->selectRaw('centro_costo_id, COUNT(*) as n')->pluck('n', 'centro_costo_id'),
        ]);
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $ruc = $this->autorizar();
        $d = $request->validate(['codigo' => 'required|string|max:10|regex:/^[A-Za-z0-9-]+$/', 'nombre' => 'required|string|max:100',
            'tipo' => 'required|in:'.implode(',', array_keys(CentrosCosto::TIPOS)), 'id_empresa_negocio' => 'nullable|required_if:tipo,LOCAL|integer',
            'cuenta_destino' => 'required|in:'.implode(',', array_keys(CentrosCosto::DESTINOS)), 'activo' => 'nullable|boolean'],
            ['codigo.regex' => 'El código va con letras, números o guiones.', 'id_empresa_negocio.required_if' => 'Elige el local.']);
        try {
            $id = CentrosCosto::guardar($ruc, $d + ['activo' => $request->boolean('activo', true)], $id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        Bitacora::registrar('config.centro_costo', 'Guardó el centro de costo '.mb_strtoupper($d['codigo']).' '.mb_strtoupper($d['nombre']), null, ['referencia' => ['centro_costo', $id]]);

        return redirect()->route('centros_costo.index')->with('success', 'Centro de costo guardado.');
    }

    public function crearLocales(): RedirectResponse
    {
        $ruc = $this->autorizar();
        $n = CentrosCosto::crearDeLocales($ruc);

        return back()->with('success', $n ? "Se creó un centro de costo para {$n} local(es)." : 'Todos tus locales ya tienen su centro de costo.');
    }

    public function reporte(Request $request): View
    {
        $ruc = $this->autorizar();
        [$desde, $hasta] = $this->rango($request);

        return view('empresas.centros_costo.reporte', ['desde' => $desde, 'hasta' => $hasta, 'filas' => CentrosCosto::resultados($ruc, $desde, $hasta)]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $ruc = $this->autorizar();
        [$desde, $hasta] = $this->rango($request);
        $filas = CentrosCosto::resultados($ruc, $desde, $hasta);
        $ruta = (new Excel)
            ->hoja('Resultado por centro', ['Código', 'Centro de costo', 'Tipo', 'Ventas (sin IGV)', 'Costo de ventas', 'Gastos', 'Depreciación', 'Resultado', 'Margen %'],
                $filas->map(fn ($f) => [$f->codigo, $f->nombre, CentrosCosto::TIPOS[$f->tipo] ?? '', $f->ventas, $f->costo, $f->gastos, $f->depreciacion, $f->resultado, $f->margen ?? ''])->all())
            ->hoja('Gastos por categoría', ['Centro de costo', 'Categoría', 'Monto'],
                $filas->flatMap(fn ($f) => collect($f->porCategoria)->map(fn ($m, $k) => [$f->codigo.' '.$f->nombre, $k, $m])->values())->all())
            ->guardar();

        return response()->download($ruta, "centros_de_costo_{$desde}_al_{$hasta}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/centros-costo'), 403, 'No tienes permiso para los centros de costo.');

        return (string) Auth::user()->IdEmpresa;
    }

    /** @return array{0: string, 1: string} del mes actual por defecto; máximo un año */
    private function rango(Request $request): array
    {
        try {
            $desde = Carbon::parse((string) $request->query('desde', now()->startOfMonth()->toDateString()))->startOfDay();
            $hasta = Carbon::parse((string) $request->query('hasta', now()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            [$desde, $hasta] = [now()->startOfMonth(), now()->startOfDay()];
        }
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        if ($desde->lt($hasta->copy()->subYear())) {
            $desde = $hasta->copy()->subYear();
        }

        return [$desde->toDateString(), $hasta->toDateString()];
    }
}
