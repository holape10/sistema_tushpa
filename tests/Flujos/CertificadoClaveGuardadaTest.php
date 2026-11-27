<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use Illuminate\Http\UploadedFile;

/** Subir el certificado sin escribir su contraseña usa la que ya está guardada (por ejemplo, la importada del sistema antiguo) */
class CertificadoClaveGuardadaTest extends FlujoTestCase
{
    protected function tearDown(): void
    {
        foreach (['pfx', 'pem'] as $ext) {
            @unlink(storage_path('app/certificados/'.$this->usuario->IdEmpresa.'.'.$ext));
        }
        parent::tearDown();
    }

    /** Un certificado .pfx de prueba con esa contraseña */
    private function pfx(string $clave): UploadedFile
    {
        $cnf = tempnam(sys_get_temp_dir(), 'cnf');
        file_put_contents($cnf, "[req]\ndistinguished_name=dn\n[dn]\n");
        $opc = ['config' => $cnf, 'private_key_bits' => 2048];
        $llave = openssl_pkey_new($opc);
        $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'PRUEBA'], $llave, $opc), null, $llave, 365, $opc);
        openssl_pkcs12_export($cert, $contenido, $llave, $clave);
        @unlink($cnf);
        $ruta = tempnam(sys_get_temp_dir(), 'pfx');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, 'certificado.pfx', 'application/x-pkcs12', null, true);
    }

    private function subir(UploadedFile $pfx)
    {
        return $this->from(route('empresas.edit', $this->usuario->IdEmpresa))->patch(route('empresas.update', $this->usuario->IdEmpresa), [
            'nomEmpresa' => 'EMPRESA DE PRUEBA', 'dirEmpresa' => 'LIMA', 'txtWsUsuario' => 'DEVSOFT1', 'txtCertificado' => $pfx, 'txtPassCert' => '',
            'envio' => 1, 'icbper' => 0.5, 'tip_env_fac' => '01', 'produccion' => '0']);
    }

    public function test_sin_escribir_la_contrasena_usa_la_guardada(): void
    {
        $empresa = Empresa::find($this->usuario->IdEmpresa);
        $empresa->passcert = 'clave-pfx';
        $empresa->save();

        $r = $this->subir($this->pfx('clave-pfx'));
        $r->assertSessionMissing('error');

        $this->assertSame($this->usuario->IdEmpresa.'.pfx', $empresa->fresh()->certificado);
        $this->assertFileExists(storage_path('app/certificados/'.$this->usuario->IdEmpresa.'.pem'));
    }

    public function test_si_la_guardada_no_es_la_del_certificado_lo_dice(): void
    {
        $empresa = Empresa::find($this->usuario->IdEmpresa);
        $empresa->passcert = 'otra-clave';
        $empresa->save();

        $this->subir($this->pfx('clave-pfx'))->assertSessionHas('error', 'Certificado: La contraseña del certificado es incorrecta.');
        $this->assertFileDoesNotExist(storage_path('app/certificados/'.$this->usuario->IdEmpresa.'.pem'));
    }
}
