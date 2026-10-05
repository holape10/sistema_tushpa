<?php
namespace App\Support;

/**
 * Lee la primera hoja de un .xlsx (o un .csv) y devuelve sus filas como arrays de textos, sin librerías externas.
 * Las celdas vacías intermedias se respetan (la columna C siempre es el índice 2).
 */
class ExcelLector
{
    /** @return array<int, array<int, string>> */
    public static function filas(string $ruta, string $extension): array
    {
        $extension = strtolower($extension);
        $filas = in_array($extension, ['csv', 'txt']) ? self::csv($ruta) : self::xlsx($ruta);

        // Quita filas totalmente vacías del final y espacios sobrantes
        $filas = array_map(fn($f) => array_map(fn($v) => trim((string) $v), $f), $filas);
        while ($filas && implode('', end($filas)) === '') {
            array_pop($filas);
        }
        return $filas;
    }

    private static function csv(string $ruta): array
    {
        $texto = file_get_contents($ruta);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);           // BOM
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252'); // CSV guardado por Excel en Windows
        }
        $primera = strtok($texto, "\n");
        $sep = substr_count($primera, ';') >= substr_count($primera, ',') ? ';' : ',';

        $filas = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, $texto);
        rewind($h);
        while (($f = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $filas[] = $f;
        }
        fclose($h);
        return $filas;
    }

    private static function xlsx(string $ruta): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new \RuntimeException('El archivo no es un Excel (.xlsx) válido.');
        }

        // Textos compartidos (la mayoría de celdas de texto apuntan aquí)
        $compartidos = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($xml)->si as $si) {
                $compartidos[] = self::texto($si);
            }
        }

        $hoja = $zip->getFromName(self::rutaPrimeraHoja($zip));
        $zip->close();
        if ($hoja === false) {
            throw new \RuntimeException('No se encontró ninguna hoja en el Excel.');
        }

        $filas = [];
        foreach (simplexml_load_string($hoja)->sheetData->row as $row) {
            $fila = [];
            foreach ($row->c as $c) {
                $col = self::indiceColumna((string) $c['r']);
                $tipo = (string) $c['t'];
                $valor = match ($tipo) {
                    's'         => $compartidos[(int) $c->v] ?? '',
                    'inlineStr' => self::texto($c->is),
                    'b'         => (string) $c->v === '1' ? 'VERDADERO' : 'FALSO',
                    default     => self::numero((string) $c->v),
                };
                $fila[$col ?? count($fila)] = $valor;
            }
            if ($fila) {
                $max = max(array_keys($fila));
                $fila += array_fill(0, $max + 1, '');
                ksort($fila);
            }
            $filas[((int) $row['r'] ?: count($filas) + 1) - 1] = array_values($fila);
        }

        // Rellena filas saltadas para que el número de fila coincida con el Excel
        if ($filas) {
            $filas += array_fill(0, max(array_keys($filas)) + 1, []);
            ksort($filas);
        }
        return array_values($filas);
    }

    /** Sigue workbook.xml → relaciones para hallar la primera hoja (no siempre es sheet1.xml) */
    private static function rutaPrimeraHoja(\ZipArchive $zip): string
    {
        $libro = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro && $rels) {
            $wb = simplexml_load_string($libro);
            $hoja = $wb->sheets->sheet[0] ?? null;
            $rId = $hoja ? (string) $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
            foreach (simplexml_load_string($rels)->Relationship as $r) {
                if ((string) $r['Id'] === $rId) {
                    $target = ltrim((string) $r['Target'], '/');
                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** Texto de un <si> o <is>: simple (<t>) o con formato (<r><t>) */
    private static function texto(?\SimpleXMLElement $nodo): string
    {
        if (!$nodo) {
            return '';
        }
        if (isset($nodo->t)) {
            return (string) $nodo->t;
        }
        $s = '';
        foreach ($nodo->r as $r) {
            $s .= (string) $r->t;
        }
        return $s;
    }

    /** Evita notaciones como 2.9999999999999996 o 1E+15 en números guardados por Excel */
    private static function numero(string $v): string
    {
        if ($v === '' || !is_numeric($v)) {
            return $v;
        }
        $n = (float) $v;
        return floor($n) == $n && abs($n) < 1e15 ? number_format($n, 0, '.', '') : rtrim(rtrim(number_format(round($n, 6), 6, '.', ''), '0'), '.');
    }

    private static function indiceColumna(string $ref): ?int
    {
        if (!preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
            return null;
        }
        $n = 0;
        foreach (str_split($m[1]) as $l) {
            $n = $n * 26 + (ord($l) - 64);
        }
        return $n - 1;
    }
}
