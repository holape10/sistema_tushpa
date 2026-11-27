<?php

namespace Tests\Flujos;

use App\Support\Transportes;
use Illuminate\Support\Facades\DB;

/** Transportes: plano de asientos, venta de pasajes con su comprobante, asientos que no se repiten y manifiesto */
class TransportesTest extends FlujoTestCase
{
    /** Ruta, unidad (bus de 1 piso) y una salida mañana con precio 50 */
    private function salida(array $extra = []): int
    {
        $ruta = DB::table('tra_rutas')->insertGetId(['id_empresa_negocio' => $this->sucursal, 'origen' => 'LIMA', 'destino' => 'HUANCAYO', 'precio' => 50,
            'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $plano = Transportes::plantilla('BUS_1P');
        $unidad = DB::table('tra_unidades')->insertGetId(['id_empresa_negocio' => $this->sucursal, 'tipo' => 'TERRESTRE', 'nombre' => 'BUS 01', 'placa' => 'ABC-123',
            'plano' => json_encode($plano), 'asientos' => count(Transportes::asientosDe($plano)), 'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('tra_salidas')->insertGetId($extra + ['id_empresa_negocio' => $this->sucursal, 'ruta_id' => $ruta, 'unidad_id' => $unidad,
            'salida' => now()->addDay()->setTime(21, 0), 'precio' => 50, 'estado' => 'PROGRAMADA', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function vender(int $salida, array $asientos, array $extra = [])
    {
        return $this->postJson(route('transportes.vender', $salida), $extra + ['tdocod' => '03', 'id_med_pag' => $this->efectivo,
            'pasajeros' => array_map(fn ($a) => ['asiento' => (string) $a, 'tdicod' => '1', 'doc' => '4567890'.$a % 10, 'nombre' => 'PASAJERO '.$a], $asientos)]);
    }

    public function test_las_plantillas_numeran_y_marcan_ventana_o_pasillo(): void
    {
        $asientos = Transportes::asientosDe(Transportes::plantilla('BUS_2P'));

        $this->assertCount(52, $asientos, '12 cama + 40 semicama');
        $this->assertSame(['V', 'P'], [$asientos['1']['pos'], $asientos['2']['pos']], 'Orilla = ventana; al lado del pasillo = pasillo');
        $this->assertSame(['CAMA', 1, 'SEMICAMA', 2], [$asientos['1']['cat'], $asientos['1']['piso'], $asientos['52']['cat'], $asientos['52']['piso']]);
        $this->assertCount(20, array_filter(Transportes::asientosDe(Transportes::plantilla('LANCHA')), fn ($a) => $a['t'] === 'H'), 'La lancha trae hamacas');
    }

    public function test_el_numero_escrito_y_el_lado_cambiado_a_mano_se_respetan(): void
    {
        $plano = ['pisos' => [['nombre' => 'Piso 1', 'filas' => 1, 'columnas' => 3, 'celdas' => [[
            ['t' => 'A', 'n' => '7A', 'cat' => 'SEMICAMA'], ['t' => 'A', 'cat' => 'CAMA', 'pos' => 'V', 'manual' => true], ['t' => 'X'],
        ]]]]];

        $asientos = Transportes::asientosDe(Transportes::numerar($plano));

        $this->assertEquals(['7A', '1'], array_keys($asientos));
        $this->assertSame('V', $asientos['1']['pos'], 'Estaba al medio, pero se marcó ventana a mano');
    }

    public function test_vende_asientos_con_su_boleta_y_nadie_puede_volver_a_venderlos(): void
    {
        $salida = $this->salida(['precios' => json_encode(['SEMICAMA' => 60])]);

        $r = $this->vender($salida, [1, 2])->assertOk()->assertJson(['ok' => true]);

        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $r->json('comprobante.id'))->first();
        $this->assertSame(['03', 120.0, 'TRANSPORTE'], [$cab->tdocod, (float) $cab->ccaitv, $cab->ped_tip]);
        $this->assertStringContainsString('PASAJE LIMA - HUANCAYO', DB::table('cpe_detalle')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->value('cdedes'));
        $this->assertEquals(['1', '2'], array_keys(Transportes::ocupados($salida)));

        $this->vender($salida, [2])->assertJson(['ok' => false, 'mensaje' => 'El asiento 2 ya está vendido: elige otro.']);
        $this->vender($salida, [99])->assertJson(['ok' => false]);
        $this->assertSame(1, DB::table('cpe_cabecera')->where('ped_tip', 'TRANSPORTE')->count(), 'No se emitió otro comprobante');

        // Si se anula el comprobante, los asientos vuelven a estar libres
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->update(['ccabaj' => now()->toDateTimeString()]);
        $this->assertSame([], Transportes::ocupados($salida));
    }

    public function test_una_salida_que_ya_partio_no_vende_y_con_pasajes_no_se_cancela(): void
    {
        $salida = $this->salida();
        $this->vender($salida, [5])->assertJson(['ok' => true]);

        $this->post(route('transportes.salidas.estado', $salida), ['estado' => 'CANCELADA'])->assertSessionHasErrors('estado');
        $this->post(route('transportes.salidas.estado', $salida), ['estado' => 'PARTIO'])->assertRedirect();
        $this->vender($salida, [6])->assertJson(['ok' => false, 'mensaje' => 'Esa salida ya no vende pasajes (partio).']);
    }

    public function test_el_manifiesto_lista_los_pasajeros_y_las_pantallas_abren(): void
    {
        $salida = $this->salida();
        $this->vender($salida, [3]);

        $this->get(route('transportes.manifiesto', $salida))->assertOk()->assertSee('MANIFIESTO DE PASAJEROS')->assertSee('PASAJERO 3');
        $this->get(route('transportes.index', ['salida' => $salida]))->assertOk()->assertSee('LIMA → HUANCAYO', false);
        foreach (['transportes.salidas', 'transportes.unidades', 'transportes.rutas'] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }
    }

    public function test_guarda_la_unidad_con_su_plano_numerado(): void
    {
        $r = $this->postJson(route('transportes.unidades.guardar'), ['tipo' => 'FLUVIAL', 'nombre' => 'lancha san pedro', 'plano' => Transportes::plantilla('LANCHA')])
            ->assertOk()->assertJson(['ok' => true, 'asientos' => 60]);

        $this->assertSame('LANCHA SAN PEDRO', DB::table('tra_unidades')->where('id', $r->json('id'))->value('nombre'));
        $this->postJson(route('transportes.unidades.guardar'), ['tipo' => 'TERRESTRE', 'nombre' => 'VACIA', 'plano' => ['pisos' => [['filas' => 2, 'columnas' => 2, 'celdas' => []]]]])
            ->assertJson(['ok' => false, 'mensaje' => 'El plano no tiene asientos.']);
    }
}
