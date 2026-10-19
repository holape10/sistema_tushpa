<?php

namespace App\Http\Controllers;

use App\Support\Prestamos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Configuración de préstamos (administrador): planes, días de cobro, feriados, mora y capital inicial */
class PrestamoConfigController extends Controller
{
    /** Feriados nacionales del Perú (mes-día) para cargarlos con un clic */
    public const FERIADOS_PERU = ['01-01', '05-01', '06-07', '06-29', '07-23', '07-28', '07-29', '08-06', '08-30', '10-08', '11-01', '12-08', '12-09', '12-25'];

    private function admin(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura los préstamos.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(): View
    {
        $suc = $this->admin();

        return view('empresas.prestamos.configuracion', [
            'cfg' => Prestamos::config($suc),
            'planes' => DB::table('prestamo_planes')->where('id_empresa_negocio', $suc)->orderByDesc('activo')->orderByDesc('primer_prestamo')->orderBy('cuotas')->get(),
            'feriadosPeru' => collect(self::FERIADOS_PERU)->flatMap(fn ($md) => [now()->year.'-'.$md, (now()->year + 1).'-'.$md])->sort()->values(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate([
            'dias' => 'required|array|min:1', 'dias.*' => 'integer|between:1,7', 'mora_diaria' => 'nullable|numeric|min:0|max:9999',
            'dias_gracia' => 'nullable|integer|min:0|max:60', 'feriados' => 'nullable|string|max:3000', 'capital_inicial' => 'nullable|numeric|min:0|max:99999999',
        ], ['dias.required' => 'Marca al menos un día de cobro.']);
        $feriados = collect(preg_split('/[\s,;]+/', (string) ($d['feriados'] ?? '')))->map(fn ($f) => trim($f))->filter(fn ($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f))->unique()->sort()->implode("\n");
        Prestamos::config($suc);
        DB::table('prestamo_config')->where('id_empresa_negocio', $suc)->update([
            'dias_cobro' => collect($d['dias'])->map(fn ($x) => (int) $x)->unique()->sort()->implode(','), 'mora_diaria' => $d['mora_diaria'] ?? 0,
            'dias_gracia' => $d['dias_gracia'] ?? 0, 'feriados' => $feriados ?: null, 'capital_inicial' => $d['capital_inicial'] ?? 0, 'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'mensaje' => 'Configuración guardada. Se aplica a los préstamos nuevos.']);
    }

    public function plan(Request $request): JsonResponse
    {
        $suc = $this->admin();
        $d = $request->validate([
            'plan_id' => 'nullable|integer', 'nombre' => 'required|string|max:80', 'interes' => 'required|numeric|min:0|max:500', 'cuotas' => 'required|integer|min:1|max:400',
            'frecuencia' => 'required|in:'.implode(',', array_keys(Prestamos::FRECUENCIAS)), 'monto_min' => 'nullable|numeric|min:0', 'monto_max' => 'nullable|numeric|min:0',
            'primer_prestamo' => 'nullable|boolean', 'activo' => 'nullable|boolean',
        ], [], ['interes' => 'interés', 'cuotas' => 'número de cuotas']);
        $fila = ['nombre' => mb_strtoupper(trim($d['nombre'])), 'interes' => $d['interes'], 'cuotas' => $d['cuotas'], 'frecuencia' => $d['frecuencia'],
            'monto_min' => $d['monto_min'] ?? 0, 'monto_max' => $d['monto_max'] ?? 0, 'primer_prestamo' => (int) ($d['primer_prestamo'] ?? 0), 'activo' => (int) ($d['activo'] ?? 1)];
        if (! empty($d['plan_id'])) {
            abort_unless(DB::table('prestamo_planes')->where('plan_id', $d['plan_id'])->where('id_empresa_negocio', $suc)->update($fila) !== false, 404);
        } else {
            DB::table('prestamo_planes')->insert($fila + ['id_empresa_negocio' => $suc]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Plan guardado.',
            'planes' => DB::table('prestamo_planes')->where('id_empresa_negocio', $suc)->orderByDesc('activo')->orderByDesc('primer_prestamo')->orderBy('cuotas')->get()]);
    }
}
