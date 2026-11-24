<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use App\Support\EmpresaInicial;
use App\Support\Sunat\EnvioProgramado;
use App\Support\VentaDirecta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Envío a SUNAT programado (una vez al día a la hora elegida) y el ICBPER de S/ 0.50 en las empresas nuevas */
class EnvioProgramadoTest extends FlujoTestCase
{
    private function empresa(): Empresa
    {
        return Empresa::find($this->usuario->IdEmpresa);
    }

    public function test_la_empresa_elige_programado_y_la_hora(): void
    {
        $e = $this->empresa();
        $this->patch(route('empresas.update', $e->IdEmpresa), ['nomEmpresa' => $e->NomEmpresa, 'dirEmpresa' => $e->DirEmpresa,
            'produccion' => $e->produccion, 'txtWsUsuario' => 'MODDATOS', 'envio' => 3, 'envio_hora' => '19:30', 'icbper' => 0.5, 'tip_env_fac' => '01'])
            ->assertSessionHasNoErrors();

        $e->refresh();
        $this->assertSame(3, (int) $e->tipo_envio);
        $this->assertSame('19:30', $e->envio_hora);
        $this->get(route('empresas.edit', $e->IdEmpresa))->assertOk()->assertSee('Programado (una vez al día)');
    }

    public function test_solo_envia_pasada_la_hora_y_una_vez_al_dia(): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['tipo_envio' => 3, 'envio_hora' => '19:00', 'envio_programado_el' => null]);
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JABON', 3.5, 10), 1]], '01'), 'PV');
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('ARROZ', 4, 10), 1]], '03'), 'PV');
        $hoy = Carbon::parse(now()->toDateString());

        $this->assertFalse(EnvioProgramado::toca($this->empresa(), $hoy->copy()->setTime(18, 59)), 'Antes de la hora, no.');
        $this->assertTrue(EnvioProgramado::toca($this->empresa(), $hoy->copy()->setTime(19, 5)));

        $r = EnvioProgramado::ejecutar($hoy->copy()->setTime(19, 5));
        $this->assertSame(1, $r['empresas']);
        // La factura se intenta enviar sola y la boleta va al resumen del día (aquí sin certificado: queda con problema)
        $this->assertNotEmpty(array_filter($r['detalle'], fn ($d) => str_starts_with($d, 'F')), json_encode($r['detalle']));
        $this->assertNotEmpty(array_filter($r['detalle'], fn ($d) => str_contains($d, 'Resumen')), json_encode($r['detalle']));
        $this->assertSame($hoy->toDateString(), substr((string) $this->empresa()->envio_programado_el, 0, 10));

        // Ya se hizo hoy: no se repite
        $this->assertSame(0, EnvioProgramado::ejecutar($hoy->copy()->setTime(21, 0))['empresas']);
        // Al día siguiente, otra vez
        $this->assertTrue(EnvioProgramado::toca($this->empresa(), $hoy->copy()->addDay()->setTime(19, 1)));
    }

    public function test_la_empresa_nueva_empieza_con_icbper_de_50_centimos(): void
    {
        DB::table('empresa')->where('IdEmpresa', '20999999991')->delete();
        EmpresaInicial::crear(['ruc' => '20999999991', 'razon_social' => 'EMPRESA NUEVA SAC', 'direccion' => 'LIMA',
            'usuario' => 'nueva'.uniqid().'@prueba.test', 'password' => 'clave-de-prueba']);

        $this->assertEquals(0.50, (float) DB::table('empresa')->where('IdEmpresa', '20999999991')->value('icbper'));
    }
}
