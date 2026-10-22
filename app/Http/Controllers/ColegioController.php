<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Colegio;
use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Colegio: alumnos con sus responsables, matrícula, cuenta de cada alumno, cobro de pensiones (una o varias),
 * pensión gratis, retiro y el sorteo de una pensión entre los padres puntuales.
 */
class ColegioController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja usan el colegio.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    private function admin(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador puede hacer esto.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    private function json(callable $accion): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) $accion());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    private function grados(int $suc)
    {
        return DB::table('col_grados')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('orden')->orderBy('grado_id')->get();
    }

    // ------------------------------------------------------------------ alumnos

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $cfg = Colegio::config($suc);
        $anio = (int) $request->query('anio', $cfg->anio);
        $situacion = Colegio::situacion($suc, $anio);
        $grados = $this->grados($suc)->keyBy('grado_id');
        $matriculas = DB::table('col_matriculas')->where('id_empresa_negocio', $suc)->where('anio', $anio)->get()->keyBy('alu_id');
        $pagadores = DB::table('col_responsables as r')->join('cliente as c', 'c.clicod', '=', 'r.clicod')->where('r.id_empresa_negocio', $suc)
            ->orderByDesc('r.paga')->get(['r.alu_id', 'r.tipo', 'c.clinom', 'c.telefono'])->groupBy('alu_id');

        $alumnos = DB::table('col_alumnos')->where('id_empresa_negocio', $suc)->orderBy('nombres')->get()->map(function ($a) use ($situacion, $matriculas, $grados, $pagadores) {
            $mat = $matriculas[$a->alu_id] ?? null;
            $s = $situacion[$a->alu_id] ?? null;
            [$etiqueta, $color] = $a->estado === 'RETIRADO' ? ['RETIRADO', 'gray'] : Colegio::estado($s);
            $resp = ($pagadores[$a->alu_id] ?? collect())->first();

            return [
                'id' => $a->alu_id, 'codigo' => $a->codigo, 'dni' => $a->dni, 'nombre' => $a->nombres, 'retirado' => $a->estado === 'RETIRADO',
                'grado_id' => $mat->grado_id ?? null, 'grado' => $mat ? Colegio::etiquetaGrado($grados[$mat->grado_id] ?? null) : 'Sin matrícula',
                'orden' => (int) ($grados[$mat->grado_id ?? 0]->orden ?? 999), 'dscto' => (float) ($mat->dscto_pct ?? 0),
                'meses' => $s->meses ?? 0, 'vencido' => $s->vencido ?? 0, 'pendiente' => $s->pendiente ?? 0,
                'etiqueta' => $etiqueta, 'color' => $color, 'responsable' => $resp ? $resp->tipo.': '.$resp->clinom : '', 'telefono' => $resp->telefono ?? '',
                'url' => route('colegio.ver', $a->alu_id),
            ];
        })->values();

        $matriculados = $alumnos->whereNotNull('grado_id')->where('retirado', false);
        $inicioMes = now()->startOfMonth()->toDateTimeString();

        return view('empresas.colegio.index', [
            'cfg' => $cfg, 'anio' => $anio, 'alumnos' => $alumnos, 'grados' => $grados->values(),
            'kpi' => (object) [
                'matriculados' => $matriculados->count(),
                'alDia' => $matriculados->where('etiqueta', 'AL DÍA')->count(),
                'deben1' => $matriculados->where('meses', 1)->count(),
                'deben2' => $matriculados->where('meses', '>=', 2)->count(),
                'vencido' => round($matriculados->sum('vencido'), 2),
                'porCobrar' => round($matriculados->sum('pendiente'), 2),
                'cobradoMes' => round((float) DB::table('col_pagos as p')->join('col_cargos as c', 'c.car_id', '=', 'p.car_id')
                    ->where('c.id_empresa_negocio', $suc)->where('p.anulado', 0)->where('p.fecha', '>=', $inicioMes)->sum('p.monto'), 2),
            ],
            'esAdmin' => Auth::user()->esAdmin(),
        ]);
    }

    /** Alumnos para elegir el hermano o referido */
    public function buscar(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $q = trim((string) $request->query('q'));

        return response()->json(DB::table('col_alumnos')->where('id_empresa_negocio', $suc)
            ->where(fn ($w) => $w->where('nombres', 'like', "%{$q}%")->orWhere('dni', 'like', "{$q}%")->orWhere('codigo', $q))
            ->orderBy('nombres')->limit(10)->get(['alu_id', 'nombres', 'dni', 'grupo_id']));
    }

    /**
     * Alumno nuevo (o editado) con su padre, madre y apoderado; si es nuevo y se elige grado, queda matriculado en el año
     * con toda su deuda (inscripción, matrícula y pensiones).
     */
    public function guardar(Request $request): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate([
            'id' => 'nullable|integer', 'dni' => 'nullable|string|max:15', 'nombres' => 'required|string|max:150',
            'fecha_nac' => 'nullable|date', 'sexo' => 'nullable|in:M,F', 'observacion' => 'nullable|string|max:255',
            'hermano_de' => 'nullable|integer', 'grado_id' => 'nullable|integer', 'nuevo' => 'nullable|boolean',
            'responsables' => 'nullable|array|max:3',
            'responsables.*.tipo' => 'required|in:'.implode(',', Colegio::RESPONSABLES),
            'responsables.*.clinum' => 'nullable|string|max:15', 'responsables.*.clinom' => 'nullable|string|max:150',
            'responsables.*.telefono' => 'nullable|string|max:20', 'responsables.*.clicor' => 'nullable|email|max:120',
            'responsables.*.clidir' => 'nullable|string|max:200', 'paga' => 'nullable|in:'.implode(',', Colegio::RESPONSABLES),
        ], [], ['nombres' => 'nombre del alumno', 'responsables.*.clicor' => 'correo']);

        return $this->json(function () use ($d, $suc) {
            $user = Auth::user();
            $cfg = Colegio::config($suc);
            $id = DB::transaction(function () use ($d, $suc, $user) {
                $datos = ['dni' => trim((string) ($d['dni'] ?? '')) ?: null, 'nombres' => mb_strtoupper(trim($d['nombres'])),
                    'fecha_nac' => $d['fecha_nac'] ?? null, 'sexo' => $d['sexo'] ?? null, 'observacion' => $d['observacion'] ?? null];
                if ($datos['dni'] && DB::table('col_alumnos')->where('id_empresa_negocio', $suc)->where('dni', $datos['dni'])
                    ->when($d['id'] ?? null, fn ($q, $id) => $q->where('alu_id', '!=', $id))->exists()) {
                    throw new \RuntimeException("Ya hay un alumno con el DNI {$datos['dni']}.");
                }
                if (! empty($d['id'])) {
                    abort_unless(DB::table('col_alumnos')->where('alu_id', $d['id'])->where('id_empresa_negocio', $suc)->exists(), 404);
                    DB::table('col_alumnos')->where('alu_id', $d['id'])->update($datos);
                    $id = (int) $d['id'];
                } else {
                    $ultimo = (int) DB::table('col_alumnos')->where('id_empresa_negocio', $suc)->max(DB::raw('CAST(codigo AS UNSIGNED)'));
                    $id = DB::table('col_alumnos')->insertGetId($datos + ['codigo' => str_pad((string) ($ultimo + 1), 5, '0', STR_PAD_LEFT),
                        'estado' => 'ACTIVO', 'id_empresa_negocio' => $suc, 'creado' => now()]);
                }

                // Hermano o referido: comparten grupo familiar (el grupo se crea con el primero)
                if (! empty($d['hermano_de']) && (int) $d['hermano_de'] !== $id) {
                    $otro = DB::table('col_alumnos')->where('alu_id', $d['hermano_de'])->where('id_empresa_negocio', $suc)->first();
                    if ($otro) {
                        $grupo = $otro->grupo_id ?: DB::table('col_grupos')->insertGetId(['nombre' => 'GRUPO DE '.$otro->nombres, 'id_empresa_negocio' => $suc]);
                        DB::table('col_alumnos')->whereIn('alu_id', [$otro->alu_id, $id])->update(['grupo_id' => $grupo]);
                    }
                }

                // Responsables: cada uno es un cliente (con DNI o RUC para su boleta o factura)
                if (array_key_exists('responsables', $d)) {
                    DB::table('col_responsables')->where('alu_id', $id)->delete();
                    foreach ($d['responsables'] ?? [] as $r) {
                        if (trim((string) ($r['clinom'] ?? '')) === '') {
                            continue;
                        }
                        $doc = preg_replace('/\D/', '', (string) ($r['clinum'] ?? '')) ?: '00000000';
                        $fila = ['clinom' => mb_strtoupper(trim($r['clinom'])), 'telefono' => $r['telefono'] ?? null, 'clicor' => $r['clicor'] ?? null,
                            'clidir' => trim((string) ($r['clidir'] ?? '')) ?: '--'];
                        $clicod = $doc !== '00000000' ? DB::table('cliente')->where('rucemp', $user->IdEmpresa)->where('clinum', $doc)->value('clicod') : null;
                        if ($clicod) {
                            DB::table('cliente')->where('clicod', $clicod)->update($fila);
                        } else {
                            $clicod = DB::table('cliente')->insertGetId($fila + ['tdicod' => strlen($doc) === 11 ? '6' : '1', 'clinum' => $doc,
                                'rucemp' => $user->IdEmpresa, 'cliest' => 'Activo']);
                        }
                        DB::table('col_responsables')->insert(['alu_id' => $id, 'tipo' => $r['tipo'], 'clicod' => $clicod,
                            'paga' => ($d['paga'] ?? null) === $r['tipo'], 'id_empresa_negocio' => $suc]);
                    }
                }

                return $id;
            });

            $mensaje = empty($d['id']) ? 'Alumno registrado.' : 'Datos guardados.';
            if (empty($d['id']) && ! empty($d['grado_id'])) {
                Colegio::matricular($user, $id, (int) $d['grado_id'], (int) $cfg->anio, (bool) ($d['nuevo'] ?? true));
                $mensaje = 'Alumno matriculado: ya tiene su inscripción, matrícula y pensiones del '.$cfg->anio.'.';
            }

            return ['id' => $id, 'mensaje' => $mensaje, 'url' => route('colegio.ver', $id)];
        });
    }

    /** Ficha del alumno: datos, responsables, hermanos o referidos, y su cuenta (lo que debe, lo pagado y lo gratis) */
    public function ver(Request $request, int $id): View
    {
        $suc = $this->sucursal();
        $cfg = Colegio::config($suc);
        $alumno = DB::table('col_alumnos')->where('alu_id', $id)->where('id_empresa_negocio', $suc)->first();
        abort_unless($alumno, 404);
        $matriculas = DB::table('col_matriculas as m')->join('col_grados as g', 'g.grado_id', '=', 'm.grado_id')->where('m.alu_id', $id)
            ->orderByDesc('m.anio')->get(['m.*', 'g.nivel', 'g.grado', 'g.seccion']);
        $anio = (int) $request->query('anio', $matriculas->first()->anio ?? $cfg->anio);

        $responsables = DB::table('col_responsables as r')->join('cliente as c', 'c.clicod', '=', 'r.clicod')->where('r.alu_id', $id)
            ->orderByRaw("FIELD(r.tipo, 'PADRE', 'MADRE', 'APODERADO')")->get(['r.tipo', 'r.paga', 'c.clinum', 'c.clinom', 'c.tdicod', 'c.telefono', 'c.clicor', 'c.clidir']);
        $cargos = DB::table('col_cargos as c')->leftJoin('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')->where('c.alu_id', $id)
            ->where(fn ($q) => $q->where('m.anio', $anio)->orWhereNull('c.mat_id'))
            ->orderByRaw("FIELD(c.concepto, 'INSCRIPCION', 'MATRICULA', 'PENSION', 'OTRO')")->orderBy('c.periodo')->orderBy('c.car_id')->get(['c.*']);
        $pagos = DB::table('col_pagos as p')->join('cpe_cabecera as cab', 'cab.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->whereIn('p.car_id', $cargos->pluck('car_id'))->where('p.anulado', 0)
            ->get(['p.car_id', 'p.monto', 'p.descuento', 'p.fecha', 'p.pagador', 'cab.IdCpe_cabecera', 'cab.serdoc', 'cab.numdoc'])->groupBy('car_id');

        return view('empresas.colegio.ver', [
            'cfg' => $cfg, 'anio' => $anio, 'alumno' => $alumno, 'matriculas' => $matriculas, 'matricula' => $matriculas->firstWhere('anio', $anio),
            'responsables' => $responsables, 'cargos' => $cargos, 'pagos' => $pagos,
            'grupo' => $alumno->grupo_id ? DB::table('col_alumnos')->where('grupo_id', $alumno->grupo_id)->where('alu_id', '!=', $id)->orderBy('nombres')->get(['alu_id', 'nombres', 'estado']) : collect(),
            'grados' => $this->grados($suc), 'turno' => Turno::abiertoDe(Auth::user()),
            'comprobantes' => DB::table('tipo_documento')->whereIn('tdocod', ['03', '01', '13'])->orderByRaw("FIELD(tdocod, '03', '01', '13')")->get(['tdocod', 'tdodes']),
            'medios' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
            'esAdmin' => Auth::user()->esAdmin(),
        ]);
    }

    public function cobrar(Request $request, int $id): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate([
            'cargos' => 'required|array|min:1', 'cargos.*' => 'integer', 'tdocod' => 'required|in:01,03,13', 'pagador' => 'nullable|string|max:12',
            'tdicod' => 'nullable|string|size:1', 'clinum' => 'nullable|string|max:15', 'clinom' => 'nullable|string|max:150', 'clidir' => 'nullable|string|max:200',
            'id_med_pag' => 'nullable|array|max:5', 'mon_med_pag' => 'nullable|array|max:5', 'op_med_pag' => 'nullable|array|max:5',
            'paga' => 'nullable|numeric|min:0', 'imprimir' => 'nullable|boolean',
        ]);
        if ($d['tdocod'] === '01' && strlen(preg_replace('/\D/', '', (string) ($d['clinum'] ?? ''))) !== 11) {
            return response()->json(['ok' => false, 'mensaje' => 'Para factura escribe el RUC (11 dígitos) de quien paga.']);
        }
        try {
            $cabId = Colegio::cobrar(Auth::user(), $id, $d['cargos'], $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo registrar el cobro.']);
        }
        $impreso = false;
        if (! empty($d['imprimir'])) {
            try {
                $impreso = Impresion::comprobante($cabId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'impreso' => $impreso] + VentaDirecta::respuesta($cabId));
    }

    /** Pensión gratis (beca, convenio): solo el administrador */
    public function becar(Request $request, int $carId): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate(['motivo' => 'required|string|max:200'], [], ['motivo' => 'motivo']);

        return $this->json(function () use ($suc, $carId, $d) {
            Colegio::becar($suc, $carId, mb_strtoupper($d['motivo']));

            return ['mensaje' => 'Listo: no se cobrará.'];
        });
    }

    public function anularCargo(Request $request, int $carId): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate(['motivo' => 'required|string|max:200'], [], ['motivo' => 'motivo']);
        $n = DB::table('col_cargos')->where('car_id', $carId)->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')->where('pagado', 0)
            ->update(['estado' => 'ANULADO', 'motivo' => mb_strtoupper($d['motivo'])]);

        return response()->json($n ? ['ok' => true, 'mensaje' => 'Cargo anulado.'] : ['ok' => false, 'mensaje' => 'Solo se anula lo que aún no tiene pagos.']);
    }

    /** Otro cobro al alumno: uniforme, actividad, libro, examen… */
    public function agregarCargo(Request $request, int $id): JsonResponse
    {
        $suc = $this->sucursal();
        $d = $request->validate(['descripcion' => 'required|string|max:150', 'monto' => 'required|numeric|gt:0|max:999999', 'vence' => 'nullable|date'],
            [], ['descripcion' => 'concepto', 'monto' => 'monto']);
        abort_unless(DB::table('col_alumnos')->where('alu_id', $id)->where('id_empresa_negocio', $suc)->exists(), 404);
        $mat = DB::table('col_matriculas')->where('alu_id', $id)->orderByDesc('anio')->value('mat_id');
        DB::table('col_cargos')->insert(['alu_id' => $id, 'mat_id' => $mat, 'concepto' => 'OTRO', 'descripcion' => mb_strtoupper($d['descripcion']),
            'monto' => round((float) $d['monto'], 2), 'vence' => $d['vence'] ?? now()->toDateString(), 'estado' => 'PENDIENTE',
            'IdProducto' => Colegio::concepto(Auth::user(), 'OTRO'), 'id_empresa_negocio' => $suc, 'creado' => now()]);

        return response()->json(['ok' => true, 'mensaje' => 'Cargo agregado.']);
    }

    /** Matrícula de otro año (el que pasa de grado): solo matrícula y pensiones, sin inscripción */
    public function matricular(Request $request, int $id): JsonResponse
    {
        $this->sucursal();
        $d = $request->validate(['grado_id' => 'required|integer', 'anio' => 'required|integer|between:2020,2100', 'nuevo' => 'nullable|boolean']);

        return $this->json(function () use ($id, $d) {
            Colegio::matricular(Auth::user(), $id, (int) $d['grado_id'], (int) $d['anio'], (bool) ($d['nuevo'] ?? false));

            return ['mensaje' => "Matriculado en {$d['anio']}."];
        });
    }

    /** El administrador cambia el % de descuento de la pensión (hermanos, referidos, convenio) */
    public function descuento(Request $request, int $matId): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate(['pct' => 'required|numeric|between:0,100']);
        abort_unless(DB::table('col_matriculas')->where('mat_id', $matId)->where('id_empresa_negocio', $suc)->exists(), 404);
        Colegio::cambiarDescuento($matId, (float) $d['pct']);

        return response()->json(['ok' => true, 'mensaje' => 'Descuento actualizado en las pensiones por pagar.']);
    }

    /** Retiro: ya no se le cobran las pensiones que aún no vencen */
    public function retirar(Request $request, int $id): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate(['motivo' => 'required|string|max:200'], [], ['motivo' => 'motivo']);
        abort_unless(DB::table('col_alumnos')->where('alu_id', $id)->where('id_empresa_negocio', $suc)->exists(), 404);
        DB::transaction(function () use ($id, $d) {
            DB::table('col_alumnos')->where('alu_id', $id)->update(['estado' => 'RETIRADO', 'observacion' => mb_substr('RETIRADO: '.$d['motivo'], 0, 255)]);
            DB::table('col_matriculas')->where('alu_id', $id)->where('estado', 'ACTIVA')->update(['estado' => 'RETIRADA']);
            DB::table('col_cargos')->where('alu_id', $id)->where('estado', 'PENDIENTE')->where('pagado', 0)->where('vence', '>=', now()->toDateString())
                ->update(['estado' => 'ANULADO', 'motivo' => mb_substr('RETIRO: '.mb_strtoupper($d['motivo']), 0, 200)]);
        });

        return response()->json(['ok' => true, 'mensaje' => 'Alumno retirado: ya no se le cobrarán las pensiones que faltan.']);
    }

    // ------------------------------------------------------------------ sorteo

    public function sorteo(Request $request): View
    {
        $suc = $this->admin();
        $cfg = Colegio::config($suc);
        $periodos = collect(range((int) $cfg->mes_inicio, (int) $cfg->mes_fin))->map(fn ($m) => sprintf('%04d%02d', $cfg->anio, $m));
        $periodo = $periodos->contains($request->query('periodo')) ? $request->query('periodo')
            : ($periodos->first(fn ($p) => $p >= now()->format('Ym')) ?? $periodos->last());

        return view('empresas.colegio.sorteo', [
            'cfg' => $cfg, 'periodos' => $periodos, 'periodo' => $periodo,
            'candidatos' => Colegio::candidatosSorteo($suc, (int) $cfg->anio, $periodo),
            'historial' => DB::table('col_sorteos as s')->join('col_alumnos as a', 'a.alu_id', '=', 's.alu_id')->where('s.id_empresa_negocio', $suc)
                ->orderByDesc('s.sor_id')->limit(20)->get(['s.*', 'a.nombres']),
        ]);
    }

    public function sortear(Request $request): JsonResponse
    {
        $this->admin();
        $d = $request->validate(['periodo' => 'required|digits:6']);

        return $this->json(function () use ($d) {
            $g = Colegio::sortear(Auth::user(), $d['periodo']);

            return ['ganador' => ['id' => $g->alu_id, 'nombre' => $g->nombres, 'grado' => Colegio::etiquetaGrado($g), 'monto' => (float) $g->monto,
                'candidatos' => $g->candidatos, 'mes' => Colegio::nombreMes($d['periodo'])]];
        });
    }
}
