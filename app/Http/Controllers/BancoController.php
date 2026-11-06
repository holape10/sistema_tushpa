<?php

namespace App\Http\Controllers;

use App\Support\Bancos;
use App\Support\Bitacora;
use App\Support\Conciliacion;
use App\Support\Contabilidad\Contabilidad;
use App\Support\Excel;
use App\Support\ExcelLector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Bancos y conciliación bancaria (el administrador o quien tenga la opción en su menú) */
class BancoController extends Controller
{
    public function index(): View
    {
        $ruc = $this->autorizar();
        $hoy = now()->toDateString();
        $cuentas = Bancos::cuentas($ruc)->map(function ($c) use ($hoy) {
            $c->saldo = Bancos::saldoLibros($c, $hoy);
            $c->medios = DB::table('medios_pagos')->where('cuenta_bancaria_id', $c->id)->pluck('nom_med_pag')->all();
            $c->pendientes = DB::table('bancos_extracto as e')->leftJoin('bancos_conciliados as k', 'k.extracto_id', '=', 'e.id')
                ->where('e.cuenta_id', $c->id)->whereNull('k.id')->count();
            $c->ultimo_extracto = DB::table('bancos_extracto')->where('cuenta_id', $c->id)->max('fecha');

            return $c;
        });

        return view('empresas.bancos.index', ['cuentas' => $cuentas] + $this->datosCuenta(null));
    }

    public function editar(int $id): View
    {
        $ruc = $this->autorizar();

        return view('empresas.bancos.cuenta', $this->datosCuenta(Bancos::cuenta($ruc, $id)));
    }

    public function guardarCuenta(Request $request, ?int $id = null): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'banco' => 'required|string|max:60', 'numero' => 'required|string|max:40', 'cuenta_contable' => 'required|string|max:12',
            'saldo_inicial' => 'required|numeric|min:-999999999|max:999999999', 'fecha_inicio' => 'required|date',
            'activo' => 'nullable|boolean', 'medios' => 'nullable|array', 'medios.*' => 'integer',
        ], [], ['numero' => 'número de cuenta', 'cuenta_contable' => 'cuenta contable', 'fecha_inicio' => 'fecha del saldo inicial']);
        try {
            $id = Bancos::guardarCuenta(Auth::user(), $d + ['activo' => $request->boolean('activo', true)], $id);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        Bitacora::registrar('config.banco', "Guardó la cuenta bancaria {$d['banco']} {$d['numero']} (cuenta contable {$d['cuenta_contable']})", null,
            ['referencia' => ['banco', $id], 'datos' => ['medios' => DB::table('medios_pagos')->where('cuenta_bancaria_id', $id)->pluck('nom_med_pag')->all()]]);

        return redirect()->route('bancos.show', $id)->with('success', 'Cuenta bancaria guardada.');
    }

    public function show(Request $request, int $id): View
    {
        $ruc = $this->autorizar();
        $cuenta = Bancos::cuenta($ruc, $id);
        [$periodo, $desde, $hasta] = $this->mes($request, $cuenta);
        $vista = in_array($request->query('vista'), ['conciliar', 'libro', 'reporte'], true) ? $request->query('vista') : 'conciliar';
        $datos = ['cuenta' => $cuenta, 'periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta, 'vista' => $vista,
            'tipos' => Bancos::TIPOS, 'otras' => Bancos::cuentas($ruc, true)->where('id', '!=', $cuenta->id)->values(),
            'mesesExtracto' => DB::table('bancos_extracto')->where('cuenta_id', $id)->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes")->distinct()->orderByDesc('mes')->limit(24)->pluck('mes')];

        if ($vista === 'conciliar') {
            $datos += Conciliacion::pendientes($cuenta, $desde, $hasta) + ['grupos' => $this->grupos($cuenta, $desde, $hasta)];
        } elseif ($vista === 'libro') {
            $movimientos = Bancos::movimientos($cuenta, $desde, $hasta);
            $conciliados = DB::table('bancos_conciliados')->where('cuenta_id', $id)->whereNotNull('origen')->get(['origen', 'origen_id'])
                ->map(fn ($c) => $c->origen.':'.$c->origen_id)->flip();
            $datos += ['movimientos' => $movimientos, 'conciliados' => $conciliados,
                'saldoAnterior' => Bancos::saldoLibros($cuenta, Carbon::parse($desde)->subDay()->toDateString())];
        } else {
            $datos += ['reporte' => Conciliacion::reporte($cuenta, $periodo)];
        }

        return view('empresas.bancos.show', $datos);
    }

    /** Sube el estado de cuenta (Excel o CSV) */
    public function extracto(Request $request, int $id): RedirectResponse
    {
        $ruc = $this->autorizar();
        $cuenta = Bancos::cuenta($ruc, $id);
        $request->validate(['archivo' => 'required|file|max:10240|extensions:'.ExcelLector::EXTENSIONES],
            ['archivo.extensions' => 'Sube el extracto en Excel (.xlsx, .xls, .ods) o .csv.'], ['archivo' => 'extracto']);
        try {
            $r = Conciliacion::importar(Auth::user(), $cuenta, $request->file('archivo')->getRealPath(), $request->file('archivo')->getClientOriginalExtension());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('cuenta.extracto', "Subió el extracto de {$cuenta->banco} {$cuenta->numero}: {$r['nuevas']} línea(s) nueva(s)", null,
            ['referencia' => ['banco', $id], 'datos' => ['archivo' => mb_substr((string) $request->file('archivo')->getClientOriginalName(), 0, 120)] + $r]);

        return back()->with('success', "Extracto cargado: {$r['nuevas']} línea(s) nueva(s)".($r['repetidas'] ? ", {$r['repetidas']} ya estaban" : '').'. Ahora toca "Conciliar automático".');
    }

    public function formato(): BinaryFileResponse
    {
        $this->autorizar();

        return response()->download(Conciliacion::formato(), 'formato_extracto_bancario.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    public function automatico(Request $request, int $id): RedirectResponse
    {
        $ruc = $this->autorizar();
        $cuenta = Bancos::cuenta($ruc, $id);
        [, $desde, $hasta] = $this->mes($request, $cuenta);
        $n = Conciliacion::automatico(Auth::user(), $cuenta, $desde, $hasta);

        return back()->with('success', $n ? "✔ {$n} línea(s) del banco conciliadas automáticamente." : 'No encontré coincidencias seguras: concilia a mano lo que falta.');
    }

    public function conciliar(Request $request, int $id): RedirectResponse
    {
        $ruc = $this->autorizar();
        $d = $request->validate(['extracto' => 'required|array|min:1', 'extracto.*' => 'integer', 'sistema' => 'required|array|min:1',
            'sistema.*' => ['string', 'regex:/^(venta|cobro|pago|gasto|banco):\d+$/']],
            ['extracto.required' => 'Marca al menos una línea del banco.', 'sistema.required' => 'Marca al menos un movimiento del sistema.']);
        try {
            Conciliacion::manual(Auth::user(), Bancos::cuenta($ruc, $id), $d['extracto'], $d['sistema']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Conciliado.');
    }

    public function deshacer(Request $request, int $id): RedirectResponse
    {
        $ruc = $this->autorizar();
        Conciliacion::deshacer(Bancos::cuenta($ruc, $id), (string) $request->validate(['grupo' => 'required|uuid'])['grupo']);

        return back()->with('success', 'Se deshizo esa conciliación: volvió a pendientes.');
    }

    /** Lo que solo está en el banco se registra en el sistema (con su asiento) y queda conciliado */
    public function registrar(Request $request, int $id): RedirectResponse
    {
        $ruc = $this->autorizar();
        $cuenta = Bancos::cuenta($ruc, $id);
        $d = $request->validate(['extracto_id' => 'required|integer', 'tipo' => 'required|in:'.implode(',', array_keys(Bancos::TIPOS)),
            'contrapartida' => 'nullable|string|max:12']);
        if ($d['tipo'] === 'TRANSFERENCIA') {
            return back()->with('error', 'Las transferencias entre tus cuentas regístralas en "Movimiento del banco" eligiendo la cuenta de destino.');
        }
        try {
            $mov = Conciliacion::registrarDesdeBanco(Auth::user(), $cuenta, (int) $d['extracto_id'], $d['tipo'], $d['contrapartida'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->bitacoraMovimiento($cuenta, $mov, 'desde el extracto');

        return back()->with('success', 'Registrado con su asiento contable y conciliado.');
    }

    /** Movimiento propio del banco escrito a mano */
    public function movimiento(Request $request, int $id): RedirectResponse
    {
        $ruc = $this->autorizar();
        $cuenta = Bancos::cuenta($ruc, $id);
        $d = $request->validate(['tipo' => 'required|in:'.implode(',', array_keys(Bancos::TIPOS)), 'fecha' => 'required|date|before_or_equal:today',
            'monto' => 'required|numeric|min:0.01|max:999999999', 'descripcion' => 'nullable|string|max:200', 'operacion' => 'nullable|string|max:40',
            'contrapartida' => 'nullable|string|max:12', 'cuenta_destino_id' => 'nullable|required_if:tipo,TRANSFERENCIA|integer',
            'sentido' => 'nullable|in:entra,sale'], ['cuenta_destino_id.required_if' => 'Elige a qué cuenta se transfiere.']);
        try {
            $mov = Bancos::registrarMovimiento(Auth::user(), $cuenta, $d);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $this->bitacoraMovimiento($cuenta, $mov, 'a mano');

        return back()->with('success', 'Movimiento registrado con su asiento contable.');
    }

    public function anularMovimiento(int $movimiento): RedirectResponse
    {
        $this->autorizar();
        try {
            $m = Bancos::anularMovimiento(Auth::user(), $movimiento);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('cuenta.banco_anular', 'Anuló el movimiento bancario '.(Bancos::TIPOS[$m->tipo][0] ?? $m->tipo).' de S/ '.number_format(abs((float) $m->monto), 2)
            .' del '.date('d/m/Y', strtotime($m->fecha)).' (y su asiento)', null, ['referencia' => ['banco_movimiento', $movimiento], 'alerta' => true]);

        return back()->with('success', 'Movimiento anulado: se borró su asiento contable.');
    }

    public function reporteExcel(Request $request, int $id): BinaryFileResponse
    {
        $ruc = $this->autorizar();
        $cuenta = Bancos::cuenta($ruc, $id);
        [$periodo] = $this->mes($request, $cuenta);
        $r = Conciliacion::reporte($cuenta, $periodo);
        $f = fn ($x) => Carbon::parse($x)->format('d/m/Y');
        $resumen = [
            ['Saldo según libros al '.$f($r['hasta']), $r['saldo_libros']],
            ['(−) Movimientos del sistema que el banco aún no refleja', -$r['total_sistema_pendiente']],
            ['(+) Movimientos del banco que el sistema aún no tiene', $r['total_banco_pendiente']],
            ['= Saldo calculado', $r['calculado']],
            ['Saldo según el banco', $r['saldo_banco']],
            ['Diferencia', $r['diferencia']],
            ['Saldo de la cuenta '.$cuenta->cuenta_contable.' en el Libro Mayor', $r['saldo_mayor']],
        ];
        $ruta = (new Excel)
            ->hoja('Conciliación', ['Concepto', 'S/'], $resumen)
            ->hoja('Pendientes del sistema', ['Fecha', 'Descripción', 'Operación', 'Monto'],
                $r['sistema_pendiente']->map(fn ($m) => [$f($m['fecha']), $m['descripcion'], (string) $m['operacion'], $m['monto']])->all())
            ->hoja('Pendientes del banco', ['Fecha', 'Descripción', 'Operación', 'Monto'],
                $r['banco_pendiente']->map(fn ($e) => [$f($e->fecha), (string) $e->descripcion, (string) $e->operacion, (float) $e->monto])->all())
            ->guardar();

        return response()->download($ruta, 'conciliacion_'.$cuenta->banco.'_'.$periodo.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    // ------------------------------------------------------------------

    private function autorizar(): string
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/bancos'), 403, 'No tienes permiso para bancos y conciliación.');

        return (string) Auth::user()->IdEmpresa;
    }

    /** @return array{0: string, 1: string, 2: string} [AAAAMM, desde, hasta] del mes elegido (por defecto el actual) */
    private function mes(Request $request, object $cuenta): array
    {
        $mes = (string) $request->input('mes', now()->format('Y-m'));
        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $mes, $m)) {
            $mes = now()->format('Y-m');
            preg_match('/^(\d{4})-(\d{2})$/', $mes, $m);
        }
        $inicio = Carbon::create((int) $m[1], (int) $m[2], 1);

        return [$inicio->format('Ym'), $inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()];
    }

    /** Lo ya conciliado en el mes, agrupado (para poder deshacerlo) */
    private function grupos(object $cuenta, string $desde, string $hasta)
    {
        $grupos = DB::table('bancos_conciliados as k')->join('bancos_extracto as e', 'e.id', '=', 'k.extracto_id')
            ->where('k.cuenta_id', $cuenta->id)->whereBetween('e.fecha', [$desde, $hasta])->distinct()->pluck('k.grupo');
        if ($grupos->isEmpty()) {
            return collect();
        }
        $filas = DB::table('bancos_conciliados as k')->leftJoin('bancos_extracto as e', 'e.id', '=', 'k.extracto_id')->whereIn('k.grupo', $grupos)
            ->get(['k.grupo', 'k.extracto_id', 'k.origen', 'k.monto', 'e.fecha', 'e.descripcion']);

        return $filas->groupBy('grupo')->map(fn ($g, $grupo) => (object) [
            'grupo' => $grupo, 'fecha' => $g->whereNotNull('extracto_id')->min('fecha'),
            'banco' => $g->whereNotNull('extracto_id')->pluck('descripcion')->filter()->implode(' + ') ?: 'Línea del banco',
            'sistema' => $g->whereNotNull('origen')->count(), 'monto' => round($g->whereNotNull('extracto_id')->sum('monto'), 2),
        ])->sortBy('fecha')->values();
    }

    private function datosCuenta(?object $cuenta): array
    {
        $ruc = (string) Auth::user()->IdEmpresa;
        Contabilidad::asegurar($ruc);

        return [
            'cuenta' => $cuenta,
            'medios' => DB::table('medios_pagos')->where('IdEmpresa', $ruc)->orderBy('nom_med_pag')->get(['id_med_pag', 'nom_med_pag', 'cuenta_bancaria_id', 'id_empresa_negocio']),
            'cuentasBanco' => Contabilidad::imputables($ruc)->filter(fn ($n, $c) => str_starts_with((string) $c, '10')),
            'bancos' => Bancos::BANCOS,
        ];
    }

    private function bitacoraMovimiento(object $cuenta, int $movimiento, string $como): void
    {
        $m = DB::table('bancos_movimientos')->where('id', $movimiento)->first();
        Bitacora::registrar('cuenta.banco_movimiento', 'Registró '.(Bancos::TIPOS[$m->tipo][0] ?? $m->tipo).' de S/ '.number_format(abs((float) $m->monto), 2)
            ." en {$cuenta->banco} {$cuenta->numero} ({$como})", null, ['referencia' => ['banco_movimiento', $movimiento]]);
    }
}
