<?php

namespace Tests\Flujos;

use App\Models\Cliente;
use App\Models\Turno;
use Illuminate\Support\Facades\DB;

/**
 * Clínica: el "Nuevo tratamiento" hace todo de una vez (ficha, consentimiento firmado, pagos de antes del sistema) y el
 * administrador puede cambiar el precio del adelanto al cobrar.
 */
class ClinicaTratamientoRapidoTest extends FlujoTestCase
{
    /** Un PNG de 1x1 (la firma que manda la tablet) */
    private const FIRMA = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private int $historia;

    private int $especialidad;

    protected function setUp(): void
    {
        parent::setUp();
        $cliente = Cliente::create(['clinum' => '47664500', 'clinom' => 'PACIENTE DE PRUEBA', 'tdicod' => '1', 'clidir' => '--', 'rucemp' => $this->usuario->IdEmpresa]);
        $this->historia = DB::table('historia_clinica')->insertGetId(['his_cli_cod' => 'HC-000001', 'clicod' => $cliente->clicod, 'id_empresa_negocio' => $this->sucursal]);
        $this->especialidad = DB::table('especialidad')->insertGetId(['esp_des' => 'Ortodoncia', 'id_empresa_negocio' => $this->sucursal, 'activo' => 1]);
    }

    private function nuevo(array $datos): array
    {
        return $this->postJson(route('clinica.tratamientos.guardar', $this->historia), $datos)->assertOk()->json();
    }

    public function test_todo_de_una_vez_sin_nombre_con_ficha_y_consentimiento_firmado(): void
    {
        $r = $this->nuevo(['esp_id' => $this->especialidad, 'costo' => 2000, 'detalle' => 'INDICACIONES', 'adelanto' => 200,
            'firma' => self::FIRMA, 'alergias' => 'PENICILINA', 'antecedentes' => '', 'consentimiento' => true, 'riesgos' => 'DOLOR LEVE']);
        $this->assertTrue($r['ok'], $r['mensaje'] ?? '');

        $tra = DB::table('clinica_tratamientos')->where('tra_id', $r['tra_id'])->first();
        $this->assertSame('TRATAMIENTO DE ORTODONCIA', $tra->descripcion, 'Sin nombre va el de la especialidad (antes salía "Falta completar tratamiento").');
        $h = DB::table('historia_clinica')->where('his_cli_id', $this->historia)->first();
        $this->assertSame('PENICILINA', $h->alergias);
        $con = DB::table('clinica_consentimientos')->where('his_cli_id', $this->historia)->first();
        $this->assertSame('TRATAMIENTO DE ORTODONCIA', $con->tratamiento);
        $this->assertNotNull($con->firma, 'El consentimiento queda firmado con la misma firma.');
        $this->assertNotNull(DB::table('clinica_tratamiento_registros')->where('tra_id', $tra->tra_id)->value('firma'));
    }

    public function test_un_tratamiento_mensual_de_antes_marca_pagadas_las_cuotas_vencidas(): void
    {
        // Empezó el 01/12/2025: inicial S/ 300 + cuotas de S/ 100 desde el 05/01/2026 (la prueba corre el 30/10/2026)
        $r = $this->nuevo(['descripcion' => 'ORTODONCIA BRACKETS', 'modalidad' => 'MENSUAL', 'cuota_inicial' => 300, 'cuotas' => 24, 'cuota' => 100,
            'primer_vencimiento' => '2026-01-05', 'inicio' => '2025-12-01', 'cuotas_pagadas' => 11]);
        $this->assertTrue($r['ok'], $r['mensaje'] ?? '');

        $pago = DB::table('clinica_tratamiento_registros')->where('tra_id', $r['tra_id'])->first();
        $this->assertEquals(1300, (float) $pago->adelanto, 'Inicial + 10 cuotas (enero a octubre).');
        $this->assertNull($pago->ped_id, 'No va a caja: ya se cobró antes del sistema.');
        $this->assertStringContainsString('PAGOS ANTERIORES', $pago->detalle);
        $this->assertSame('2025-12-01', substr((string) DB::table('clinica_tratamientos')->where('tra_id', $r['tra_id'])->value('creado'), 0, 10));
        $this->assertSame(0, DB::table('pedidos')->where('ped_tip', 'Clinica')->count());
    }

    public function test_un_tratamiento_normal_de_antes_registra_lo_que_ya_pago(): void
    {
        $r = $this->nuevo(['descripcion' => 'ENDODONCIA', 'costo' => 1200, 'inicio' => '2026-03-10', 'pagado_antes' => 500]);
        $this->assertEquals(500, (float) DB::table('clinica_tratamiento_registros')->where('tra_id', $r['tra_id'])->value('adelanto'));
        $this->assertSame('EN CURSO', DB::table('clinica_tratamientos')->where('tra_id', $r['tra_id'])->value('estado'));
    }

    public function test_el_administrador_cambia_el_precio_del_adelanto_al_cobrar(): void
    {
        $r = $this->nuevo(['descripcion' => 'CURACIONES', 'costo' => 2000, 'detalle' => 'PRIMERA SESION', 'adelanto' => 200]);
        $reg = DB::table('clinica_tratamiento_registros')->where('tra_id', $r['tra_id'])->first();
        $linea = DB::table('pedidos_detalle')->where('ped_id', $reg->ped_id)->first();
        $clave = $linea->IdProducto.'||'.substr(md5((string) $linea->opciones), 0, 8).'|200.00';

        $cobro = $this->postJson(route('cobros.registrar'), [
            'ped_id' => $reg->ped_id, 'tdocod' => '13', 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => '1', 'clinum' => '47664500', 'clinom' => 'PACIENTE DE PRUEBA', 'precios' => [$clave => 150],
            'id_med_pag' => [$this->efectivo], 'mon_med_pag' => [150], 'paga' => 150,
        ])->assertOk()->json();
        $this->assertSame('success', $cobro['estado'] ?? null, $cobro['mensaje'] ?? '');

        $this->assertEquals(150, (float) DB::table('cpe_cabecera')->where('ped_id', $reg->ped_id)->value('ccaitv'));
        $reg = DB::table('clinica_tratamiento_registros')->where('reg_id', $reg->reg_id)->first();
        $this->assertEquals(150, (float) $reg->adelanto, 'La sesión queda con lo que de verdad se cobró.');
        $this->assertEquals(1850, (float) $reg->saldo);
    }

    public function test_el_cajero_no_puede_cambiar_el_precio(): void
    {
        $r = $this->nuevo(['descripcion' => 'CURACIONES', 'costo' => 2000, 'detalle' => 'PRIMERA SESION', 'adelanto' => 200]);
        $reg = DB::table('clinica_tratamiento_registros')->where('tra_id', $r['tra_id'])->first();
        $linea = DB::table('pedidos_detalle')->where('ped_id', $reg->ped_id)->first();
        $clave = $linea->IdProducto.'||'.substr(md5((string) $linea->opciones), 0, 8).'|200.00';
        $cajero = $this->usuario->replicate();
        $cajero->email = 'caja'.$this->usuario->IdUsuario.'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['user_IdUsuario' => $cajero->IdUsuario, 'role_id' => 4]);
        Turno::create(['turno' => 2, 'IdUsuario' => $cajero->IdUsuario, 'apertura' => now(), 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 0]);

        // El cajero manda un precio menor: se ignora y se cobra el de verdad
        $this->actingAs($cajero)->get(route('cobros.cobrar', $reg->ped_id))->assertOk()->assertDontSee('class="precio-admin"', false);
        $cobro = $this->postJson(route('cobros.registrar'), [
            'ped_id' => $reg->ped_id, 'tdocod' => '13', 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => '1', 'clinum' => '47664500', 'clinom' => 'PACIENTE DE PRUEBA', 'precios' => [$clave => 1],
            'id_med_pag' => [$this->efectivo], 'mon_med_pag' => [200], 'paga' => 200,
        ])->assertOk()->json();
        $this->assertSame('success', $cobro['estado'] ?? null, $cobro['mensaje'] ?? '');
        $this->assertEquals(200, (float) DB::table('cpe_cabecera')->where('ped_id', $reg->ped_id)->value('ccaitv'));
        $this->assertEquals(200, (float) DB::table('clinica_tratamiento_registros')->where('reg_id', $reg->reg_id)->value('adelanto'));
    }

    public function test_nueva_atencion_tiene_todo_en_una_sola_pantalla(): void
    {
        $atencion = $this->post(route('clinica.nueva_atencion', $this->historia), ['esp_id' => $this->especialidad]);
        $atencion->assertRedirect();
        $this->get($atencion->headers->get('Location'))->assertOk()
            ->assertSee('Registro de tratamientos')->assertSee('+ Nuevo tratamiento')
            ->assertSee('Presupuestos')->assertSee('Signos vitales');
        $this->get(route('clinica.historia', $this->historia))->assertOk()->assertSee('⋯ Más')->assertSee('Nueva atención');
    }
}
