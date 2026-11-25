<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** Separados (apartados): adelanto hoy, el producto queda reservado, abonos por Cuentas por cobrar y se entrega al terminar de pagar */
class SeparadosTest extends FlujoTestCase
{
    private function separar(array $extra = [])
    {
        $cocina = $this->producto('COCINA ROLO HOME', 350, 5);

        return [$cocina, $this->postJson(route('separados.crear'), $extra + [
            'items' => [['id' => $cocina->IdProducto, 'cantidad' => 1, 'precio' => 350]], 'tdocod' => '13', 'fecEmi' => now()->toDateString(),
            'tdicod' => '1', 'clinum' => '45678912', 'clinom' => 'ROSA PEREZ', 'clidir' => '',
            'adelanto' => 100, 'adelanto_medio' => $this->efectivo, 'vence' => now()->addDays(15)->toDateString(), 'telefono' => '987654321',
        ])];
    }

    public function test_al_separar_el_producto_sale_del_stock_y_el_adelanto_entra_a_la_caja(): void
    {
        [$cocina, $r] = $this->separar();
        $d = $r->assertOk()->json();

        $this->assertSame('success', $d['estado'], json_encode($d));
        $this->assertSame('SP-000001', $d['codigo']);
        $this->assertEquals(250.0, $d['saldo']);
        $this->assertEquals(4.0, $this->stock($cocina), 'Lo separado ya no se puede vender a otro.');
        $s = DB::table('separados')->first();
        $this->assertSame('987654321', $s->telefono);
        $this->assertEquals(250.0, (float) DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $s->IdCpe_cabecera)->value('saldo'));
        $this->assertEquals(100.0, (float) DB::table('movimientoscaja')->where('id_turno', $this->turno->id_turno)->where('tip_caj_id', '007')->sum('importe'),
            'El adelanto en efectivo entra a la caja (una sola vez).');

        $this->get('/inicio')->assertOk()->assertSeeText('1 separado(s) · faltan cobrar S/ 250.00');
        $this->get(route('separados.index'))->assertOk()->assertSeeText('SP-000001 · ROSA PEREZ')->assertSeeText('Falta S/ 250.00');
        $this->get(route('pv.index'))->assertOk()->assertSee('abrirSeparar()', false);
    }

    public function test_se_entrega_cuando_termina_de_pagar(): void
    {
        $this->separar();
        $s = DB::table('separados')->first();
        $cuenta = (int) DB::table('cuentas_cobrar')->where('IdCpe_cabecera', $s->IdCpe_cabecera)->value('cue_cob_id');

        $this->post(route('separados.entregar', $s->id))->assertSessionHas('error', 'Aún falta cobrar S/ 250.00. Cobra el saldo antes de entregarlo.');
        $this->assertSame('SEPARADO', DB::table('separados')->where('id', $s->id)->value('estado'));

        $this->postJson(route('cuentas.pagar', ['cobrar', $cuenta]), ['fecha' => now()->toDateString(), 'medios' => [['id' => $this->efectivo, 'monto' => 250]]])
            ->assertJson(['estado' => 'ok']);
        $this->post(route('separados.entregar', $s->id))->assertSessionHas('success');

        $this->assertSame('ENTREGADO', DB::table('separados')->where('id', $s->id)->value('estado'));
        $this->get(route('separados.index'))->assertSeeText('No hay separados pendientes');
    }

    public function test_sin_dni_o_si_el_adelanto_paga_todo_no_se_separa(): void
    {
        [, $r] = $this->separar(['clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR']);
        $r->assertJson(['estado' => 'error', 'mensaje' => 'Para separar pon el DNI y el nombre del cliente (así se sabe de quién es).']);

        [, $r] = $this->separar(['adelanto' => 350]);
        $r->assertJson(['estado' => 'error']);
        $this->assertSame(0, DB::table('separados')->count());
        $this->assertSame(0, DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->count(), 'No queda una venta a medias.');
    }

    public function test_el_aviso_por_whatsapp_dice_lo_que_falta_y_hasta_cuando(): void
    {
        $this->separar();
        $s = DB::table('separados')->first();

        $destino = $this->get(route('separados.whatsapp', $s->id))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://wa.me/51987654321?text=', $destino);
        $texto = rawurldecode(substr($destino, strpos($destino, '=') + 1));
        $this->assertStringContainsString('1 × COCINA ROLO HOME', $texto);
        $this->assertStringContainsString('*Te falta: S/ 250.00*', $texto);
        $this->assertStringContainsString('Puedes recogerlo hasta el '.now()->addDays(15)->format('d/m/Y'), $texto);
    }
}
