<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Support\Tenancy\Provisionador;
use Illuminate\Support\Facades\DB;

/** El mismo RUC con sistema en otra marca (otro negocio): el nuevo sistema nunca repite su número (ni su rango de resúmenes) */
class RucEnOtraMarcaTest extends FlujoTestCase
{
    private const RUC = '20999999991';

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        parent::tearDown();
    }

    public function test_el_nuevo_sistema_salta_los_numeros_que_ya_usa_otra_marca(): void
    {
        DB::connection('central')->beginTransaction();
        Cliente::create(['ruc' => self::RUC, 'razon_social' => 'OTRO NEGOCIO', 'estado' => 'ACTIVO', 'base_datos' => 'bd_ws_'.self::RUC]);
        Cliente::create(['ruc' => self::RUC, 'razon_social' => 'OTRO NEGOCIO 2', 'estado' => 'ACTIVO', 'base_datos' => 'bd_ws_'.self::RUC.'_2']);

        // La 1 y la 2 son de la otra marca: este panel crea la 3 (resúmenes desde 201), aunque bd_{RUC}_2 esté libre
        $this->assertSame(3, Provisionador::siguienteSede(self::RUC, 'bd_'));
    }
}
