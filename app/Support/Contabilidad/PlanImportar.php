<?php

namespace App\Support\Contabilidad;

use App\Support\ExcelLector;
use Illuminate\Support\Facades\DB;

/**
 * Importa el plan contable desde el Excel que exporta el sistema contable en FoxPro (columnas Pckey, Nomcta, Tipcta,
 * Cargo, Abono…). También acepta un Excel simple con las columnas Cuenta y Descripción (y opcional Nivel).
 * Las cuentas que ya existen se actualizan (nombre, nivel y amarres); las nuevas se crean. No borra ninguna.
 */
class PlanImportar
{
    /**
     * FoxPro guarda los textos en la página de códigos de DOS (850): al pasar a Excel salen "MERCADERÖAS", "INVERSIàN",
     * "dep¢sito". Se devuelven a su letra.
     */
    private const LETRAS_DOS = ['Ö' => 'Í', '¢' => 'ó', '¡' => 'í', '£' => 'ú', '¤' => 'ñ', '¥' => 'Ñ', 'µ' => 'Á', '‚' => 'é', 'š' => 'Ü'];

    /** Estas también son letras normales: solo se cambian dentro de una palabra en mayúsculas ("INVERSIàN", "PéBLICO") */
    private const LETRAS_DOS_MAYUSCULAS = ['à' => 'Ó', 'é' => 'Ú'];

    /** Títulos de columna que se reconocen (en minúsculas) */
    private const COLUMNAS = [
        'cuenta' => ['pckey', 'cuenta', 'codigo', 'código', 'cta'],
        'descripcion' => ['nomcta', 'descripcion', 'descripción', 'denominacion', 'denominación', 'nombre'],
        'tipcta' => ['tipcta', 'nivel', 'tipo'],
        'cargo' => ['cargo', 'amarre cargo', 'debe'],
        'abono' => ['abono', 'amarre abono', 'haber'],
    ];

    public static function corregirTexto(string $texto): string
    {
        $texto = strtr($texto, self::LETRAS_DOS);
        $texto = preg_replace_callback('/(?<=\p{Lu})[àé]|[àé](?=\p{Lu})/u', fn ($m) => self::LETRAS_DOS_MAYUSCULAS[$m[0]], $texto);
        // La "á" de DOS sale como espacio duro: solo entre dos letras ("Cr\u{A0}dito")
        $texto = preg_replace('/(?<=\p{L})\x{00A0}(?=\p{L})/u', 'á', $texto);

        return trim(preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Lee el Excel: las cuentas válidas y lo que no se pudo leer.
     *
     * @return array{cuentas: array<string, array{cuenta: string, descripcion: string, tipcta: ?int, cargo: ?string, abono: ?string}>, errores: list<string>}
     */
    public static function leer(string $ruta, string $extension): array
    {
        $filas = ExcelLector::filas($ruta, $extension);
        $columnas = null;
        $cuentas = [];
        $errores = [];
        foreach ($filas as $i => $fila) {
            $celdas = array_map(fn ($v) => trim((string) $v), $fila);
            if ($columnas === null) {
                $columnas = self::columnas($celdas);

                continue;
            }
            $cuenta = preg_replace('/\s+/', '', $celdas[$columnas['cuenta']] ?? '');
            $nombre = self::corregirTexto($celdas[$columnas['descripcion']] ?? '');
            if ($cuenta === '' && $nombre === '') {
                continue;
            }
            if (! preg_match('/^\d{1,12}$/', $cuenta) || $nombre === '') {
                $errores[] = 'Fila '.($i + 1).": «{$cuenta}» ".($nombre === '' ? 'sin nombre' : 'no es un número de cuenta').'.';

                continue;
            }
            $amarre = function (?int $col) use ($celdas): ?string {
                $v = $col === null ? '' : preg_replace('/\s+/', '', $celdas[$col] ?? '');

                return preg_match('/^\d{2,12}$/', $v) ? $v : null;
            };
            $tipcta = isset($columnas['tipcta']) && preg_match('/^[1-9]$/', $celdas[$columnas['tipcta']] ?? '') ? (int) $celdas[$columnas['tipcta']] : null;
            $cuentas[$cuenta] = ['cuenta' => $cuenta, 'descripcion' => mb_substr(mb_strtoupper($nombre), 0, 200), 'tipcta' => $tipcta,
                'cargo' => $amarre($columnas['cargo'] ?? null), 'abono' => $amarre($columnas['abono'] ?? null)];
        }
        if ($columnas === null) {
            $errores[] = 'No encontré los títulos de las columnas (Pckey y Nomcta, o Cuenta y Descripción).';
        }
        ksort($cuentas, SORT_STRING);

        return ['cuentas' => $cuentas, 'errores' => $errores];
    }

    /** @return ?array<string, int> columna => posición, si esta fila es la de los títulos */
    private static function columnas(array $celdas): ?array
    {
        $titulos = array_map(fn ($c) => mb_strtolower($c), $celdas);
        $pos = [];
        foreach (self::COLUMNAS as $campo => $nombres) {
            foreach ($titulos as $i => $t) {
                if (in_array($t, $nombres, true)) {
                    $pos[$campo] = $i;

                    break;
                }
            }
        }

        return isset($pos['cuenta'], $pos['descripcion']) ? $pos : null;
    }

    /**
     * Guarda las cuentas en el plan de una empresa.
     *
     * @return array{nuevas: int, actualizadas: int}
     */
    public static function importar(string $ruc, array $cuentas): array
    {
        $existen = DB::table('conta_plan')->where('IdEmpresa', $ruc)->pluck('id', 'cuenta');
        $nuevas = $actualizadas = 0;
        DB::transaction(function () use ($ruc, $cuentas, $existen, &$nuevas, &$actualizadas) {
            foreach ($cuentas as $c) {
                $datos = ['descripcion' => $c['descripcion'], 'tipcta' => $c['tipcta'], 'amarre_cargo' => $c['cargo'], 'amarre_abono' => $c['abono'], 'updated_at' => now()];
                if (isset($existen[$c['cuenta']])) {
                    DB::table('conta_plan')->where('id', $existen[$c['cuenta']])->update($datos);
                    $actualizadas++;

                    continue;
                }
                [$tipo, $naturaleza] = Pcge::tipo($c['cuenta']);
                DB::table('conta_plan')->insert($datos + ['IdEmpresa' => $ruc, 'cuenta' => $c['cuenta'], 'nivel' => strlen($c['cuenta']),
                    'tipo' => $tipo, 'naturaleza' => $naturaleza, 'imputable' => 1, 'estado' => 'Activo', 'created_at' => now()]);
                $nuevas++;
            }
            // De título las que tienen subcuentas; de movimiento las demás
            $todas = DB::table('conta_plan')->where('IdEmpresa', $ruc)->pluck('cuenta')->all();
            sort($todas, SORT_STRING);
            $conHijas = [];
            foreach ($todas as $i => $cuenta) {
                $siguiente = $todas[$i + 1] ?? '';
                if ($siguiente !== '' && str_starts_with($siguiente, $cuenta)) {
                    $conHijas[] = $cuenta;
                }
            }
            DB::table('conta_plan')->where('IdEmpresa', $ruc)->update(['imputable' => 1]);
            foreach (array_chunk($conHijas, 500) as $parte) {
                DB::table('conta_plan')->where('IdEmpresa', $ruc)->whereIn('cuenta', $parte)->update(['imputable' => 0]);
            }
        });

        return ['nuevas' => $nuevas, 'actualizadas' => $actualizadas];
    }
}
