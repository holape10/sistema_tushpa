<?php

namespace Tests\Flujos;

use App\Support\ArqueoDiario;
use Illuminate\Support\Facades\DB;

/** Un gasto al contado pagado en efectivo sale de la caja del turno (como en el sistema antiguo) y lo cuenta el arqueo */
class GastosSalenDeCajaTest extends FlujoTestCase
{
    private int $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categoria = DB::table('gasto_categorias')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'nombre' => 'GASTOS DE EMPRESA']);
    }

    private function gasto(array $cambios = [], ?int $id = null): array
    {
        return $this->postJson(route('gastos.guardar', $id), $cambios + ['fecha' => now()->toDateString(), 'tdocod' => '01', 'serie' => 'F001', 'numero' => '20729',
            'prov_nombre' => 'LA ESTACION', 'descripcion' => 'COMPRA DE GASOLINA', 'categoria_id' => $this->categoria, 'moneda' => 'PEN',
            'base' => 42.37, 'igv' => 7.63, 'forma_pago' => 'CONTADO', 'id_med_pag' => $this->efectivo])->assertOk()->json();
    }

    private function salidaDeCaja(): ?object
    {
        $mov = DB::table('gastos')->latest('id')->value('mov_caj_id');

        return $mov ? DB::table('movimientoscaja')->where('mov_caj_id', $mov)->first() : null;
    }

    public function test_en_efectivo_sale_de_la_caja_y_aparece_en_el_arqueo(): void
    {
        $this->assertNull($this->gasto()['aviso']);
        $mov = $this->salidaDeCaja();
        $this->assertSame(['003', 50.0, 'ACTIVO', $this->turno->id_turno, 'F001-20729'],
            [$mov->tip_caj_id, (float) $mov->importe, $mov->estado, (int) $mov->id_turno, $mov->mov_num_doc]);

        $arqueo = ArqueoDiario::datos($this->sucursal, now()->toDateString());
        $this->assertSame(50.0, $arqueo['totales']['gastos'], 'El arqueo diario lo cuenta.');
        $this->assertSame(50.0, $this->get(route('turnos.index'))->viewData('r')['egresos'], 'Y el cierre del turno también.');

        // Corregido el monto, la salida de caja también; pagado con Yape, ya no sale de la caja
        $id = (int) DB::table('gastos')->latest('id')->value('id');
        $this->gasto(['base' => 84.75, 'igv' => 15.25], $id);
        $this->assertSame(100.0, (float) $this->salidaDeCaja()->importe);
        $yape = DB::table('medios_pagos')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'nom_med_pag' => 'YAPE', 'id_empresa_negocio' => $this->sucursal]);
        $this->gasto(['id_med_pag' => $yape], $id);
        $this->assertSame('ANULADO', $this->salidaDeCaja()->estado);
        $this->assertSame(0.0, ArqueoDiario::datos($this->sucursal, now()->toDateString())['totales']['gastos']);

        // De nuevo en efectivo vuelve a salir; al anular el gasto, se anula la salida
        $this->gasto([], $id);
        $this->assertSame('ACTIVO', $this->salidaDeCaja()->estado);
        $this->post(route('gastos.anular', $id));
        $this->assertSame('ANULADO', $this->salidaDeCaja()->estado);
    }

    public function test_sin_turno_abierto_se_guarda_y_avisa_que_no_salio_de_caja(): void
    {
        $this->turno->update(['estado' => 'CERRADO', 'cierre' => now()]);

        $this->assertStringContainsString('NO salió de la caja', $this->gasto()['aviso']);
        $this->assertNull($this->salidaDeCaja());
        $this->get(route('gastos.index', ['dia' => now()->toDateString()]))->assertOk()->assertSee('No salió de caja');
    }

    public function test_a_credito_no_toca_la_caja(): void
    {
        $this->gasto(['forma_pago' => 'CREDITO']);

        $this->assertNull($this->salidaDeCaja());
    }
}
