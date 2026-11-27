<?php

namespace Tests\Flujos;

use App\Support\Transportes;
use Carbon\Carbon;
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

        $this->vender($salida, [2])->assertJson(['ok' => false, 'mensaje' => 'El asiento 2 ya está ocupado en ese tramo: elige otro.']);
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

    // ------------------------------------------------------------------ fase 2

    /** Una salida de una ruta con paradas: LIMA (0) - LA OROYA (1) - HUANCAYO (2) */
    private function salidaConParadas(): int
    {
        $salida = $this->salida();
        DB::table('tra_rutas')->where('id', DB::table('tra_salidas')->where('id', $salida)->value('ruta_id'))->update(['paradas' => 'La Oroya']);

        return $salida;
    }

    private function pasajero(string $asiento, array $extra = []): array
    {
        return $extra + ['asiento' => $asiento, 'tdicod' => '1', 'doc' => '4567890'.((int) $asiento % 10), 'nombre' => 'PASAJERO '.$asiento, 'telefono' => '987654321'];
    }

    public function test_por_tramos_el_asiento_se_vende_dos_veces_sin_cruzarse_y_el_tramo_cobra_lo_escrito(): void
    {
        $salida = $this->salidaConParadas();

        $this->vender($salida, [7], ['desde' => 0, 'hasta' => 1, 'pasajeros' => [$this->pasajero('7', ['precio' => 25])]])->assertJson(['ok' => true]);
        $this->vender($salida, [7], ['desde' => 1, 'hasta' => 99])->assertJson(['ok' => true]);
        $this->vender($salida, [7], ['desde' => 0, 'hasta' => 99])->assertJson(['ok' => false, 'mensaje' => 'El asiento 7 ya está ocupado en ese tramo: elige otro.']);

        $pasajes = DB::table('tra_pasajes')->where('salida_id', $salida)->orderBy('id')->get();
        $this->assertSame([25.0, 50.0], [(float) $pasajes[0]->precio, (float) $pasajes[1]->precio], 'El tramo cobra lo escrito; el viaje completo, el de su categoría');
        $this->assertStringContainsString('PASAJE LIMA - LA OROYA', DB::table('cpe_detalle')->where('IdCpe_cabecera', $pasajes[0]->IdCpe_cabecera)->value('cdedes'));
    }

    public function test_el_viaje_completo_ignora_el_precio_que_llega(): void
    {
        $salida = $this->salida();

        $this->vender($salida, [3], ['pasajeros' => [$this->pasajero('3', ['precio' => 1])]])->assertJson(['ok' => true]);

        $this->assertSame(50.0, (float) DB::table('tra_pasajes')->where('salida_id', $salida)->value('precio'));
    }

    public function test_la_reserva_toma_el_asiento_hasta_que_vence_y_al_cobrarla_queda_vendida(): void
    {
        $salida = $this->salida();

        $grupo = $this->postJson(route('transportes.reservar', $salida), ['pasajeros' => [$this->pasajero('4')]])->assertJson(['ok' => true])->json('grupo');
        $this->vender($salida, [4])->assertJson(['ok' => false]);
        $this->assertSame(0, DB::table('cpe_cabecera')->where('ped_tip', 'TRANSPORTE')->count(), 'Reservar no emite comprobante');

        // Vence (2 horas por defecto): el asiento vuelve a estar libre, pero si nadie lo tomó la reserva se cobra igual
        Carbon::setTestNow(now()->addHours(3));
        $this->assertSame([], Transportes::ocupados($salida));
        $r = $this->postJson(route('transportes.reservas.cobrar', $grupo), ['tdocod' => '03', 'id_med_pag' => $this->efectivo])->assertJson(['ok' => true]);

        $pasaje = DB::table('tra_pasajes')->where('grupo', $grupo)->first();
        $this->assertSame(['VENDIDO', (int) $r->json('comprobante.id')], [$pasaje->estado, (int) $pasaje->IdCpe_cabecera]);
        $this->postJson(route('transportes.reservas.cobrar', $grupo), ['tdocod' => '03'])->assertJson(['ok' => false, 'mensaje' => 'Esa reserva ya no existe (se cobró o se liberó).']);
    }

    public function test_la_reserva_vencida_que_otro_ya_tomo_no_se_cobra_y_liberar_suelta_el_asiento(): void
    {
        $salida = $this->salida();
        $grupo = Transportes::reservar($this->sucursal, null, $salida, ['pasajeros' => [$this->pasajero('9')]]);
        Carbon::setTestNow(now()->addHours(3));
        $this->vender($salida, [9])->assertJson(['ok' => true]);

        $this->postJson(route('transportes.reservas.cobrar', $grupo), ['tdocod' => '03'])
            ->assertJson(['ok' => false, 'mensaje' => 'La reserva venció y el asiento 9 ya lo tomó otra persona.']);

        $otra = Transportes::reservar($this->sucursal, null, $salida, ['pasajeros' => [$this->pasajero('10')]]);
        $this->postJson(route('transportes.reservas.liberar', $otra))->assertJson(['ok' => true]);
        $this->assertArrayNotHasKey('10', Transportes::ocupados($salida));
    }

    public function test_cambia_de_asiento_y_de_fecha_sin_anular_la_boleta(): void
    {
        $salida = $this->salida();
        $cab = $this->vender($salida, [1])->json('comprobante.id');
        $pasaje = DB::table('tra_pasajes')->where('salida_id', $salida)->value('id');
        $base = DB::table('tra_salidas')->where('id', $salida)->first();
        $manana = DB::table('tra_salidas')->insertGetId(['id_empresa_negocio' => $this->sucursal, 'ruta_id' => $base->ruta_id, 'unidad_id' => $base->unidad_id,
            'salida' => now()->addDays(2)->setTime(21, 0), 'precio' => 50, 'estado' => 'PROGRAMADA', 'created_at' => now(), 'updated_at' => now()]);
        $this->vender($salida, [2]);

        $this->postJson(route('transportes.pasajes.cambiar', $pasaje), ['salida_id' => $salida, 'asiento' => '2'])
            ->assertJson(['ok' => false, 'mensaje' => 'El asiento 2 ya está ocupado en esa salida.']);
        $this->postJson(route('transportes.pasajes.cambiar', $pasaje), ['salida_id' => $manana, 'asiento' => '5'])->assertJson(['ok' => true]);

        $p = DB::table('tra_pasajes')->where('id', $pasaje)->first();
        $this->assertSame([$manana, '5', (int) $cab], [(int) $p->salida_id, $p->asiento, (int) $p->IdCpe_cabecera]);
        $this->assertNull(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cab)->value('ccabaj'), 'La boleta sigue vigente');
        $this->assertArrayNotHasKey('1', Transportes::ocupados($salida));

        // Solo a otra salida de la misma ruta
        $otraRuta = $this->salida();
        $this->postJson(route('transportes.pasajes.cambiar', $pasaje), ['salida_id' => $otraRuta, 'asiento' => '5'])
            ->assertJson(['ok' => false, 'mensaje' => 'Solo se cambia a una salida de la misma ruta.']);
    }

    public function test_el_embarque_valida_el_qr_una_sola_vez_y_rechaza_reservas_y_otras_salidas(): void
    {
        $salida = $this->salida();
        $this->vender($salida, [6]);
        $codigo = DB::table('tra_pasajes')->where('salida_id', $salida)->value('codigo');
        $grupo = Transportes::reservar($this->sucursal, null, $salida, ['pasajeros' => [$this->pasajero('8')]]);
        $reserva = DB::table('tra_pasajes')->where('grupo', $grupo)->value('codigo');

        $this->postJson(route('transportes.embarcar'), ['codigo' => $codigo, 'salida_id' => $salida + 1000])->assertJson(['ok' => false]);
        $this->postJson(route('transportes.embarcar'), ['codigo' => strtolower($codigo), 'salida_id' => $salida])->assertJson(['ok' => true, 'mensaje' => 'Puede subir: asiento 6.']);
        $this->postJson(route('transportes.embarcar'), ['codigo' => $codigo, 'salida_id' => $salida])->assertJson(['ok' => false, 'mensaje' => 'Ya subió a las 12:00.']);
        $this->postJson(route('transportes.embarcar'), ['codigo' => $reserva])->assertJson(['ok' => false, 'mensaje' => 'Es una reserva sin pagar: que pase por caja.']);
        $this->postJson(route('transportes.embarcar'), ['codigo' => 'ZZZZZZZZZZ'])->assertJson(['ok' => false, 'mensaje' => 'Ese boleto no existe en esta empresa.']);

        $this->get(route('transportes.embarque', ['salida' => $salida]))->assertOk()->assertSee('PASAJERO 6');
    }

    public function test_el_reporte_resta_los_gastos_de_lo_cobrado(): void
    {
        $salida = $this->salida();
        $this->vender($salida, [1, 2]);
        $this->post(route('transportes.gastos.guardar'), ['salida_id' => $salida, 'concepto' => 'combustible', 'monto' => 30])->assertRedirect();

        $fila = Transportes::reporte($this->sucursal, now()->toDateString(), now()->addDays(3)->toDateString())->firstWhere('id', $salida);

        $this->assertSame([2, 100.0, 30.0], [(int) $fila->vendidos, (float) $fila->cobrado, (float) $fila->gastos]);
        $this->get(route('transportes.reporte', ['desde' => now()->toDateString(), 'hasta' => now()->addDays(3)->toDateString()]))
            ->assertOk()->assertSee('COMBUSTIBLE')->assertSee('S/ 70.00');
        $this->get(route('transportes.configuracion'))->assertOk();
    }

    // ------------------------------------------------------------------ por internet

    private function webActiva(): int
    {
        DB::table('tra_config')->insert(['id_empresa_negocio' => $this->sucursal, 'web_activo' => true, 'web_reserva_min' => 60]);

        return (int) DB::table('medios_pagos')->insertGetId(['nom_med_pag' => 'YAPE', 'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
    }

    private function registrarse(string $doc = '44556677'): void
    {
        $this->post(route('pasajes.registro', $this->sucursal), ['doc' => $doc, 'nombre' => 'Ana Torres', 'celular' => '987111222',
            'password' => 'secreto1', 'password_confirmation' => 'secreto1'])->assertRedirect();
    }

    public function test_sin_venta_por_internet_la_pagina_no_existe(): void
    {
        $this->get(route('pasajes.web', $this->sucursal))->assertNotFound();
    }

    public function test_el_pasajero_reserva_por_internet_la_caja_la_cobra_y_baja_su_boleto(): void
    {
        $yape = $this->webActiva();
        $salida = $this->salida(['precios' => json_encode(['SEMICAMA' => 60])]);
        $this->get(route('pasajes.web', $this->sucursal))->assertOk()->assertSee('LIMA → HUANCAYO', false)->assertDontSee('TUSHPA');

        // Sin cuenta no reserva
        $this->postJson(route('pasajes.reservar', [$this->sucursal, $salida]), ['pasajeros' => [['asiento' => '3', 'doc' => '44556677', 'nombre' => 'ANA']], 'medio' => $yape])
            ->assertJson(['ok' => false, 'cuenta' => true]);

        $this->registrarse();
        $this->get(route('pasajes.salida', [$this->sucursal, $salida]))->assertOk()->assertSee('ANA TORRES');
        $this->postJson(route('pasajes.reservar', [$this->sucursal, $salida]), ['pasajeros' => [['asiento' => '3', 'doc' => '44556677', 'nombre' => 'Ana Torres', 'precio' => 1]],
            'medio' => $yape, 'operacion' => 'op123'])->assertJson(['ok' => true]);

        $p = DB::table('tra_pasajes')->where('salida_id', $salida)->first();
        $this->assertSame(['RESERVADO', 'WEB', 'YAPE', 'OP123', '987111222', 60.0], [$p->estado, $p->origen_venta, $p->medio_web, $p->operacion_web, $p->telefono, (float) $p->precio],
            'El precio lo pone la empresa, no el navegador');
        $this->assertEquals(now()->addMinutes(60), now()->parse($p->reservado_hasta));
        $this->assertCount(1, Transportes::reservasWeb($this->sucursal));
        $this->get(route('pasajes.mis', $this->sucursal))->assertOk()->assertSee('Esperando tu pago');

        // La caja confirma el pago y la cobra con el medio que eligió el pasajero
        $this->postJson(route('transportes.reservas.cobrar', $p->grupo), ['tdocod' => '03', 'id_med_pag' => $yape, 'operacion' => 'OP123'])->assertJson(['ok' => true]);
        $this->assertSame('VENDIDO', DB::table('tra_pasajes')->where('id', $p->id)->value('estado'));
        $this->assertCount(0, Transportes::reservasWeb($this->sucursal));
        $this->get(route('pasajes.mis', $this->sucursal))->assertOk()->assertSee('Pagado');
        $this->get(route('pasajes.boleto', [$this->sucursal, $p->grupo]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Otro pasajero no ve ese boleto
        $this->post(route('pasajes.salir', $this->sucursal));
        $this->registrarse('11223344');
        $this->get(route('pasajes.boleto', [$this->sucursal, $p->grupo]))->assertNotFound();
    }

    public function test_la_reserva_por_internet_sale_impresa_en_la_caja_predeterminada(): void
    {
        $yape = $this->webActiva();
        $salida = $this->salida();
        foreach (['COMPARTIDO', 'WINDOWS'] as $i => $conexion) {
            DB::table('configuracion_impresoras')->where('id_empresa_negocio', $this->sucursal)->delete();
            DB::table('configuracion_impresoras')->insert(['descripcion' => 'CAJA', 'ruta' => 'CAJA', 'IdEmpresa' => $this->usuario->IdEmpresa,
                'id_empresa_negocio' => $this->sucursal, 'tip_conex_imp' => $conexion, 'columnas' => 42, 'diseno' => 'CLASICO', 'activo' => 1, 'predeterminado' => 1]);
            $this->registrarse('7000000'.$i);
            $this->postJson(route('pasajes.reservar', [$this->sucursal, $salida]), ['pasajeros' => [['asiento' => (string) (11 + $i), 'doc' => '7000000'.$i, 'nombre' => 'ANA']],
                'medio' => $yape])->assertJson(['ok' => true]);
            $this->post(route('pasajes.salir', $this->sucursal));
        }

        $cola = DB::table('cola_impresion')->where('id_empresa_negocio', $this->sucursal)->where('tipo', 'RESERVA')->orderBy('id')->pluck('contenido');
        $this->assertCount(2, $cola);
        $this->assertStringContainsString('LLAMAR: 987111222', base64_decode($cola[0]), 'La ticketera dice a quién llamar');
        $this->assertStringStartsWith('%PDF', base64_decode($cola[1]), 'La impresora A4 recibe el boleto en PDF');
    }

    public function test_la_cuenta_web_no_se_duplica_y_pide_la_contrasena_correcta(): void
    {
        $this->webActiva();
        $this->registrarse();
        $this->post(route('pasajes.salir', $this->sucursal));

        $this->post(route('pasajes.registro', $this->sucursal), ['doc' => '44556677', 'nombre' => 'OTRA', 'celular' => '987000000',
            'password' => 'secreto1', 'password_confirmation' => 'secreto1'])->assertSessionHasErrors('doc');
        $this->post(route('pasajes.ingresar', $this->sucursal), ['doc' => '44556677', 'password' => 'mala'])->assertSessionHasErrors('doc');
        $this->post(route('pasajes.ingresar', $this->sucursal), ['doc' => '44556677', 'password' => 'secreto1'])->assertRedirect(route('pasajes.web', $this->sucursal));
        $this->get(route('pasajes.cuenta', $this->sucursal))->assertRedirect(route('pasajes.mis', $this->sucursal));
    }
}
