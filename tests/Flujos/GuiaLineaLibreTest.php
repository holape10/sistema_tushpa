<?php

namespace Tests\Flujos;

use App\Support\Proformas;
use App\Support\Sunat\GuiaRemision;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Guía de remisión: líneas escritas a mano y la guía armada desde una proforma */
class GuiaLineaLibreTest extends FlujoTestCase
{
    public function test_la_guia_tiene_el_boton_de_linea_libre(): void
    {
        $this->get(route('guias.crear'))->assertOk()->assertSee('+ Línea libre')->assertSee('lineaLibre()', false);
    }

    private function proforma(): int
    {
        return Proformas::guardar($this->usuario, ['origen' => 'PV', 'tdicod' => '6', 'clinum' => '20100070970', 'clinom' => 'CONSTRUCTORA SAC',
            'clidir' => 'AV. LOS ROBLES 123', 'items' => [['id' => $this->producto('CEMENTO SOL', 32, 50)->IdProducto, 'cantidad' => 40, 'precio' => 32],
                ['id' => null, 'descripcion' => 'FLETE A OBRA', 'cantidad' => 1, 'precio' => 50]]]);
    }

    public function test_la_guia_se_arma_con_el_cliente_y_el_detalle_de_la_proforma(): void
    {
        $id = $this->proforma();

        $this->get(route('proformas.index'))->assertOk()->assertSee(route('guias.crear', ['proforma' => $id]), false);
        $this->get(route('guias.crear', ['proforma' => $id]))->assertOk()
            ->assertSee('de la proforma')->assertSee('CONSTRUCTORA SAC')->assertSee('20100070970')
            ->assertSee('CEMENTO SOL')->assertSee('FLETE A OBRA')->assertSee('AV. LOS ROBLES 123');
    }

    public function test_si_la_proforma_ya_se_cobro_la_guia_va_con_su_venta(): void
    {
        $id = $this->proforma();
        $venta = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('ARENA', 40, 10), 1]], '01'), 'PV');
        Proformas::marcarFacturada($id, $venta);

        $this->get(route('guias.crear', ['proforma' => $id]))->assertOk()->assertSee('de la venta')
            ->assertSee(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $venta)->value('serdoc'));
    }

    private function guia(string $modalidad): int
    {
        return GuiaRemision::registrar($this->usuario, ['fecha_traslado' => now()->toDateString(), 'motivo' => '01', 'modalidad' => $modalidad, 'peso' => 10,
            'dest_tdicod' => '6', 'dest_num' => '20493508645', 'dest_nom' => 'HONDA SELVA DEL PERU SA', 'partida_ubigeo' => '160101', 'partida_direccion' => 'JR PRUEBA 1',
            'llegada_ubigeo' => '160101', 'llegada_direccion' => 'CARRETERA IQUITOS NAUTA KM 5.5', 'transp_ruc' => '20600821815', 'transp_nom' => 'TRANSPORTES SAC',
            'cond_tdicod' => '1', 'cond_num' => '43499706', 'cond_nombres' => 'LITO', 'cond_apellidos' => 'HUANAQUIRI', 'cond_licencia' => 'Q43499706', 'placa' => 'ABC123'],
            [['descripcion' => 'KIT CAJA DE EMBALAJE METALICO WAVE110S', 'umecod' => 'NIU', 'cantidad' => 15]]);
    }

    private function xml(int $id): string
    {
        $g = DB::table('gre_cabecera')->where('gre_id', $id)->first();

        return GuiaRemision::paraUsuario($this->usuario)->xml($g, DB::table('gre_detalle')->where('gre_id', $id)->get());
    }

    public function test_el_transporte_publico_lleva_la_fecha_de_entrega_al_transportista(): void
    {
        // RS 108-2026: sin ella SUNAT rechaza con el error 3617
        $xml = $this->xml($this->guia('01'));
        $this->assertMatchesRegularExpression('#</cac:CarrierParty><cac:LoadingTransportEvent><cbc:OccurrenceDate>'.now()->toDateString().'</cbc:OccurrenceDate></cac:LoadingTransportEvent></cac:ShipmentStage>#', $xml);
        $this->assertStringContainsString('<cac:TransitPeriod><cbc:StartDate>'.now()->toDateString().'</cbc:StartDate></cac:TransitPeriod>', $xml);

        $this->assertStringNotContainsString('LoadingTransportEvent', $this->xml($this->guia('02')), 'El transporte privado no la lleva.');
    }

    public function test_una_guia_rechazada_se_copia_en_otra_nueva_con_todo_escrito(): void
    {
        $id = $this->guia('01');
        DB::table('gre_cabecera')->where('gre_id', $id)->update(['est_sunat' => 'RECHAZADO']);
        $numero = DB::table('gre_cabecera')->where('gre_id', $id)->value('numero');

        $this->get(route('guias.index'))->assertOk()->assertSee(route('guias.crear', ['copiar' => $id]), false);
        $this->get(route('guias.crear', ['copiar' => $id]))->assertOk()
            ->assertSee('HONDA SELVA DEL PERU SA')->assertSee('CARRETERA IQUITOS NAUTA KM 5.5')->assertSee('TRANSPORTES SAC')
            ->assertSee('KIT CAJA DE EMBALAJE METALICO WAVE110S')->assertSee('REEMPLAZA A T001-'.$numero);
    }
}
