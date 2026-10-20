<?php

namespace Tests\Unit;

use App\Support\NombreCorto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NombreCortoTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function nombres(): array
    {
        return [
            'dos nombres y dos apellidos' => ['ÁLVARO JUNIOR IZQUIERDO TUESTA', 'ÁLVARO IZQUIERDO'],
            'apellido con partícula' => ['LEONARDO DEL CASTILLO PEÑA', 'LEONARDO DEL CASTILLO'],
            'dos nombres y apellido con partícula' => ['ARIANNY VALENTINA LA CRUZ ALARCON', 'ARIANNY LA CRUZ'],
            'apellidos, nombres' => ['TANGOA PACAYA, JIM BORIS', 'JIM TANGOA'],
            'un nombre y dos apellidos' => ['LESTER LAVI VASQUEZ', 'LESTER LAVI'],
            'nombre y apellido' => ['KIARA CHAHUD', 'KIARA CHAHUD'],
            'razón social' => ['CORPORATION 360 S.A.C.', 'CORPORATION 360 S.A.C.'],
            'espacios de más' => ['  DANICA   KIARA RAMIREZ MORENO ', 'DANICA RAMIREZ'],
        ];
    }

    #[DataProvider('nombres')]
    public function test_deja_primer_nombre_y_primer_apellido(string $completo, string $corto): void
    {
        $this->assertSame($corto, NombreCorto::de($completo));
    }
}
