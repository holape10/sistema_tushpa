<?php

namespace App\Http\Controllers;

use App\Support\Colegio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Configuración del colegio (administrador): año escolar, meses de pensión, vencimiento, descuentos y grados con sus precios */
class ColegioConfigController extends Controller
{
    private function admin(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura el colegio.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(): View
    {
        $suc = $this->admin();

        return view('empresas.colegio.configuracion', [
            'cfg' => Colegio::config($suc),
            'grados' => DB::table('col_grados')->where('id_empresa_negocio', $suc)->orderBy('orden')->orderBy('grado_id')->get(),
            'usados' => DB::table('col_matriculas')->where('id_empresa_negocio', $suc)->groupBy('grado_id')->select('grado_id', DB::raw('COUNT(*) as n'))->pluck('n', 'grado_id'),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate([
            'anio' => 'required|integer|between:2020,2100', 'mes_inicio' => 'required|integer|between:1,12', 'mes_fin' => 'required|integer|between:1,12|gte:mes_inicio',
            'dia_vence' => 'required|integer|between:1,31', 'dscto_2' => 'required|numeric|between:0,100', 'dscto_3' => 'required|numeric|between:0,100',
            'adelanto_meses' => 'required|integer|between:0,12', 'adelanto_pct' => 'required|numeric|between:0,100',
        ], ['mes_fin.gte' => 'El último mes de pensión debe ser después del primero.'],
            ['anio' => 'año escolar', 'dia_vence' => 'día de vencimiento', 'dscto_2' => 'descuento del 2° alumno', 'dscto_3' => 'descuento del 3° alumno']);
        Colegio::config($suc);
        DB::table('col_config')->where('id_empresa_negocio', $suc)->update($d + ['updated_at' => now()]);

        return response()->json(['ok' => true, 'mensaje' => 'Configuración guardada. Vale para las matrículas que se hagan desde ahora.']);
    }

    /** Guarda la lista de grados (nivel, grado, sección y precios) */
    public function grados(Request $request): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate([
            'grados' => 'array|max:200', 'grados.*.grado_id' => 'nullable|integer',
            'grados.*.nivel' => 'required|in:'.implode(',', Colegio::NIVELES), 'grados.*.grado' => 'required|string|max:40',
            'grados.*.seccion' => 'nullable|string|max:20', 'grados.*.pension' => 'required|numeric|min:0|max:99999',
            'grados.*.matricula' => 'required|numeric|min:0|max:99999', 'grados.*.inscripcion' => 'required|numeric|min:0|max:99999',
            'grados.*.activo' => 'nullable|boolean',
        ], [], ['grados.*.grado' => 'grado', 'grados.*.pension' => 'pensión', 'grados.*.matricula' => 'matrícula', 'grados.*.inscripcion' => 'inscripción']);

        DB::transaction(function () use ($d, $suc) {
            $quedan = [];
            foreach (array_values($d['grados'] ?? []) as $orden => $g) {
                $fila = ['nivel' => $g['nivel'], 'grado' => mb_strtoupper(trim($g['grado'])), 'seccion' => mb_strtoupper(trim((string) ($g['seccion'] ?? ''))) ?: 'ÚNICA',
                    'pension' => round((float) $g['pension'], 2), 'matricula' => round((float) $g['matricula'], 2), 'inscripcion' => round((float) $g['inscripcion'], 2),
                    'orden' => $orden + 1, 'activo' => (bool) ($g['activo'] ?? true)];
                if (! empty($g['grado_id']) && DB::table('col_grados')->where('grado_id', $g['grado_id'])->where('id_empresa_negocio', $suc)->exists()) {
                    DB::table('col_grados')->where('grado_id', $g['grado_id'])->update($fila);
                    $quedan[] = (int) $g['grado_id'];
                } else {
                    $quedan[] = DB::table('col_grados')->insertGetId($fila + ['id_empresa_negocio' => $suc]);
                }
            }
            // Los que se quitaron de la lista: se borran si nadie los usa; si tienen alumnos, solo se desactivan
            $usados = DB::table('col_matriculas')->where('id_empresa_negocio', $suc)->distinct()->pluck('grado_id');
            $fuera = DB::table('col_grados')->where('id_empresa_negocio', $suc)->whereNotIn('grado_id', $quedan ?: [0]);
            (clone $fuera)->whereIn('grado_id', $usados)->update(['activo' => 0]);
            (clone $fuera)->whereNotIn('grado_id', $usados->all() ?: [0])->delete();
        });

        return response()->json(['ok' => true, 'mensaje' => 'Grados guardados. Los precios valen para las matrículas que se hagan desde ahora.']);
    }
}
