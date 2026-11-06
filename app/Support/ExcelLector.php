<?php

namespace App\Support;

/**
 * Lee una hoja de Excel y devuelve sus filas como arrays de textos, sin librerías externas: la primera, o la que se pida
 * por nombre (si existe). Las celdas vacías intermedias se respetan (la columna C siempre es el índice 2).
 *
 * Acepta .xlsx/.xlsm, .xls (Excel 97-2003), .ods (LibreOffice / Google Sheets) y .csv/.txt. El formato se reconoce por el
 * contenido y no por la extensión: muchos sistemas exportan como ".xls" una tabla HTML o un texto separado por
 * tabulaciones, y también se leen.
 */
class ExcelLector
{
    /** Extensiones que se aceptan al subir (para las reglas de validación) */
    public const EXTENSIONES = 'xlsx,xlsm,xls,ods,csv,txt';

    /** Para el atributo accept del <input type="file"> */
    public const ACCEPT = '.xlsx,.xlsm,.xls,.ods,.csv,.txt';

    /** @return array<int, array<int, string>> */
    public static function filas(string $ruta, string $extension = '', ?string $hoja = null): array
    {
        $inicio = (string) @file_get_contents($ruta, false, null, 0, 8);
        if ($inicio === '') {
            throw new \RuntimeException('El archivo está vacío.');
        }
        if (str_starts_with($inicio, "PK\x03\x04")) {
            $filas = self::zip($ruta, $hoja);
        } elseif ($inicio === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            $filas = self::xls($ruta, $hoja);
        } else {
            $filas = self::texto($ruta, $hoja);
        }

        // Quita filas totalmente vacías del final y espacios sobrantes
        $filas = array_map(fn ($f) => array_map(fn ($v) => trim((string) $v), $f), $filas);
        while ($filas && implode('', end($filas)) === '') {
            array_pop($filas);
        }

        return $filas;
    }

    /** Un .zip es un .xlsx/.xlsm o un .ods */
    private static function zip(string $ruta, ?string $hoja): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($ruta) !== true) {
            throw new \RuntimeException('El archivo de Excel está dañado: ábrelo y vuelve a guardarlo.');
        }
        try {
            if ($zip->locateName('content.xml') !== false && str_contains((string) $zip->getFromName('mimetype'), 'opendocument.spreadsheet')) {
                return self::ods($zip, $hoja);
            }
            if ($zip->locateName('xl/workbook.xml') === false) {
                throw new \RuntimeException('El archivo no es una hoja de Excel. Guárdalo como Libro de Excel (.xlsx).');
            }

            return self::xlsx($zip, $hoja);
        } finally {
            $zip->close();
        }
    }

    // ================================================================= CSV / texto / tabla HTML

    /** .csv o .txt (separado por ; , o tabulaciones), una tabla HTML o una "Hoja de cálculo XML 2003" guardadas como .xls */
    private static function texto(string $ruta, ?string $hoja = null): array
    {
        $texto = file_get_contents($ruta);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);           // BOM
        if (str_starts_with($texto, "\xFF\xFE")) {
            $texto = mb_convert_encoding(substr($texto, 2), 'UTF-8', 'UTF-16LE');  // "Texto Unicode" de Excel
        } elseif (! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252'); // CSV guardado por Excel en Windows
        }
        if (preg_match('/^\s*<\?xml/i', $texto) && str_contains($texto, 'urn:schemas-microsoft-com:office:spreadsheet')) {
            return self::xml2003($texto, $hoja);
        }
        if (preg_match('/^\s*<(!doctype|html|table|\?xml|meta|head|body)/i', $texto) && stripos($texto, '<tr') !== false) {
            return self::html($texto);
        }
        $primera = (string) strtok($texto, "\n");
        $conteo = [';' => substr_count($primera, ';'), ',' => substr_count($primera, ','), "\t" => substr_count($primera, "\t")];
        $sep = array_search(max($conteo), $conteo, true);

        $filas = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, $texto);
        rewind($h);
        while (($f = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $filas[] = $f === [null] ? [] : $f;
        }
        fclose($h);

        return $filas;
    }

    private static function html(string $texto): array
    {
        $dom = new \DOMDocument;
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$texto, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $filas = [];
        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $fila = [];
            foreach ($tr->childNodes as $td) {
                if ($td instanceof \DOMElement && in_array(strtolower($td->nodeName), ['td', 'th'])) {
                    $fila[] = preg_replace('/\s+/u', ' ', $td->textContent);
                    for ($i = 1; $i < min((int) $td->getAttribute('colspan'), 100); $i++) {
                        $fila[] = '';
                    }
                }
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    /** "Hoja de cálculo XML 2003" de Excel: <Worksheet ss:Name> → <Row ss:Index> → <Cell ss:Index ss:MergeAcross><Data> */
    private static function xml2003(string $texto, ?string $nombreHoja): array
    {
        $ss = 'urn:schemas-microsoft-com:office:spreadsheet';
        $dom = new \DOMDocument;
        $previo = libxml_use_internal_errors(true);
        $leido = $dom->loadXML($texto, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if (! $leido) {
            throw new \RuntimeException('El archivo XML de Excel está dañado: ábrelo y guárdalo como .xlsx.');
        }
        $hojas = [];
        foreach ($dom->getElementsByTagNameNS($ss, 'Worksheet') as $w) {
            $hojas[$w->getAttributeNS($ss, 'Name')] ??= $w;
        }
        $elegida = self::elegirHoja(array_keys($hojas), $nombreHoja);
        if ($elegida === null) {
            throw new \RuntimeException('No se encontró ninguna hoja en el Excel.');
        }
        $celdas = [];
        $r = -1;
        foreach ($hojas[$elegida]->getElementsByTagNameNS($ss, 'Row') as $row) {
            $r = $row->hasAttributeNS($ss, 'Index') ? (int) $row->getAttributeNS($ss, 'Index') - 1 : $r + 1;
            $c = -1;
            foreach ($row->getElementsByTagNameNS($ss, 'Cell') as $cell) {
                $c = $cell->hasAttributeNS($ss, 'Index') ? (int) $cell->getAttributeNS($ss, 'Index') - 1 : $c + 1;
                $data = $cell->getElementsByTagNameNS($ss, 'Data')->item(0);
                if ($data && $r < self::MAX_FILAS && $c < self::MAX_COLUMNAS) {
                    $celdas[$r][$c] = match ($data->getAttributeNS($ss, 'Type')) {
                        'Number' => self::numero($data->textContent),
                        'Boolean' => $data->textContent === '1' ? 'VERDADERO' : 'FALSO',
                        'DateTime' => self::serialDeFecha($data->textContent),
                        default => $data->textContent,
                    };
                }
                $c += (int) $cell->getAttributeNS($ss, 'MergeAcross');
            }
            $celdas[$r] ??= [];
        }

        return self::tabla($celdas);
    }

    // ================================================================= .xlsx / .xlsm

    private static function xlsx(\ZipArchive $zip, ?string $nombreHoja): array
    {
        // Textos compartidos (la mayoría de celdas de texto apuntan aquí)
        $compartidos = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($xml)->si as $si) {
                $compartidos[] = self::textoXlsx($si);
            }
        }

        $hoja = $zip->getFromName(self::rutaHoja($zip, $nombreHoja));
        if ($hoja === false) {
            throw new \RuntimeException('No se encontró ninguna hoja en el Excel.');
        }

        $celdas = [];
        $numeroFila = 0;
        foreach (simplexml_load_string($hoja)->sheetData->row as $row) {
            $numeroFila = (int) $row['r'] ?: $numeroFila + 1;
            $columna = 0;
            foreach ($row->c as $c) {
                $columna = self::indiceColumna((string) $c['r']) ?? $columna;
                $celdas[$numeroFila - 1][$columna++] = match ((string) $c['t']) {
                    's' => $compartidos[(int) $c->v] ?? '',
                    'inlineStr' => self::textoXlsx($c->is),
                    'b' => (string) $c->v === '1' ? 'VERDADERO' : 'FALSO',
                    'str', 'e' => (string) $c->v,
                    default => self::numero((string) $c->v),
                };
            }
            $celdas[$numeroFila - 1] ??= [];
        }

        return self::tabla($celdas);
    }

    /**
     * Sigue workbook.xml → relaciones para hallar la hoja (no siempre es sheet1.xml): la de ese nombre (sin importar
     * mayúsculas ni espacios) o, si no hay, la primera.
     */
    private static function rutaHoja(\ZipArchive $zip, ?string $nombre = null): string
    {
        $libro = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro && $rels) {
            $wb = simplexml_load_string($libro);
            $hojas = [];
            foreach ($wb->sheets->sheet as $s) {
                $hojas[(string) $s['name']] = $s;
            }
            $elegida = self::elegirHoja(array_keys($hojas), $nombre);
            $hoja = $elegida !== null ? $hojas[$elegida] : null;
            $rId = $hoja ? (string) $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
            foreach (simplexml_load_string($rels)->Relationship as $r) {
                if ((string) $r['Id'] === $rId) {
                    $target = ltrim((string) $r['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** Texto de un <si> o <is>: simple (<t>) o con formato (<r><t>) */
    private static function textoXlsx(?\SimpleXMLElement $nodo): string
    {
        if (! $nodo) {
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

    // ================================================================= .ods (OpenDocument)

    private const ODS_TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

    private const ODS_OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';

    private const ODS_TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

    /** Tope de filas y columnas: las filas/celdas "repetidas" vacías del final de una hoja llegan al millón */
    private const MAX_FILAS = 200000;

    private const MAX_COLUMNAS = 1000;

    private static function ods(\ZipArchive $zip, ?string $nombreHoja): array
    {
        $dom = new \DOMDocument;
        if (! $dom->loadXML((string) $zip->getFromName('content.xml'), LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new \RuntimeException('El archivo .ods está dañado: ábrelo y vuelve a guardarlo.');
        }
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('table', self::ODS_TABLE);
        $tablas = [];
        foreach ($xp->query('//table:table[not(ancestor::table:table)]') as $t) {
            $tablas[$t->getAttributeNS(self::ODS_TABLE, 'name')] ??= $t;
        }
        $elegida = self::elegirHoja(array_keys($tablas), $nombreHoja);
        if ($elegida === null) {
            throw new \RuntimeException('No se encontró ninguna hoja en el archivo.');
        }

        $celdas = [];
        $f = 0;
        foreach ($xp->query('.//table:table-row', $tablas[$elegida]) as $row) {
            $repetir = max(1, (int) $row->getAttributeNS(self::ODS_TABLE, 'number-rows-repeated'));
            $fila = [];
            $c = 0;
            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof \DOMElement || ! in_array($cell->localName, ['table-cell', 'covered-table-cell'])) {
                    continue;
                }
                $veces = max(1, (int) $cell->getAttributeNS(self::ODS_TABLE, 'number-columns-repeated'));
                $valor = self::valorOds($cell);
                if ($valor !== '') {
                    for ($i = 0; $i < $veces && $c + $i < self::MAX_COLUMNAS; $i++) {
                        $fila[$c + $i] = $valor;
                    }
                }
                $c += $veces;
            }
            // Una fila vacía repetida (el relleno del final) solo mueve el contador
            if ($fila) {
                for ($i = 0; $i < $repetir && $f + $i < self::MAX_FILAS; $i++) {
                    $celdas[$f + $i] = $fila;
                }
            }
            $f += $repetir;
            if ($f >= self::MAX_FILAS) {
                break;
            }
        }

        return self::tabla($celdas);
    }

    private static function valorOds(\DOMElement $cell): string
    {
        $o = self::ODS_OFFICE;

        return match ($cell->getAttributeNS($o, 'value-type')) {
            'float', 'percentage', 'currency' => self::numero($cell->getAttributeNS($o, 'value')),
            'boolean' => $cell->getAttributeNS($o, 'boolean-value') === 'true' ? 'VERDADERO' : 'FALSO',
            'date' => self::serialDeFecha($cell->getAttributeNS($o, 'date-value')),
            // Solo los párrafos de la celda (un comentario también trae <text:p> adentro)
            default => implode("\n", array_map(fn ($p) => self::textoOds($p), array_values(array_filter(iterator_to_array($cell->childNodes),
                fn ($n) => $n instanceof \DOMElement && $n->namespaceURI === self::ODS_TEXT && $n->localName === 'p')))),
        };
    }

    /** El texto de un <text:p>, con sus espacios (<text:s text:c="3"/>), tabulaciones y saltos */
    private static function textoOds(\DOMNode $nodo): string
    {
        $s = '';
        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof \DOMText) {
                $s .= $hijo->data;
            } elseif ($hijo instanceof \DOMElement) {
                $s .= match ($hijo->localName) {
                    's' => str_repeat(' ', max(1, (int) $hijo->getAttributeNS(self::ODS_TEXT, 'c'))),
                    'tab' => "\t",
                    'line-break' => "\n",
                    'note', 'annotation' => '',
                    default => self::textoOds($hijo),
                };
            }
        }

        return $s;
    }

    /** Una fecha se entrega como en Excel (el número de serie), igual que al leer un .xlsx o un .xls */
    private static function serialDeFecha(string $fecha): string
    {
        try {
            $d = new \DateTimeImmutable($fecha, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return $fecha;
        }
        $base = new \DateTimeImmutable('1899-12-30', new \DateTimeZone('UTC'));
        $dias = (int) $base->diff($d)->format('%r%a');
        $fraccion = ((int) $d->format('H') * 3600 + (int) $d->format('i') * 60 + (int) $d->format('s')) / 86400;

        return self::numero((string) ($dias + $fraccion));
    }

    // ================================================================= .xls (Excel 97-2003: BIFF8 dentro de un archivo OLE)

    private const OLE_FIN = 0xFFFFFFFE;

    private static function xls(string $ruta, ?string $nombreHoja): array
    {
        $ole = file_get_contents($ruta);
        $libro = self::streamOle($ole, ['Workbook', 'Book']);
        unset($ole);

        // Las hojas (BOUNDSHEET) y la tabla de textos (SST) están en la parte global del libro, antes de las hojas
        $hojas = [];
        $sst = [];
        $pos = 0;
        $largo = strlen($libro);
        $version = null;
        while ($pos + 4 <= $largo) {
            ['t' => $tipo, 'l' => $l] = unpack('vt/vl', $libro, $pos);
            $datos = substr($libro, $pos + 4, $l);
            $pos += 4 + $l;
            if ($tipo === 0x0809 && $version === null) {
                $version = unpack('v', $datos)[1];
                if ($version !== 0x0600) {
                    throw new \RuntimeException('Este Excel es muy antiguo (Excel 95 o anterior): ábrelo y guárdalo como .xlsx.');
                }
            } elseif ($tipo === 0x002F) {
                throw new \RuntimeException('El Excel tiene contraseña: quítasela, guárdalo y vuelve a subirlo.');
            } elseif ($tipo === 0x0085) {
                $off = unpack('V', $datos)[1];
                if (ord($datos[5]) === 0) {     // 0 = hoja de cálculo (no gráfico ni macro)
                    $hojas[self::cadenaBiff($datos, 6, true)[0]] = $off;
                }
            } elseif ($tipo === 0x00FC) {
                // La SST sigue en los CONTINUE: se juntan sabiendo dónde empieza cada pedazo
                $bloques = [$datos];
                while ($pos + 4 <= $largo && unpack('v', $libro, $pos)[1] === 0x003C) {
                    $lc = unpack('v', $libro, $pos + 2)[1];
                    $bloques[] = substr($libro, $pos + 4, $lc);
                    $pos += 4 + $lc;
                }
                $sst = self::sst($bloques);
            } elseif ($tipo === 0x000A) {
                break;
            }
        }
        $elegida = self::elegirHoja(array_keys($hojas), $nombreHoja);
        if ($elegida === null) {
            throw new \RuntimeException('No se encontró ninguna hoja en el Excel.');
        }

        $celdas = [];
        $pos = $hojas[$elegida];
        $formula = null;   // celda de la fórmula cuyo texto viene en el siguiente registro STRING
        while ($pos + 4 <= $largo) {
            ['t' => $tipo, 'l' => $l] = unpack('vt/vl', $libro, $pos);
            $d = substr($libro, $pos + 4, $l);
            $pos += 4 + $l;
            if ($tipo === 0x000A) {
                break;
            }
            if (strlen($d) < 6 && $tipo !== 0x0207) {
                continue;
            }
            switch ($tipo) {
                case 0x00FD:    // LABELSST: texto de la tabla compartida
                    ['r' => $r, 'c' => $c, 'i' => $i] = unpack('vr/vc/vx/Vi', $d);
                    $celdas[$r][$c] = $sst[$i] ?? '';
                    break;
                case 0x0204:    // LABEL / RSTRING: texto en la misma celda
                case 0x00D6:
                    ['r' => $r, 'c' => $c] = unpack('vr/vc', $d);
                    $celdas[$r][$c] = self::cadenaBiff($d, 6)[0];
                    break;
                case 0x0203:    // NUMBER
                    ['r' => $r, 'c' => $c] = unpack('vr/vc', $d);
                    $celdas[$r][$c] = self::numero((string) unpack('e', $d, 6)[1]);
                    break;
                case 0x027E:    // RK: número comprimido
                    ['r' => $r, 'c' => $c, 'k' => $k] = unpack('vr/vc/vx/Vk', $d);
                    $celdas[$r][$c] = self::numero((string) self::rk($k));
                    break;
                case 0x00BD:    // MULRK: varios RK seguidos en la fila
                    ['r' => $r, 'c' => $c] = unpack('vr/vc', $d);
                    for ($o = 4; $o + 6 <= strlen($d) - 2; $o += 6, $c++) {
                        $celdas[$r][$c] = self::numero((string) self::rk(unpack('V', $d, $o + 2)[1]));
                    }
                    break;
                case 0x0205:    // BOOLERR: verdadero/falso o un error (#N/A…)
                    ['r' => $r, 'c' => $c] = unpack('vr/vc', $d);
                    $celdas[$r][$c] = ord($d[7]) === 1 ? '' : (ord($d[6]) ? 'VERDADERO' : 'FALSO');
                    break;
                case 0x0006:    // FORMULA: se toma el último resultado que guardó Excel
                    ['r' => $r, 'c' => $c] = unpack('vr/vc', $d);
                    if (substr($d, 12, 2) !== "\xFF\xFF") {
                        $celdas[$r][$c] = self::numero((string) unpack('e', $d, 6)[1]);
                    } else {
                        $celdas[$r][$c] = match (ord($d[6])) {
                            1 => ord($d[8]) ? 'VERDADERO' : 'FALSO',
                            default => '',
                        };
                        $formula = ord($d[6]) === 0 ? [$r, $c] : null;
                    }
                    break;
                case 0x0207:    // STRING: el texto que dio la fórmula anterior
                    if ($formula) {
                        $celdas[$formula[0]][$formula[1]] = self::cadenaBiff($d, 0)[0];
                        $formula = null;
                    }
                    break;
            }
        }

        return self::tabla($celdas);
    }

    /**
     * Saca un stream (el libro) del contenedor OLE (Compound File): sigue la cadena de sectores en la FAT, o en la
     * mini FAT si el stream es pequeño (menos de 4096 bytes).
     *
     * @param  array<int, string>  $nombres
     */
    private static function streamOle(string $ole, array $nombres): string
    {
        if (strlen($ole) < 512) {
            throw new \RuntimeException('El archivo .xls está dañado.');
        }
        $h = unpack('vmenor/vmayor/vorden/vsector/vmini', $ole, 0x18);
        $tam = 1 << $h['sector'];
        $tamMini = 1 << $h['mini'];
        $c = unpack('VnFat/VdirInicio/Vtx/Vcorte/VminiInicio/VnMini/VdifInicio/VnDif', $ole, 0x2C);
        if ($tam < 512 || $tam > 4096) {
            throw new \RuntimeException('El archivo .xls está dañado.');
        }
        $sector = fn (int $id) => substr($ole, ($id + 1) * $tam, $tam);
        $maxSectores = intdiv(strlen($ole), $tam) + 1;

        // Los sectores de la FAT: los primeros 109 en la cabecera, el resto en la cadena DIFAT
        $idsFat = array_values(unpack('V109', $ole, 0x4C));
        for ($id = $c['difInicio'], $n = 0; $id < 0xFFFFFFFA && $n < $c['nDif'] && $n < $maxSectores; $n++) {
            $v = array_values(unpack('V*', $sector($id)));
            $id = array_pop($v);
            array_push($idsFat, ...$v);
        }
        $fat = [];
        foreach (array_slice($idsFat, 0, $c['nFat']) as $id) {
            if ($id < 0xFFFFFFFA) {
                array_push($fat, ...array_values(unpack('V*', $sector($id))));
            }
        }
        $cadena = function (int $id, array $tabla, callable $leer) use ($maxSectores): string {
            $s = '';
            for ($n = 0; $id < 0xFFFFFFFA && isset($tabla[$id]) && $n < $maxSectores * 64; $n++) {
                $s .= $leer($id);
                $id = $tabla[$id];
            }

            return $s;
        };

        // El directorio: entradas de 128 bytes con el nombre (UTF-16), el tipo, el primer sector y el tamaño
        $dir = $cadena($c['dirInicio'], $fat, $sector);
        $raiz = null;
        $buscado = null;
        $cifrado = false;
        for ($o = 0; $o + 128 <= strlen($dir); $o += 128) {
            $largoNombre = unpack('v', $dir, $o + 0x40)[1];
            $nombre = mb_convert_encoding(substr($dir, $o, max(0, $largoNombre - 2)), 'UTF-8', 'UTF-16LE');
            $e = ['tipo' => ord($dir[$o + 0x42]), 'inicio' => unpack('V', $dir, $o + 0x74)[1], 'tam' => unpack('V', $dir, $o + 0x78)[1]];
            if ($e['tipo'] === 5) {
                $raiz = $e;
            } elseif ($e['tipo'] === 2 && in_array($nombre, $nombres, true)) {
                $buscado ??= $e;
            } elseif ($nombre === 'EncryptedPackage') {
                $cifrado = true;
            }
        }
        if ($cifrado) {
            throw new \RuntimeException('El Excel tiene contraseña: quítasela, guárdalo y vuelve a subirlo.');
        }
        if (! $buscado) {
            throw new \RuntimeException('El archivo no es un libro de Excel.');
        }
        if ($buscado['tam'] >= $c['corte'] || ! $raiz) {
            return substr($cadena($buscado['inicio'], $fat, $sector), 0, $buscado['tam']);
        }

        // Stream pequeño: va en el mini stream (la cadena del Root Entry) en sectores de 64 bytes
        $miniFat = array_values(unpack('V*', $cadena($c['miniInicio'], $fat, $sector)) ?: []);
        $miniStream = $cadena($raiz['inicio'], $fat, $sector);

        return substr($cadena($buscado['inicio'], $miniFat, fn (int $id) => substr($miniStream, $id * $tamMini, $tamMini)), 0, $buscado['tam']);
    }

    /**
     * Los textos de la SST. Cuando un texto no cabe en un registro sigue en el próximo CONTINUE, que empieza con un byte
     * que dice si lo que falta va comprimido (1 byte por letra) o en UTF-16.
     *
     * @param  array<int, string>  $bloques
     * @return array<int, string>
     */
    private static function sst(array $bloques): array
    {
        $datos = implode('', $bloques);
        $limites = [];      // dónde empieza cada CONTINUE dentro de $datos
        $acum = 0;
        foreach ($bloques as $b) {
            $acum += strlen($b);
            $limites[] = $acum;
        }
        $total = unpack('V', $datos, 4)[1];
        $pos = 8;
        $largo = strlen($datos);
        $textos = [];
        $siguienteLimite = function (int $p) use ($limites, $largo): int {
            foreach ($limites as $l) {
                if ($l > $p) {
                    return $l;
                }
            }

            return $largo;
        };
        for ($i = 0; $i < $total && $pos + 3 <= $largo; $i++) {
            $letras = unpack('v', $datos, $pos)[1];
            $op = ord($datos[$pos + 2]);
            $pos += 3;
            $runs = 0;
            $ext = 0;
            if ($op & 0x08) {
                $runs = unpack('v', $datos, $pos)[1];
                $pos += 2;
            }
            if ($op & 0x04) {
                $ext = unpack('V', $datos, $pos)[1];
                $pos += 4;
            }
            $ancho = $op & 0x01 ? 2 : 1;
            $s = '';
            while ($letras > 0 && $pos < $largo) {
                $hasta = $siguienteLimite($pos);
                $n = min($letras, intdiv($hasta - $pos, $ancho));
                $s .= self::caracteres(substr($datos, $pos, $n * $ancho), $ancho);
                $pos += $n * $ancho;
                $letras -= $n;
                if ($letras > 0) {
                    // Se partió: el CONTINUE trae el byte de opciones para el resto
                    $ancho = ord($datos[$pos]) & 0x01 ? 2 : 1;
                    $pos++;
                }
            }
            $pos += 4 * $runs + $ext;   // formato del texto y fonética: no se usan
            $textos[] = $s;
        }

        return $textos;
    }

    /**
     * Un texto BIFF8 que empieza en $o: largo (2 bytes, o 1 en los nombres de hoja), opciones y las letras.
     *
     * @return array{0: string, 1: int}
     */
    private static function cadenaBiff(string $d, int $o, bool $corto = false): array
    {
        $letras = $corto ? ord($d[$o] ?? "\0") : unpack('v', str_pad(substr($d, $o, 2), 2, "\0"))[1];
        $o += $corto ? 1 : 2;
        $op = ord($d[$o] ?? "\0");
        $o++;
        if ($op & 0x08) {
            $o += 2;
        }
        if ($op & 0x04) {
            $o += 4;
        }
        $ancho = $op & 0x01 ? 2 : 1;

        return [self::caracteres(substr($d, $o, $letras * $ancho), $ancho), $o + $letras * $ancho];
    }

    private static function caracteres(string $bytes, int $ancho): string
    {
        return mb_convert_encoding($bytes, 'UTF-8', $ancho === 2 ? 'UTF-16LE' : 'ISO-8859-1');
    }

    /** Número RK: un entero de 30 bits o los 30 bits altos de un double; con el bit 0 se divide entre 100 */
    private static function rk(int $rk): float|int
    {
        if ($rk & 0x02) {
            $n = $rk >> 2;
            if ($n & 0x20000000) {
                $n -= 0x40000000;     // negativo
            }
        } else {
            $n = unpack('e', pack('VV', 0, $rk & 0xFFFFFFFC))[1];
        }

        return $rk & 0x01 ? $n / 100 : $n;
    }

    // ================================================================= comunes

    /**
     * La hoja con ese nombre (sin importar mayúsculas ni espacios) o, si no hay, la primera.
     *
     * @param  array<int, string>  $nombres
     */
    private static function elegirHoja(array $nombres, ?string $buscar): ?string
    {
        if ($buscar !== null) {
            foreach ($nombres as $n) {
                if (mb_strtoupper(trim($n)) === mb_strtoupper(trim($buscar))) {
                    return $n;
                }
            }
        }

        return $nombres[0] ?? null;
    }

    /**
     * Celdas [fila][columna] → filas seguidas, rellenando las filas y celdas vacías para que el número de fila y la
     * columna coincidan con el Excel.
     *
     * @param  array<int, array<int, string>>  $celdas
     * @return array<int, array<int, string>>
     */
    private static function tabla(array $celdas): array
    {
        if (! $celdas) {
            return [];
        }
        $filas = array_fill(0, min(max(array_keys($celdas)), self::MAX_FILAS) + 1, []);
        foreach ($celdas as $r => $fila) {
            if ($r >= self::MAX_FILAS || ! $fila) {
                continue;
            }
            $fila += array_fill(0, min(max(array_keys($fila)), self::MAX_COLUMNAS) + 1, '');
            ksort($fila);
            $filas[$r] = array_values(array_slice($fila, 0, self::MAX_COLUMNAS, true));
        }

        return $filas;
    }

    /** Evita notaciones como 2.9999999999999996 o 1E+15 en números guardados por Excel */
    private static function numero(string $v): string
    {
        if ($v === '' || ! is_numeric($v)) {
            return $v;
        }
        $n = (float) $v;

        return floor($n) == $n && abs($n) < 1e15 ? number_format($n, 0, '.', '') : rtrim(rtrim(number_format(round($n, 6), 6, '.', ''), '0'), '.');
    }

    private static function indiceColumna(string $ref): ?int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
            return null;
        }
        $n = 0;
        foreach (str_split($m[1]) as $l) {
            $n = $n * 26 + (ord($l) - 64);
        }

        return $n - 1;
    }
}
