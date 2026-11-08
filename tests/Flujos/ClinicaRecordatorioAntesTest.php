<?php

namespace Tests\Flujos;

use App\Support\ClinicaRecordatorios;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Clínica: recordatorio por WhatsApp unas horas antes de la cita, al paciente y a su doctor (una sola vez) */
class ClinicaRecordatorioAntesTest extends FlujoTestCase
{
    private int $cita;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.token' => 'token', 'services.whatsapp.phone_id' => '123456', 'services.whatsapp.idioma' => 'es',
            'services.whatsapp.plantilla_paciente' => 'recordatorio_cita', 'services.whatsapp.plantilla_doctor' => 'citas_doctor', 'mail.default' => 'array']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        DB::table('users')->where('IdUsuario', $this->usuario->IdUsuario)->update(['telefono' => '987654321']);
        $this->travelTo('2026-11-10 14:50');
        $this->cita = DB::table('citas')->insertGetId(['paciente' => 'ANA TORRES', 'telefono' => '912 345 678', 'doctor' => $this->usuario->IdUsuario,
            'fecha' => '2026-11-10', 'hora' => '17:00:00', 'motivo' => 'Limpieza dental', 'estado' => 'PROGRAMADA', 'id_empresa_negocio' => $this->sucursal]);
    }

    public function test_dos_horas_antes_le_llega_al_paciente_y_al_doctor_una_sola_vez(): void
    {
        // 2:50 pm: faltan más de 2 horas
        $this->assertSame(['pacientes' => 0, 'doctores' => 0, 'errores' => 0], ClinicaRecordatorios::ejecutar(now()));

        // 3:00 pm: faltan 2 horas → sale para los dos
        $this->travelTo('2026-11-10 15:00');
        $this->assertSame(['pacientes' => 1, 'doctores' => 1, 'errores' => 0], ClinicaRecordatorios::ejecutar(now()));
        Http::assertSent(fn (Peticion $p) => $p['to'] === '51912345678' && $p['template']['name'] === 'recordatorio_cita'
            && $p['template']['components'][0]['parameters'][3]['text'] === '5:00 pm');
        Http::assertSent(fn (Peticion $p) => $p['to'] === '51987654321' && $p['template']['name'] === 'citas_doctor'
            && str_contains($p['template']['components'][0]['parameters'][3]['text'], 'ANA TORRES'));

        // 3:15 pm: ya se envió, no se repite
        $this->travelTo('2026-11-10 15:15');
        $this->assertSame(['pacientes' => 0, 'doctores' => 0, 'errores' => 0], ClinicaRecordatorios::ejecutar(now()));
        Http::assertSentCount(2);
    }

    public function test_se_puede_apagar_o_cambiar_las_horas(): void
    {
        DB::table('clinica_config')->updateOrInsert(['id_empresa_negocio' => $this->sucursal], ['recordar_antes' => 0, 'hora_envio' => '18:00']);
        $this->travelTo('2026-11-10 15:00');
        $this->assertSame(0, ClinicaRecordatorios::ejecutar(now())['pacientes']);

        // Con 3 horas: a las 2 pm ya sale
        DB::table('clinica_config')->where('id_empresa_negocio', $this->sucursal)->update(['recordar_antes' => 1, 'horas_antes' => 3]);
        $this->travelTo('2026-11-10 14:00');
        $this->assertSame(1, ClinicaRecordatorios::ejecutar(now())['pacientes']);
    }

    public function test_una_cita_cancelada_o_que_ya_paso_no_se_recuerda(): void
    {
        DB::table('citas')->where('cit_id', $this->cita)->update(['estado' => 'CANCELADA']);
        $this->travelTo('2026-11-10 15:30');
        $this->assertSame(0, ClinicaRecordatorios::ejecutar(now())['pacientes']);

        DB::table('citas')->where('cit_id', $this->cita)->update(['estado' => 'PROGRAMADA']);
        $this->travelTo('2026-11-10 17:05');
        $this->assertSame(0, ClinicaRecordatorios::ejecutar(now())['pacientes']);
    }
}
