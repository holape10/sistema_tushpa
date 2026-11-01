<?php

namespace Tests\Flujos;

use App\Events\CambioEnSucursal;
use App\Support\TiempoReal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** Tiempo real: quién puede escuchar el canal de una sucursal y que guardar algo avise a las pantallas */
class TiempoRealTest extends FlujoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'clave', 'broadcasting.connections.reverb.secret' => 'secreto',
            'broadcasting.connections.reverb.app_id' => '1']);
        require base_path('routes/channels.php');   // phpunit arranca con el broadcaster "null": los canales se registran de nuevo en Reverb
    }

    private function entrar(string $canal): int
    {
        return $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$canal])->getStatusCode();
    }

    public function test_solo_los_usuarios_de_la_sucursal_escuchan_su_canal(): void
    {
        $ruc = (string) $this->usuario->IdEmpresa;

        $this->assertSame(200, $this->entrar(CambioEnSucursal::canal($ruc, $this->sucursal)));
        $this->assertSame(403, $this->entrar(CambioEnSucursal::canal($ruc, $this->sucursal + 999)), 'Otra sucursal no debe poder escuchar.');
        $this->assertSame(403, $this->entrar(CambioEnSucursal::canal('20999999999', $this->sucursal)), 'Otra empresa no debe poder escuchar.');
    }

    public function test_guardar_en_cocina_avisa_a_las_pantallas_de_la_sucursal(): void
    {
        Event::fake([CambioEnSucursal::class]);
        $ticket = DB::table('cocina_tickets')->insertGetId(['ped_id' => 0, 'id_empresa_negocio' => $this->sucursal, 'destino' => 'MESA 01', 'tipo' => 'NUEVO', 'creado' => now()]);
        $item = DB::table('cocina_items')->insertGetId(['ticket_id' => $ticket, 'descripcion' => 'CEVICHE', 'cantidad' => 1]);

        $this->postJson('/cocina/item/'.$item.'/alternar')->assertOk();
        TiempoReal::enviar();   // en el servidor sale solo al terminar la respuesta

        Event::assertDispatched(CambioEnSucursal::class, fn ($e) => $e->sucursal === $this->sucursal && in_array('cocina', $e->temas, true));
    }

    public function test_sin_reverb_no_se_avisa_ni_se_rompe_nada(): void
    {
        config(['broadcasting.default' => 'log']);
        Event::fake([CambioEnSucursal::class]);

        TiempoReal::avisar($this->sucursal);
        TiempoReal::enviar();

        Event::assertNotDispatched(CambioEnSucursal::class);
    }
}
