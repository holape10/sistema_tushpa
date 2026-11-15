<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Support\Tenancy\Respaldos;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** En Copias de seguridad, varios sistemas del mismo RUC se distinguen por su subdominio y su base */
class RespaldosEtiquetasTest extends TestCase
{
    public function test_cada_sistema_del_mismo_ruc_se_ve_con_su_subdominio_y_su_base(): void
    {
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        DB::connection('central')->beginTransaction();
        try {
            Cliente::create(['ruc' => '10999999991', 'base_datos' => 'bd_10999999991', 'subdominio' => 'tiendita', 'razon_social' => 'TIENDITA PRUEBA', 'estado' => 'ACTIVO']);
            Cliente::create(['ruc' => '10999999991', 'base_datos' => 'bd_10999999991_2', 'subdominio' => 'tiendita2', 'razon_social' => 'TIENDITA PRUEBA', 'estado' => 'ACTIVO']);

            $bases = Respaldos::bases();

            $this->assertStringContainsString('tiendita.', $bases['bd_10999999991']);
            $this->assertStringContainsString('tiendita2.', $bases['bd_10999999991_2']);
            $this->assertStringEndsWith('· bd_10999999991_2', $bases['bd_10999999991_2']);
            $this->assertNotSame($bases['bd_10999999991'], $bases['bd_10999999991_2']);
        } finally {
            DB::connection('central')->rollBack();
        }
    }
}
