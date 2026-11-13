<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Sunat\SunatService;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Support\Facades\DB;

/** Empresa con "Tipo de envío: OSE" (como en el sistema anterior): sus comprobantes van a Nubefact y no directo a SUNAT */
class EnvioOseTest extends FlujoTestCase
{
    private function sunat(array $empresa): SunatService
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update($empresa);

        return new SunatService(Empresa::findOrFail($this->usuario->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));
    }

    public function test_con_ose_envia_a_nubefact_en_produccion_y_a_su_demo_en_pruebas(): void
    {
        $this->assertSame(SunatService::OSE_PRODUCCION, $this->sunat(['tip_env_fac_id' => '02', 'produccion' => '1'])->endpoint());
        // En empresas antiguas el OSE quedó guardado como 2 (sin el cero)
        $this->assertSame(SunatService::OSE_BETA, $this->sunat(['tip_env_fac_id' => '2', 'produccion' => '0'])->endpoint());
        $this->assertSame(SunatEndpoints::FE_PRODUCCION, $this->sunat(['tip_env_fac_id' => '01', 'produccion' => '1'])->endpoint());
    }

    public function test_con_ose_sin_su_usuario_y_clave_avisa_que_faltan(): void
    {
        $sunat = $this->sunat(['tip_env_fac_id' => '02', 'produccion' => '0', 'wsusuario' => '', 'claveSunat' => '']);

        $this->expectExceptionMessage('configura el usuario y la clave del OSE');
        (new \ReflectionMethod($sunat, 'see'))->invoke($sunat);
    }
}
