<?php
namespace App\Support;

/**
 * Genera un .xlsx real (Office Open XML) sin librerías externas: varias hojas, encabezado en negrita,
 * fila de títulos fija, autofiltro y montos como números. Los textos con ceros a la izquierda
 * (RUC, series, números de comprobante) se mantienen como texto.
 */
class Excel
{
    private array $hojas = [];

    /**
     * @param array $cabecera  títulos de columna, o varias filas de títulos (array de arrays)
     * @param array $filas     filas (arrays de valores); int/float = número; los textos se dejan como texto
     * @param array $numericas índices de columna (desde 0) cuyos textos numéricos ("0", "177", "-100.00") van como número
     */
    public function hoja(string $nombre, array $cabecera, array $filas, array $numericas = []): self
    {
        $nombre = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $nombre), 0, 31);
        $numericas = array_flip($numericas);
        $this->hojas[] = compact('nombre', 'cabecera', 'filas', 'numericas');
        return $this;
    }

    /** Guarda el .xlsx en un archivo temporal y devuelve su ruta */
    public function guardar(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($ruta, \ZipArchive::OVERWRITE);

        $n = count($this->hojas);
        $tipos = $rels = $hojasXml = '';
        foreach ($this->hojas as $i => $h) {
            $id = $i + 1;
            $tipos .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $rels .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
            $hojasXml .= '<sheet name="' . $this->x($h['nombre']) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
            $zip->addFromString("xl/worksheets/sheet{$id}.xml", $this->hojaXml($h['cabecera'], $h['filas'], $h['numericas']));
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $tipos . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $hojasXml . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        // Estilos: 0 normal | 1 encabezado (negrita, fondo) | 2 monto #,##0.00
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF312E81"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment wrapText="1" vertical="center"/></xf>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            . '</styleSheet>');

        $zip->close();
        return $ruta;
    }

    private function hojaXml(array $cabecera, array $filas, array $numericas): string
    {
        // Una o varias filas de títulos (ej. la plantilla CONCAR usa 3: campo, restricciones y tamaño)
        $titulos = is_array($cabecera[0] ?? null) ? $cabecera : [$cabecera];
        $nt = count($titulos);
        $cols = max(max(array_map('count', $titulos)), ...array_map('count', $filas ?: [[]]));
        $ultima = $this->columna(max($cols, 1)) . (count($filas) + $nt);

        // Ancho de cada columna según su contenido más largo (tope 45)
        $anchos = '';
        for ($c = 0; $c < $cols; $c++) {
            $largo = mb_strlen((string) ($titulos[0][$c] ?? ''));
            foreach (array_slice($filas, 0, 500) as $f) {
                $largo = max($largo, mb_strlen((string) ($f[$c] ?? '')));
            }
            $anchos .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . min(45, max(8, $largo + 2)) . '" customWidth="1"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $nt . '" topLeftCell="A' . ($nt + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $anchos . '</cols><sheetData>';

        foreach ($titulos as $t => $filaTitulo) {
            $r = $t + 1;
            $xml .= '<row r="' . $r . '">';
            foreach ($filaTitulo as $c => $titulo) {
                $xml .= '<c r="' . $this->columna($c + 1) . $r . '" t="inlineStr" s="1"><is><t>' . $this->x((string) $titulo) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        foreach ($filas as $i => $fila) {
            $r = $i + $nt + 1;
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($fila) as $c => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                $ref = $this->columna($c + 1) . $r;
                $esMonto = isset($numericas[$c]) && is_string($valor) && preg_match('/^-?\d+(\.\d+)?$/', trim($valor));
                if (is_int($valor) || is_float($valor) || $esMonto) {
                    $xml .= '<c r="' . $ref . '" s="2"><v>' . (float) $valor . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $this->x((string) $valor) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData><autoFilter ref="A' . $nt . ':' . $ultima . '"/></worksheet>';
    }

    private function columna(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    private function x(string $texto): string
    {
        // Quita caracteres de control no válidos en XML
        $texto = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $texto) ?? '';
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
