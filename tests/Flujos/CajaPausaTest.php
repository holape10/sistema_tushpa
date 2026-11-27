<?php

namespace Tests\Flujos;

use App\Models\User;
use App\Support\LineaTiempo;
use App\Support\PausasCaja;
use Illuminate\Support\Facades\DB;

/** Pausar caja: mensaje grande para los clientes, nadie la usa mientras tanto y se vuelve con la clave */
class CajaPausaTest extends FlujoTestCase
{
    private function cajero(): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'cajero'.uniqid().'@prueba.test';
        $u->password = bcrypt('clave-del-cajero');
        $u->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        return $u;
    }

    public function test_en_pausa_nadie_usa_la_caja_y_se_vuelve_con_la_clave(): void
    {
        $this->postJson(route('pv.pausa.pausar'), ['motivo' => 'DESCANSO', 'minutos' => 15, 'pc' => 'CAJA 3'])->assertOk()
            ->assertJson(['ok' => true, 'pausa' => ['motivo' => 'DESCANSO', 'minutos' => 15, 'pc' => 'CAJA 3', 'vuelve' => '12:15']]);

        // La caja muestra el mensaje y lo demás lleva de vuelta ahí; cobrar no se puede
        $this->get(route('pv.index'))->assertOk()->assertSee('Clave para volver');
        $this->get(route('turnos.index'))->assertRedirect(route('pv.index'));
        $this->postJson(route('pv.registrar'), [])->assertStatus(423);

        $this->postJson(route('pv.pausa.reanudar'), ['clave' => 'otra'])->assertJson(['ok' => false, 'mensaje' => 'Clave incorrecta.']);
        $this->travel(20)->minutes();
        $this->postJson(route('pv.pausa.reanudar'), ['clave' => 'clave-de-prueba'])->assertJson(['ok' => true, 'minutos' => 20]);

        $this->get(route('turnos.index'))->assertOk();
        $pausa = DB::table('caja_pausas')->where('IdUsuario', $this->usuario->IdUsuario)->first();
        $this->assertSame([$this->turno->id_turno, 'CAJA 3'], [(int) $pausa->id_turno, $pausa->pc]);
        $this->assertNotNull($pausa->fin);
        $this->assertStringContainsString('Caja en pausa 20 min', DB::table('bitacora')->where('accion', 'caja.pausa')->orderByDesc('id')->value('descripcion'));
    }

    public function test_el_administrador_tambien_puede_quitar_la_pausa_de_un_cajero(): void
    {
        $cajero = $this->cajero();
        PausasCaja::iniciar($cajero, 'CERRADA');

        $this->assertSame((int) $this->usuario->IdUsuario, (function () use ($cajero) {
            PausasCaja::terminar($cajero, 'clave-de-prueba');

            return (int) DB::table('caja_pausas')->where('IdUsuario', $cajero->IdUsuario)->value('IdUsuario_fin');
        })(), 'La quitó el administrador con su clave');
    }

    public function test_la_pausa_sale_en_la_linea_de_tiempo(): void
    {
        $p = PausasCaja::iniciar($this->usuario, 'CERRADA', null, 'CAJA 1');
        $this->travel(5)->minutes();
        PausasCaja::terminar($this->usuario, 'clave-de-prueba');

        $evento = LineaTiempo::eventos($this->sucursal, now()->toDateString())->firstWhere('icono', '⏸');
        $this->assertSame('Pausó la caja: caja cerrada', $evento['titulo']);
        $this->assertStringContainsString('(5 min)', $evento['detalle']);
        $this->assertSame((int) $p->id, (int) DB::table('caja_pausas')->value('id'));
    }
}
