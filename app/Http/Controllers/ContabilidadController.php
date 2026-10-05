<?php
namespace App\Http\Controllers;

use App\Support\Contabilidad\{Contabilidad, Pcge};
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class ContabilidadController extends Controller
{
    private function ruc(): string
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador accede a la contabilidad.');
        $ruc = (string) Auth::user()->IdEmpresa;
        Contabilidad::asegurar($ruc);
        return $ruc;
    }

    private function periodo(Request $request): string
    {
        $p = preg_replace('/\D/', '', (string) $request->get('periodo', now()->format('Ym')));
        return strlen($p) === 6 ? $p : now()->format('Ym');
    }

    private function descargar(Excel $x, string $nombre)
    {
        return response()->download($x->guardar(), $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    private function nombres(string $ruc)
    {
        return DB::table('conta_plan')->where('IdEmpresa', $ruc)->pluck('descripcion', 'cuenta');
    }

    // ================================================================== PLAN CONTABLE

    public function plan(Request $request)
    {
        $ruc = $this->ruc();
        $q = trim((string) $request->get('q'));
        $cuentas = DB::table('conta_plan')->where('IdEmpresa', $ruc)
            ->when($request->filled('elemento'), fn($w) => $w->where('cuenta', 'like', $request->elemento . '%'))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('cuenta', 'like', "$q%")->orWhere('descripcion', 'like', "%$q%")))
            ->orderBy('cuenta')->get();
        $usadas = DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.IdEmpresa', $ruc)->distinct()->pluck('d.cuenta')->flip();

        return view('empresas.contabilidad.plan', [
            'cuentas' => $cuentas, 'usadas' => $usadas, 'q' => $q,
            'config' => Contabilidad::config($ruc), 'imputables' => Contabilidad::imputables($ruc),
            'medios' => DB::table('medios_pagos')->where('IdEmpresa', $ruc)->orWhereIn('id_empresa_negocio',
                DB::table('empresa_negocios')->where('IdEmpresa', $ruc)->pluck('id_empresa_negocio'))->orderBy('nom_med_pag')->get(),
            'elementos' => ['1' => 'Activo disponible y exigible', '2' => 'Activo realizable', '3' => 'Activo inmovilizado', '4' => 'Pasivo',
                '5' => 'Patrimonio', '6' => 'Gastos', '7' => 'Ingresos', '8' => 'Saldos intermediarios', '9' => 'Contabilidad analítica'],
        ]);
    }

    public function cuentaGuardar(Request $request)
    {
        $ruc = $this->ruc();
        $d = $request->validate([
            'cuenta' => 'required|regex:/^\d{2,12}$/', 'descripcion' => 'required|string|max:200', 'estado' => 'nullable|in:Activo,Inactivo',
        ], ['cuenta.regex' => 'La cuenta solo lleva números (2 a 12 dígitos).']);
        $cuenta = $d['cuenta'];
        $existe = DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('cuenta', $cuenta)->first();

        if ($existe) {
            DB::table('conta_plan')->where('id', $existe->id)->update(['descripcion' => mb_strtoupper(trim($d['descripcion'])),
                'estado' => $d['estado'] ?? $existe->estado, 'updated_at' => now()]);
            return back()->with('success', "Cuenta {$cuenta} actualizada.");
        }

        // La cuenta padre debe existir (ej. 104103 necesita a 1041 o 104)
        $padre = DB::table('conta_plan')->where('IdEmpresa', $ruc)->whereIn('cuenta', array_map(fn($i) => substr($cuenta, 0, $i), range(2, strlen($cuenta) - 1)))
            ->orderByDesc(DB::raw('LENGTH(cuenta)'))->first();
        if (!$padre && strlen($cuenta) > 2) {
            return back()->withInput()->withErrors(['cuenta' => "No existe una cuenta superior para {$cuenta} (ej. " . substr($cuenta, 0, 2) . ').']);
        }
        if ($padre && DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
                ->where('a.IdEmpresa', $ruc)->where('d.cuenta', $padre->cuenta)->exists()) {
            return back()->withInput()->withErrors(['cuenta' => "La cuenta {$padre->cuenta} ya tiene movimientos; no puede pasar a ser cuenta de título."]);
        }

        [$tipo, $nat] = Pcge::tipo($cuenta);
        DB::transaction(function () use ($ruc, $cuenta, $d, $tipo, $nat, $padre) {
            DB::table('conta_plan')->insert(['IdEmpresa' => $ruc, 'cuenta' => $cuenta, 'descripcion' => mb_strtoupper(trim($d['descripcion'])),
                'nivel' => strlen($cuenta), 'tipo' => $tipo, 'naturaleza' => $nat, 'imputable' => 1, 'estado' => 'Activo',
                'created_at' => now(), 'updated_at' => now()]);
            if ($padre) {
                DB::table('conta_plan')->where('id', $padre->id)->update(['imputable' => 0]);
            }
        });
        return back()->with('success', "Cuenta {$cuenta} creada.");
    }

    public function cuentaEliminar(int $id)
    {
        $ruc = $this->ruc();
        $c = DB::table('conta_plan')->where('id', $id)->where('IdEmpresa', $ruc)->first();
        abort_unless($c, 404);
        $hijas = DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('cuenta', 'like', $c->cuenta . '%')->where('id', '!=', $id)->exists();
        $usada = DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.IdEmpresa', $ruc)->where('d.cuenta', $c->cuenta)->exists();
        $config = collect((array) Contabilidad::config($ruc))->contains($c->cuenta);
        if ($hijas || $usada || $config) {
            return back()->withErrors(['cuenta' => "La cuenta {$c->cuenta} " . ($hijas ? 'tiene subcuentas' : ($usada ? 'tiene movimientos' : 'se usa en la centralización')) . '; desactívala en lugar de eliminarla.']);
        }
        DB::transaction(function () use ($ruc, $c) {
            DB::table('conta_plan')->where('id', $c->id)->delete();
            // Si el padre se quedó sin hijas, vuelve a ser imputable
            for ($i = strlen($c->cuenta) - 1; $i >= 2; $i--) {
                $padre = DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('cuenta', substr($c->cuenta, 0, $i))->first();
                if ($padre) {
                    $quedan = DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('cuenta', 'like', $padre->cuenta . '%')->where('cuenta', '!=', $padre->cuenta)->exists();
                    DB::table('conta_plan')->where('id', $padre->id)->update(['imputable' => $quedan ? 0 : 1]);
                    break;
                }
            }
        });
        return back()->with('success', "Cuenta {$c->cuenta} eliminada.");
    }

    public function configGuardar(Request $request)
    {
        $ruc = $this->ruc();
        $campos = ['cta_caja', 'cta_banco', 'cta_por_cobrar', 'cta_igv', 'cta_ventas', 'cta_ventas_exo', 'cta_por_pagar', 'cta_compras',
            'cta_mercaderias', 'cta_variacion', 'cta_costo_ventas', 'cta_sueldos', 'cta_essalud_gasto', 'cta_remun_pagar', 'cta_essalud_pagar',
            'cta_onp_pagar', 'cta_afp_pagar', 'cta_renta5_pagar', 'cta_renta4_pagar', 'cta_adelantos'];
        $d = $request->validate(array_fill_keys($campos, 'required|string|max:12') + ['medios' => 'nullable|array']);
        $imp = Contabilidad::imputables($ruc);
        foreach ($campos as $c) {
            if (!isset($imp[$d[$c]])) {
                return back()->withErrors(['config' => "La cuenta {$d[$c]} no existe o no es imputable (tiene subcuentas)."])->withInput();
            }
        }
        foreach ((array) $request->medios as $id => $cuenta) {
            if ($cuenta && !isset($imp[$cuenta])) {
                return back()->withErrors(['config' => "La cuenta {$cuenta} de un medio de pago no existe o no es imputable."])->withInput();
            }
        }
        DB::transaction(function () use ($ruc, $d, $campos, $request) {
            DB::table('conta_config')->where('IdEmpresa', $ruc)->update(array_intersect_key($d, array_flip($campos)) + [
                'asiento_costo' => $request->boolean('asiento_costo'), 'asiento_destino' => $request->boolean('asiento_destino'), 'updated_at' => now()]);
            foreach ((array) $request->medios as $id => $cuenta) {
                DB::table('medios_pagos')->where('id_med_pag', $id)->update(['cuenta_contable' => $cuenta ?: null]);
            }
        });
        return redirect()->route('contabilidad.plan', ['tab' => 'config'])->with('success', 'Cuentas de centralización guardadas.');
    }

    public function planExcel()
    {
        $ruc = $this->ruc();
        $filas = DB::table('conta_plan')->where('IdEmpresa', $ruc)->orderBy('cuenta')->get()
            ->map(fn($c) => [(string) $c->cuenta, $c->descripcion, $c->nivel, $c->tipo, $c->naturaleza === 'D' ? 'DEUDORA' : 'ACREEDORA', $c->imputable ? 'SÍ' : 'NO', $c->estado])->all();
        return $this->descargar((new Excel())->hoja('Plan contable', ['Cuenta', 'Descripción', 'Nivel', 'Tipo', 'Naturaleza', 'Imputable', 'Estado'], $filas), 'plan_contable.xlsx');
    }

    // ================================================================== CENTRALIZACIÓN

    public function centralizar(Request $request, string $tipo)
    {
        $ruc = $this->ruc();
        $periodo = $this->periodo($request);
        $origenes = $tipo === 'ventas' ? ['VENTAS', 'COBRANZAS'] : ['COMPRAS', 'PAGOS'];
        [$desde, $hasta] = [Carbon::createFromFormat('Ym', $periodo)->startOfMonth()->toDateString(), Carbon::createFromFormat('Ym', $periodo)->endOfMonth()->toDateString()];

        $asientos = DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->whereIn('origen', $origenes)
            ->orderBy('fecha')->orderBy('subdiario')->orderBy('numero')->get();
        $detalle = DB::table('conta_asiento_detalle')->whereIn('asiento_id', $asientos->pluck('id'))->orderBy('id')->get()->groupBy('asiento_id');

        // Lo que hay para centralizar en el periodo
        $pendiente = $tipo === 'ventas'
            ? DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->whereIn('tdocod', ['01', '03', '07', '08'])->whereNull('ccabaj')
                ->whereBetween('ccafem', [$desde, $hasta])->selectRaw("COUNT(*) as n, SUM(CASE WHEN tdocod='07' THEN -ccaitv ELSE ccaitv END) as total, MAX(fecha_hora) as ultimo")->first()
            : DB::table('compras_cabecera')->where('IdEmpresa', $ruc)->where('est_compra', 'Registrado')->whereBetween('com_fec', [$desde, $hasta])
                ->selectRaw("COUNT(*) as n, SUM(CASE WHEN mon_id='USD' THEN total_com*tip_cam ELSE total_com END) as total, MAX(updated_at) as ultimo")->first();

        return view('empresas.contabilidad.centralizar', [
            'tipo' => $tipo, 'periodo' => $periodo, 'asientos' => $asientos, 'detalle' => $detalle, 'pendiente' => $pendiente,
            'cerrado' => Contabilidad::cerrado($ruc, $periodo), 'nombres' => $this->nombres($ruc),
            'ultimaVez' => $asientos->max('created_at'),
        ]);
    }

    public function centralizarEjecutar(Request $request, string $tipo)
    {
        $ruc = $this->ruc();
        $periodo = $this->periodo($request);
        try {
            $r = $tipo === 'ventas' ? Contabilidad::centralizarVentas($ruc, $periodo) : Contabilidad::centralizarCompras($ruc, $periodo);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['centralizar' => $e->getMessage()]);
        }
        $msg = $tipo === 'ventas'
            ? "Ventas centralizadas: {$r['documentos']} comprobantes en {$r['asientos']} asientos. Ventas S/ " . number_format($r['ventas'], 2)
                . ', IGV S/ ' . number_format($r['igv'], 2) . ', costo S/ ' . number_format($r['costo'], 2) . ', cobranzas S/ ' . number_format($r['cobranzas'], 2) . '.'
            : "Compras centralizadas: {$r['documentos']} documentos en {$r['asientos']} asientos. Compras S/ " . number_format($r['compras'], 2)
                . ', gastos S/ ' . number_format($r['gastos'] ?? 0, 2)
                . ', IGV S/ ' . number_format($r['igv'], 2) . ', pagos S/ ' . number_format($r['pagos'], 2) . '.';
        return redirect()->route('contabilidad.centralizar', ['tipo' => $tipo, 'periodo' => $periodo])->with('success', $msg);
    }

    // ================================================================== LIBRO DIARIO

    public function diario(Request $request)
    {
        $ruc = $this->ruc();
        $periodo = $this->periodo($request);
        $q = trim((string) $request->get('q'));

        $asientos = DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)
            ->when($request->filled('origen'), fn($w) => $w->where('origen', $request->origen))
            ->when($request->filled('subdiario'), fn($w) => $w->where('subdiario', $request->subdiario))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('glosa', 'like', "%$q%")->orWhere('documento', 'like', "%$q%")
                ->orWhereExists(fn($s) => $s->select(DB::raw(1))->from('conta_asiento_detalle as d')->whereColumn('d.asiento_id', 'conta_asientos.id')->where('d.cuenta', 'like', "$q%"))))
            ->orderBy('fecha')->orderBy('subdiario')->orderBy('numero');

        if ($request->get('excel')) {
            $nombres = $this->nombres($ruc);
            $lista = $asientos->get();
            $det = DB::table('conta_asiento_detalle')->whereIn('asiento_id', $lista->pluck('id'))->orderBy('id')->get()->groupBy('asiento_id');
            $filas = [];
            foreach ($lista as $a) {
                foreach ($det[$a->id] ?? [] as $d) {
                    $filas[] = [$a->subdiario . '-' . str_pad($a->numero, 4, '0', STR_PAD_LEFT), Carbon::parse($a->fecha)->format('d/m/Y'), $a->glosa,
                        (string) $d->cuenta, $nombres[$d->cuenta] ?? '', $d->documento ?? '', $d->anexo_doc ?? '', (float) $d->debe, (float) $d->haber];
                }
            }
            return $this->descargar((new Excel())->hoja('Libro Diario ' . $periodo,
                ['Asiento', 'Fecha', 'Glosa', 'Cuenta', 'Denominación', 'Documento', 'RUC/DNI', 'Debe', 'Haber'], $filas), "libro_diario_{$periodo}.xlsx");
        }

        $paginados = $asientos->paginate(40)->withQueryString();
        $totales = DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.IdEmpresa', $ruc)->where('a.periodo', $periodo)->selectRaw('SUM(d.debe) as debe, SUM(d.haber) as haber, COUNT(DISTINCT a.id) as n')->first();

        return view('empresas.contabilidad.diario', [
            'asientos' => $paginados, 'periodo' => $periodo, 'q' => $q, 'totales' => $totales,
            'detalle' => DB::table('conta_asiento_detalle')->whereIn('asiento_id', $paginados->pluck('id'))->orderBy('id')->get()->groupBy('asiento_id'),
            'nombres' => $this->nombres($ruc), 'imputables' => Contabilidad::imputables($ruc), 'cerrado' => Contabilidad::cerrado($ruc, $periodo),
            'periodos' => DB::table('conta_periodos')->where('IdEmpresa', $ruc)->pluck('estado', 'periodo'),
        ]);
    }

    public function asientoGuardar(Request $request, ?int $id = null)
    {
        $ruc = $this->ruc();
        $d = $request->validate([
            'fecha' => 'required|date', 'glosa' => 'required|string|max:200', 'subdiario' => 'required|in:00,35,01',
            'lineas' => 'required|array|min:2|max:200', 'lineas.*.cuenta' => 'required|string|max:12',
            'lineas.*.debe' => 'nullable|numeric|min:0', 'lineas.*.haber' => 'nullable|numeric|min:0',
            'lineas.*.glosa' => 'nullable|string|max:150', 'lineas.*.documento' => 'nullable|string|max:30',
        ], [], ['lineas' => 'líneas del asiento']);

        try {
            $nuevo = DB::transaction(function () use ($ruc, $d, $id) {
                $conservar = [];
                if ($id) {
                    $a = DB::table('conta_asientos')->where('id', $id)->where('IdEmpresa', $ruc)->whereIn('origen', ['MANUAL', 'APERTURA'])->lockForUpdate()->first();
                    if (!$a) {
                        throw new \RuntimeException('Solo se editan asientos manuales; los automáticos se regeneran al centralizar.');
                    }
                    Contabilidad::validarPeriodo($ruc, $a->periodo);
                    DB::table('conta_asientos')->where('id', $id)->delete();
                    $conservar = ['numero' => $a->numero, 'periodo_anterior' => $a->periodo . $a->subdiario];
                }
                return Contabilidad::crearAsiento($ruc, ['fecha' => $d['fecha'], 'glosa' => $d['glosa'], 'subdiario' => $d['subdiario'],
                    'origen' => $d['subdiario'] === '00' ? 'APERTURA' : 'MANUAL'] + $conservar, $d['lineas']);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
        $a = DB::table('conta_asientos')->where('id', $nuevo)->first();
        return response()->json(['success' => true, 'message' => "Asiento {$a->subdiario}-" . str_pad($a->numero, 4, '0', STR_PAD_LEFT) . ' guardado.',
            'periodo' => $a->periodo]);
    }

    public function asientoEliminar(int $id)
    {
        $ruc = $this->ruc();
        $a = DB::table('conta_asientos')->where('id', $id)->where('IdEmpresa', $ruc)->first();
        abort_unless($a, 404);
        if (!in_array($a->origen, ['MANUAL', 'APERTURA'], true)) {
            return back()->withErrors(['asiento' => 'Los asientos automáticos no se eliminan: vuelve a centralizar el periodo.']);
        }
        try {
            Contabilidad::validarPeriodo($ruc, $a->periodo);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['asiento' => $e->getMessage()]);
        }
        DB::table('conta_asientos')->where('id', $id)->delete();
        return back()->with('success', 'Asiento eliminado.');
    }

    public function periodoEstado(Request $request)
    {
        $ruc = $this->ruc();
        $periodo = $this->periodo($request);
        if ($request->input('accion') === 'cerrar') {
            DB::table('conta_periodos')->updateOrInsert(['IdEmpresa' => $ruc, 'periodo' => $periodo],
                ['estado' => 'CERRADO', 'IdUsuario' => Auth::id(), 'updated_at' => now(), 'created_at' => now()]);
            return back()->with('success', 'Periodo ' . Contabilidad::nombrePeriodo($periodo) . ' cerrado: ya no se puede modificar ni volver a centralizar.');
        }
        DB::table('conta_periodos')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->delete();
        return back()->with('success', 'Periodo ' . Contabilidad::nombrePeriodo($periodo) . ' abierto nuevamente.');
    }

    // ================================================================== LIBRO MAYOR

    public function mayor(Request $request)
    {
        $ruc = $this->ruc();
        $desde = $request->get('desde', now()->startOfYear()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());
        $cuenta = preg_replace('/\D/', '', (string) $request->get('cuenta'));
        $nombres = $this->nombres($ruc);

        // Resumen de todas las cuentas con movimiento (para elegir)
        $resumen = Contabilidad::sumas($ruc, $desde, $hasta);
        $movs = collect();
        $saldoInicial = 0.0;
        if ($cuenta !== '') {
            $base = DB::table('conta_asiento_detalle as d')->join('conta_asientos as a', 'a.id', '=', 'd.asiento_id')
                ->where('a.IdEmpresa', $ruc)->where('d.cuenta', 'like', $cuenta . '%');
            $ini = (clone $base)->where('a.fecha', '<', $desde)->selectRaw('SUM(d.debe) as debe, SUM(d.haber) as haber')->first();
            $saldoInicial = round((float) $ini->debe - (float) $ini->haber, 2);
            $movs = (clone $base)->whereBetween('a.fecha', [$desde, $hasta])->orderBy('a.fecha')->orderBy('a.subdiario')->orderBy('a.numero')->orderBy('d.id')
                ->select('d.*', 'a.fecha', 'a.subdiario', 'a.numero', 'a.glosa as glosa_asiento', 'a.periodo')->get();
            $saldo = $saldoInicial;
            foreach ($movs as $m) {
                $saldo = round($saldo + (float) $m->debe - (float) $m->haber, 2);
                $m->saldo = $saldo;
            }

            if ($request->get('excel')) {
                $filas = [['', '', 'SALDO ANTERIOR', '', '', '', '', $saldoInicial]];
                foreach ($movs as $m) {
                    $filas[] = [Carbon::parse($m->fecha)->format('d/m/Y'), $m->subdiario . '-' . str_pad($m->numero, 4, '0', STR_PAD_LEFT),
                        $m->glosa ?: $m->glosa_asiento, (string) $m->cuenta, $m->documento ?? '', (float) $m->debe, (float) $m->haber, (float) $m->saldo];
                }
                return $this->descargar((new Excel())->hoja('Mayor ' . $cuenta, ['Fecha', 'Asiento', 'Glosa', 'Cuenta', 'Documento', 'Debe', 'Haber', 'Saldo'], $filas),
                    "libro_mayor_{$cuenta}.xlsx");
            }
        }

        return view('empresas.contabilidad.mayor', compact('desde', 'hasta', 'cuenta', 'nombres', 'resumen', 'movs', 'saldoInicial'));
    }

    // ================================================================== BALANCE DE COMPROBACIÓN

    public function balance(Request $request)
    {
        $ruc = $this->ruc();
        $desde = $request->get('desde', now()->startOfYear()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());
        $nivel = in_array((int) $request->get('nivel'), [2, 3, 4, 6], true) ? (int) $request->get('nivel') : 2;
        $nombres = $this->nombres($ruc);

        $filas = [];
        foreach (Contabilidad::sumas($ruc, $desde, $hasta) as $c => $s) {
            $c = (string) $c;
            $k = substr($c, 0, $nivel);
            $filas[$k] ??= ['cuenta' => $k, 'nombre' => $nombres[$k] ?? ($nombres[substr($c, 0, 2)] ?? ''), 'debe' => 0, 'haber' => 0];
            $filas[$k]['debe'] += (float) $s->debe;
            $filas[$k]['haber'] += (float) $s->haber;
        }
        ksort($filas, SORT_STRING);
        $tot = array_fill_keys(['debe', 'haber', 'deudor', 'acreedor', 'activo', 'pasivo', 'perdida', 'ganancia'], 0.0);
        foreach ($filas as &$f) {
            $saldo = round($f['debe'] - $f['haber'], 2);
            $f['deudor'] = $saldo > 0 ? $saldo : 0;
            $f['acreedor'] = $saldo < 0 ? -$saldo : 0;
            $el = $f['cuenta'][0];
            // Hoja de trabajo: 1-5 van al inventario (balance); 6, 7 (menos la 79) y 8 a resultados por naturaleza
            $f['activo'] = $f['pasivo'] = $f['perdida'] = $f['ganancia'] = 0;
            if (in_array($el, ['1', '2', '3', '4', '5'], true)) {
                $f['activo'] = $f['deudor'];
                $f['pasivo'] = $f['acreedor'];
            } elseif (in_array($el, ['6', '7', '8'], true) && !str_starts_with($f['cuenta'], '79')) {
                $f['perdida'] = $f['deudor'];
                $f['ganancia'] = $f['acreedor'];
            }
            foreach ($tot as $k => $v) {
                $tot[$k] = round($v + $f[$k], 2);
            }
        }
        unset($f);
        $resultadoBalance = round($tot['activo'] - $tot['pasivo'], 2);
        $resultadoNaturaleza = round($tot['ganancia'] - $tot['perdida'], 2);

        if ($request->get('excel')) {
            $x = array_map(fn($f) => [(string) $f['cuenta'], $f['nombre'], $f['debe'], $f['haber'], $f['deudor'], $f['acreedor'], $f['activo'], $f['pasivo'], $f['perdida'], $f['ganancia']], array_values($filas));
            $x[] = ['', 'TOTALES', $tot['debe'], $tot['haber'], $tot['deudor'], $tot['acreedor'], $tot['activo'], $tot['pasivo'], $tot['perdida'], $tot['ganancia']];
            $x[] = ['', 'RESULTADO DEL EJERCICIO', '', '', '', '', $resultadoBalance < 0 ? -$resultadoBalance : '', $resultadoBalance > 0 ? $resultadoBalance : '',
                $resultadoNaturaleza > 0 ? $resultadoNaturaleza : '', $resultadoNaturaleza < 0 ? -$resultadoNaturaleza : ''];
            return $this->descargar((new Excel())->hoja('Balance de comprobación', [
                ['', '', 'SUMAS', '', 'SALDOS', '', 'INVENTARIO', '', 'RESULTADOS POR NATURALEZA', ''],
                ['Cuenta', 'Denominación', 'Debe', 'Haber', 'Deudor', 'Acreedor', 'Activo', 'Pasivo y Patrimonio', 'Pérdidas', 'Ganancias']], $x),
                "balance_comprobacion_{$desde}_{$hasta}.xlsx");
        }

        return view('empresas.contabilidad.balance', compact('filas', 'tot', 'desde', 'hasta', 'nivel', 'resultadoBalance', 'resultadoNaturaleza'));
    }

    // ================================================================== ESTADOS FINANCIEROS

    public function estados(Request $request)
    {
        $ruc = $this->ruc();
        $desde = $request->get('desde', now()->startOfYear()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        // Estado de resultados por naturaleza (del periodo)
        $s = Contabilidad::sumas($ruc, $desde, $hasta);
        $n = fn($p, $x = []) => Contabilidad::neto($s, (array) $p, $x);
        $ventas = $n(['70']);
        $descuentos = $n(['74']);
        $costo = -$n(['69', '60', '61']);
        $bruta = round($ventas + $descuentos - $costo, 2);
        $gastos = ['Gastos de personal' => -$n(['62']), 'Servicios prestados por terceros' => -$n(['63']), 'Tributos' => -$n(['64']),
            'Otros gastos de gestión' => -$n(['65']), 'Valuación y deterioro de activos' => -$n(['68'])];
        $otrosIngresos = $n(['73', '75', '76', '78']) - (-$n(['66']));
        $operativo = round($bruta - array_sum($gastos) + $otrosIngresos, 2);
        $finIngresos = $n(['77']);
        $finGastos = -$n(['67']);
        $antesImp = round($operativo + $finIngresos - $finGastos, 2);
        $renta = -$n(['88']);
        $resultado = round($antesImp - $renta, 2);
        $er = compact('ventas', 'descuentos', 'costo', 'bruta', 'gastos', 'otrosIngresos', 'operativo', 'finIngresos', 'finGastos', 'antesImp', 'renta', 'resultado');

        // Estado de situación financiera (acumulado al corte)
        $a = Contabilidad::sumas($ruc, null, $hasta);
        $d = fn($p) => -Contabilidad::neto($a, (array) $p);    // saldo deudor
        $c = fn($p) => Contabilidad::neto($a, (array) $p);     // saldo acreedor
        $activoCorriente = ['Efectivo y equivalentes de efectivo' => $d('10'), 'Inversiones financieras' => $d('11'),
            'Cuentas por cobrar comerciales' => $d(['12', '13']), 'Otras cuentas por cobrar' => $d(['14', '16', '17']),
            'Servicios contratados por anticipado' => $d('18'), 'Estimación de cobranza dudosa' => $d('19'),
            'Inventarios' => $d(['20', '21', '22', '23', '24', '25', '26', '27', '28', '29'])];
        $activoNoCorriente = ['Inversiones mobiliarias' => $d('30'), 'Propiedades de inversión' => $d('31'), 'Activos por derecho de uso' => $d('32'),
            'Propiedades, planta y equipo' => $d('33'), 'Intangibles' => $d('34'), 'Activos biológicos' => $d('35'),
            'Depreciación y amortización acumulada' => $d(['36', '39']), 'Activo diferido y otros' => $d(['37', '38'])];
        $pasivoCorriente = ['Tributos por pagar' => $c('40'), 'Remuneraciones por pagar' => $c('41'), 'Cuentas por pagar comerciales' => $c(['42', '43']),
            'Cuentas por pagar a accionistas y gerentes' => $c('44'), 'Obligaciones financieras' => $c('45'),
            'Otras cuentas por pagar' => $c(['46', '47']), 'Provisiones' => $c('48')];
        $pasivoNoCorriente = ['Pasivo diferido' => $c('49')];
        $resultadoAcum = Contabilidad::neto($a, ['6', '7', '8'], ['79']);
        $patrimonio = ['Capital' => $c(['50', '51']), 'Capital adicional' => $c('52'), 'Resultados no realizados y revaluación' => $c(['56', '57']),
            'Reservas' => $c('58'), 'Resultados acumulados' => $c('59'), 'Resultado del ejercicio' => $resultadoAcum];

        $totActivo = round(array_sum($activoCorriente) + array_sum($activoNoCorriente), 2);
        $totPasivo = round(array_sum($pasivoCorriente) + array_sum($pasivoNoCorriente), 2);
        $totPatrimonio = round(array_sum($patrimonio), 2);
        $esf = compact('activoCorriente', 'activoNoCorriente', 'pasivoCorriente', 'pasivoNoCorriente', 'patrimonio', 'totActivo', 'totPasivo', 'totPatrimonio');

        if ($request->get('excel')) {
            $f = [['ESTADO DE RESULTADOS', ''], ['Ventas netas', $ventas + $descuentos], ['Costo de ventas', -$costo], ['UTILIDAD BRUTA', $bruta]];
            foreach ($gastos as $k => $v) {
                $f[] = [$k, -$v];
            }
            array_push($f, ['Otros ingresos (gastos) de gestión', $otrosIngresos], ['RESULTADO DE OPERACIÓN', $operativo], ['Ingresos financieros', $finIngresos],
                ['Gastos financieros', -$finGastos], ['RESULTADO ANTES DE IMPUESTOS', $antesImp], ['Impuesto a la renta', -$renta], ['RESULTADO DEL PERIODO', $resultado],
                ['', ''], ['ESTADO DE SITUACIÓN FINANCIERA', '']);
            foreach (['ACTIVO CORRIENTE' => $activoCorriente, 'ACTIVO NO CORRIENTE' => $activoNoCorriente, 'PASIVO CORRIENTE' => $pasivoCorriente,
                      'PASIVO NO CORRIENTE' => $pasivoNoCorriente, 'PATRIMONIO' => $patrimonio] as $tit => $grupo) {
                $f[] = [$tit, round(array_sum($grupo), 2)];
                foreach ($grupo as $k => $v) {
                    $f[] = ['   ' . $k, $v];
                }
            }
            array_push($f, ['TOTAL ACTIVO', $totActivo], ['TOTAL PASIVO Y PATRIMONIO', round($totPasivo + $totPatrimonio, 2)]);
            return $this->descargar((new Excel())->hoja('Estados financieros', ['Concepto', 'S/'], $f), "estados_financieros_{$hasta}.xlsx");
        }

        return view('empresas.contabilidad.estados', compact('desde', 'hasta', 'er', 'esf'));
    }
}
