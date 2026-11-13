<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Sunat\GuiaRemision;
use Illuminate\Support\Facades\DB;

/**
 * Guías de una empresa que factura por OSE: su usuario/clave de facturación son los de Nubefact y SUNAT no los acepta en
 * el API de guías; se usan los SOL del SIRE. Y el conductor se escribe en un solo campo (nombre completo).
 */
class GuiaOseTest extends FlujoTestCase
{
    private function credenciales(string $envio, bool $conSire): array
    {
        // Las claves se guardan cifradas: se ponen con el modelo
        $e = Empresa::findOrFail($this->usuario->IdEmpresa);
        $e->tip_env_fac_id = $envio;
        $e->wsusuario = 'OSEUSER';
        $e->claveSunat = 'clave-ose';
        $e->sire_usuario = $conSire ? 'SIREUSR' : null;
        $e->sire_clave = $conSire ? 'clave-sol' : null;
        $e->sol_usuario = null;
        $e->save();
        $gre = new GuiaRemision(Empresa::findOrFail($this->usuario->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));

        return (new \ReflectionMethod($gre, 'credencialesSol'))->invoke($gre);
    }

    public function test_con_ose_las_guias_usan_el_usuario_sol_del_sire(): void
    {
        $this->assertSame(['SIREUSR', 'clave-sol'], $this->credenciales('02', true));
    }

    public function test_directo_a_sunat_siguen_con_el_usuario_de_facturacion(): void
    {
        $this->assertSame(['OSEUSER', 'clave-ose'], $this->credenciales('01', true));
    }

    public function test_con_ose_sin_usuario_sol_avisa_donde_ponerlo(): void
    {
        $this->expectExceptionMessage('Usuario SOL para el SIRE y las guías');
        $this->credenciales('02', false);
    }

    public function test_el_conductor_con_nombre_completo_va_con_apellido_aunque_tenga_un_solo_nombre(): void
    {
        $id = GuiaRemision::registrar($this->usuario, ['fecha_traslado' => now()->toDateString(), 'motivo' => '01', 'modalidad' => '02', 'peso' => 10,
            'dest_tdicod' => '6', 'dest_num' => '20493508645', 'dest_nom' => 'HONDA SELVA DEL PERU SA', 'partida_ubigeo' => '160101', 'partida_direccion' => 'JR PRUEBA 1',
            'llegada_ubigeo' => '160101', 'llegada_direccion' => 'CARRETERA IQUITOS NAUTA KM 5.5',
            'cond_tdicod' => '1', 'cond_num' => '70073488', 'cond_nombres' => 'HELGA', 'cond_apellidos' => '', 'cond_licencia' => 'Q123456', 'placa' => '95518L'],
            [['descripcion' => 'DOCUMENTOS', 'umecod' => 'NIU', 'cantidad' => 1]]);
        $g = DB::table('gre_cabecera')->where('gre_id', $id)->first();

        $xml = GuiaRemision::paraUsuario($this->usuario)->xml($g, DB::table('gre_detalle')->where('gre_id', $id)->get());

        $this->assertStringContainsString('<cbc:FirstName>HELGA</cbc:FirstName>', $xml);
        $this->assertStringContainsString('<cbc:FamilyName>-</cbc:FamilyName>', $xml);
    }

    public function test_la_pantalla_pide_el_nombre_completo_del_conductor(): void
    {
        $this->get(route('guias.crear'))->assertOk()->assertSee('Nombre completo')->assertDontSee('>Apellidos<', false);
    }
}
