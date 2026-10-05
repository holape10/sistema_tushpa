<?php
namespace App\Http\Controllers;

use App\Support\{Excel, Planilla};
use App\Support\Contabilidad\Contabilidad;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class PlanillaController extends Controller
{
    private function ruc(): string
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador gestiona la planilla.');
        return (string) Auth::user()->IdEmpresa;
    }

    // ================================================================== TRABAJADORES

    public function trabajadores()
    {
        $ruc = $this->ruc();
        $empleados = DB::table('empleado as e')
            ->join('empresa_negocios as n', 'n.id_empresa_negocio', '=', 'e.id_empresa_negocio')
            ->leftJoin('planilla_trabajadores as t', 't.emp_id', '=', 'e.emp_id')
            ->where('n.IdEmpresa', $ruc)->where('e.emp_num_doc', '!=', $ruc)
            ->orderByRaw('t.activo IS NULL, t.activo DESC')->orderBy('e.emp_nom')
            ->select('e.emp_id', 'e.emp_nom', 'e.emp_ape_pat', 'e.emp_ape_mat', 'e.emp_num_doc', 'n.nombre_comercial as sucursal', 't.*', 'e.emp_id as id')->get();
        $p = Planilla::parametros($ruc, now()->year);
        return view('empresas.planilla.trabajadores', ['empleados' => $empleados, 'afps' => array_keys($p->comisiones), 'rmv' => $p->rmv]);
    }

    public function trabajadorGuardar(Request $request, int $empId)
    {
        $ruc = $this->ruc();
        abort_unless(DB::table('empleado as e')->join('empresa_negocios as n', 'n.id_empresa_negocio', '=', 'e.id_empresa_negocio')
            ->where('e.emp_id', $empId)->where('n.IdEmpresa', $ruc)->exists(), 404);
        $d = $request->validate([
            'cargo' => 'nullable|string|max:100', 'fecha_ingreso' => 'nullable|date', 'fecha_cese' => 'nullable|date|after_or_equal:fecha_ingreso',
            'sueldo' => 'required|numeric|min:0|max:999999', 'asignacion_familiar' => 'boolean', 'sistema_pension' => 'required|in:ONP,AFP,NINGUNO',
            'afp' => 'nullable|required_if:sistema_pension,AFP|string|max:20', 'afp_comision' => 'nullable|in:FLUJO,MIXTA', 'cuspp' => 'nullable|string|max:15',
            'banco' => 'nullable|string|max:30', 'cuenta' => 'nullable|string|max:30', 'activo' => 'boolean',
        ], ['afp.required_if' => 'Elige la AFP del trabajador.']);
        DB::table('planilla_trabajadores')->updateOrInsert(['emp_id' => $empId], [
            'IdEmpresa' => $ruc, 'cargo' => mb_strtoupper(trim((string) ($d['cargo'] ?? ''))) ?: null,
            'fecha_ingreso' => $d['fecha_ingreso'] ?? null, 'fecha_cese' => $d['fecha_cese'] ?? null, 'sueldo' => $d['sueldo'],
            'asignacion_familiar' => $request->boolean('asignacion_familiar'), 'sistema_pension' => $d['sistema_pension'],
            'afp' => $d['sistema_pension'] === 'AFP' ? $d['afp'] : null, 'afp_comision' => $d['afp_comision'] ?? 'FLUJO',
            'cuspp' => $d['cuspp'] ?? null, 'banco' => $d['banco'] ?? null, 'cuenta' => $d['cuenta'] ?? null,
            'activo' => $request->boolean('activo', true), 'updated_at' => now(), 'created_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }

    // ================================================================== PARÁMETROS

    public function parametros(Request $request)
    {
        $ruc = $this->ruc();
        $anio = (int) $request->get('anio', now()->year);
        return view('empresas.planilla.parametros', ['p' => Planilla::parametros($ruc, $anio), 'anio' => $anio]);
    }

    public function parametrosGuardar(Request $request)
    {
        $ruc = $this->ruc();
        $d = $request->validate([
            'anio' => 'required|integer|min:2020|max:2100', 'regimen_laboral' => 'required|in:GENERAL,PEQUENA,MICRO',
            'rmv' => 'required|numeric|min:0', 'uit' => 'required|numeric|min:0', 'essalud' => 'required|numeric|min:0|max:100',
            'onp' => 'required|numeric|min:0|max:100', 'afp_aporte' => 'required|numeric|min:0|max:100', 'afp_prima' => 'required|numeric|min:0|max:100',
            'afp_tope_prima' => 'required|numeric|min:0', 'comisiones' => 'required|array', 'comisiones.*' => 'required|numeric|min:0|max:100',
        ]);
        Planilla::parametros($ruc, (int) $d['anio']);
        DB::table('planilla_parametros')->where('IdEmpresa', $ruc)->where('anio', $d['anio'])->update(
            collect($d)->except(['anio', 'comisiones'])->all() + ['afp_comisiones' => json_encode(array_map('floatval', $d['comisiones'])),
                'calcular_quinta' => $request->boolean('calcular_quinta'), 'updated_at' => now()]);
        return back()->with('success', "Parámetros de {$d['anio']} guardados.");
    }

    // ================================================================== PLANILLAS

    public function index(Request $request)
    {
        $ruc = $this->ruc();
        $periodo = preg_replace('/\D/', '', (string) $request->get('periodo', now()->format('Ym')));
        $planilla = DB::table('planillas')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->first();
        $detalle = $planilla ? DB::table('planilla_detalle')->where('planilla_id', $planilla->id)->orderBy('nombre')->get() : collect();

        if ($planilla && $request->get('excel')) {
            $filas = $detalle->map(fn($d) => [$d->nombre, (string) $d->dni, $d->cargo, $d->sistema_pension, $d->dias, (float) $d->faltas, $d->tardanza_min,
                (float) $d->sueldo, (float) $d->asig_familiar, (float) $d->horas_extra, (float) $d->bonos, (float) $d->total_ingresos,
                (float) $d->desc_faltas + (float) $d->desc_tardanza, (float) $d->onp, (float) $d->afp_aporte + (float) $d->afp_prima + (float) $d->afp_comision,
                (float) $d->renta_quinta, (float) $d->adelantos + (float) $d->otros_descuentos, (float) $d->total_descuentos, (float) $d->neto, (float) $d->essalud])->all();
            $ruta = (new Excel())->hoja('Planilla ' . $periodo, ['Trabajador', 'DNI', 'Cargo', 'Pensión', 'Días', 'Faltas', 'Tardanza (min)', 'Sueldo',
                'Asig. familiar', 'Horas extra', 'Bonos', 'Total ingresos', 'Desc. faltas/tard.', 'ONP', 'AFP', 'Renta 5ta', 'Adelantos/otros',
                'Total descuentos', 'Neto a pagar', 'EsSalud'], $filas)->guardar();
            return response()->download($ruta, "planilla_{$periodo}.xlsx")->deleteFileAfterSend();
        }

        return view('empresas.planilla.index', [
            'periodo' => $periodo, 'planilla' => $planilla, 'detalle' => $detalle,
            'historial' => DB::table('planillas')->where('IdEmpresa', $ruc)->orderByDesc('periodo')->limit(12)->get(),
            'sinDatos' => DB::table('planilla_trabajadores')->where('IdEmpresa', $ruc)->where('activo', 1)->count(),
        ]);
    }

    public function generar(Request $request)
    {
        $ruc = $this->ruc();
        $periodo = preg_replace('/\D/', '', (string) $request->periodo);
        try {
            Planilla::generar($ruc, $periodo);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['planilla' => $e->getMessage()]);
        }
        return redirect()->route('planilla.index', ['periodo' => $periodo])->with('success', 'Planilla generada con la asistencia del mes. Revisa y ajusta antes de cerrarla.');
    }

    public function fila(Request $request, int $id)
    {
        $ruc = $this->ruc();
        $d = DB::table('planilla_detalle as d')->join('planillas as p', 'p.id', '=', 'd.planilla_id')->where('d.id', $id)->where('p.IdEmpresa', $ruc)->select('d.*')->first();
        abort_unless($d, 404);
        $f = $request->validate(['dias' => 'required|integer|min:0|max:30', 'faltas' => 'required|numeric|min:0|max:30', 'tardanza_min' => 'required|integer|min:0',
            'he25' => 'required|numeric|min:0', 'he35' => 'required|numeric|min:0', 'bonos' => 'required|numeric|min:0',
            'adelantos' => 'required|numeric|min:0', 'otros_descuentos' => 'required|numeric|min:0']);
        try {
            Planilla::actualizarFila($id, $f);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
        return response()->json(['success' => true, 'fila' => DB::table('planilla_detalle')->where('id', $id)->first(),
            'planilla' => DB::table('planillas')->where('id', $d->planilla_id)->first()]);
    }

    public function quitar(int $id)
    {
        $ruc = $this->ruc();
        $d = DB::table('planilla_detalle as d')->join('planillas as p', 'p.id', '=', 'd.planilla_id')->where('d.id', $id)->where('p.IdEmpresa', $ruc)
            ->where('p.estado', 'BORRADOR')->select('d.*')->first();
        abort_unless($d, 404);
        DB::table('planilla_detalle')->where('id', $id)->delete();
        Planilla::totales($d->planilla_id);
        return back()->with('success', 'Trabajador quitado de esta planilla.');
    }

    public function cerrar(Request $request, int $id)
    {
        $ruc = $this->ruc();
        $request->validate(['fecha_pago' => 'required|date']);
        try {
            Planilla::cerrar($ruc, $id, $request->fecha_pago);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['planilla' => $e->getMessage()]);
        }
        return back()->with('success', 'Planilla cerrada' . (DB::table('planillas')->where('id', $id)->value('asiento_id') ? ' y registrada en el Libro diario.' : '.'));
    }

    public function reabrir(int $id)
    {
        $ruc = $this->ruc();
        try {
            Planilla::reabrir($ruc, $id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['planilla' => $e->getMessage()]);
        }
        return back()->with('success', 'Planilla abierta para corregirla (se quitó su asiento contable).');
    }

    /** Boletas de pago (todas o una) listas para imprimir */
    public function boletas(Request $request, int $id)
    {
        $ruc = $this->ruc();
        $pl = DB::table('planillas')->where('id', $id)->where('IdEmpresa', $ruc)->first();
        abort_unless($pl, 404);
        $detalle = DB::table('planilla_detalle')->where('planilla_id', $id)->when($request->filled('emp'), fn($w) => $w->where('emp_id', $request->emp))->orderBy('nombre')->get();
        $trab = DB::table('planilla_trabajadores')->whereIn('emp_id', $detalle->pluck('emp_id'))->get()->keyBy('emp_id');
        $empresa = DB::table('empresa')->where('IdEmpresa', $ruc)->first();
        $p = Planilla::parametros($ruc, (int) substr($pl->periodo, 0, 4));
        return view('empresas.planilla.boletas', ['pl' => $pl, 'detalle' => $detalle, 'trab' => $trab, 'empresa' => $empresa, 'p' => $p,
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first(),
            'nombrePeriodo' => Contabilidad::nombrePeriodo($pl->periodo)]);
    }
}
