<?php

namespace Tests\Flujos;

use App\Support\CartaMesa;
use App\Support\Encuestas;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Encuesta de satisfacción: en el ticket (opcional), en la mesa antes de cobrar y en la carta QR; una sola por mesa */
class EncuestasTest extends FlujoTestCase
{
    private int $mesa;

    protected function setUp(): void
    {
        parent::setUp();
        $piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        $this->mesa = DB::table('mesas')->insertGetId(['mes_nom' => 'M5', 'pis_id' => $piso, 'mes_est' => 'Libre',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['encuesta_activa' => false, 'encuesta_qr_ticket' => false]);
    }

    private function pedir(): int
    {
        $plato = $this->producto('CEVICHE MIXTO', 32, 10);
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $this->mesa, 'mesa_nombre' => 'M5'])->assertOk();
        $this->postJson(route('comandas.add_to_cart'), ['id' => $plato->IdProducto])->assertOk();
        $this->postJson(route('comandas.enviar'))->assertOk();

        return (int) DB::table('pedidos')->where('mes_id', $this->mesa)->where('ped_est', 'Aperturado')->value('ped_id');
    }

    private function cobrar(int $pedId): int
    {
        $this->postJson(route('cobros.registrar'), ['ped_id' => $pedId, 'tdocod' => '03', 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR', 'id_med_pag' => [$this->efectivo], 'mon_med_pag' => [32], 'paga' => 32])->assertOk();

        return (int) DB::table('cpe_cabecera')->where('ped_id', $pedId)->value('IdCpe_cabecera');
    }

    public function test_al_activarla_el_qr_del_ticket_viene_marcado_y_se_puede_apagar(): void
    {
        $this->post(route('encuestas.activar'))->assertSessionHas('success');
        $this->assertSame(1, (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('encuesta_qr_ticket'));
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 1]], '13'), 'PV');
        $this->get(route('cobros.voucher', $id))->assertSee('QUÉ TAL TE ATENDIMOS');

        $this->post(route('encuestas.qr_ticket'), ['qr_ticket' => 0])->assertSessionHas('success');

        $this->get(route('cobros.voucher', $id))->assertDontSee('QUÉ TAL TE ATENDIMOS');
        $this->get(route('encuestas.index'))->assertOk()->assertSee('Imprimir el QR de la encuesta en el ticket');
    }

    public function test_el_mozo_muestra_el_qr_antes_de_cobrar_y_la_mesa_responde_una_sola_vez(): void
    {
        $this->post(route('encuestas.activar'));
        $ped = $this->pedir();
        $this->get(route('comandas.seleccion'))->assertOk()->assertSee('ENCUESTA · QR para el cliente');

        $qr = $this->getJson(route('encuestas.qr_pedido', $ped))->assertJson(['ok' => true]);
        $this->assertStringContainsString(Encuestas::codigoPedido($ped), $qr->json('url'));
        $this->assertStringContainsString('<svg', $qr->json('svg'));

        $this->post(route('encuesta.responder', Encuestas::codigoPedido($ped)), ['comida' => 5, 'atencion' => 4, 'rapidez' => 5, 'comentario' => 'Muy rico'])
            ->assertRedirect();
        $this->getJson(route('encuestas.qr_pedido', $ped))->assertJson(['ok' => false]);

        // Al cobrar, el ticket ya no lleva el QR y su código dice que ya respondió
        $cab = $this->cobrar($ped);
        $this->get(route('cobros.voucher', $cab))->assertDontSee('QUÉ TAL TE ATENDIMOS');
        $this->get(route('encuesta.ver', Encuestas::codigo($cab)))->assertSee('Ya respondiste esta encuesta');
        $this->assertSame(1, DB::table('encuestas')->where('id_empresa_negocio', $this->sucursal)->count());

        $this->get(route('encuestas.index'))->assertOk()->assertSee('Muy rico')->assertSee('Mesa M5')->assertSee('Mozo');
    }

    public function test_en_la_carta_qr_el_cliente_califica_desde_mi_consumo(): void
    {
        $this->post(route('encuestas.activar'));
        $ped = $this->pedir();
        $consumo = CartaMesa::consumo($this->mesa);
        $this->assertStringContainsString(Encuestas::codigoPedido($ped), (string) $consumo['encuesta']);

        $this->post(route('encuesta.responder', Encuestas::codigoPedido($ped)), ['comida' => 2, 'atencion' => 1, 'rapidez' => 2, 'origen' => 'CARTA']);

        $this->assertSame('CARTA', DB::table('encuestas')->where('ped_id', $ped)->value('origen'));
        $this->assertNull(CartaMesa::consumo($this->mesa)['encuesta'], 'Ya respondió: no se vuelve a ofrecer.');
    }

    public function test_la_calificacion_mala_llega_al_telegram_del_administrador_que_lo_activo(): void
    {
        config(['services.telegram.citas_token' => 'token-bot']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $this->post(route('encuestas.activar'));
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['telegram_chat_id' => '555']);

        $this->get(route('encuestas.index'))->assertOk()->assertSee('Calificaciones malas por Telegram');
        $this->postJson(route('encuestas.telegram.recibir', $this->usuario->IdUsuario), ['recibir' => true])->assertJson(['ok' => true]);

        $ped = $this->pedir();
        $this->post(route('encuesta.responder', Encuestas::codigoPedido($ped)), ['comida' => 1, 'atencion' => 2, 'rapidez' => 2,
            'comentario' => 'Demoraron mucho', 'telefono' => '987654321']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] === '555'
            && str_contains($r['text'], 'Calificación baja: 1.7 de 5') && str_contains($r['text'], 'Mesa M5') && str_contains($r['text'], 'Demoraron mucho'));

        // Una buena no molesta
        $enviados = fn () => Http::recorded(fn ($r) => str_contains($r->url(), 'sendMessage'))->count();
        $antes = $enviados();
        $otro = $this->cobrar($this->pedirOtraVez());
        $this->post(route('encuesta.responder', Encuestas::codigo($otro)), ['comida' => 5, 'atencion' => 5, 'rapidez' => 4])->assertRedirect();
        $this->assertSame(2, DB::table('encuestas')->where('id_empresa_negocio', $this->sucursal)->count());
        $this->assertSame($antes, $enviados());
    }

    private function pedirOtraVez(): int
    {
        DB::table('pedidos')->where('mes_id', $this->mesa)->where('ped_est', 'Aperturado')->update(['ped_est' => 'Cerrado']);
        DB::table('mesas')->where('mes_id', $this->mesa)->update(['mes_est' => 'Libre']);

        return $this->pedir();
    }
}
