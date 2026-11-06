<?php

namespace Tests\Flujos;

use App\Support\Gastos;
use Illuminate\Support\Facades\DB;

/** Candado de meses: un mes cerrado (ya declarado) no se toca; solo el administrador cierra y reabre */
class MesesCerradosTest extends FlujoTestCase
{
    private const MENSAJE = 'y ese mes está CERRADO (ya declarado)';

    /** Una compra al contado con fecha de emisión e ingreso */
    private function compra(int $productoId, string $fecha, string $numero = '123'): array
    {
        return ['tdocod' => '01', 'serie' => 'F001', 'numero' => $numero, 'fecEmi' => $fecha, 'fecIng' => $fecha, 'estadopago' => $this->contado,
            'moneda' => 'PEN', 'id_almacen' => $this->almacen, 'prov_tdicod' => '6', 'prov_num' => '20100070970', 'prov_nom' => 'PROVEEDOR DE PRUEBA SAC',
            'items' => [['id' => $productoId, 'tip_igv' => '10', 'cantidad' => 12, 'costo' => 3]]];
    }

    private function cerrar(string $mes): void
    {
        $this->post(route('compras.mes'), ['mes' => $mes, 'accion' => 'cerrar'])->assertSessionHas('success');
    }

    public function test_en_un_mes_cerrado_no_se_registra_ni_edita_ni_anula_una_compra(): void
    {
        $gaseosa = $this->producto('INCA KOLA 500ML', 5);
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, '2026-09-15'))->assertJson(['estado' => 'success']);
        $id = (int) DB::table('compras_cabecera')->where('id_empresa_negocio', $this->sucursal)->value('com_cab_id');

        $this->cerrar('2026-09');

        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, '2026-09-20', '124'))
            ->assertJson(['estado' => 'error'])->assertJsonFragment(['mensaje' => 'La compra es de Septiembre 2026 '.self::MENSAJE.'. Si de verdad hay que corregirlo, el administrador debe reabrir el mes.']);
        $this->putJson(route('compras.update', $id), $this->compra($gaseosa->IdProducto, '2026-09-15'))->assertJson(['estado' => 'error']);
        // Tampoco se puede "sacar" la compra del mes cerrado cambiándole la fecha
        $this->putJson(route('compras.update', $id), $this->compra($gaseosa->IdProducto, '2026-10-05'))->assertJson(['estado' => 'error']);
        $this->post(route('compras.anular', $id))->assertSessionHas('error');

        $this->assertSame('Registrado', DB::table('compras_cabecera')->where('com_cab_id', $id)->value('est_compra'));
        $this->assertSame(1, DB::table('compras_cabecera')->where('id_empresa_negocio', $this->sucursal)->count());
        // El mes siguiente sigue abierto
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, '2026-10-05', '125'))->assertJson(['estado' => 'success']);
    }

    public function test_reabrir_el_mes_permite_corregir_y_queda_como_alerta_en_la_bitacora(): void
    {
        $gaseosa = $this->producto('INCA KOLA 500ML', 5);
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, '2026-09-15'))->assertJson(['estado' => 'success']);
        $id = (int) DB::table('compras_cabecera')->where('id_empresa_negocio', $this->sucursal)->value('com_cab_id');
        $this->cerrar('2026-09');

        $this->post(route('compras.mes'), ['mes' => '2026-09', 'accion' => 'abrir'])->assertSessionHas('success');
        $this->post(route('compras.anular', $id))->assertSessionHas('success');

        $this->assertTrue((bool) DB::table('bitacora')->where('accion', 'compra.mes_abrir')->value('alerta'));
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'compra.mes_cerrar')->count());
    }

    public function test_los_gastos_de_un_mes_cerrado_tampoco_se_tocan(): void
    {
        $categoria = DB::table('gasto_categorias')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'nombre' => 'LUZ']);
        $gasto = ['fecha' => '2026-09-10', 'tdocod' => '01', 'serie' => 'E001', 'numero' => '7', 'prov_doc' => '20100070970', 'prov_nombre' => 'LUZ DEL ORIENTE',
            'categoria_id' => $categoria, 'moneda' => 'PEN', 'base' => 100, 'igv' => 18, 'forma_pago' => 'CONTADO'];
        $id = Gastos::guardar($this->usuario, $gasto);
        $this->cerrar('2026-09');

        $this->post(route('gastos.anular', $id))->assertSessionHas('error');
        $this->assertSame('Registrado', DB::table('gastos')->where('id', $id)->value('estado'));
        try {
            Gastos::guardar($this->usuario, ['numero' => '8'] + $gasto);
            $this->fail('Registró un gasto en un mes cerrado.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString(self::MENSAJE, $e->getMessage());
        }
    }

    public function test_cerrar_el_mes_en_el_libro_diario_tambien_bloquea_las_compras(): void
    {
        $gaseosa = $this->producto('INCA KOLA 500ML', 5);
        $this->post(route('contabilidad.periodo'), ['periodo' => '202609', 'accion' => 'cerrar'])->assertSessionHas('success');

        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, '2026-09-15'))->assertJson(['estado' => 'error']);
    }

    public function test_solo_el_administrador_cierra_meses_y_no_uno_que_no_llega(): void
    {
        $this->post(route('compras.mes'), ['mes' => '2026-12', 'accion' => 'cerrar'])->assertSessionHas('error', 'No se cierra un mes que todavía no llega.');

        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->actingAs($cajero)->post(route('compras.mes'), ['mes' => '2026-09', 'accion' => 'cerrar'])->assertForbidden();

        $this->assertSame(0, DB::table('conta_periodos')->where('IdEmpresa', $this->usuario->IdEmpresa)->count());
    }

    public function test_la_lista_de_compras_muestra_el_candado_al_administrador(): void
    {
        $gaseosa = $this->producto('INCA KOLA 500ML', 5);
        $this->postJson(route('compras.store'), $this->compra($gaseosa->IdProducto, '2026-09-15'))->assertJson(['estado' => 'success']);
        $this->cerrar('2026-09');

        $this->get(route('compras.index', ['desde' => '2026-09-01', 'hasta' => '2026-09-30']))->assertOk()
            ->assertSee('🔒 Mes cerrado')->assertSee('Septiembre 2026')->assertDontSee('>Anular</button>', false);
    }
}
