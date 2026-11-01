<?php

namespace Tests\Flujos;

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

    public function test_las_horas_se_dicen_como_la_gente(): void
    {
        $this->assertSame(['12 am', '9 am', '12 m', '9 pm'], array_map([PanelDueno::class, 'hora'], [0, 9, 12, 21]));
    }
}
