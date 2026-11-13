<?php

namespace Tests\Flujos;

use App\Support\Sunat\GuiaRemision;
use App\Support\Sunat\TransporteGuias;
use Illuminate\Support\Facades\DB;

/** Conductores y vehículos de las guías: se guardan solos, se ven y se editan, y se consultan por DNI (licencia) y por placa */
class GuiaTransporteTest extends FlujoTestCase
{
    protected function tearDown(): void
    {
        TransporteGuias::$falso = null;
        parent::tearDown();
    }

    /** json.pe de mentira: licencia por DNI y vehículo por placa */
    private function jsonpe(): void
    {
        TransporteGuias::$falso = fn (string $ruta, array $d) => match ($ruta) {
            'licencia' => ['nombre_completo' => 'CARLOS EDGARDO DELGADO RIVERA',
                'licencia' => ['numero' => 'Q10400310', 'categoria' => 'A I', 'fecha_vencimiento' => '06/03/2027', 'estado' => 'VIGENTE']],
            'placa' => ['placa' => $d['placa'], 'marca' => 'FIAT', 'modelo' => 'FIORINO', 'color' => 'BLANCO'],
            default => null,
        };
    }

    public function test_la_guia_con_transporte_privado_deja_su_conductor_y_su_vehiculo_en_la_lista(): void
    {
        GuiaRemision::registrar($this->usuario, ['fecha_traslado' => now()->toDateString(), 'motivo' => '01', 'modalidad' => '02', 'peso' => 10,
            'dest_tdicod' => '6', 'dest_num' => '20493508645', 'dest_nom' => 'HONDA SELVA DEL PERU SA', 'partida_ubigeo' => '160101', 'partida_direccion' => 'JR PRUEBA 1',
            'llegada_ubigeo' => '160101', 'llegada_direccion' => 'CARRETERA IQUITOS NAUTA KM 5.5',
            'cond_tdicod' => '1', 'cond_num' => '70073488', 'cond_nombres' => 'HELGA KATHERINE', 'cond_apellidos' => 'PEZO ULLOA', 'cond_licencia' => 'Q123456', 'placa' => '955-18L'],
            [['descripcion' => 'DOCUMENTOS', 'umecod' => 'NIU', 'cantidad' => 1]]);

        $c = DB::table('gre_conductores')->where('id_empresa_negocio', $this->sucursal)->where('num', '70073488')->first();
        $this->assertSame(['HELGA KATHERINE', 'PEZO ULLOA', 'Q123456'], [$c->nombres, $c->apellidos, $c->licencia]);
        $this->assertTrue(DB::table('gre_vehiculos')->where('id_empresa_negocio', $this->sucursal)->where('placa', '95518L')->exists());
        $this->get(route('guias.transporte'))->assertOk()->assertSee('HELGA KATHERINE PEZO ULLOA')->assertSee('95518L');
    }

    public function test_el_dni_trae_el_nombre_y_la_licencia_y_la_placa_el_vehiculo(): void
    {
        $this->jsonpe();

        $this->getJson(route('guias.conductor', '10400310'))->assertOk()
            ->assertJson(['nombres' => 'CARLOS EDGARDO', 'apellidos' => 'DELGADO RIVERA', 'licencia' => 'Q10400310', 'categoria' => 'A I', 'vence' => '2027-03-06']);
        $this->getJson(route('guias.placa', 'F3H-792'))->assertOk()->assertJson(['marca' => 'FIAT', 'modelo' => 'FIORINO', 'color' => 'BLANCO']);

        // Quedan guardados: la segunda vez no se vuelve a consultar a json.pe
        TransporteGuias::$falso = fn () => null;
        $this->getJson(route('guias.conductor', '10400310'))->assertJson(['licencia' => 'Q10400310']);
        $this->getJson(route('guias.placa', 'F3H792'))->assertJson(['marca' => 'FIAT']);
    }

    public function test_se_agrega_edita_y_quita_un_conductor_y_un_vehiculo(): void
    {
        $this->post(route('guias.transporte.conductor'), ['tdicod' => '1', 'num' => '43499706', 'nombre' => 'lito huanaquiri ruiz', 'licencia' => 'q43499706'])
            ->assertRedirect(route('guias.transporte'));
        $c = DB::table('gre_conductores')->where('num', '43499706')->first();
        $this->assertSame(['LITO', 'HUANAQUIRI RUIZ', 'Q43499706'], [$c->nombres, $c->apellidos, $c->licencia]);

        $this->post(route('guias.transporte.conductor'), ['id' => $c->id, 'tdicod' => '1', 'num' => '43499706', 'nombre' => 'LITO JUAN HUANAQUIRI RUIZ'])->assertRedirect();
        $this->assertSame('LITO JUAN', DB::table('gre_conductores')->where('id', $c->id)->value('nombres'));

        $this->post(route('guias.transporte.vehiculo'), ['placa' => 'abc-123', 'marca' => 'toyota'])->assertRedirect();
        $v = DB::table('gre_vehiculos')->where('placa', 'ABC123')->first();
        $this->assertSame('TOYOTA', $v->marca);

        $this->post(route('guias.transporte.eliminar', ['conductor', $c->id]))->assertRedirect();
        $this->post(route('guias.transporte.eliminar', ['vehiculo', $v->id]))->assertRedirect();
        $this->assertSame(0, DB::table('gre_conductores')->where('num', '43499706')->count() + DB::table('gre_vehiculos')->where('placa', 'ABC123')->count());
    }
}
