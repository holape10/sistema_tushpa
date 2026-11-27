<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\TelegramCitas;
use App\Support\Tenancy\CertificadosClientes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Certificados por vencer: franja en el panel y aviso al Telegram de cada usuario del panel (lo que ve) y de la empresa */
class CertificadosAvisosTest extends TestCase
{
    private const MARCA = 66;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        DB::connection('central')->beginTransaction();
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        parent::tearDown();
    }

    private function usuario(?int $marca = null, bool $dueno = false): Superadmin
    {
        return Superadmin::create(['nombre' => 'USUARIO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true,
            'es_dueno' => $dueno, 'marca_id' => $marca]);
    }

    private function empresa(string $ruc, int $dias, ?int $marca): Cliente
    {
        return Cliente::create(['ruc' => $ruc, 'base_datos' => 'bd_prueba_cert_'.$ruc, 'razon_social' => 'EMPRESA '.$ruc, 'estado' => 'ACTIVO',
            'marca_id' => $marca, 'cert_vence' => now()->addDays($dias)->toDateString()]);
    }

    public function test_la_franja_cuenta_solo_las_empresas_que_ve_cada_usuario(): void
    {
        $this->empresa('20666666661', 5, self::MARCA);
        $this->empresa('20666666662', -3, null);
        $this->empresa('20666666663', 40, self::MARCA);   // lejos: no cuenta

        $this->assertSame(['vencidos' => 0, 'por_vencer' => 1], CertificadosClientes::paraFranja($this->usuario(self::MARCA)));
        $dueno = $this->usuario(dueno: true);
        $franja = CertificadosClientes::paraFranja($dueno);
        $this->assertGreaterThanOrEqual(1, $franja['vencidos']);

        $this->actingAs($dueno, 'superadmin');
        $this->get(route('admin.clientes.index'))->assertOk()->assertSeeText('Certificados digitales')->assertSeeText('vencido(s)');
    }

    public function test_cada_usuario_del_panel_recibe_por_telegram_solo_sus_empresas(): void
    {
        config(['services.telegram.token' => 'bot-prueba', 'services.telegram.chat_id' => '111', 'services.telegram.citas_token' => 'bot-prueba']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $deMarca = $this->usuario(self::MARCA);
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => uniqid(), 'base' => TelegramCitas::PANEL, 'IdUsuario' => $deMarca->id,
            'chat_id' => '222', 'nombre' => 'EXPERTO', 'vinculado_at' => now(), 'created_at' => now()]);
        $suya = $this->empresa('20666666664', 5, self::MARCA);
        $ajena = $this->empresa('20666666665', 2, null);
        $avisos = array_map(fn (Cliente $c) => ['empresa' => $c->razon_social, 'ruc' => $c->ruc, 'vence' => $c->cert_vence, 'dias' => (int) now()->startOfDay()->diffInDays($c->cert_vence, false),
            'telefono' => null, 'cliente' => $c], [$suya, $ajena]);

        (new \ReflectionMethod(CertificadosClientes::class, 'avisarPanel'))->invoke(null, $avisos);

        Http::assertSent(fn ($r) => ($r['chat_id'] ?? null) === '222' && str_contains((string) $r['text'], 'RUC 20666666664') && ! str_contains((string) $r['text'], '20666666665'));
    }

    public function test_el_mensaje_para_la_empresa_dice_cuando_vence_y_que_hacer(): void
    {
        $a = ['empresa' => 'BODEGA ROSA', 'ruc' => '20666666666', 'vence' => now()->addDays(15), 'dias' => 15];

        $texto = CertificadosClientes::mensajeEmpresa($a);

        $this->assertStringContainsString('Tu certificado digital vence en 15 días', $texto);
        $this->assertStringContainsString('Mantenimiento › Empresas', $texto);
        $this->assertStringContainsString('ya no puedes enviar', mb_strtolower(CertificadosClientes::mensajeEmpresa(['dias' => -2] + $a)));
    }
}
