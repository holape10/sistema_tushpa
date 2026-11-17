<?php

namespace Tests\Flujos;

use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/**
 * Empresas › editar: «Anular boletas y facturas que aún no se envían a SUNAT» (apagado de fábrica). Encendido, se anulan
 * en Ventas como una nota de venta; ya enviadas a SUNAT, solo con comunicación de baja o nota de crédito.
 */
class AnularSinEnviarTest extends FlujoTestCase
{
    private function activar(bool $si = true): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['anular_sin_enviar' => $si]);
    }

    public function test_apagado_una_boleta_no_se_anula_como_nota_de_venta(): void
    {
        $plato = $this->producto('JUANE', 15, 10);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '03'), 'PV');

        $this->get(route('empresas.edit', $this->usuario->IdEmpresa))->assertOk()->assertSee('Anular boletas y facturas que aún no se envían a SUNAT');
        $this->postJson(route('ventas.anular', $id), ['motivo' => 'CLIENTE DESISTIO'])->assertJson(['success' => false]);

        $this->assertNull(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccabaj'));
        $this->assertSame(8.0, $this->stock($plato));
    }

    public function test_encendido_se_anula_si_aun_no_se_envio_a_sunat(): void
    {
        $this->activar();
        $plato = $this->producto('JUANE', 15, 10);
        $boleta = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '03'), 'PV');
        $factura = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '01'), 'PV');

        $this->get(route('ventas.index'))->assertOk();
        $this->postJson(route('ventas.anular', $boleta), ['motivo' => 'CLIENTE DESISTIO'])->assertJson(['success' => true]);
        $this->assertStringStartsWith('ANULADO', (string) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $boleta)->value('ccabaj'));
        $this->assertSame(9.0, $this->stock($plato), 'Lo vendido vuelve al stock.');
        $this->assertDatabaseHas('bitacora', ['accion' => 'venta.anular', 'referencia_id' => $boleta]);

        // Ya enviada a SUNAT: no se anula aquí (solo comunicación de baja o nota de crédito)
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->update(['est_sunat' => 'ACEPTADO', 'enviado' => 1]);
        $this->postJson(route('ventas.anular', $factura), ['motivo' => 'CLIENTE DESISTIO']);
        $this->assertNull(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $factura)->value('ccabaj'));
    }

    public function test_la_boleta_que_ya_va_en_un_resumen_diario_tampoco(): void
    {
        $this->activar();
        $plato = $this->producto('JUANE', 15, 10);
        $boleta = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '03'), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $boleta)->update(['est_sunat' => 'EN RESUMEN', 'res_id' => 99]);

        $this->postJson(route('ventas.anular', $boleta), ['motivo' => 'CLIENTE DESISTIO']);
        $this->assertNull(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $boleta)->value('ccabaj'));
    }
}
