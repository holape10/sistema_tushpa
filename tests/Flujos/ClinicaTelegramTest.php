<?php

namespace Tests\Flujos;

use App\Support\ClinicaRecordatorios;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Clínica: los recordatorios al doctor por Telegram (gratis), vinculándolo una vez con el enlace del bot */
class ClinicaTelegramTest extends FlujoTestCase
{
    private int $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        // Sin WhatsApp: solo Telegram
        config(['services.telegram.citas_token' => 'token-bot', 'services.whatsapp.token' => null, 'services.whatsapp.phone_id' => null, 'cache.default' => 'array', 'mail.default' => 'array']);
        $doc = $this->usuario->replicate();
        $doc->email = 'doctora'.uniqid().'@prueba.test';
        $doc->apeusu = 'CLAUDIA VARGAS';
        $doc->save();
        $this->doctor = (int) $doc->IdUsuario;
        DB::table('role_user')->insert(['role_id' => 10, 'user_IdUsuario' => $this->doctor, 'id_empresa_negocio' => $this->sucursal]);

        // Telegram de mentira: el doctor ya tocó Iniciar con el código del último enlace
        Http::fake(function (Peticion $p) {
            if (str_ends_with($p->url(), '/getMe')) {
                return Http::response(['ok' => true, 'result' => ['username' => 'CitasClinicaBot']]);
            }
            if (str_contains($p->url(), '/getUpdates')) {
                $codigo = DB::connection('central')->table('telegram_vinculos')->whereNull('chat_id')->value('codigo');

                return Http::response(['ok' => true, 'result' => $codigo ? [['update_id' => 10, 'message' => ['text' => "/start {$codigo}",
                    'chat' => ['id' => 5551234], 'from' => ['first_name' => 'Claudia']]]] : []]);
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
        });
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    public function test_el_doctor_vincula_su_telegram_y_le_llegan_sus_citas_gratis(): void
    {
        $enlace = $this->postJson(route('clinica.recordatorios.telegram.enlace', $this->doctor))->assertJson(['ok' => true])->json('enlace');
        $this->assertMatchesRegularExpression('#^https://t\.me/CitasClinicaBot\?start=[a-z0-9]{16}$#', $enlace);

        // Toca Iniciar en el bot: el sistema lo detecta y queda vinculado
        $this->postJson(route('clinica.recordatorios.telegram.verificar'))->assertJson(['ok' => true, 'vinculados' => [$this->doctor]]);
        $this->assertSame('5551234', DB::table('users')->where('IdUsuario', $this->doctor)->value('telegram_chat_id'));
        Http::assertSent(fn (Peticion $p) => str_contains($p->url(), 'sendMessage') && $p['chat_id'] === '5551234' && str_contains($p['text'], 'Listo, Claudia'));

        // La noche anterior: su lista; y 2 horas antes, cada cita
        $this->travelTo('2026-11-09 18:00');
        DB::table('citas')->insert(['paciente' => 'ANA TORRES', 'telefono' => '912345678', 'doctor' => $this->doctor, 'fecha' => '2026-11-10',
            'hora' => '17:00:00', 'motivo' => 'Limpieza', 'estado' => 'PROGRAMADA', 'id_empresa_negocio' => $this->sucursal]);
        $this->assertSame(1, ClinicaRecordatorios::ejecutar(now())['doctores']);
        Http::assertSent(fn (Peticion $p) => str_contains($p->url(), 'sendMessage') && str_contains($p['text'], '5:00 pm — ANA TORRES'));

        $this->travelTo('2026-11-10 15:00');
        $this->assertSame(1, ClinicaRecordatorios::ejecutar(now())['doctores']);
        Http::assertSent(fn (Peticion $p) => str_contains($p->url(), 'sendMessage') && str_contains($p['text'], 'a las 5:00 pm tienes cita'));
        $this->assertSame(0, ClinicaRecordatorios::ejecutar(now())['doctores'], 'No se repite.');

        // Se puede quitar
        $this->postJson(route('clinica.recordatorios.telegram.quitar', $this->doctor))->assertJson(['ok' => true]);
        $this->assertNull(DB::table('users')->where('IdUsuario', $this->doctor)->value('telegram_chat_id'));
    }

    public function test_un_enlace_de_otra_empresa_o_viejo_no_vincula_a_nadie_de_aqui(): void
    {
        DB::connection('central')->table('telegram_vinculos')->insert(['codigo' => 'abcdefghijklmnop', 'base' => 'bd_otra_empresa', 'IdUsuario' => $this->doctor]);

        $this->postJson(route('clinica.recordatorios.telegram.verificar'))->assertJson(['ok' => true, 'vinculados' => []]);
        $this->assertNull(DB::table('users')->where('IdUsuario', $this->doctor)->value('telegram_chat_id'));
    }
}
