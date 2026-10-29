<?php

namespace Tests\Unit;

use App\Support\CourierManifiestos;
use App\Support\CourierWeb;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CourierOperacionTest extends TestCase
{
    /** @return array<string, array{string, ?array{codigo: string, bulto: ?int}}> */
    public static function lecturas(): array
    {
        return [
            'la guía escrita a mano' => ['48gyvxmsz4', ['codigo' => '48GYVXMSZ4', 'bulto' => null]],
            'el QR de la etiqueta con su bulto' => ['https://20601234567.tushpa.app/rastreo/48GYVXMSZ4?b=2', ['codigo' => '48GYVXMSZ4', 'bulto' => 2]],
            'el QR de la copia del remitente' => ['https://demos.tushpa.app/rastreo/48GYVXMSZ4', ['codigo' => '48GYVXMSZ4', 'bulto' => null]],
            'con espacios del lector' => ["  48GYVXMSZ4\n", ['codigo' => '48GYVXMSZ4', 'bulto' => null]],
            'una guía con letras que no usamos (O, I)' => ['48GYVXMSO4', null],
            'cualquier otro código' => ['7750182000123', null],
        ];
    }

    #[DataProvider('lecturas')]
    public function test_lee_la_guia_escaneada(string $leido, ?array $esperado): void
    {
        $this->assertSame($esperado, CourierManifiestos::leerCodigo($leido));
    }

    public function test_la_recepcion_separa_completos_incompletos_y_faltantes(): void
    {
        $r = CourierManifiestos::resumenRecepcion([10 => 1, 11 => 3, 12 => 2, 13 => 2], [10 => 1, 11 => 2, 13 => 5]);

        $this->assertSame([10, 13], $r['completos']);
        $this->assertSame([11 => [2, 3]], $r['incompletos']);
        $this->assertSame([12], $r['faltantes']);
    }

    public function test_sin_escanear_llega_todo_completo(): void
    {
        $piezas = [5 => 2, 6 => 1];

        $this->assertSame([5, 6], CourierManifiestos::resumenRecepcion($piezas, $piezas)['completos']);
    }

    /** @return array<string, array{string, bool}> */
    public static function verificaciones(): array
    {
        return [
            'DNI del remitente' => ['70000101', true],
            'RUC del destinatario' => ['20601234567', true],
            'celular del destinatario con +51' => ['+51 987 654 321', true],
            'celular del remitente' => ['965100101', true],
            'otro DNI' => ['12345678', false],
            'muy corto para comprobar' => ['4321', false],
        ];
    }

    #[DataProvider('verificaciones')]
    public function test_el_reclamo_lo_hace_el_remitente_o_el_destinatario(string $dato, bool $esperado): void
    {
        $envio = (object) ['rem_doc' => '70000101', 'des_doc' => '20601234567', 'rem_telefono' => '965100101', 'des_telefono' => '987654321'];

        $this->assertSame($esperado, CourierWeb::esDelCliente($envio, $dato));
    }
}
