<?php

namespace Tests\Unit;

use App\Support\Excel;
use App\Support\ExcelLector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El mismo libro guardado en cada formato de Excel se lee igual. Los .xls/.ods de tests/fixtures/excel siguen cómo los
 * guardan Excel y LibreOffice: la tabla de textos partida en CONTINUE, números RK/MULRK, fórmulas, el mini stream de los
 * libros pequeños, filas y celdas "repetidas" hasta el millón.
 */
class ExcelLectorTest extends TestCase
{
    private const EMPRESAS = [
        ['RUC *', 'Razón social *', 'Nombre comercial', 'Dirección', 'Usuario SOL principal (SIRE)', 'Clave SOL principal (SIRE)', 'ID API SUNAT - client_id (SIRE)'],
        ['20600695771', 'COMPAÑÍA AMAZÓNICA DEMO S.A.C.', '', 'JR. PRÓSPERO 123 - IQUITOS', 'USUARIO1', '123456', 'a1b2c3d4-0000-1111-2222-333344445555'],
        ['10609005110', 'PERSONA NATURAL ÑANDÚ', '', '', '', '', 'ID-FORMULA'],
    ];

    private static function fixture(string $nombre): string
    {
        return dirname(__DIR__).'/fixtures/excel/'.$nombre;
    }

    /** @return array<string, array{string}> */
    public static function formatos(): array
    {
        return [
            'Excel 97-2003 (.xls)' => ['libro.xls'],
            'LibreOffice (.ods)' => ['libro.ods'],
            'Hoja de cálculo XML 2003' => ['libro_xml2003.xls'],
            'tabla HTML guardada como .xls' => ['libro_html.xls'],
            'texto con tabulaciones guardado como .xls' => ['libro_tab.xls'],
            'CSV de Excel en Windows' => ['libro.csv'],
        ];
    }

    #[DataProvider('formatos')]
    public function test_lee_la_hoja_pedida_igual_en_cada_formato(string $archivo): void
    {
        // La hoja Empresas no es la primera: se busca por su nombre (sin importar mayúsculas)
        $this->assertSame(self::EMPRESAS, ExcelLector::filas(self::fixture($archivo), pathinfo($archivo, PATHINFO_EXTENSION), 'empresas'));
    }

    public function test_el_formato_se_reconoce_por_el_contenido_y_no_por_la_extension(): void
    {
        $this->assertSame(self::EMPRESAS, ExcelLector::filas(self::fixture('libro.xls'), 'xlsx', 'Empresas'));
        $this->assertSame(self::EMPRESAS, ExcelLector::filas(self::fixture('libro.ods'), '', 'Empresas'));
    }

    public function test_xlsx_y_xlsm(): void
    {
        $ruta = (new Excel)->hoja('Otra', ['x'], [['y']])->hoja('Empresas', self::EMPRESAS[0], array_slice(self::EMPRESAS, 1))->guardar();
        try {
            $this->assertSame(self::EMPRESAS, ExcelLector::filas($ruta, 'xlsx', 'Empresas'));
            // Un .xlsm (con macros) es el mismo formato
            $this->assertSame(self::EMPRESAS, ExcelLector::filas($ruta, 'xlsm', 'Empresas'));
        } finally {
            @unlink($ruta);
        }
    }

    public function test_un_xls_pequeno_que_va_en_el_mini_stream(): void
    {
        $this->assertSame(array_slice(self::EMPRESAS, 0, 2), ExcelLector::filas(self::fixture('libro_pequeno.xls'), 'xls', 'Empresas'));
    }

    /** @return array<string, array{string}> */
    public static function conMuchosTextos(): array
    {
        return ['xls' => ['libro.xls'], 'ods' => ['libro.ods']];
    }

    #[DataProvider('conMuchosTextos')]
    public function test_muchas_filas_con_textos_largos(string $archivo): void
    {
        $filas = ExcelLector::filas(self::fixture($archivo), '', 'Muchos');

        $this->assertCount(700, $filas);
        foreach ([0, 180, 181, 699] as $i) {
            $this->assertSame(['Texto largo número '.($i + 1).' con tildes áéíóú y eñe ñ', (string) ($i * 3)], $filas[$i]);
        }
    }

    public function test_las_otras_formas_de_guardar_numeros_y_textos_en_xls(): void
    {
        $this->assertSame([['7', '-8', '9'], ['12.34', '0.18', 'VERDADERO', 'Precio en €: Ω']],
            ExcelLector::filas(self::fixture('libro.xls'), 'xls', 'Tipos'));
    }

    public function test_celdas_repetidas_combinadas_y_fechas_en_ods(): void
    {
        $this->assertSame([
            ['5', '5', "A   B negrita\nsegunda línea", '46245', '0.18', 'VERDADERO'],
            ['combinada', '', 'tras'],
            ['igual'],
            ['igual'],
        ], ExcelLector::filas(self::fixture('libro.ods'), 'ods', 'Tipos'));
    }

    public function test_sin_la_hoja_pedida_lee_la_primera(): void
    {
        $this->assertSame([['Esta hoja no se importa']], ExcelLector::filas(self::fixture('libro.xls'), 'xls', 'NO EXISTE'));
    }

    public function test_un_archivo_que_no_es_excel_avisa(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xls');
        file_put_contents($ruta, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 2000));
        try {
            $this->expectException(\RuntimeException::class);
            ExcelLector::filas($ruta, 'xls');
        } finally {
            @unlink($ruta);
        }
    }
}
