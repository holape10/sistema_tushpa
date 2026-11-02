<?php

namespace Tests\Flujos;

use Illuminate\Support\Facades\DB;

/** El dueño arma sus promociones automáticas desde la pantalla (solo el administrador) */
class PromocionesVentaTest extends FlujoTestCase
{
    public function test_crear_pausar_y_eliminar_una_promo(): void
    {
        $caipi = $this->producto('CAIPIRIÑA', 15, 10);
        $this->get(route('promociones.index'))->assertOk()->assertSee('Nueva promoción')->assertSee('Promociones 2x1');

        $this->post(route('promociones.guardar'), ['nombre' => '3x2 caipiriña', 'tipo' => 'LLEVA_PAGA', 'lleva' => 3, 'paga' => 2,
            'dias' => [5, 6], 'hora_inicio' => '22:00', 'hora_fin' => '02:00', 'productos' => [$caipi->IdProducto]])
            ->assertSessionHasNoErrors()->assertRedirect(route('promociones.index'));
        $promo = DB::table('promociones_venta')->where('id_empresa_negocio', $this->sucursal)->first();
        $this->assertSame(['3X2 CAIPIRIÑA', '56', '22:00:00', '02:00:00'], [$promo->nombre, $promo->dias, $promo->hora_inicio, $promo->hora_fin]);
        $this->assertSame([$caipi->IdProducto], DB::table('promocion_venta_producto')->where('promocion_id', $promo->id)->pluck('IdProducto')->map(fn ($id) => (int) $id)->all());

        $this->post(route('promociones.estado', $promo->id))->assertRedirect();
        $this->assertSame(0, (int) DB::table('promociones_venta')->where('id', $promo->id)->value('activo'));

        $this->post(route('promociones.eliminar', $promo->id))->assertRedirect(route('promociones.index'));
        $this->assertFalse(DB::table('promociones_venta')->where('id', $promo->id)->exists());
        $this->assertFalse(DB::table('promocion_venta_producto')->where('promocion_id', $promo->id)->exists());
    }

    public function test_no_deja_guardar_una_promo_que_no_tiene_sentido(): void
    {
        $caipi = $this->producto('CAIPIRIÑA', 15, 10);
        $base = ['nombre' => 'MALA', 'tipo' => 'LLEVA_PAGA', 'dias' => [1], 'todo_el_dia' => 1, 'productos' => [$caipi->IdProducto]];

        $this->post(route('promociones.guardar'), $base + ['lleva' => 2, 'paga' => 2])->assertSessionHasErrors('paga');
        $this->post(route('promociones.guardar'), ['tipo' => 'POR_PRECIO', 'lleva' => 3] + $base)->assertSessionHasErrors('precio');
        $this->post(route('promociones.guardar'), ['productos' => []] + $base + ['lleva' => 3, 'paga' => 2])->assertSessionHasErrors('productos');
        $this->assertSame(0, DB::table('promociones_venta')->where('id_empresa_negocio', $this->sucursal)->count());
    }
}
