<?php
namespace App\Http\Controllers;

use App\Models\{Mesa, Pedido, Turno};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class TurnoController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja manejan turnos.');
    }

    // Turno de la sucursal del usuario; un cajero solo puede ver los suyos, el admin todos
    private function turnoVisible($id): Turno
    {
        $user = Auth::user();
        $turno = Turno::where('id_turno', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->firstOrFail();
        abort_unless($user->esAdmin() || $turno->IdUsuario == $user->IdUsuario, 403);
        return $turno;
    }

    /** Totales del turno calculados en vivo desde ventas y movimientos de caja */
    public static function resumen(Turno $turno): array
    {
        $ventasPorMedio = DB::table('venta_medio_pago as v')
            ->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
            ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
            ->where('v.id_turno', $turno->id_turno)
            ->whereNull('c.ccabaj')
            ->groupBy('v.id_med_pag', 'm.nom_med_pag')
            ->select('v.id_med_pag', 'm.nom_med_pag', DB::raw('SUM(v.monto) as monto'), DB::raw('COUNT(DISTINCT v.IdCpe_cabecera) as operaciones'))
            ->orderByDesc('monto')
            ->get();

        // Cada cobro con su medio de pago (un comprobante con pago mixto aparece una vez por medio)
        $ventas = DB::table('venta_medio_pago as v')
            ->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
            ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
            ->leftJoin('mesas as me', 'me.mes_id', '=', 'c.mes_id')
            ->where('v.id_turno', $turno->id_turno)
            ->whereNull('c.ccabaj')
            ->orderByDesc('c.IdCpe_cabecera')
            ->select('v.id_med_pag', 'm.nom_med_pag', 'v.monto', 'c.IdCpe_cabecera', 'c.serdoc', 'c.numdoc',
                'c.ccanom', 'c.ccaitv', 'c.fecha_hora', 'c.ped_tip', 'me.mes_nom')
            ->get();

        $comprobantes = DB::table('cpe_cabecera as c')
            ->leftJoin('tipo_documento as t', 't.tdocod', '=', 'c.tdocod')
            ->where('c.id_turno', $turno->id_turno)
            ->whereNull('c.ccabaj')
            ->groupBy('c.tdocod', 't.tdodes')
            ->select('c.tdocod', 't.tdodes', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(c.ccaitv) as total'),
                DB::raw('SUM(c.totalcredito) as credito'))
            ->get();

        $movimientos = DB::table('movimientoscaja as mc')
            ->leftJoin('tiposcaja as tc', 'tc.tip_caj_id', '=', 'mc.tip_caj_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'mc.IdUsuario')
            ->where('mc.id_turno', $turno->id_turno)
            ->orderBy('mc.mov_caj_id')
            ->select('mc.*', 'tc.tip_caj_nom', 'tc.tipo', 'u.apeusu')
            ->get();

        $activos = $movimientos->where('estado', 'ACTIVO');
        $ingresos = round((float) $activos->where('tipo', 'ENTRADA')->sum('importe'), 2);
        $egresos = round((float) $activos->where('tipo', 'SALIDA')->sum('importe'), 2);

        $ventasEfectivo = round((float) $ventasPorMedio->filter(fn($v) => strtoupper((string) $v->nom_med_pag) === 'EFECTIVO')->sum('monto'), 2);
        $ventasTotal = round((float) $comprobantes->sum('total'), 2);
        $ventasCredito = round((float) $comprobantes->sum('credito'), 2);
        $efectivoEsperado = round((float) $turno->monto + $ventasEfectivo + $ingresos - $egresos, 2);

        return compact('ventasPorMedio', 'ventas', 'comprobantes', 'movimientos', 'ingresos', 'egresos',
            'ventasEfectivo', 'ventasTotal', 'ventasCredito', 'efectivoEsperado');
    }

    public function index()
    {
        $this->autorizar();
        $user = Auth::user();
        $turno = Turno::abiertoDe($user);

        if (!$turno) {
            return view('empresas.turnos.abrir', ['denominaciones' => Turno::DENOMINACIONES]);
        }

        $resumen = self::resumen($turno);
        $tiposCaja = DB::table('tiposcaja')->whereNotIn('tip_caj_id', ['001', '002'])->orderBy('tipo')->orderBy('tip_caj_id')->get();
        $pedidosAbiertos = Pedido::where('id_empresa_negocio', $user->id_empresa_negocio)->where('ped_est', 'Aperturado')->count();

        return view('empresas.turnos.panel', [
            'turno' => $turno, 'r' => $resumen, 'tiposCaja' => $tiposCaja,
            'pedidosAbiertos' => $pedidosAbiertos, 'denominaciones' => Turno::DENOMINACIONES,
        ]);
    }

    public function abrir(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();

        $request->validate(['monto' => 'required|numeric|min:0'], [], ['monto' => 'Fondo inicial']);

        DB::transaction(function () use ($request, $user) {
            // Bloqueo por usuario para que un doble clic no abra dos turnos
            DB::table('users')->where('IdUsuario', $user->IdUsuario)->lockForUpdate()->first();
            if (Turno::abiertoDe($user)) {
                return;
            }

            $numero = Turno::where('id_empresa_negocio', $user->id_empresa_negocio)
                ->whereDate('apertura', now()->toDateString())->count() + 1;

            $datos = [
                'turno' => $numero, 'IdUsuario' => $user->IdUsuario, 'apertura' => now(),
                'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
                'estado' => 'ABIERTO', 'monto' => round((float) $request->monto, 2),
            ];
            foreach (array_keys(Turno::DENOMINACIONES) as $campo) {
                $datos[$campo] = max(0, (int) $request->input($campo, 0));
            }
            Turno::create($datos);
        });

        return redirect()->route('turnos.index')->with('success', 'Turno aperturado. Ya puedes cobrar.');
    }

    public function movimiento(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $turno = Turno::abiertoDe($user);
        if (!$turno) {
            return redirect()->route('turnos.index')->withErrors(['turno' => 'No tienes un turno abierto.']);
        }

        $request->validate([
            'tip_caj_id' => 'required|exists:tiposcaja,tip_caj_id',
            'importe'    => 'required|numeric|min:0.01',
            'mov_com'    => 'required|string|max:255',
        ], [], ['tip_caj_id' => 'Tipo', 'importe' => 'Importe', 'mov_com' => 'Descripción']);

        abort_if(in_array($request->tip_caj_id, ['001', '002']), 422, 'Ese tipo se registra automáticamente.');

        DB::table('movimientoscaja')->insert([
            'tip_caj_id' => $request->tip_caj_id, 'mov_com' => trim($request->mov_com),
            'mov_num_doc' => $request->mov_num_doc, 'importe' => round((float) $request->importe, 2),
            'estado' => 'ACTIVO', 'mov_fecha' => now()->toDateString(), 'registro' => now()->toDateTimeString(),
            'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
            'id_turno' => $turno->id_turno, 'IdUsuario' => $user->IdUsuario,
        ]);

        return redirect()->route('turnos.index')->with('success', 'Movimiento registrado.');
    }

    public function anularMovimiento($id)
    {
        $this->autorizar();
        $turno = Turno::abiertoDe(Auth::user());
        abort_unless($turno, 403, 'No tienes un turno abierto.');

        $ok = DB::table('movimientoscaja')
            ->where('mov_caj_id', $id)->where('id_turno', $turno->id_turno)->where('estado', 'ACTIVO')
            ->update(['estado' => 'ANULADO']);

        return redirect()->route('turnos.index')->with('success', $ok ? 'Movimiento anulado.' : 'No se encontró el movimiento.');
    }

    public function cerrar(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();

        $request->validate(['montocierre' => 'nullable|numeric|min:0'], [], ['montocierre' => 'Efectivo contado']);

        $idTurno = DB::transaction(function () use ($request, $user) {
            $turno = Turno::where('IdUsuario', $user->IdUsuario)
                ->where('id_empresa_negocio', $user->id_empresa_negocio)
                ->where('estado', 'ABIERTO')->lockForUpdate()->first();
            if (!$turno) {
                return null;
            }

            $r = self::resumen($turno);

            // Arqueo: si contó billetes y monedas se usa esa suma; si no, el monto escrito
            $datos = [];
            $sumaArqueo = 0;
            foreach (Turno::DENOMINACIONES as $campo => $valor) {
                $cant = max(0, (int) $request->input('cierre_' . $campo, 0));
                $datos[$campo] = $cant;
                $sumaArqueo += $cant * $valor;
            }
            $montocierre = $sumaArqueo > 0 ? round($sumaArqueo, 2) : round((float) $request->input('montocierre', 0), 2);

            $mesas = Mesa::where('id_empresa_negocio', $user->id_empresa_negocio)->get();

            $turno->update($datos + [
                'estado'         => 'CERRADO',
                'cierre'         => now(),
                'montocierre'    => $montocierre,
                'total_ingresos' => $r['ingresos'],
                'total_gastos'   => $r['egresos'],
                'totalocupados'  => $mesas->where('mes_est', '!=', 'Libre')->count(),
                'totallibres'    => $mesas->where('mes_est', 'Libre')->count(),
            ]);

            DB::table('turno_medio_pago')->where('id_turno', $turno->id_turno)->delete();
            foreach ($r['ventasPorMedio'] as $v) {
                DB::table('turno_medio_pago')->insert([
                    'id_turno' => $turno->id_turno, 'id_med_pag' => $v->id_med_pag,
                    'monto' => $v->monto, 'id_empresa_negocio' => $user->id_empresa_negocio,
                ]);
            }

            return $turno->id_turno;
        });

        if (!$idTurno) {
            return redirect()->route('turnos.index')->withErrors(['turno' => 'No tienes un turno abierto.']);
        }

        return redirect()->route('turnos.show', $idTurno)->with('success', 'Turno cerrado correctamente.');
    }

    public function listado(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $desde = $request->get('desde', now()->subDays(30)->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $turnos = Turno::with('usuario')
            ->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->when(!$user->esAdmin(), fn($q) => $q->where('IdUsuario', $user->IdUsuario))
            ->whereDate('apertura', '>=', $desde)
            ->whereDate('apertura', '<=', $hasta)
            ->orderByDesc('id_turno')
            ->paginate(20)->withQueryString();

        return view('empresas.turnos.listado', compact('turnos', 'desde', 'hasta'));
    }

    public function show($id)
    {
        $this->autorizar();
        $turno = $this->turnoVisible($id);
        $r = self::resumen($turno);

        return view('empresas.turnos.show', [
            'turno' => $turno, 'r' => $r, 'denominaciones' => Turno::DENOMINACIONES,
        ]);
    }
}
