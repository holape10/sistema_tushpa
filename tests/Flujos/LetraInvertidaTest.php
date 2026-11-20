<?php

namespace Tests\Flujos;

use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Ticketeras con la letra al revés (NES POS E804): la letra normal sale chica y la pequeña grande, así que se les pide la otra */
class LetraInvertidaTest extends FlujoTestCase
{
    private function guardar(bool $invertida): object
    {
        $this->post(route('impresion.guardar'), ['descripcion' => 'CAJA', 'tip_conex_imp' => 'COMPARTIDO', 'ruta' => 'CAJA', 'columnas' => 42,
            'diseno' => 'COMPACTO', 'activo' => 1, 'letra_invertida' => $invertida ? 1 : 0])->assertRedirect(route('impresion.index'));

        return DB::table('configuracion_impresoras')->where('id_empresa_negocio', $this->sucursal)->orderByDesc('Id')->first();
    }

    public function test_con_letra_invertida_el_ticket_pide_la_fuente_contraria(): void
    {
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('ACEITE VISTONY', 10, 30), 1]], '03'), 'PV');
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();

        $normal = Impresion::ticketComprobante($cab, $this->guardar(false));
        $this->assertStringStartsWith("\x1B@\x1Bt\x02\x1BM\x00", $normal, 'Sin marcar: arranca pidiendo la letra normal (A).');

        $imp = $this->guardar(true);
        $this->assertSame(1, (int) $imp->letra_invertida);
        $invertido = Impresion::ticketComprobante($cab, $imp);
        $this->assertStringStartsWith("\x1B@\x1Bt\x02\x1BM\x01", $invertido, 'Marcada: la letra normal se pide como B.');
        $this->assertSame(substr_count($normal, "\x1BM\x00"), substr_count($invertido, "\x1BM\x01"), 'Cada cambio de letra va al revés.');
        $this->assertSame(substr_count($normal, "\x1BM\x01"), substr_count($invertido, "\x1BM\x00"));
        $this->assertStringContainsString('ACEITE VISTONY', $invertido);
    }
}
