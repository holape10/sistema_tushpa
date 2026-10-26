<?php

namespace App\Http\Controllers;

use App\Support\Gimnasio;
use App\Support\GimnasioRutina;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Panel del entrenador: sus clientes, el historial de cada uno (medidas, IMC, asistencia) y los planes de nutrición.
 * Lo que registra le aparece al cliente en su portal ({subdominio}/socio).
 * Entran el rol Entrenador y el Administrador; cada entrenador solo modifica lo que él registró.
 */
class EntrenadorController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Gimnasio::esEntrenador(Auth::user()) || Auth::user()->esAdmin(), 403, 'Solo entrenadores o el administrador.');
    }

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index()
    {
        $this->autorizar();
        $suc = $this->sucursal();
        $mems = Gimnasio::membresiasVigentes($suc);
        $cong = Gimnasio::congelamientosVigentes($mems->flatten()->pluck('mem_id')->all());
        // Solo los clientes de esta sucursal
        $ultimos = DB::table('gym_nutricion as n')->join('socios as s', 's.soc_id', '=', 'n.soc_id')->where('s.id_empresa_negocio', $suc)
            ->where('n.activo', 1)->groupBy('n.soc_id')->select('n.soc_id', DB::raw('MAX(n.fecha) as fecha'))->pluck('fecha', 'soc_id');
        // Quién ya tiene su rutina de este mes
        $hoy = now()->toDateString();
        $rutinas = DB::table('gym_rutinas as r')->join('socios as s', 's.soc_id', '=', 'r.soc_id')->where('s.id_empresa_negocio', $suc)
            ->where('r.activo', 1)->where('r.desde', '<=', $hoy)->where('r.hasta', '>=', $hoy)->pluck('r.rut_id', 'r.soc_id');

        $clientes = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('s.id_empresa_negocio', $suc)->where('s.estado', 'ACTIVO')->orderBy('c.clinom')
            ->get(['s.soc_id', 's.codigo', 's.foto', 's.entrenador_id', 's.fecha_nac', 'c.clinom', 'c.clinum', 'c.telefono'])
            ->map(function ($s) use ($mems, $cong, $ultimos, $rutinas) {
                $sit = Gimnasio::situacion($mems->get($s->soc_id, collect()), $cong);

                return ['soc_id' => $s->soc_id, 'codigo' => $s->codigo, 'nombre' => $s->clinom, 'doc' => $s->clinum, 'telefono' => $s->telefono,
                    'foto' => $s->foto && is_file(public_path($s->foto)) ? asset($s->foto) : null, 'mio' => (int) $s->entrenador_id === (int) Auth::id(),
                    'edad' => $s->fecha_nac ? Carbon::parse($s->fecha_nac)->age : null, 'estado' => $sit['texto'], 'color' => $sit['color'],
                    'ultimo_plan' => $ultimos[$s->soc_id] ?? null, 'vip' => ! empty($sit['membresia']->vip) && in_array($sit['estado'], ['VIGENTE', 'POR_VENCER'], true),
                    'rutina_mes' => isset($rutinas[$s->soc_id])];
            })->values();

        return view('empresas.gimnasio.entrenador', [
            'clientes' => $clientes, 'objetivos' => Gimnasio::OBJETIVOS, 'yo' => Auth::id(), 'esAdmin' => Auth::user()->esAdmin(),
            'antecedentes' => GimnasioRutina::ANTECEDENTES, 'calentamiento' => GimnasioRutina::CALENTAMIENTO,
            'perimetros' => GimnasioRutina::PERIMETROS, 'diasSemana' => GimnasioRutina::DIAS,
        ]);
    }

    private function socio(int $id): object
    {
        $s = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('s.soc_id', $id)->where('s.id_empresa_negocio', $this->sucursal())
            ->first(['s.soc_id', 's.codigo', 's.foto', 's.fecha_nac', 's.entrenador_id', 'c.clinom', 'c.clinum', 'c.telefono']);
        abort_unless($s, 404);

        return $s;
    }

    /** Historial del cliente: planes de nutrición con medidas y su asistencia del último mes */
    public function cliente(int $id)
    {
        $this->autorizar();
        $s = $this->socio($id);
        $sit = Gimnasio::situacionDe($id, $this->sucursal());

        return response()->json([
            'cliente' => ['soc_id' => $s->soc_id, 'nombre' => $s->clinom, 'doc' => $s->clinum, 'telefono' => $s->telefono,
                'edad' => $s->fecha_nac ? Carbon::parse($s->fecha_nac)->age : null, 'mio' => (int) $s->entrenador_id === (int) Auth::id(),
                'foto' => $s->foto && is_file(public_path($s->foto)) ? asset($s->foto) : null,
                'estado' => $sit['texto'], 'color' => $sit['color'], 'plan' => $sit['membresia']->plan ?? null,
                'vence' => $sit['vence'] ? Carbon::parse($sit['vence'])->format('d/m/Y') : null],
            'nutricion' => GimnasioController::nutricionDe($id),
            'asistencias' => DB::table('gym_asistencias')->where('soc_id', $id)->where('resultado', 'PERMITIDO')
                ->where('fecha_hora', '>=', now()->subDays(30))->count(),
            'ficha' => GimnasioRutina::ficha($id),
            'medidas' => GimnasioRutina::medidas($id),
            'rutinas' => GimnasioRutina::rutinas($id),
            'hoja' => route('entrenador.hoja', $id),
        ]);
    }

    /** Ficha de evaluación: objetivo, antecedentes médicos y observaciones importantes */
    public function guardarFicha(Request $request, int $id)
    {
        $this->autorizar();
        $this->socio($id);
        $d = $request->validate([
            'objetivo' => 'nullable|in:'.implode(',', Gimnasio::OBJETIVOS), 'antecedentes' => 'nullable|array|max:20',
            'antecedentes.*' => 'string|in:'.implode(',', array_keys(GimnasioRutina::ANTECEDENTES)),
            'otros' => 'nullable|string|max:150', 'observaciones' => 'nullable|string|max:500',
        ]);
        GimnasioRutina::guardarFicha($id, (int) Auth::id(), $d);

        return response()->json(['ok' => true, 'mensaje' => 'Ficha guardada.']);
    }

    /** Medidas de una fecha (el IMC se calcula con el peso y la estatura) */
    public function guardarMedida(Request $request, int $id)
    {
        $this->autorizar();
        $s = $this->socio($id);
        $reglas = ['med_id' => 'nullable|integer', 'fecha' => 'required|date|before_or_equal:today', 'edad' => 'nullable|integer|min:5|max:110',
            'peso' => 'nullable|numeric|min:20|max:400', 'estatura' => 'nullable|numeric|min:0.8|max:2.6', 'grasa' => 'nullable|numeric|min:1|max:80'];
        foreach (array_keys(GimnasioRutina::PERIMETROS) as $c) {
            $reglas[$c] = 'nullable|numeric|min:5|max:250';
        }
        $d = $request->validate($reglas, [], ['estatura' => 'estatura (en metros, ej. 1.70)', 'grasa' => '% de grasa']);
        $fila = collect($d)->except('med_id')->map(fn ($v) => $v === '' ? null : $v)->all();
        if (! collect($fila)->except(['fecha', 'edad'])->filter(fn ($v) => $v !== null)->count()) {
            return response()->json(['ok' => false, 'mensaje' => 'Escribe al menos una medida.']);
        }
        $fila['edad'] ??= $s->fecha_nac ? Carbon::parse($s->fecha_nac)->age : null;

        if (! empty($d['med_id'])) {
            $hecho = DB::table('gym_medidas')->where('med_id', $d['med_id'])->where('soc_id', $id)->update($fila + ['IdUsuario' => Auth::id()]);
            if (! $hecho && ! DB::table('gym_medidas')->where('med_id', $d['med_id'])->where('soc_id', $id)->exists()) {
                return response()->json(['ok' => false, 'mensaje' => 'Medida no encontrada.']);
            }
        } else {
            DB::table('gym_medidas')->insert($fila + ['soc_id' => $id, 'IdUsuario' => Auth::id(), 'creado' => now()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Medidas guardadas: el cliente ya las ve en su portal.']);
    }

    public function quitarMedida(int $medId)
    {
        $this->autorizar();
        $m = DB::table('gym_medidas as m')->join('socios as s', 's.soc_id', '=', 'm.soc_id')
            ->where('m.med_id', $medId)->where('s.id_empresa_negocio', $this->sucursal())->first(['m.med_id']);
        abort_unless($m, 404);
        DB::table('gym_medidas')->where('med_id', $medId)->delete();

        return response()->json(['ok' => true, 'mensaje' => 'Medida quitada.']);
    }

    /** Rutina del mes: calentamiento y ejercicios por día. El cliente la ve en su portal */
    public function guardarRutina(Request $request, int $id)
    {
        $this->autorizar();
        $this->socio($id);
        $d = $request->validate([
            'rut_id' => 'nullable|integer', 'mes' => 'required|date_format:Y-m', 'objetivo' => 'nullable|in:'.implode(',', Gimnasio::OBJETIVOS),
            'calentamiento' => 'nullable|array', 'calentamiento.*' => 'nullable|integer|min:0|max:120',
            'dias' => 'required|array|max:7', 'dias.*.dia' => 'nullable|string|max:20', 'dias.*.grupo' => 'nullable|string|max:60',
            'dias.*.ejercicios' => 'nullable|array|max:20', 'dias.*.ejercicios.*.nombre' => 'nullable|string|max:80',
            'dias.*.ejercicios.*.series' => 'nullable|string|max:10', 'dias.*.ejercicios.*.reps' => 'nullable|string|max:20',
            'dias.*.ejercicios.*.nota' => 'nullable|string|max:80', 'indicaciones' => 'nullable|string|max:2000',
        ], [], ['mes' => 'mes de la rutina']);
        $dias = GimnasioRutina::limpiarDias($d['dias']);
        if (! $dias) {
            return response()->json(['ok' => false, 'mensaje' => 'Escribe al menos un día con sus ejercicios.']);
        }
        [$desde, $hasta] = GimnasioRutina::mesDe($d['mes'].'-01');
        $fila = ['desde' => $desde, 'hasta' => $hasta, 'objetivo' => $d['objetivo'] ?? null,
            'calentamiento' => json_encode(GimnasioRutina::limpiarCalentamiento($d['calentamiento'] ?? [])),
            'dias' => json_encode($dias, JSON_UNESCAPED_UNICODE), 'indicaciones' => trim((string) ($d['indicaciones'] ?? '')) ?: null];

        if (! empty($d['rut_id'])) {
            $r = DB::table('gym_rutinas')->where('rut_id', $d['rut_id'])->where('soc_id', $id)->where('activo', 1)->first();
            if (! $r) {
                return response()->json(['ok' => false, 'mensaje' => 'Rutina no encontrada.']);
            }
            if ((int) $r->IdUsuario !== (int) Auth::id() && ! Auth::user()->esAdmin()) {
                return response()->json(['ok' => false, 'mensaje' => 'Solo el entrenador que hizo la rutina puede editarla.']);
            }
            DB::table('gym_rutinas')->where('rut_id', $r->rut_id)->update($fila);
        } else {
            // Una rutina por mes: la nueva reemplaza a la que hubiera de ese mes
            DB::table('gym_rutinas')->where('soc_id', $id)->where('activo', 1)->where('desde', $desde)->update(['activo' => 0]);
            DB::table('gym_rutinas')->insert($fila + ['soc_id' => $id, 'IdUsuario' => Auth::id(), 'activo' => 1, 'creado' => now()]);
            DB::table('socios')->where('soc_id', $id)->whereNull('entrenador_id')->update(['entrenador_id' => Auth::id()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Rutina guardada: el cliente ya la ve en su cuenta.']);
    }

    public function quitarRutina(int $rutId)
    {
        $this->autorizar();
        $r = DB::table('gym_rutinas as r')->join('socios as s', 's.soc_id', '=', 'r.soc_id')
            ->where('r.rut_id', $rutId)->where('s.id_empresa_negocio', $this->sucursal())->first(['r.*']);
        abort_unless($r, 404);
        if ((int) $r->IdUsuario !== (int) Auth::id() && ! Auth::user()->esAdmin()) {
            return response()->json(['ok' => false, 'mensaje' => 'Solo el entrenador que hizo la rutina puede quitarla.']);
        }
        DB::table('gym_rutinas')->where('rut_id', $rutId)->update(['activo' => 0]);

        return response()->json(['ok' => true, 'mensaje' => 'Rutina quitada.']);
    }

    /** Hoja para imprimir, como la del gimnasio: datos, antecedentes, medidas (últimas 5 fechas), calentamiento y rutina del mes */
    public function hoja(int $id)
    {
        $this->autorizar();
        $s = $this->socio($id);
        $negocio = DB::table('empresa_negocios as n')->join('empresa as e', 'e.IdEmpresa', '=', 'n.IdEmpresa')
            ->where('n.id_empresa_negocio', $this->sucursal())->first(['n.nombre_comercial', 'n.logo_suc', 'e.NomEmpresa', 'e.LogEmpresa']);
        $rutinas = GimnasioRutina::rutinas($id);

        return view('empresas.gimnasio.hoja', [
            'socio' => $s, 'negocio' => $negocio, 'ficha' => GimnasioRutina::ficha($id),
            'medidas' => GimnasioRutina::medidas($id)->take(5)->reverse()->values(),
            'rutina' => $rutinas->firstWhere('vigente', true) ?? $rutinas->first(),
            'inicio' => DB::table('gym_membresias')->where('soc_id', $id)->where('estado', 'ACTIVA')->min('inicio'),
            'antecedentes' => GimnasioRutina::ANTECEDENTES, 'calentamiento' => GimnasioRutina::CALENTAMIENTO, 'perimetros' => GimnasioRutina::PERIMETROS,
        ]);
    }

    /** El entrenador toma (o suelta) al cliente: le aparece en "Mis clientes" */
    public function asignar(Request $request, int $id)
    {
        $this->autorizar();
        $s = $this->socio($id);
        $tomar = $request->boolean('tomar');
        if (! $tomar && (int) $s->entrenador_id !== (int) Auth::id() && ! Auth::user()->esAdmin()) {
            return response()->json(['ok' => false, 'mensaje' => 'Ese cliente no es tuyo.']);
        }
        DB::table('socios')->where('soc_id', $id)->update(['entrenador_id' => $tomar ? Auth::id() : null]);

        return response()->json(['ok' => true, 'mensaje' => $tomar ? 'Ahora es tu cliente.' : 'Ya no está en tus clientes.']);
    }

    public function guardarNutricion(Request $request, int $id)
    {
        $this->autorizar();
        $this->socio($id);
        $d = $request->validate([
            'nut_id' => 'nullable|integer', 'fecha' => 'required|date|before_or_equal:today',
            'objetivo' => 'required|in:'.implode(',', Gimnasio::OBJETIVOS),
            'peso' => 'nullable|numeric|min:20|max:400', 'talla' => 'nullable|numeric|min:0.8|max:2.6',
            'grasa' => 'nullable|numeric|min:1|max:80', 'calorias' => 'nullable|integer|min:500|max:9000',
            'desayuno' => 'nullable|string|max:2000', 'media_manana' => 'nullable|string|max:2000', 'almuerzo' => 'nullable|string|max:2000',
            'media_tarde' => 'nullable|string|max:2000', 'cena' => 'nullable|string|max:2000', 'indicaciones' => 'nullable|string|max:3000',
        ], [], ['talla' => 'talla (en metros, ej. 1.70)', 'grasa' => '% de grasa', 'media_manana' => 'media mañana']);

        if (! collect(['desayuno', 'media_manana', 'almuerzo', 'media_tarde', 'cena', 'indicaciones'])->first(fn ($k) => trim((string) ($d[$k] ?? '')) !== '')) {
            return response()->json(['ok' => false, 'mensaje' => 'Escribe al menos una comida o las indicaciones.']);
        }
        $fila = collect($d)->except('nut_id')->map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v)->all();

        if (! empty($d['nut_id'])) {
            $n = DB::table('gym_nutricion')->where('nut_id', $d['nut_id'])->where('soc_id', $id)->where('activo', 1)->first();
            if (! $n) {
                return response()->json(['ok' => false, 'mensaje' => 'Plan no encontrado.']);
            }
            if ((int) $n->IdUsuario !== (int) Auth::id() && ! Auth::user()->esAdmin()) {
                return response()->json(['ok' => false, 'mensaje' => 'Solo el entrenador que hizo el plan puede editarlo.']);
            }
            DB::table('gym_nutricion')->where('nut_id', $n->nut_id)->update($fila);
        } else {
            DB::table('gym_nutricion')->insert($fila + ['soc_id' => $id, 'IdUsuario' => Auth::id(), 'activo' => 1, 'creado' => now()]);
            // Quien le hace el plan queda como su entrenador si aún no tiene
            DB::table('socios')->where('soc_id', $id)->whereNull('entrenador_id')->update(['entrenador_id' => Auth::id()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Plan de nutrición guardado: el cliente ya lo ve en su portal.']);
    }

    public function quitarNutricion(int $nutId)
    {
        $this->autorizar();
        $n = DB::table('gym_nutricion as n')->join('socios as s', 's.soc_id', '=', 'n.soc_id')
            ->where('n.nut_id', $nutId)->where('s.id_empresa_negocio', $this->sucursal())->first(['n.*']);
        abort_unless($n, 404);
        if ((int) $n->IdUsuario !== (int) Auth::id() && ! Auth::user()->esAdmin()) {
            return response()->json(['ok' => false, 'mensaje' => 'Solo el entrenador que hizo el plan puede quitarlo.']);
        }
        DB::table('gym_nutricion')->where('nut_id', $nutId)->update(['activo' => 0]);

        return response()->json(['ok' => true, 'mensaje' => 'Plan quitado.']);
    }
}
