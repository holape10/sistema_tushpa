<?php

namespace Tests\Flujos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** QR de cobro de un medio de pago (Yape, Plin…): se sube en Medios de pago y el Punto Venta lo muestra al cobrar */
class QrMedioPagoTest extends FlujoTestCase
{
    /** @var list<string> imágenes creadas en la prueba (se borran al final) */
    private array $creadas = [];

    protected function tearDown(): void
    {
        foreach ($this->creadas as $ruta) {
            @unlink(public_path($ruta));
        }
        parent::tearDown();
    }

    public function test_el_qr_se_sube_se_muestra_en_el_punto_venta_y_se_quita(): void
    {
        $yape = DB::table('medios_pagos')->insertGetId(['nom_med_pag' => 'YAPE', 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'predeterminado' => '0']);

        $this->get(route('mediospagos.edit', $yape))->assertOk()->assertSee('QR de cobro');
        $this->put(route('mediospagos.update', $yape), ['nom_med_pag' => 'YAPE', 'qr' => UploadedFile::fake()->image('mi-qr.png', 300, 300)])
            ->assertRedirect(route('mediospagos.index'));
        $qr = (string) DB::table('medios_pagos')->where('id_med_pag', $yape)->value('qr');
        $this->creadas[] = $qr;
        $this->assertStringStartsWith('imagenes/qr/', $qr);
        $this->assertFileExists(public_path($qr));

        // El Punto Venta lo trae con su medio y muestra el aviso de cobro con QR
        $this->get(route('pv.index'))->assertOk()->assertSee(str_replace('/', '\\/', asset($qr)), false)->assertSee('Ya pagó: cobrar e imprimir');

        // Un archivo que no es imagen no se acepta
        $this->put(route('mediospagos.update', $yape), ['nom_med_pag' => 'YAPE', 'qr' => UploadedFile::fake()->create('virus.php', 10)])->assertSessionHasErrors('qr');

        $this->put(route('mediospagos.update', $yape), ['nom_med_pag' => 'YAPE', 'quitar_qr' => 1]);
        $this->assertNull(DB::table('medios_pagos')->where('id_med_pag', $yape)->value('qr'));
        $this->assertFileDoesNotExist(public_path($qr), 'La imagen anterior se borra.');
    }
}
