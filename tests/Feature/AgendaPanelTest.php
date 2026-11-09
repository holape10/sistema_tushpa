<?php

namespace Tests\Feature;

use App\Models\Central\Superadmin;
use App\Support\AgendaPanel;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/** Agenda del panel: la de cada usuario, la general de su marca (sin cruzarse con otra) y los avisos por Telegram */
class AgendaPanelTest extends TestCase
{
    private Superadmin $ana;

    private Superadmin $beto;

    private Superadmin $deMarca;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        if (! Schema::connection('central')->hasTable('agenda_eventos')) {
            $this->markTestSkipped('Falta migrar la base central.');
        }
        DB::connection('central')->beginTransaction();
        config(['services.telegram.citas_token' => 'token-bot']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $marca = DB::connection('central')->table('marcas')->insertGetId(['nombre' => 'WILCAT PRUEBA', 'dominio' => 'wilcat-prueba.test', 'prefijo_bd' => 'wp', 'activa' => 1]);
        $nuevo = fn (string $nombre, ?int $marcaId, string $chat) => Superadmin::create(['nombre' => $nombre, 'email' => uniqid().'@prueba.test',
            'password' => 'clave-de-prueba', 'activo' => true, 'marca_id' => $marcaId, 'telegram_chat_id' => $chat]);
        $this->ana = $nuevo('ANA', null, '111');
        $this->beto = $nuevo('BETO', null, '222');
        $this->deMarca = $nuevo('CARLA', $marca, '333');
        $this->travelTo('2026-11-10 08:00');
    }

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        parent::tearDown();
    }

    private function enviadosA(string $chat): int
    {
        return Http::recorded(fn (Peticion $p) => str_contains($p->url(), 'sendMessage') && $p['chat_id'] === $chat)->count();
    }

    public function test_la_personal_es_de_uno_y_la_general_solo_de_su_marca(): void
    {
        AgendaPanel::guardar($this->ana, ['titulo' => 'Dentista', 'fecha' => '2026-11-12', 'hora' => '10:00']);
        AgendaPanel::guardar($this->ana, ['titulo' => 'Reunión de equipo', 'fecha' => '2026-11-12', 'hora' => '15:00', 'general' => true]);
        AgendaPanel::guardar($this->deMarca, ['titulo' => 'Reunión WILCAT', 'fecha' => '2026-11-12', 'hora' => '16:00', 'general' => true]);

        $ve = fn (Superadmin $s) => AgendaPanel::entre($s, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'))->pluck('titulo')->all();
        $this->assertSame(['Dentista', 'Reunión de equipo'], $ve($this->ana));
        $this->assertSame(['Reunión de equipo'], $ve($this->beto), 'Beto ve la general de TUSHPA, no lo personal de Ana.');
        $this->assertSame(['Reunión WILCAT'], $ve($this->deMarca), 'La de otra marca nunca se cruza.');
    }

    public function test_avisa_por_telegram_en_cada_momento_elegido_una_sola_vez(): void
    {
        // Personal de Ana, el 11 a las 3 pm, con aviso 1 día y 1 hora antes
        AgendaPanel::guardar($this->ana, ['titulo' => 'Pagar SUNAT', 'fecha' => '2026-11-11', 'hora' => '15:00', 'avisos' => [1440, 60]]);

        $this->assertSame(0, AgendaPanel::avisar(Carbon::parse('2026-11-10 14:55')));
        $this->assertSame(1, AgendaPanel::avisar(Carbon::parse('2026-11-10 15:00')), '1 día antes.');
        $this->assertSame(0, AgendaPanel::avisar(Carbon::parse('2026-11-10 15:05')), 'No se repite.');
        $this->assertSame(1, AgendaPanel::avisar(Carbon::parse('2026-11-11 14:00')), '1 hora antes.');
        $this->assertSame([2, 0], [$this->enviadosA('111'), $this->enviadosA('222')], 'Solo a Ana: es personal.');
        Http::assertSent(fn (Peticion $p) => str_contains($p['text'] ?? '', '1 hora antes: Pagar SUNAT'));
    }

    public function test_la_general_avisa_a_todos_los_de_la_marca(): void
    {
        AgendaPanel::guardar($this->beto, ['titulo' => 'Capacitación', 'fecha' => '2026-11-10', 'hora' => '10:00', 'general' => true, 'avisos' => [120]]);

        $this->assertSame(2, AgendaPanel::avisar(Carbon::parse('2026-11-10 08:00')));
        $this->assertSame([1, 1, 0], [$this->enviadosA('111'), $this->enviadosA('222'), $this->enviadosA('333')]);
    }

    public function test_solo_quien_lo_creo_lo_cambia_y_si_cambia_la_fecha_vuelve_a_avisar(): void
    {
        $id = AgendaPanel::guardar($this->ana, ['titulo' => 'Llamar a cliente', 'fecha' => '2026-11-10', 'hora' => '09:00', 'avisos' => [60]]);
        $this->assertSame(1, AgendaPanel::avisar(Carbon::parse('2026-11-10 08:00')));

        AgendaPanel::guardar($this->ana, ['titulo' => 'Llamar a cliente', 'fecha' => '2026-11-10', 'hora' => '11:00', 'avisos' => [60]], $id);
        $this->assertSame(1, AgendaPanel::avisar(Carbon::parse('2026-11-10 10:00')), 'Se movió: avisa de nuevo a la nueva hora.');

        $this->expectException(NotFoundHttpException::class);
        AgendaPanel::eliminar($this->beto, $id);
    }
}
