<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el entrenador lleva de cada cliente, como la hoja "Rutina de entrenamiento" del gimnasio:
 *  - Ficha: objetivo, antecedentes médicos y observaciones importantes.
 *  - Medidas por fecha: edad, peso, estatura, tórax, cintura, cadera, muslo, pantorrilla, brazo, % grasa (el IMC se calcula).
 *  - Rutina del mes: calentamiento (minutos en caminadora, elíptica, bicicleta y escaladora) y los ejercicios de cada día.
 * El cliente ve su rutina y sus medidas en su portal ({subdominio}/socio).
 */
class GimnasioRutina
{
    public const ANTECEDENTES = [
        'HIPERTENSION' => 'Hipertensión arterial', 'DIABETES' => 'Diabetes', 'CARDIACA' => 'Enfermedad cardíaca',
        'RESPIRATORIA' => 'Asma o problemas respiratorios', 'LESIONES' => 'Lesiones musculares o articulares',
        'CIRUGIAS' => 'Cirugías recientes (últimos 12 meses)', 'ESPALDA' => 'Dolor frecuente de espalda o columna',
        'EMBARAZO' => 'Embarazo (si aplica)', 'MEDICAMENTOS' => 'Toma medicamentos de forma permanente',
    ];

    public const CALENTAMIENTO = ['CAMINADORA' => 'Caminadora', 'ELIPTICA' => 'Elíptica', 'BICICLETA' => 'Bicicleta', 'ESCALADORA' => 'Escaladora'];

    /** Medidas en centímetros (además de edad, peso, estatura y % grasa) */
    public const PERIMETROS = ['torax' => 'Tórax', 'cintura' => 'Cintura', 'cadera' => 'Cadera', 'muslo' => 'Muslo', 'pantorrilla' => 'Pantorrilla', 'brazo' => 'Brazo'];

    public const DIAS = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO', 'DOMINGO'];

    public static function ficha(int $socId): ?object
    {
        $f = DB::table('gym_fichas')->where('soc_id', $socId)->first();
        if ($f) {
            $f->antecedentes = json_decode((string) $f->antecedentes, true) ?: [];
        }

        return $f;
    }

    /** @param array{objetivo?: ?string, antecedentes?: array, otros?: ?string, observaciones?: ?string} $d */
    public static function guardarFicha(int $socId, int $usuario, array $d): void
    {
        $antecedentes = array_values(array_intersect(array_keys(self::ANTECEDENTES), (array) ($d['antecedentes'] ?? [])));
        DB::table('gym_fichas')->updateOrInsert(['soc_id' => $socId], [
            'objetivo' => $d['objetivo'] ?? null, 'antecedentes' => json_encode($antecedentes),
            'otros' => mb_strtoupper(trim((string) ($d['otros'] ?? ''))) ?: null,
            'observaciones' => trim((string) ($d['observaciones'] ?? '')) ?: null,
            'IdUsuario' => $usuario, 'actualizado' => now(),
        ]);
    }

    /** Medidas del cliente, de la más reciente a la más antigua, con su IMC */
    public static function medidas(int $socId): Collection
    {
        return DB::table('gym_medidas')->where('soc_id', $socId)->orderByDesc('fecha')->orderByDesc('med_id')->get()
            ->map(fn ($m) => (array) $m + ['imc' => Gimnasio::imc((float) $m->peso, (float) $m->estatura)]);
    }

    /** Rutinas del cliente (la primera es la vigente o la más reciente) */
    public static function rutinas(int $socId): Collection
    {
        return DB::table('gym_rutinas as r')->leftJoin('users as u', 'u.IdUsuario', '=', 'r.IdUsuario')
            ->where('r.soc_id', $socId)->where('r.activo', 1)->orderByDesc('r.desde')->orderByDesc('r.rut_id')->limit(12)
            ->get(['r.*', 'u.apeusu as entrenador'])
            ->map(fn ($r) => array_merge((array) $r, [
                'calentamiento' => json_decode((string) $r->calentamiento, true) ?: [],
                'dias' => json_decode((string) $r->dias, true) ?: [],
                'vigente' => $r->desde <= now()->toDateString() && $r->hasta >= now()->toDateString(),
            ]));
    }

    /**
     * Limpia la rutina que llega de la pantalla: solo días con nombre de grupo o ejercicios, y ejercicios con nombre.
     *
     * @return array<int, array{dia: string, grupo: string, ejercicios: array<int, array{nombre: string, series: string, reps: string, nota: string}>}>
     */
    public static function limpiarDias(array $dias): array
    {
        $limpio = [];
        foreach ($dias as $d) {
            $ejercicios = collect($d['ejercicios'] ?? [])->map(fn ($e) => [
                'nombre' => mb_strtoupper(mb_substr(trim((string) ($e['nombre'] ?? '')), 0, 80)),
                'series' => mb_substr(trim((string) ($e['series'] ?? '')), 0, 10), 'reps' => mb_substr(trim((string) ($e['reps'] ?? '')), 0, 20),
                'nota' => mb_substr(trim((string) ($e['nota'] ?? '')), 0, 80),
            ])->filter(fn ($e) => $e['nombre'] !== '')->values()->all();
            $grupo = mb_strtoupper(mb_substr(trim((string) ($d['grupo'] ?? '')), 0, 60));
            if (! $ejercicios && $grupo === '') {
                continue;
            }
            $limpio[] = ['dia' => in_array($d['dia'] ?? '', self::DIAS, true) ? $d['dia'] : 'DÍA '.(count($limpio) + 1), 'grupo' => $grupo, 'ejercicios' => $ejercicios];
        }

        return $limpio;
    }

    /** Calentamiento: minutos por máquina (solo los que tienen minutos) */
    public static function limpiarCalentamiento(array $c): array
    {
        return collect(self::CALENTAMIENTO)->keys()->mapWithKeys(fn ($k) => [$k => (int) ($c[$k] ?? 0)])
            ->filter(fn ($min) => $min > 0 && $min <= 120)->all();
    }

    /** Del 1 al último día del mes de esa fecha */
    public static function mesDe(string $fecha): array
    {
        $f = Carbon::parse($fecha);

        return [$f->copy()->startOfMonth()->toDateString(), $f->copy()->endOfMonth()->toDateString()];
    }
}
