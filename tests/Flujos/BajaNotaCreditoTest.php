<?php

namespace Tests\Flujos;

use App\Support\Notas;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/**
 * Nota de crédito que hay que dejar sin efecto (por ejemplo, salió con un número equivocado): se da de baja ante SUNAT y,
 * al aceptarse, la nota queda anulada, la factura vuelve a estar vigente y lo devuelto al stock vuelve a salir.
 */
class BajaNotaCreditoTest extends FlujoTestCase
{
    private function facturaConNota(): array
    {
        $plato = $this->producto('PATARASHCA', 59, 10);
        $factura = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '01'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->update(['est_sunat' => 'ACEPTADO', 'enviado' => 1]);
        $nota = Notas::emitir($this->usuario, $factura, ['tdocod' => '07', 'tipnot' => '01', 'motivo' => 'ANULACION DE LA OPERACION']);

        return [$plato, $factura, $nota];
    }

    public function test_al_aceptarse_la_baja_la_nota_queda_anulada_y_la_factura_vigente(): void
    {
        [$plato, $factura, $nota] = $this->facturaConNota();
        $this->assertEquals(10.0, $this->stock($plato), 'La nota devolvió los 2 al almacén.');
        $this->assertNotNull(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->value('anulado_nc'));

        Notas::anularPorBaja(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nota)->first());

        $this->assertStringStartsWith('BAJA SUNAT', (string) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nota)->value('ccabaj'));
        $this->assertNull(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->value('anulado_nc'), 'La factura vuelve a estar vigente.');
        $this->assertEquals(8.0, $this->stock($plato), 'Lo que devolvió la nota vuelve a salir.');
        // Y se le puede emitir otra nota a esa factura
        $otra = $this->cabecera(Notas::emitir($this->usuario, $factura, ['tdocod' => '07', 'tipnot' => '01', 'motivo' => 'ANULACION DE LA OPERACION']));
        $this->assertSame('07', $otra->tdocod);
    }

    public function test_la_baja_de_una_nota_se_pide_desde_ventas_solo_si_esta_aceptada(): void
    {
        [, , $nota] = $this->facturaConNota();

        // En manual la nota no se envía sola: queda pendiente y no se puede dar de baja todavía
        $this->assertSame('PENDIENTE', DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nota)->value('est_sunat') ?: 'PENDIENTE');
        $this->postJson(route('ventas.anular', $nota), ['motivo' => 'NUMERO EQUIVOCADO'])
            ->assertJson(['success' => false, 'message' => 'La nota está PENDIENTE en SUNAT: solo se da de baja cuando está ACEPTADA.']);
        $this->assertNull(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nota)->value('ccabaj'));
    }

    public function test_la_nota_aceptada_ofrece_la_baja_en_ventas_y_en_notas(): void
    {
        [, , $nota] = $this->facturaConNota();
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nota)->update(['est_sunat' => 'ACEPTADO', 'enviado' => 1]);

        // En Ventas, el menú ⋮ de la nota trae la Comunicación de baja (aunque la nota no lleve notas propias)
        $this->get(route('ventas.index'))->assertOk()->assertSee('menu.venta?.notas || menu.venta?.baja', false)
            ->assertSee('\u0022baja\u0022:true', false)->assertSee('\u0022esNota\u0022:true', false);
        // Y en Notas de crédito/débito, su botón
        $this->get(route('notas.index'))->assertOk()->assertSee('title="Dar de baja ante SUNAT"', false);
    }

    public function test_en_envio_manual_la_nota_de_factura_no_se_manda_sola(): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['tipo_envio' => 0]);
        $plato = $this->producto('PATARASHCA', 59, 10);
        $factura = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '01'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->update(['est_sunat' => 'ACEPTADO', 'enviado' => 1]);

        $this->postJson(route('notas.store'), ['ref' => $factura, 'tdocod' => '07', 'tipnot' => '01', 'motivo' => 'ANULACION DE LA OPERACION'])
            ->assertJson(['success' => true, 'message' => 'Nota de crédito FC01-00000001 emitida. Envíala a SUNAT desde esta lista (el envío está en manual).']);
        $this->assertSame(0, (int) DB::table('cpe_cabecera')->where('tdocod', '07')->orderByDesc('IdCpe_cabecera')->value('enviado'));
    }
}
