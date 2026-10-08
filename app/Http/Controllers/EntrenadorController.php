<?php

namespace App\Http\Controllers;

use App\Support\Gimnasio;
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
        $ultimos = DB::table('gym_nutricion')->where('activo', 1)->groupBy('soc_id')->select('soc_id', DB::raw('MAX(fecha) as fecha'))->pluck('fecha', 'soc_id');

        $clientes = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('s.id_empresa_negocio', $suc)->where('s.estado', 'ACTIVO')->orderBy('c.clinom')
            ->get(['s.soc_id', 's.codigo', 's.foto', 's.entrenador_id', 's.fecha_nac', 'c.clinom', 'c.clinum', 'c.telefono'])
            ->map(function ($s) use ($mems, $cong, $ultimos) {
                $sit = Gimnasio::situacion($mems->get($s->soc_id, collect()), $cong);

                return ['soc_id' => $s->soc_id, 'codigo' => $s->codigo, 'nombre' => $s->clinom, 'doc' => $s->clinum, 'telefono' => $s->telefono,
                    'foto' => $s->foto && is_file(public_path($s->foto)) ? asset($s->foto) : null, 'mio' => (int) $s->entrenador_id === (int) Auth::id(),
                    'edad' => $s->fecha_nac ? Carbon::parse($s->fecha_nac)->age : null, 'estado' => $sit['texto'], 'color' => $sit['color'],
                    'ultimo_plan' => $ultimos[$s->soc_id] ?? null];
            })->values();

        return view('empresas.gimnasio.entrenador', [
            'clientes' => $clientes, 'objetivos' => Gimnasio::OBJETIVOS, 'yo' => Auth::id(), 'esAdmin' => Auth::user()->esAdmin(),
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
