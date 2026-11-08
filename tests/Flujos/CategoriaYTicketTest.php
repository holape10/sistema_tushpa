<?php

namespace Tests\Flujos;

use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Categorías sin línea elegida y el ticket compacto de una persona natural que no quiere su nombre impreso */
class CategoriaYTicketTest extends FlujoTestCase
{
    public function test_una_categoria_sin_linea_se_crea_y_se_edita_sin_error(): void
    {
        $this->post(route('categorias.store'), ['cat_nom' => 'BEBIDAS', 'tip_pro_id' => ''])->assertRedirect(route('categorias.index'));
        $id = DB::table('categorias')->where('id_empresa_negocio', $this->sucursal)->where('cat_nom', 'BEBIDAS')->value('cat_id');
        $this->assertNotNull($id, 'Antes fallaba: "tip_pro_id cannot be null".');

        $this->put(route('categorias.update', $id), ['cat_nom' => 'BEBIDAS FRIAS', 'tip_pro_id' => ''])->assertRedirect(route('categorias.index'));
        $this->assertSame('BEBIDAS FRIAS', DB::table('categorias')->where('cat_id', $id)->value('cat_nom'));
    }

    public function test_el_ticket_sale_con_la_fecha_de_emision_elegida_no_con_la_de_hoy(): void
    {
        // El 8 se emite con fecha del 7 (lo que va a SUNAT): el ticket y el voucher también dicen 7
        $this->travelTo('2026-11-08 10:15:00');
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('CAFE', 5, 5), 1]], '03', ['fecEmi' => '2026-11-07']), 'PV');
        $this->assertSame('2026-11-07', DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('ccafem'));

        foreach (['CLASICO', 'COMPACTO', 'MODERNO'] as $diseno) {
            $ticket = Impresion::ticketComprobante($this->cabecera($id), (object) ['columnas' => 48, 'diseno' => $diseno, 'abrir_cajon' => 0]);
            $this->assertMatchesRegularExpression('/07\/11\/(2026|26)|2026-11-07/', $ticket, "Ticket {$diseno}");
            $this->assertDoesNotMatchRegularExpression('/08\/11\/(2026|26)|2026-11-08/', $ticket, "Ticket {$diseno}");
        }
        $this->get(route('cobros.voucher', $id))->assertOk()->assertSee('07-11-2026')->assertDontSee('08-11-2026');
    }

    public function test_el_ticket_compacto_puede_salir_sin_el_nombre_del_titular(): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['NomEmpresa' => 'JUAN PEREZ TITULAR', 'LogEmpresa' => null]);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['nombre_comercial' => 'POLLERIA EL SABOR', 'logo_suc' => null]);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('POLLO A LA BRASA', 20, 5), 1]]), 'PV');
        $imp = (object) ['columnas' => 48, 'diseno' => 'COMPACTO', 'abrir_cajon' => 0];
        $ticket = fn () => Impresion::ticketComprobante($this->cabecera($id), $imp);

        $this->assertStringContainsString('DE: JUAN PEREZ TITULAR', $ticket());

        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['ocultar_titular' => true]);
        $this->assertStringNotContainsString('JUAN PEREZ', $ticket());
        $this->assertStringContainsString('POLLERIA EL SABOR', $ticket());
        $this->assertStringContainsString('RUC:'.$this->usuario->IdEmpresa, $ticket());
    }
}
