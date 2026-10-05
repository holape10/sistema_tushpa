<?php
namespace App\Http\Controllers;

use App\Support\{Asistencia, Excel};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\ValidationException;

/** Asistencia: turnos, matriz de horarios, motivos, configuración y reportes (tareo y jornadas) */
class AsistenciaAdminController extends Controller
{
    private const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    private const LETRA_DIA = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador gestiona la asistencia.');
    }

    private function listaTurnos()
    {
        Asistencia::asegurarTurnos($this->sucursal());
        return DB::table('asistencia_turnos')->where('id_empresa_negocio', $this->sucursal())
            ->orderByRaw("FIELD(tipo, 'TRABAJO', 'DESCANSO', 'LEYENDA')")->orderBy('hora_entrada_1')->orderBy('codigo')->get();
    }

    private function descargar(Excel $x, string $nombre)
    {
        return response()->download($x->guardar(), $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    // ================================================================== TURNOS

    public function turnos()
    {
        $this->autorizar();
        $turnos = $this->listaTurnos();
        $enUso = DB::table('asistencia_horarios')->whereIn('turno_id', $turnos->pluck('id'))->distinct()->pluck('turno_id')->flip();
        return view('empresas.asistencia.turnos', compact('turnos', 'enUso'));
    }

    private function datosTurno(Request $request, ?int $id = null): array
    {
        $d = $request->validate([
            'codigo'             => 'required|string|max:5',
            'descripcion'        => 'required|string|max:100',
            'tipo'               => 'required|in:TRABAJO,DESCANSO,LEYENDA',
            'hora_entrada_1'     => 'nullable|required_if:tipo,TRABAJO|date_format:H:i',
            'hora_salida_1'      => 'nullable|required_if:tipo,TRABAJO|date_format:H:i',
            'hora_entrada_2'     => 'nullable|date_format:H:i|required_with:hora_salida_2',
            'hora_salida_2'      => 'nullable|date_format:H:i|required_with:hora_entrada_2',
            'tolerancia_minutos' => 'nullable|integer|min:0|max:120',
            'color'              => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
        ], ['hora_entrada_1.required_if' => 'Un turno de trabajo necesita hora de entrada.', 'hora_salida_1.required_if' => 'Un turno de trabajo necesita hora de salida.'],
            ['hora_entrada_1' => 'entrada', 'hora_salida_1' => 'salida', 'hora_entrada_2' => 'retorno', 'hora_salida_2' => 'salida final']);

        $d['codigo'] = mb_strtoupper(trim($d['codigo']));
        $d['descripcion'] = mb_strtoupper(trim($d['descripcion']));
        $d['tolerancia_minutos'] = (int) ($d['tolerancia_minutos'] ?? 0);
        $d['color'] = $d['color'] ?? '#6366f1';
        if ($d['tipo'] !== 'TRABAJO') {
            $d = array_merge($d, ['hora_entrada_1' => null, 'hora_salida_1' => null, 'hora_entrada_2' => null, 'hora_salida_2' => null]);
        }

        $repetido = DB::table('asistencia_turnos')->where('id_empresa_negocio', $this->sucursal())->where('codigo', $d['codigo'])
            ->when($id, fn($w) => $w->where('id', '!=', $id))->exists();
        if ($repetido) {
            throw ValidationException::withMessages(['codigo' => "Ya existe un turno con el código {$d['codigo']}."]);
        }

        // Duración: avisa (no bloquea) si un turno de trabajo no llega a 8 horas
        if ($d['tipo'] === 'TRABAJO') {
            $t = (object) $d;
            $min = 0;
            foreach ([['hora_entrada_1', 'hora_salida_1'], ['hora_entrada_2', 'hora_salida_2']] as [$a, $b]) {
                if ($t->$a && $t->$b) {
                    $ini = Carbon::parse('2000-01-01 ' . $t->$a);
                    $fin = Carbon::parse('2000-01-01 ' . $t->$b);
                    if ($fin->lte($ini)) {
                        $fin->addDay();
                    }
                    $min += $ini->diffInMinutes($fin);
                }
            }
            if ($min < Asistencia::JORNADA_MINUTOS) {
                session()->flash('aviso', "El turno {$d['codigo']} suma " . Asistencia::horas((int) $min) . ' (menos de 8 horas). Se guardó igual por si es medio tiempo.');
            }
        }
        return $d;
    }

    public function turnoGuardar(Request $request)
    {
        $this->autorizar();
        $d = $this->datosTurno($request);
        DB::table('asistencia_turnos')->insert($d + ['id_empresa_negocio' => $this->sucursal(), 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', "Turno {$d['codigo']} creado.");
    }

    public function turnoActualizar(Request $request, int $id)
    {
        $this->autorizar();
        abort_unless(DB::table('asistencia_turnos')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->exists(), 404);
        $d = $this->datosTurno($request, $id);
        DB::table('asistencia_turnos')->where('id', $id)->update($d + ['updated_at' => now()]);
        return back()->with('success', "Turno {$d['codigo']} actualizado.");
    }

    public function turnoEliminar(int $id)
    {
        $this->autorizar();
        $t = DB::table('asistencia_turnos')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        abort_unless($t, 404);
        if (DB::table('asistencia_horarios')->where('turno_id', $id)->exists()) {
            return back()->withErrors(['turno' => "El turno {$t->codigo} está asignado en la matriz de horarios; quítalo de ahí primero."]);
        }
        DB::table('asistencia_turnos')->where('id', $id)->delete();
        return back()->with('success', "Turno {$t->codigo} eliminado.");
    }

    // ================================================================== MATRIZ SEMANAL

    public function matriz(Request $request)
    {
        $this->autorizar();
        $inicio = Carbon::parse($request->get('semana', now()->toDateString()))->startOfWeek();
        $dias = collect(range(0, 6))->map(fn($i) => $inicio->copy()->addDays($i));
        $empleados = Asistencia::empleados($this->sucursal());
        $turnos = $this->listaTurnos();

        $asignados = DB::table('asistencia_horarios')->whereIn('emp_id', $empleados->pluck('emp_id'))
            ->whereBetween('fecha', [$dias->first()->toDateString(), $dias->last()->toDateString()])->get()
            ->groupBy('emp_id')->map(fn($g) => $g->pluck('turno_id', 'fecha'));
        $feriados = Asistencia::feriados($dias->first()->toDateString(), $dias->last()->toDateString(), $this->sucursal());

        if ($request->get('excel')) {
            $porId = $turnos->keyBy('id');
            $filas = $empleados->map(fn($e) => array_merge([Asistencia::nombre($e), $e->emp_num_doc],
                $dias->map(fn($d) => ($t = $porId[$asignados[$e->emp_id][$d->toDateString()] ?? 0] ?? null)
                    ? $t->codigo . ($t->hora_entrada_1 ? ' ' . substr($t->hora_entrada_1, 0, 5) . '-' . substr($t->hora_salida_2 ?: $t->hora_salida_1, 0, 5) : '') : '')->all()))->all();
            $cab = array_merge(['Trabajador', 'DNI'], $dias->map(fn($d) => self::DIAS[$d->dayOfWeekIso - 1] . ' ' . $d->format('d/m'))->all());
            return $this->descargar((new Excel())->hoja('Horarios', $cab, $filas), 'horarios_' . $inicio->format('Ymd') . '.xlsx');
        }

        return view('empresas.asistencia.matriz', [
            'inicio' => $inicio, 'dias' => $dias, 'nombresDias' => self::DIAS, 'empleados' => $empleados,
            'turnos' => $turnos, 'asignados' => $asignados, 'feriados' => $feriados,
        ]);
    }

    public function matrizGuardar(Request $request)
    {
        $this->autorizar();
        $request->validate(['semana' => 'required|date', 'horario' => 'nullable|array']);
        $empleados = Asistencia::empleados($this->sucursal())->pluck('emp_id')->flip();
        $turnos = DB::table('asistencia_turnos')->where('id_empresa_negocio', $this->sucursal())->pluck('id')->flip();
        $inicio = Carbon::parse($request->semana)->startOfWeek();
        $validas = collect(range(0, 6))->map(fn($i) => $inicio->copy()->addDays($i)->toDateString())->flip();

        DB::transaction(function () use ($request, $empleados, $turnos, $validas) {
            foreach ((array) $request->horario as $empId => $fechas) {
                if (!isset($empleados[$empId])) {
                    continue;
                }
                foreach ((array) $fechas as $fecha => $turnoId) {
                    if (!isset($validas[$fecha])) {
                        continue;
                    }
                    if ($turnoId && isset($turnos[$turnoId])) {
                        DB::table('asistencia_horarios')->updateOrInsert(['emp_id' => $empId, 'fecha' => $fecha],
                            ['turno_id' => $turnoId, 'updated_at' => now(), 'created_at' => now()]);
                    } else {
                        DB::table('asistencia_horarios')->where('emp_id', $empId)->where('fecha', $fecha)->delete();
                    }
                }
            }
        });

        return redirect()->route('asistencia.matriz', ['semana' => $inicio->toDateString()])->with('success', 'Horarios de la semana guardados.');
    }

    /** Copia la semana anterior en la semana mostrada (no pisa lo que ya está asignado salvo que se pida) */
    public function matrizCopiar(Request $request)
    {
        $this->autorizar();
        $request->validate(['semana' => 'required|date']);
        $inicio = Carbon::parse($request->semana)->startOfWeek();
        $empleados = Asistencia::empleados($this->sucursal())->pluck('emp_id');
        $origen = DB::table('asistencia_horarios')->whereIn('emp_id', $empleados)
            ->whereBetween('fecha', [$inicio->copy()->subWeek()->toDateString(), $inicio->copy()->subDay()->toDateString()])->get();
        if ($origen->isEmpty()) {
            return back()->withErrors(['matriz' => 'La semana anterior no tiene horarios para copiar.']);
        }
        $pisar = $request->boolean('reemplazar');
        $n = 0;
        foreach ($origen as $h) {
            $fecha = Carbon::parse($h->fecha)->addWeek()->toDateString();
            $existe = DB::table('asistencia_horarios')->where('emp_id', $h->emp_id)->where('fecha', $fecha)->exists();
            if ($existe && !$pisar) {
                continue;
            }
            DB::table('asistencia_horarios')->updateOrInsert(['emp_id' => $h->emp_id, 'fecha' => $fecha],
                ['turno_id' => $h->turno_id, 'updated_at' => now(), 'created_at' => now()]);
            $n++;
        }
        return redirect()->route('asistencia.matriz', ['semana' => $inicio->toDateString()])->with('success', "Se copiaron {$n} horarios de la semana anterior.");
    }

    // ================================================================== MOTIVOS

    public function motivos()
    {
        $this->autorizar();
        $motivos = DB::table('asistencia_motivos')->where('id_empresa_negocio', $this->sucursal())->orderBy('descripcion')->get();
        return view('empresas.asistencia.motivos', compact('motivos'));
    }

    public function motivoGuardar(Request $request, ?int $id = null)
    {
        $this->autorizar();
        $d = $request->validate(['descripcion' => 'required|string|max:100', 'estado' => 'nullable|in:Activo,Inactivo']);
        $datos = ['descripcion' => mb_strtoupper(trim($d['descripcion'])), 'estado' => $d['estado'] ?? 'Activo', 'updated_at' => now()];
        if ($id) {
            DB::table('asistencia_motivos')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->update($datos);
        } else {
            DB::table('asistencia_motivos')->insert($datos + ['id_empresa_negocio' => $this->sucursal(), 'created_at' => now()]);
        }
        return back()->with('success', 'Motivo guardado.');
    }

    public function motivoEliminar(int $id)
    {
        $this->autorizar();
        DB::table('asistencia_motivos')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->delete();
        return back()->with('success', 'Motivo eliminado.');
    }

    // ================================================================== CONFIGURACIÓN (IP y feriados)

    public function configuracion(Request $request)
    {
        $this->autorizar();
        $anio = (int) $request->get('anio', now()->year);
        return view('empresas.asistencia.configuracion', [
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->first(),
            'miIp' => $request->ip(), 'anio' => $anio,
            'nacionales' => Asistencia::feriados("$anio-01-01", "$anio-12-31", 0),
            'propios' => DB::table('feriados')->where('id_empresa_negocio', $this->sucursal())->whereYear('fecha', $anio)->orderBy('fecha')->get(),
            'empleados' => Asistencia::empleados($this->sucursal())->count(),
        ]);
    }

    public function configuracionIp(Request $request)
    {
        $this->autorizar();
        $ips = collect(explode(',', (string) $request->input('ip_asistencia')))->map(fn($i) => trim($i))->filter();
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return back()->withErrors(['ip' => "\"{$ip}\" no es una IP válida."]);
            }
        }
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal())->update(['ip_asistencia' => $ips->implode(',') ?: null]);
        return back()->with('success', $ips->isEmpty() ? 'Restricción por IP desactivada: se puede marcar desde cualquier red.' : 'IP permitidas: ' . $ips->implode(', '));
    }

    public function feriadoGuardar(Request $request)
    {
        $this->autorizar();
        $d = $request->validate(['fecha' => 'required|date', 'descripcion' => 'required|string|max:100']);
        DB::table('feriados')->insert(['id_empresa_negocio' => $this->sucursal(), 'fecha' => $d['fecha'],
            'descripcion' => mb_strtoupper(trim($d['descripcion'])), 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Feriado agregado.');
    }

    public function feriadoEliminar(int $id)
    {
        $this->autorizar();
        DB::table('feriados')->where('id', $id)->where('id_empresa_negocio', $this->sucursal())->delete();
        return back()->with('success', 'Feriado eliminado.');
    }

    // ================================================================== REPORTES

    private function rango(Request $request, string $desde, string $hasta): array
    {
        $d = Carbon::parse($request->get('desde', $desde))->toDateString();
        $h = Carbon::parse($request->get('hasta', $hasta))->toDateString();
        if ($h < $d) {
            [$d, $h] = [$h, $d];
        }
        // Límite razonable para el tareo (una fila por día)
        if (Carbon::parse($d)->diffInDays(Carbon::parse($h)) > 62) {
            $h = Carbon::parse($d)->addDays(62)->toDateString();
        }
        return [$d, $h];
    }

    /** Tareo: matriz trabajador × día con la leyenda de cada día y totales */
    public function tareo(Request $request)
    {
        $this->autorizar();
        [$desde, $hasta] = $this->rango($request, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $sucursal = $this->sucursal();
        $todos = Asistencia::empleados($sucursal);
        $empleados = $request->filled('emp') ? $todos->where('emp_id', (int) $request->emp)->values() : $todos;
        $ids = $empleados->pluck('emp_id');

        $fechas = [];
        for ($f = Carbon::parse($desde); $f->lte(Carbon::parse($hasta)); $f->addDay()) {
            $fechas[] = ['fecha' => $f->toDateString(), 'num' => $f->format('d'), 'letra' => self::LETRA_DIA[$f->dayOfWeek]];
        }
        $asist = DB::table('asistencias')->whereIn('emp_id', $ids)->whereBetween('fecha', [$desde, $hasta])->get()
            ->groupBy('emp_id')->map(fn($g) => $g->keyBy('fecha'));
        $horarios = DB::table('asistencia_horarios as h')->join('asistencia_turnos as t', 't.id', '=', 'h.turno_id')
            ->whereIn('h.emp_id', $ids)->whereBetween('h.fecha', [$desde, $hasta])->select('h.emp_id', 'h.fecha', 't.codigo', 't.tipo')->get()
            ->groupBy('emp_id')->map(fn($g) => $g->keyBy('fecha'));
        $feriados = Asistencia::feriados($desde, $hasta, $sucursal);
        $hoy = now()->toDateString();

        $leyendas = ['1' => 'Asistió', 'T' => 'Tardanza', '0' => 'Falta', 'D' => 'Descanso', 'F' => 'Feriado'];
        $matriz = [];
        foreach ($empleados as $e) {
            $dias = [];
            $tot = [];
            foreach ($fechas as $f) {
                $a = $asist[$e->emp_id][$f['fecha']] ?? null;
                $h = $horarios[$e->emp_id][$f['fecha']] ?? null;
                if ($a && $a->check_in_1) {
                    $letra = $a->tardanza_minutos > 0 ? 'T' : '1';
                } elseif ($f['fecha'] > $hoy) {
                    $letra = $h ? ($h->tipo === 'TRABAJO' ? '' : $h->codigo) : '';
                } elseif ($h && $h->tipo === 'LEYENDA') {
                    $letra = $h->codigo;
                    $leyendas[$h->codigo] ??= DB::table('asistencia_turnos')->where('id_empresa_negocio', $sucursal)->where('codigo', $h->codigo)->value('descripcion');
                } elseif (isset($feriados[$f['fecha']])) {
                    $letra = 'F';
                } elseif ($h && $h->tipo === 'DESCANSO') {
                    $letra = 'D';
                } elseif ($h) {
                    $letra = '0';
                } else {
                    $letra = '-';   // sin horario y sin marcación
                }
                $dias[$f['fecha']] = $letra;
                if ($letra !== '' && $letra !== '-') {
                    $tot[$letra] = ($tot[$letra] ?? 0) + 1;
                }
            }
            $matriz[] = ['empleado' => $e, 'dias' => $dias, 'totales' => $tot];
        }

        if ($request->get('excel')) {
            $cols = array_keys($leyendas);
            $cab = array_merge(['Trabajador', 'DNI'], array_map(fn($f) => $f['num'] . ' ' . $f['letra'], $fechas), array_map(fn($c) => "Total $c", $cols));
            $filas = array_map(fn($m) => array_merge([Asistencia::nombre($m['empleado']), (string) $m['empleado']->emp_num_doc],
                array_values($m['dias']), array_map(fn($c) => (int) ($m['totales'][$c] ?? 0), $cols)), $matriz);
            $ley = collect($leyendas)->map(fn($d, $c) => [$c, $d])->values()->all();
            return $this->descargar((new Excel())->hoja('Tareo', $cab, $filas)->hoja('Leyenda', ['Código', 'Significado'], $ley),
                "tareo_{$desde}_al_{$hasta}.xlsx");
        }

        return view('empresas.asistencia.tareo', compact('matriz', 'fechas', 'desde', 'hasta', 'todos', 'leyendas', 'feriados'));
    }

    /** Jornadas: detalle por día con horas laboradas, tardanza, horas extra y si cumplió su jornada */
    public function jornadas(Request $request)
    {
        $this->autorizar();
        [$desde, $hasta] = $this->rango($request, now()->startOfWeek()->toDateString(), now()->toDateString());
        $sucursal = $this->sucursal();
        $todos = Asistencia::empleados($sucursal);

        $registros = DB::table('asistencias as a')
            ->join('empleado as e', 'e.emp_id', '=', 'a.emp_id')
            ->leftJoin('asistencia_turnos as t', 't.id', '=', 'a.turno_id')
            ->where('a.id_empresa_negocio', $sucursal)->whereBetween('a.fecha', [$desde, $hasta])
            ->when($request->filled('emp'), fn($w) => $w->where('a.emp_id', (int) $request->emp))
            ->orderByDesc('a.fecha')->orderBy('e.emp_nom')
            ->select('a.*', 'e.emp_nom', 'e.emp_ape_pat', 'e.emp_ape_mat', 'e.emp_num_doc',
                't.codigo', 't.tipo', 't.hora_entrada_1', 't.hora_salida_1', 't.hora_entrada_2', 't.hora_salida_2', 't.tolerancia_minutos', 't.color')
            ->get()
            ->map(function ($a) {
                $turno = $a->codigo ? (object) ['tipo' => $a->tipo, 'hora_entrada_1' => $a->hora_entrada_1, 'hora_salida_1' => $a->hora_salida_1,
                    'hora_entrada_2' => $a->hora_entrada_2, 'hora_salida_2' => $a->hora_salida_2, 'tolerancia_minutos' => $a->tolerancia_minutos] : null;
                $a->calc = Asistencia::jornada($a, $turno);
                return $a;
            });

        $totales = [
            'laborado' => $registros->sum(fn($r) => $r->calc['laborado']), 'tardanza' => $registros->sum(fn($r) => $r->calc['tardanza']),
            'extra' => $registros->sum(fn($r) => $r->calc['extra']), 'conformes' => $registros->filter(fn($r) => $r->calc['conforme'])->count(),
        ];

        if ($request->get('excel')) {
            $h = fn($c) => $c ? Carbon::parse($c)->format('H:i') : '';
            $filas = $registros->map(fn($r) => [Carbon::parse($r->fecha)->format('d/m/Y'), Asistencia::nombre($r), (string) $r->emp_num_doc, $r->codigo ?? 'SIN HORARIO',
                $h($r->check_in_1), $h($r->check_out_1), $h($r->check_in_2), $h($r->check_out_2),
                Asistencia::horas($r->calc['laborado']), Asistencia::horas($r->calc['tardanza']), Asistencia::horas($r->calc['extra']),
                $r->calc['abierta'] ? 'EN CURSO' : ($r->calc['conforme'] ? 'CONFORME' : 'INCOMPLETO'), $r->autorizado_por ?? '', $r->motivo ?? ''])->all();
            return $this->descargar((new Excel())->hoja('Jornadas', ['Fecha', 'Trabajador', 'DNI', 'Turno', 'Entrada', 'Salida', 'Retorno', 'Salida final',
                'Laborado', 'Tardanza', 'Extra', 'Estado', 'Autorizado por', 'Motivo'], $filas), "jornadas_{$desde}_al_{$hasta}.xlsx");
        }

        return view('empresas.asistencia.jornadas', compact('registros', 'desde', 'hasta', 'todos', 'totales'));
    }
}
