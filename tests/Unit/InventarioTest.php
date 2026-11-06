<?php

namespace Tests\Unit;

use App\Support\Tenancy\Inventario;
use PHPUnit\Framework\TestCase;

/** Limpieza de lo de prueba: qué bases son huérfanas y cuáles nunca se pueden borrar */
class InventarioTest extends TestCase
{
    private const PROTEGIDAS = ['bd_sistema_tushpa', 'bd_tushpa_central', 'bd_20614580063', 'bd_ws_20222222222'];

    public function test_huerfanas_son_las_que_no_usa_el_sistema_ni_mysql(): void
    {
        $servidor = ['mysql', 'information_schema', 'performance_schema', 'sys', 'bd_sistema_tushpa', 'bd_tushpa_central',
            'bd_20614580063', 'bd_ws_20222222222', 'antiguo_20114052311_20261006_103135', 'bd_10476645005'];

        $this->assertSame(['antiguo_20114052311_20261006_103135', 'bd_10476645005'], Inventario::filtrarHuerfanas($servidor, self::PROTEGIDAS));
    }

    public function test_nunca_se_borran_las_del_sistema_ni_las_de_una_empresa(): void
    {
        $servidor = array_merge(self::PROTEGIDAS, ['mysql', 'antiguo_1']);
        $motivo = fn (string $b) => Inventario::motivoParaNoBorrar($b, self::PROTEGIDAS, $servidor);

        foreach (self::PROTEGIDAS as $base) {
            $this->assertNotNull($motivo($base), $base);
        }
        $this->assertNotNull($motivo('mysql'));
        $this->assertNotNull($motivo('bd_x`; DROP DATABASE mysql; --'), 'Un nombre raro nunca llega al DROP.');
        $this->assertNotNull($motivo('no_existe'));
        $this->assertNull($motivo('antiguo_1'), 'Una huérfana sí se puede borrar (con su respaldo).');
    }
}
