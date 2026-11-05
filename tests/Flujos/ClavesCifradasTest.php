<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

/** La clave SOL de envío y la del certificado van cifradas: no salen en los respaldos ni en la pantalla */
class ClavesCifradasTest extends FlujoTestCase
{
    private function ruc(): string
    {
        return (string) $this->usuario->IdEmpresa;
    }

    public function test_se_guardan_cifradas_y_nunca_se_muestran(): void
    {
        $this->patch(route('empresas.update', $this->ruc()), ['nomEmpresa' => 'EMPRESA DE PRUEBA', 'dirEmpresa' => 'LIMA', 'envio' => 1,
            'txtWsUsuario' => 'USUARIO1', 'txtWsContrasena' => 'CLAVE-SOL-SECRETA', 'txtPassCert' => 'CLAVE-CERT-SECRETA'])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();

        $fila = DB::table('empresa')->where('IdEmpresa', $this->ruc())->first();
        $this->assertStringNotContainsString('CLAVE-SOL-SECRETA', (string) $fila->claveSunat, 'En la base (y en los respaldos) va cifrada.');
        $this->assertStringNotContainsString('CLAVE-CERT-SECRETA', (string) $fila->passcert);
        $this->assertSame(['CLAVE-SOL-SECRETA', 'CLAVE-CERT-SECRETA'], [Empresa::find($this->ruc())->claveSunat, Empresa::find($this->ruc())->passcert],
            'El sistema la sigue leyendo para enviar a SUNAT.');

        $this->get(route('empresas.edit', $this->ruc()))->assertOk()->assertDontSee('CLAVE-SOL-SECRETA')->assertDontSee('CLAVE-CERT-SECRETA')
            ->assertSee('Guardada · escribe para cambiarla');

        // Guardar sin escribirlas las conserva
        $this->patch(route('empresas.update', $this->ruc()), ['nomEmpresa' => 'EMPRESA DE PRUEBA', 'dirEmpresa' => 'LIMA', 'envio' => 1, 'txtWsUsuario' => 'USUARIO1'])->assertSessionHasNoErrors();
        $this->assertSame('CLAVE-SOL-SECRETA', Empresa::find($this->ruc())->claveSunat);
    }

    public function test_una_clave_de_antes_en_texto_plano_se_sigue_leyendo(): void
    {
        // Una base que aún no pasó por la migración: el envío a SUNAT no se corta
        DB::table('empresa')->where('IdEmpresa', $this->ruc())->update(['claveSunat' => 'ClaveAntigua1']);
        $this->assertSame('ClaveAntigua1', Empresa::find($this->ruc())->claveSunat);

        // La migración la cifra
        (include database_path('migrations/2027_01_22_100001_cifrar_claves_empresa.php'))->up();
        $this->assertNotSame('ClaveAntigua1', DB::table('empresa')->where('IdEmpresa', $this->ruc())->value('claveSunat'));
        $this->assertSame('ClaveAntigua1', Empresa::find($this->ruc())->claveSunat);
    }

    public function test_solo_el_administrador_entra_a_los_datos_de_la_empresa(): void
    {
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.$this->usuario->IdUsuario.'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('empresas.edit', $this->ruc()))->assertForbidden();
        $this->actingAs($cajero)->patch(route('empresas.update', $this->ruc()), ['nomEmpresa' => 'HACKEADA', 'dirEmpresa' => 'X', 'envio' => 1, 'produccion' => 1])->assertForbidden();
        $this->assertNotSame('HACKEADA', DB::table('empresa')->where('IdEmpresa', $this->ruc())->value('NomEmpresa'));
    }
}
