<?php

namespace Tests\Unit;

use App\Support\GimnasioImportar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GimnasioImportarTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function fechas(): array
    {
        return [
            'número de serie de Excel' => ['46245', '2026-08-11'],
            'texto día/mes/año' => ['12/06/2026', '2026-06-12'],
            'con guiones' => ['3-10-2026', '2026-10-03'],
            'año mal escrito' => ['12/06/20266', null],
            'sin barra' => ['07/072026', null],
            'fecha imposible' => ['31/02/2026', null],
            'texto' => ['15 DE DICIEMBRE', null],
            'vacía' => ['', null],
        ];
    }

    #[DataProvider('fechas')]
    public function test_lee_las_fechas_del_excel(?string $valor, ?string $esperada): void
    {
        $this->assertSame($esperada, GimnasioImportar::fecha($valor));
    }

    /** @return array<string, array{string, string}> */
    public static function documentos(): array
    {
        return [
            'DNI normal' => ['70580197', '70580197'],
            'DNI al que Excel le quitó el cero' => ['5410478', '05410478'],
            'carnet de extranjería' => ['002345678', '002345678'],
            'con espacios' => [' 7058 0197 ', '70580197'],
            'basura' => ['.', ''],
            'vacío' => ['', ''],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function planes(): array
    {
        return [
            'pegado' => ['3MESES', '3 MESES'],
            'minúsculas' => ['1mes', '1 MES'],
            'singular con más de uno' => ['2 MES', '2 MESES'],
            'espacios de más' => ['2  MESES', '2 MESES'],
            'días' => ['15DIAS', '15 DIAS'],
            'semana' => ['1SEMANA', '1 SEMANA'],
            'con texto después' => ['1 MES PROMO 2X1', '1 MES PROMO 2X1'],
            'sin número' => ['INTERDIARIO', 'INTERDIARIO'],
        ];
    }

    #[DataProvider('planes')]
    public function test_escribe_el_plan_parejo(string $texto, string $esperado): void
    {
        $this->assertSame($esperado, GimnasioImportar::plan($texto));
    }

    #[DataProvider('documentos')]
    public function test_limpia_el_documento(string $valor, string $esperado): void
    {
        $this->assertSame($esperado, GimnasioImportar::documento($valor));
    }
}
