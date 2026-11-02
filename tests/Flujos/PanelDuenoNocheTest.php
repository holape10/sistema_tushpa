<?php

namespace Tests\Flujos;

use App\Http\Controllers\PanelDuenoController;
use App\Support\PanelDueno;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Panel del dueño de una discoteca: lo vendido de madrugada cuenta en la noche que se atendió, no en el día siguiente */
class PanelDuenoNocheTest extends FlujoTestCase
{
    /** Una venta de S/ $precio a esa hora */
    private function vendidoA(string $momento, float $precio): void
    {
        $trago = $this->producto('PISCO SOUR '.$precio, $precio);
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$trago, 1]]), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['fecha_hora' => $momento]);
    }

    private function deNoche(bool $si): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['atiende_de_noche' => $si]);
    }

    public function test_de_madrugada_sigue_siendo_la_noche_anterior(): void
    {
        $this->vendidoA('2026-10-30 23:00:00', 50);   // viernes 11 pm
        $this->vendidoA('2026-10-31 02:00:00', 30);   // sábado 2 am, misma noche
        $ahora = Carbon::parse('2026-10-31 03:00:00');

        $this->deNoche(true);
        $noche = PanelDueno::resumen($this->sucursal, $ahora);
        $this->assertSame(80.0, $noche['hoy']->total, 'La noche del viernes suma lo de las 11 pm y lo de las 2 am.');
        $this->assertTrue($noche['nocturno']);

        $this->deNoche(false);
        $this->assertSame(30.0, PanelDueno::resumen($this->sucursal, $ahora)['hoy']->total, 'Sin modo noche, "Hoy" empieza a las 12 am.');
    }

    public function test_el_panel_dice_esta_noche_en_vez_de_hoy(): void
    {
        $this->vendidoA('2026-10-30 23:00:00', 50);
        $this->deNoche(true);

        $this->get(route('dueno.index'))->assertOk()->assertSee('Esta noche')->assertSee('Tu panel cuenta por noches');
        foreach (['horas', 'equipo', 'alertas', 'turno'] as $ver) {
            $this->get(route('dueno.index', ['ver' => $ver]))->assertOk();
        }
        $this->getJson(route('dueno.resumen_dia'))->assertOk()->assertJsonPath('texto', fn ($t) => str_contains($t, 'resumen de la noche del'));
    }

    public function test_el_resumen_se_puede_mandar_a_cada_socio(): void
    {
        $this->postJson(route('dueno.configuracion'), ['whatsapp_dueno' => '987 654 321, 912345678; +51 956789123'])->assertOk();
        $this->assertSame('987654321,912345678,51956789123', DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->value('whatsapp_dueno'));

        $enlaces = $this->getJson(route('dueno.resumen_dia'))->assertOk()->json('enlaces');
        $this->assertCount(3, $enlaces);
        $this->assertStringStartsWith('https://wa.me/51987654321?text=', $enlaces[0]['url']);
        $this->assertStringStartsWith('https://wa.me/51956789123?text=', $enlaces[2]['url']);

        $this->postJson(route('dueno.configuracion'), ['whatsapp_dueno' => '987654321, hola'])->assertUnprocessable();
    }

    public function test_el_resumen_automatico_solo_va_a_quien_activo_los_avisos(): void
    {
        config(['services.push.publica' => 'clave-publica', 'services.push.privada' => 'clave-privada']);
        $this->assertFalse(PanelDueno::avisarCierre($this->sucursal, now()));

        DB::table('push_suscripciones')->insert(['IdUsuario' => $this->usuario->IdUsuario, 'id_empresa_negocio' => $this->sucursal, 'huella' => str_repeat('a', 64),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/x', 'p256dh' => 'p', 'auth' => 'a', 'mozo' => false, 'dueno' => true]);
        $this->assertTrue(PanelDueno::avisarCierre($this->sucursal, now()));
    }

    public function test_varias_noches_suman_la_produccion_de_cada_mozo_noche_por_noche(): void
    {
        Carbon::setTestNow('2026-11-02 03:00:00');
        $this->deNoche(true);
        $turnoViernes = DB::table('turnos')->insertGetId(['turno' => 1, 'IdUsuario' => $this->usuario->IdUsuario, 'apertura' => '2026-10-30 21:00:00',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'estado' => 'CERRADO']);
        $turnoSabado = DB::table('turnos')->insertGetId(['turno' => 2, 'IdUsuario' => $this->usuario->IdUsuario, 'apertura' => '2026-10-31 21:00:00',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal, 'estado' => 'CERRADO']);
        $this->vendidoA('2026-10-30 23:00:00', 50);   // viernes
        $this->vendidoA('2026-10-31 02:00:00', 30);   // madrugada: sigue siendo el viernes
        $this->vendidoA('2026-10-31 23:30:00', 100);  // sábado
        DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->update(['mozo' => $this->usuario->IdUsuario]);
        DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->where('fecha_hora', '<', '2026-10-31 12:00:00')->update(['id_turno' => $turnoViernes]);
        DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->where('fecha_hora', '>', '2026-10-31 12:00:00')->update(['id_turno' => $turnoSabado]);

        $rango = ['ver' => 'turno', 'periodo' => 'rango', 'desde' => '2026-10-30', 'hasta' => '2026-11-01'];
        $t = $this->get(route('dueno.index', $rango))->assertOk()->assertSee('Noche por noche')->assertSee('Para pagarles')->viewData('turno');
        $this->assertSame(180.0, $t['total']->total);
        $this->assertSame(['2026-10-30', '2026-10-31'], $t['dias']);
        $mozo = $t['mozos'][0];
        $this->assertSame(['2026-10-30' => 80.0, '2026-10-31' => 100.0], $mozo->porNoche, 'Lo de madrugada va en la noche del viernes.');
        $this->assertSame(2, $mozo->noches);

        // El dueño deja fuera el turno del sábado
        $soloViernes = $this->get(route('dueno.index', $rango + ['elegir' => 1, 'turnos' => [$turnoViernes]]))->assertOk()->viewData('turno');
        $this->assertSame(80.0, $soloViernes['total']->total);
        $this->assertSame([$turnoViernes], $soloViernes['rango']['marcados']);

        foreach (array_keys(PanelDuenoController::RANGOS) as $periodo) {
            $this->get(route('dueno.index', ['ver' => 'turno', 'periodo' => $periodo]))->assertOk();
        }
        Carbon::setTestNow();
    }

    public function test_las_horas_se_dicen_como_la_gente(): void
    {
        $this->assertSame(['12 am', '9 am', '12 m', '9 pm'], array_map([PanelDueno::class, 'hora'], [0, 9, 12, 21]));
    }
}
