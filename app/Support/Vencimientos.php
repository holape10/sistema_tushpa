<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cronograma de vencimientos SUNAT de las obligaciones mensuales (IGV, renta, PDT, PLAME…) según el último dígito del
 * RUC. La SUNAT lo publica cada diciembre en su web (R.S. 281-2022/SUNAT, Anexo I) y sigue una regla fija: el dígito 0
 * vence el 11.º día hábil del mes siguiente, el 1 el 12.º, los pares 2-3, 4-5, 6-7 y 8-9 del 13.º al 16.º, y los
 * buenos contribuyentes el 17.º. Se usa, en este orden: el cronograma descargado de la SUNAT (sunat:cronograma), el
 * que viene con el sistema y, si la SUNAT aún no publica el año, el calculado con esa regla (marcado como estimado).
 */
class Vencimientos
{
    /** Grupos del cronograma: la columna de la SUNAT y el día hábil del mes siguiente en que vence */
    public const GRUPOS = ['0' => 11, '1' => 12, '2-3' => 13, '4-5' => 14, '6-7' => 15, '8-9' => 16, 'BC' => 17];

    public const URL = 'https://www.sunat.gob.pe/orientacion/cronogramas/%d/cObligacionMensual%d.html';

    private const MESES = ['ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AGO' => 8, 'SET' => 9, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12];

    /** @var array<int, array{oficial: bool, periodos: array<string, array<string, string>>}> */
    private static array $cronogramas = [];

    /** El grupo del cronograma que le toca a un RUC */
    public static function grupo(string $ruc, bool $buenContribuyente = false): string
    {
        if ($buenContribuyente) {
            return 'BC';
        }
        $digito = (int) substr($ruc, -1);

        return $digito <= 1 ? (string) $digito : ($digito - $digito % 2).'-'.($digito - $digito % 2 + 1);
    }

    /** El periodo que se declara este mes (el mes anterior), como 'Ym' */
    public static function periodoPendiente(?CarbonImmutable $hoy = null): string
    {
        return ($hoy ?? CarbonImmutable::now())->startOfMonth()->subMonth()->format('Ym');
    }

    /** Cuándo vence un periodo ('Ym') para un grupo */
    public static function vence(string $periodo, string $grupo): CarbonImmutable
    {
        abort_unless(isset(self::GRUPOS[$grupo]) && preg_match('/^\d{6}$/', $periodo), 404);

        return CarbonImmutable::parse(self::cronograma((int) substr($periodo, 0, 4))['periodos'][$periodo][$grupo]);
    }

    /** ¿Las fechas de ese año son las publicadas por la SUNAT (y no las calculadas)? */
    public static function oficial(int $anio): bool
    {
        return self::cronograma($anio)['oficial'];
    }

    /**
     * Las fechas de los doce periodos del año (el de diciembre vence en enero del siguiente).
     *
     * @return array{oficial: bool, periodos: array<string, array<string, string>>}
     */
    public static function cronograma(int $anio): array
    {
        if (! isset(self::$cronogramas[$anio])) {
            $guardado = self::leer($anio);
            self::$cronogramas[$anio] = $guardado
                ? ['oficial' => true, 'periodos' => $guardado['periodos']]
                : ['oficial' => false, 'periodos' => self::calcular($anio)];
        }

        return self::$cronogramas[$anio];
    }

    /** Para las pruebas y después de descargar: vuelve a leer los cronogramas */
    public static function olvidar(): void
    {
        self::$cronogramas = [];
    }

    /** @return array{periodos: array<string, array<string, string>>, fuente?: string}|null el descargado o el que trae el sistema */
    private static function leer(int $anio): ?array
    {
        $archivo = self::archivo($anio);
        $texto = Storage::disk('local')->exists($archivo) ? Storage::disk('local')->get($archivo)
            : (is_file($incluido = resource_path("data/sunat/cronograma-{$anio}.json")) ? file_get_contents($incluido) : null);
        $datos = $texto ? json_decode($texto, true) : null;

        return is_array($datos['periodos'] ?? null) && count($datos['periodos']) === 12 ? $datos : null;
    }

    private static function archivo(int $anio): string
    {
        return "sunat/cronograma-{$anio}.json";
    }

    /** ¿Ya hay un cronograma oficial guardado de ese año (descargado o incluido)? */
    public static function tieneOficial(int $anio): bool
    {
        return self::leer($anio) !== null;
    }

    /**
     * Baja el cronograma del año de la web de la SUNAT y lo guarda. Devuelve false si la SUNAT todavía no lo publica.
     *
     * @throws RuntimeException si la página cambió y no se puede leer (mejor no guardar fechas dudosas)
     */
    public static function descargar(int $anio): bool
    {
        $r = Http::timeout(20)->withUserAgent('Mozilla/5.0 (compatible; SistemaContable/1.0)')->get(sprintf(self::URL, $anio, $anio));
        if ($r->status() === 404) {
            return false;
        }
        if (! $r->successful()) {
            throw new RuntimeException("La web de la SUNAT respondió {$r->status()}. Se intentará de nuevo mañana.");
        }
        $periodos = self::leerPagina($r->body(), $anio);
        Storage::disk('local')->put(self::archivo($anio), json_encode([
            'anio' => $anio, 'fuente' => sprintf(self::URL, $anio, $anio), 'descargado' => now()->toDateTimeString(), 'periodos' => $periodos,
        ], JSON_PRETTY_PRINT));
        self::olvidar();

        return true;
    }

    /**
     * Lee la tabla de la página de la SUNAT: una fila por periodo ("Ene-2026") con siete fechas ("16 Feb", "18 Ene 2027").
     *
     * @return array<string, array<string, string>>
     */
    public static function leerPagina(string $html, int $anio): array
    {
        $periodos = [];
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $filas);
        foreach ($filas[1] as $fila) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $fila, $celdas);
            $textos = array_map(fn ($c) => trim((string) preg_replace('/\s+/', ' ', str_replace('&nbsp;', ' ', strip_tags($c)))), $celdas[1]);
            if (! $textos || ! preg_match('/^([A-Za-z]{3})-(\d{4})$/', $textos[0], $p) || (int) $p[2] !== $anio) {
                continue;
            }
            $mes = self::MESES[strtoupper($p[1])] ?? null;
            if ($mes === null || count($textos) < 8) {
                throw new RuntimeException("No se entiende la fila {$textos[0]} del cronograma de la SUNAT.");
            }
            $periodo = sprintf('%04d%02d', $anio, $mes);
            foreach (array_keys(self::GRUPOS) as $i => $grupo) {
                if (! preg_match('/^(\d{1,2}) ([A-Za-z]{3})(?: (\d{4}))?$/', $textos[$i + 1], $f) || ! isset(self::MESES[strtoupper($f[2])])) {
                    throw new RuntimeException("No se entiende la fecha \"{$textos[$i + 1]}\" del periodo {$textos[0]}.");
                }
                $mesVence = self::MESES[strtoupper($f[2])];
                $anioVence = isset($f[3]) && $f[3] !== '' ? (int) $f[3] : ($mesVence < $mes ? $anio + 1 : $anio);
                $periodos[$periodo][$grupo] = sprintf('%04d-%02d-%02d', $anioVence, $mesVence, (int) $f[1]);
            }
        }
        if (count($periodos) !== 12) {
            throw new RuntimeException('El cronograma de la SUNAT del '.$anio.' trae '.count($periodos).' periodos en vez de 12.');
        }
        ksort($periodos);

        return $periodos;
    }

    /**
     * El cronograma calculado con la regla de la SUNAT (días hábiles del mes siguiente, sin sábados, domingos ni feriados).
     *
     * @return array<string, array<string, string>>
     */
    public static function calcular(int $anio): array
    {
        $periodos = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $siguiente = CarbonImmutable::create($anio, $mes, 1)->addMonth();
            foreach (self::GRUPOS as $grupo => $diaHabil) {
                $periodos[sprintf('%04d%02d', $anio, $mes)][$grupo] = self::diaHabil($siguiente, $diaHabil)->toDateString();
            }
        }

        return $periodos;
    }

    private static function diaHabil(CarbonImmutable $mes, int $numero): CarbonImmutable
    {
        $feriados = self::feriados($mes->year);
        $dia = $mes->startOfMonth();
        for ($contados = 0; ; $dia = $dia->addDay()) {
            if (! $dia->isWeekend() && ! in_array($dia->toDateString(), $feriados, true) && ++$contados === $numero) {
                return $dia;
            }
        }
    }

    /**
     * Feriados nacionales del Perú: los fijos (con los de Arica, Junín y Ayacucho desde 2022 y la Fuerza Aérea desde
     * 2023) y el Jueves y Viernes Santo.
     *
     * @return list<string>
     */
    public static function feriados(int $anio): array
    {
        $fijos = ['01-01', '05-01', '06-07', '06-29', '07-23', '07-28', '07-29', '08-06', '08-30', '10-08', '11-01', '12-08', '12-09', '12-25'];
        $pascua = self::domingoDePascua($anio);

        return [...array_map(fn ($d) => "{$anio}-{$d}", $fijos), $pascua->subDays(3)->toDateString(), $pascua->subDays(2)->toDateString()];
    }

    /** Domingo de Pascua (cálculo gregoriano anónimo; no depende de la extensión calendar de PHP) */
    private static function domingoDePascua(int $anio): CarbonImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $h = (19 * $a + $b - intdiv($b, 4) - intdiv($b - intdiv($b + 8, 25) + 1, 3) + 15) % 30;
        $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - $c % 4) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $n = $h + $l - 7 * $m + 114;

        return CarbonImmutable::create($anio, intdiv($n, 31), $n % 31 + 1);
    }
}
