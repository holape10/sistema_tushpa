<?php

namespace Tests\Unit;

use App\Support\Rentabilidad;
use PHPUnit\Framework\TestCase;

class IngenieriaMenuTest extends TestCase
{
    private function producto(string $nombre, float $cantidad, float $precio, float $costoUnidad): object
    {
        return (object) ['nombre' => $nombre, 'cantidad' => $cantidad, 'ventas' => $cantidad * $precio, 'costo' => $cantidad * $costoUnidad,
            'ganancia' => $cantidad * ($precio - $costoUnidad)];
    }

    public function test_clasifica_por_ventas_y_ganancia_por_unidad(): void
    {
        $r = Rentabilidad::clasificar(collect([
            // Ganancia promedio ponderada: (50·20 + 60·3 + 5·200 + 5·2) / 120 = S/ 18.25 por unidad
            $this->producto('Pisco sour', 50, 25, 5),    // se vende mucho y gana S/ 20 c/u
            $this->producto('Agua', 60, 5, 2),           // se vende mucho y gana S/ 3 c/u
            $this->producto('Whisky', 5, 300, 100),      // se vende poco y gana S/ 200 c/u
            $this->producto('Ensalada', 5, 10, 8),       // se vende poco y gana S/ 2 c/u
        ]))['platos']->keyBy('nombre');

        $this->assertSame('ESTRELLA', $r['Pisco sour']->clase);
        $this->assertSame('CABALLO', $r['Agua']->clase);
        $this->assertSame('ENIGMA', $r['Whisky']->clase);
        $this->assertSame('PERRO', $r['Ensalada']->clase);
    }

    public function test_umbrales_de_popularidad_y_ganancia(): void
    {
        $r = Rentabilidad::clasificar(collect([$this->producto('A', 30, 10, 4), $this->producto('B', 10, 10, 8)]));

        // Con 2 productos es popular quien vende al menos el 70% de 1/2 = 35% de las unidades
        $this->assertSame(35.0, $r['popularidad_min']);
        // Ganancia promedio ponderada: (30·6 + 10·2) / 40 = 5
        $this->assertSame(5.0, $r['ganancia_promedio']);
        // Food cost: S/ 4 de costo en un precio de S/ 10
        $this->assertSame(40.0, $r['platos']->firstWhere('nombre', 'A')->food_cost);
    }

    public function test_sin_ventas_no_falla(): void
    {
        $r = Rentabilidad::clasificar(collect());

        $this->assertTrue($r['platos']->isEmpty());
        $this->assertSame(0.0, $r['ganancia_promedio']);
    }
}
