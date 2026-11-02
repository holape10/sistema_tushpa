<?php

namespace Tests\Unit;

use App\Support\Tenancy\CertificadosClientes;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** Aviso al dueño de los certificados digitales por vencer: cuándo se avisa y qué dice */
class CertificadosClientesTest extends TestCase
{
    public function test_avisa_a_30_y_15_dias_y_cada_dia_de_la_ultima_semana_y_la_siguiente(): void
    {
        foreach ([30, 15, 7, 3, 1, 0, -1, -7] as $dias) {
            $this->assertTrue(CertificadosClientes::tocaAvisar($dias), "Debe avisar a {$dias} días.");
        }
        foreach ([60, 29, 20, 8, -8, -30] as $dias) {
            $this->assertFalse(CertificadosClientes::tocaAvisar($dias), "No debe avisar a {$dias} días.");
        }
    }

    public function test_el_mensaje_dice_cuando_vence_cada_empresa(): void
    {
        $texto = CertificadosClientes::mensaje([
            ['empresa' => 'COCOROCO', 'ruc' => '20609838427', 'vence' => Carbon::parse('2026-11-01'), 'dias' => -1, 'telefono' => '987654321'],
            ['empresa' => 'WILCAT', 'ruc' => '20567128441', 'vence' => Carbon::parse('2026-11-09'), 'dias' => 7, 'telefono' => null],
        ]);

        $this->assertStringContainsString('COCOROCO (RUC 20609838427): ❌ VENCIÓ hace 1 día(s) (01/11/2026) · 📞 987654321', $texto);
        $this->assertStringContainsString('WILCAT (RUC 20567128441): vence en 7 días (09/11/2026)', $texto);
    }
}
