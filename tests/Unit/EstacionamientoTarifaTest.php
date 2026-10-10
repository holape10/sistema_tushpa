<?php

namespace Tests\Unit;

use App\Support\Estacionamiento;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EstacionamientoTarifaTest extends TestCase
{
    private function tarifa(array $cambios = []): object
    {
        return (object) ($cambios + ['modo' => 'FRACCION', 'precio' => 4, 'fraccion_min' => 15, 'tolerancia_min' => 10, 'tope_dia' => 0]);
    }

    private function cobro(object $tarifa, int $minutos): float
    {
        $entrada = Carbon::parse('2026-10-10 08:00:00');

        return Estacionamiento::calcular($tarifa, $entrada, $entrada->copy()->addMinutes($minutos))['importe'];
    }

    /** @return array<string, array{array<string, mixed>, int, float}> */
    public static function casos(): array
    {
        return [
            'dentro de la tolerancia no paga' => [[], 10, 0.0],
            'pasada la tolerancia paga la primera hora' => [[], 11, 4.0],
            'la primera hora completa' => [[], 60, 4.0],
            'un minuto más es una fracción' => [[], 61, 5.0],
            'veinte minutos más son dos fracciones' => [[], 80, 6.0],
            'por hora completa cobra cada hora empezada' => [['modo' => 'HORA', 'precio' => 3], 61, 6.0],
            'el tope del día limita el cobro' => [['tope_dia' => 20], 600, 20.0],
            'pasadas 24 horas suma el tope y lo que sigue' => [['tope_dia' => 20], 1500, 24.0],
            'tras 24 horas la tolerancia se respeta' => [['tope_dia' => 20], 1445, 20.0],
            'precio fijo por día empezado' => [['modo' => 'FIJO', 'precio' => 10], 180, 10.0],
            'precio fijo, segundo día' => [['modo' => 'FIJO', 'precio' => 10], 1500, 20.0],
        ];
    }

    /** @param  array<string, mixed>  $cambios */
    #[DataProvider('casos')]
    public function test_calcula_lo_que_debe_segun_la_tarifa(array $cambios, int $minutos, float $esperado): void
    {
        $this->assertSame($esperado, $this->cobro($this->tarifa($cambios), $minutos));
    }

    public function test_redondea_los_segundos_al_minuto_siguiente(): void
    {
        $entrada = Carbon::parse('2026-10-10 08:00:00');
        $r = Estacionamiento::calcular($this->tarifa(), $entrada, $entrada->copy()->addSeconds(60 * 60 + 5));

        $this->assertSame(61, $r['minutos']);
        $this->assertSame(5.0, $r['importe']);
    }

    public function test_muestra_la_duracion_legible(): void
    {
        $this->assertSame('0 min', Estacionamiento::duracion(0));
        $this->assertSame('2 h 15 min', Estacionamiento::duracion(135));
        $this->assertSame('1 d 1 h', Estacionamiento::duracion(1500));
    }

    public function test_normaliza_la_placa(): void
    {
        $this->assertSame('ABC123', Estacionamiento::normalizarPlaca(' abc-123 '));
    }
}
