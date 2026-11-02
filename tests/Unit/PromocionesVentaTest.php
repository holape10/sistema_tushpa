<?php

namespace Tests\Unit;

use App\Support\PromocionesVenta;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** Promociones automáticas: la cuenta del 2x1 / 3x2 / "3 por S/ 40" y cuándo valen */
class PromocionesVentaTest extends TestCase
{
    private function promo(array $datos): object
    {
        return (object) ($datos + ['id' => 1, 'nombre' => 'PROMO', 'tipo' => 'LLEVA_PAGA', 'lleva' => 3, 'paga' => 2, 'precio' => null,
            'dias' => '1234567', 'hora_inicio' => null, 'hora_fin' => null, 'desde' => null, 'hasta' => null, 'activo' => 1, 'productos' => [10]]);
    }

    private function total(array $lineas): float
    {
        return round(array_sum(array_map(fn ($l) => $l['importe'] ?? $l['cantidad'] * $l['precio'], $lineas)), 2);
    }

    public function test_tres_por_dos_cobra_dos_y_la_cuarta_va_normal(): void
    {
        [$lineas, $aplicadas] = PromocionesVenta::aplicar(collect([$this->promo(['nombre' => '3X2 CAIPIRIÑA'])]),
            [['IdProducto' => 10, 'cantidad' => 4, 'precio' => 15.0]]);

        $this->assertSame(45.0, $this->total($lineas), '3 caipiriñas por el precio de 2 (S/ 30) + 1 normal (S/ 15).');
        $this->assertSame(15.0, $lineas[0]['descuento']);
        $this->assertSame(15.0, $lineas[0]['precio_lista']);
        $this->assertSame([['id' => 1, 'nombre' => '3X2 CAIPIRIÑA', 'veces' => 1, 'ahorro' => 15.0]], $aplicadas);
    }

    public function test_dos_por_uno_mezclado_regala_las_mas_baratas(): void
    {
        $promo = $this->promo(['lleva' => 2, 'paga' => 1, 'productos' => [10, 11]]);
        [$lineas, $aplicadas] = PromocionesVenta::aplicar(collect([$promo]), [
            ['IdProducto' => 10, 'cantidad' => 2, 'precio' => 12.0],   // cerveza grande
            ['IdProducto' => 11, 'cantidad' => 2, 'precio' => 8.0],    // cerveza chica
        ]);

        // Grupos de la más cara a la más barata: (12, 12) y (8, 8): van gratis una de 12 y una de 8
        $this->assertSame(20.0, $this->total($lineas));
        $this->assertSame(2, $aplicadas[0]['veces']);
        $this->assertSame(20.0, $aplicadas[0]['ahorro']);
    }

    public function test_tres_por_cuarenta_reparte_el_descuento_y_cuadra_al_centimo(): void
    {
        $promo = $this->promo(['tipo' => 'POR_PRECIO', 'precio' => 40.0, 'paga' => null, 'productos' => [10, 11]]);
        [$lineas] = PromocionesVenta::aplicar(collect([$promo]), [
            ['IdProducto' => 10, 'cantidad' => 2, 'precio' => 15.0],
            ['IdProducto' => 11, 'cantidad' => 1, 'precio' => 14.99],
        ]);

        $this->assertSame(40.0, $this->total($lineas));
    }

    public function test_no_entran_las_cortesias_ni_las_presentaciones_ni_lo_que_no_es_de_la_promo(): void
    {
        [$lineas, $aplicadas] = PromocionesVenta::aplicar(collect([$this->promo([])]), [
            ['IdProducto' => 10, 'cantidad' => 2, 'precio' => 15.0],
            ['IdProducto' => 10, 'cantidad' => 1, 'precio' => 15.0, 'cortesia' => true],
            ['IdProducto' => 10, 'cantidad' => 1, 'precio' => 45.0, 'factor' => 3.0, 'umecod' => 'JAR'],
            ['IdProducto' => 99, 'cantidad' => 3, 'precio' => 15.0],
        ]);

        $this->assertSame([], $aplicadas, 'Solo hay 2 caipiriñas que entran: no completan el 3x2.');
        $this->assertArrayNotHasKey('descuento', $lineas[0]);
    }

    public function test_cada_unidad_entra_en_una_sola_promo(): void
    {
        $dosPorUno = $this->promo(['id' => 1, 'lleva' => 2, 'paga' => 1]);
        $tresPorDos = $this->promo(['id' => 2]);
        [, $aplicadas] = PromocionesVenta::aplicar(collect([$dosPorUno, $tresPorDos]), [['IdProducto' => 10, 'cantidad' => 2, 'precio' => 15.0]]);

        $this->assertCount(1, $aplicadas);
        $this->assertSame(1, $aplicadas[0]['id']);
    }

    public function test_vale_en_sus_dias_y_horas_y_la_madrugada_es_del_dia_anterior(): void
    {
        // Viernes y sábado de 10 pm a 2 am
        $p = $this->promo(['dias' => '56', 'hora_inicio' => '22:00:00', 'hora_fin' => '02:00:00']);

        $this->assertTrue(PromocionesVenta::vale($p, Carbon::parse('2026-10-30 23:00')), 'Viernes 11 pm.');
        $this->assertTrue(PromocionesVenta::vale($p, Carbon::parse('2026-11-01 01:30')), 'Domingo 1:30 am es la noche del sábado.');
        $this->assertFalse(PromocionesVenta::vale($p, Carbon::parse('2026-11-02 01:30')), 'Lunes 1:30 am es la noche del domingo: no tiene promo.');
        $this->assertFalse(PromocionesVenta::vale($p, Carbon::parse('2026-10-30 21:00')), 'Viernes 9 pm: aún no empieza.');
        $this->assertFalse(PromocionesVenta::vale($p, Carbon::parse('2026-10-31 03:00')), 'Sábado 3 am: ya terminó.');
    }

    public function test_respeta_las_fechas_y_si_esta_pausada(): void
    {
        $this->assertFalse(PromocionesVenta::vale($this->promo(['hasta' => '2026-10-31']), Carbon::parse('2026-11-01 12:00')));
        $this->assertFalse(PromocionesVenta::vale($this->promo(['desde' => '2026-11-05']), Carbon::parse('2026-11-01 12:00')));
        $this->assertFalse(PromocionesVenta::vale($this->promo(['activo' => 0]), Carbon::parse('2026-11-01 12:00')));
        $this->assertTrue(PromocionesVenta::vale($this->promo([]), Carbon::parse('2026-11-01 12:00')));
    }
}
