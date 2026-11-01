<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** Avisos al celular de los mozos: solo en sucursales que usan el QR de mesas */
class AvisosCelularTest extends FlujoTestCase
{
    private const EQUIPO = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/prueba-123', 'keys' => ['p256dh' => 'BPruebaClavePublica', 'auth' => 'pruebaAuth']];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.push.publica' => 'clave-publica', 'services.push.privada' => 'clave-privada']);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['carta_pedido_mesa' => 1]);
    }

    public function test_activar_y_apagar_avisos_en_un_celular(): void
    {
        $this->postJson(route('avisos_celular.suscribir'), self::EQUIPO)->assertOk();
        $this->postJson(route('avisos_celular.suscribir'), self::EQUIPO)->assertOk();   // volver a activarlo no lo duplica

        $equipos = DB::table('push_suscripciones')->get();
        $this->assertCount(1, $equipos);
        $this->assertSame($this->sucursal, (int) $equipos[0]->id_empresa_negocio);
        $this->assertSame((int) $this->usuario->IdUsuario, (int) $equipos[0]->IdUsuario);

        $this->postJson(route('avisos_celular.quitar'), ['endpoint' => self::EQUIPO['endpoint']])->assertOk();
        $this->assertSame(0, DB::table('push_suscripciones')->count());
    }

    public function test_sin_claves_configuradas_no_se_guarda_nada(): void
    {
        config(['services.push.publica' => null]);

        $this->postJson(route('avisos_celular.suscribir'), self::EQUIPO)->assertNotFound();
        $this->assertSame(0, DB::table('push_suscripciones')->count());
    }

    public function test_sin_qr_de_mesas_no_hay_avisos(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['carta_pedido_mesa' => 0]);

        $this->postJson(route('avisos_celular.suscribir'), self::EQUIPO)->assertNotFound();
        $this->get(route('comandas.seleccion'))->assertOk()->assertDontSee('data-avisos-celular', false);
    }

    public function test_solo_acepta_direcciones_seguras_de_avisos(): void
    {
        $this->postJson(route('avisos_celular.suscribir'), ['endpoint' => 'http://otro-sitio.test/x'] + self::EQUIPO)->assertUnprocessable();
    }
}
