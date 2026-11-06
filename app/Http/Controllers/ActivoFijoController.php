<?php

namespace App\Http\Controllers;

use App\Support\ActivosFijos;
use App\Support\Bitacora;
use App\Support\CentrosCosto;
use App\Support\Contabilidad\Contabilidad;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Activos fijos: registro, depreciación mensual con su asiento, baja y cuadro de activos (administrador o con la opción) */
class ActivoFijoController extends Controller
{
    public function index(Request $request): View
    {
        $ruc = $this->autorizar();
        $fecha = $this->fecha($request);
        $ultimo = DB::table('activos_depreciacion')->where('IdEmpresa', $ruc)->max('periodo');

        return view('empresas.activos.index', [
            'fecha' => $fecha, 'activos' => ActivosFijos::cuadro($ruc, $fecha), 'ultimo' => $ultimo,
            'sugerido' => $ultimo ? Carbon::createFromFormat('!Ym', (string) $ultimo)->addMonth()->format('Y-m') : now()->format('Y-m'),
            'categorias' => ActivosFijos::CATEGORIAS,
        ]);
    }

    public function create(): View
    {
        $this->autorizar();

        return view('empresas.activos.form', $this->datos(null));
    }

    public function edit(int $id): View
    {
        $ruc = $this->autorizar();

        return view('empresas.activos.form', $this->datos(ActivosFijos::activo($ruc, $id)));
    }

    public function show(int $id): View
    {
        $ruc = $this->autorizar();
        $a = ActivosFijos::activo($ruc, $id);

        return view('empresas.activos.show', [
            'a' => $a, 'acumulado' => ActivosFijos::acumulado($a), 'cuota' => ActivosFijos::cuotaMensual($a),
            'meses' => DB::table('activos_depreciacion')->where('activo_id', $id)->orderBy('periodo')->get(),
            'centro' => $a->centro_costo_id ? DB::table('centros_costo')->where('id', $a->centro_costo_id)->first() : null,
            'categorias' => ActivosFijos::CATEGORIAS,
        ]);
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'codigo' => 'required|string|max:20', 'descripcion' => 'required|string|max:200', 'categoria' => 'required|in:'.implode(',', array_keys(ActivosFijos::CATEGORIAS)),
            'marca_modelo' => 'nullable|string|max:100', 'serie' => 'nullable|string|max:60', 'ubicacion' => 'nullable|string|max:100', 'responsable' => 'nullable|string|max:100',
            'documento' => 'nullable|string|max:60', 'fecha_adquisicion' => 'required|date|before_or_equal:today', 'fecha_uso' => 'required|date',
            'costo' => 'required|numeric|min:0.01|max:999999999', 'valor_residual' => 'nullable|numeric|min:0', 'tasa' => 'required|numeric|min:0|max:100',
            'depreciacion_inicial' => 'nullable|numeric|min:0', 'cuenta_activo' => 'nullable|string|max:12', 'cuenta_depreciacion' => 'nullable|string|max:12',
            'cuenta_gasto' => 'nullable|string|max:12', 'centro_costo_id' => 'nullable|integer', 'contrapartida_alta' => 'nullable|string|max:12',
        ], [], ['fecha_uso' => 'fecha en que se empezó a usar', 'fecha_adquisicion' => 'fecha de compra']);
        try {
            $id = ActivosFijos::guardar(Auth::user(), $id ? array_diff_key($d, ['contrapartida_alta' => 1]) : $d, $id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        Bitacora::registrar('contabilidad.activo', 'Guardó el activo fijo '.mb_strtoupper($d['codigo']).' '.mb_strtoupper($d['descripcion']).' (S/ '.number_format((float) $d['costo'], 2).')',
            null, ['referencia' => ['activo_fijo', $id]]);

        return redirect()->route('activos.show', $id)->with('success', 'Activo guardado.');
    }

    public function depreciar(Request $request): RedirectResponse
    {
        $this->autorizar();
        $mes = (string) $request->validate(['mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']], ['mes.regex' => 'Elige el mes.'])['mes'];
        $periodo = str_replace('-', '', $mes);
        try {
            $r = ActivosFijos::depreciar(Auth::user(), $periodo);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('contabilidad.depreciacion', 'Procesó la depreciación de '.Contabilidad::nombrePeriodo($periodo).": {$r['activos']} activo(s) por S/ ".number_format($r['total'], 2));

        return back()->with('success', $r['activos'] ? 'Depreciación de '.Contabilidad::nombrePeriodo($periodo).": {$r['activos']} activo(s), S/ ".number_format($r['total'], 2).' con su asiento contable.'
            : 'En '.Contabilidad::nombrePeriodo($periodo).' no hay nada que depreciar.');
    }

    public function deshacer(Request $request): RedirectResponse
    {
        $this->autorizar();
        $periodo = (string) $request->validate(['periodo' => 'required|digits:6'])['periodo'];
        try {
            ActivosFijos::deshacerMes(Auth::user(), $periodo);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('contabilidad.depreciacion', 'Deshizo la depreciación de '.Contabilidad::nombrePeriodo($periodo).' (y su asiento)', null, ['alerta' => true]);

        return back()->with('success', 'Se deshizo la depreciación de '.Contabilidad::nombrePeriodo($periodo).'.');
    }

    public function baja(Request $request, int $id): RedirectResponse
    {
        $ruc = $this->autorizar();
        $d = $request->validate(['fecha' => 'required|date|before_or_equal:today', 'motivo' => 'required|string|min:4|max:255'], [], ['fecha' => 'fecha de baja']);
        $a = ActivosFijos::activo($ruc, $id);
        try {
            ActivosFijos::darDeBaja(Auth::user(), $id, $d['fecha'], $d['motivo']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('contabilidad.activo_baja', "Dio de baja el activo {$a->codigo} {$a->descripcion}: {$d['motivo']}", null,
            ['referencia' => ['activo_fijo', $id], 'alerta' => true]);

        return back()->with('success', 'Activo dado de baja con su asiento contable.');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $ruc = $this->autorizar();
        $fecha = $this->fecha($request);
        $filas = ActivosFijos::cuadro($ruc, $fecha)->map(fn ($a) => [$a->codigo, $a->descripcion, ActivosFijos::CATEGORIAS[$a->categoria][0] ?? $a->categoria,
            $a->marca_modelo, $a->serie, Carbon::parse($a->fecha_adquisicion)->format('d/m/Y'), Carbon::parse($a->fecha_uso)->format('d/m/Y'),
            (float) $a->costo, (float) $a->tasa, $a->del_ejercicio, $a->acumulada, $a->neto, $a->cuenta_activo, $a->centro_codigo,
            $a->de_baja ? 'BAJA '.Carbon::parse($a->fecha_baja)->format('d/m/Y') : 'ACTIVO'])->all();
        $ruta = (new Excel)->hoja('Activos fijos', ['Código', 'Descripción', 'Categoría', 'Marca / modelo', 'Serie', 'Compra', 'Uso desde', 'Costo', 'Tasa %',
            'Depreciación del ejercicio', 'Depreciación acumulada', 'Valor neto', 'Cuenta', 'Centro de costo', 'Estado'], $filas)->guardar();

        return response()->download($ruta, "activos_fijos_al_{$fecha}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/activos-fijos'), 403, 'No tienes permiso para los activos fijos.');

        return (string) Auth::user()->IdEmpresa;
    }

    private function fecha(Request $request): string
    {
        try {
            return Carbon::parse((string) $request->query('fecha', now()->toDateString()))->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function datos(?object $a): array
    {
        $ruc = (string) Auth::user()->IdEmpresa;
        Contabilidad::asegurar($ruc);
        $imp = Contabilidad::imputables($ruc);

        return [
            'a' => $a, 'categorias' => ActivosFijos::CATEGORIAS, 'centros' => CentrosCosto::lista($ruc, true),
            'procesado' => $a && DB::table('activos_depreciacion')->where('activo_id', $a->id)->exists(),
            'cuentas' => [
                'activo' => $imp->filter(fn ($n, $c) => str_starts_with((string) $c, '33')),
                'depreciacion' => $imp->filter(fn ($n, $c) => str_starts_with((string) $c, '39')),
                'gasto' => $imp->filter(fn ($n, $c) => str_starts_with((string) $c, '68')),
                'pago' => $imp->filter(fn ($n, $c) => preg_match('/^(10|42|46)/', (string) $c)),
            ],
        ];
    }
}
