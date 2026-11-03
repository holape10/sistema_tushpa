<?php

namespace Tests\Unit;

use App\Support\ConsultaPeru;
use App\Support\LimiteIntentos;
use App\Support\MonitorErrores;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Lo que alguien de afuera puede mandar a propósito: RUC inventados, números gigantes, pedidos al mismo tiempo */
class EntradasSegurasTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function rucs(): array
    {
        return [
            'empresa real' => ['20615502830', true],
            'otra empresa real' => ['20114052311', true],
            'persona con negocio' => ['10609005110', true],
            'último dígito cambiado' => ['20615502831', false],
            'prefijo que no existe' => ['30615502830', false],
            'corto' => ['2061550283', false],
        ];
    }

    #[DataProvider('rucs')]
    public function test_solo_se_consulta_un_ruc_que_puede_existir(string $ruc, bool $valido): void
    {
        $this->assertSame($valido, ConsultaPeru::rucValido($ruc));
    }

    private function trabado(int $codigo): QueryException
    {
        $pdo = new \PDOException('trabado');
        $pdo->errorInfo = ['40001', $codigo, 'trabado'];

        return new QueryException('mysql', 'insert ignore into cache', [], $pdo);
    }

    public function test_un_deadlock_en_el_contador_de_intentos_deja_pasar_el_pedido(): void
    {
        $store = new class extends ArrayStore
        {
            public ?QueryException $error = null;

            public function add($key, $value, $seconds)
            {
                throw $this->error;
            }

            public function get($key)
            {
                throw $this->error;
            }
        };
        $store->error = $this->trabado(1213);
        $limite = new LimiteIntentos(new Repository($store));

        $this->assertSame(0, $limite->hit('carta-solicitud'));
        $this->assertFalse($limite->tooManyAttempts('carta-solicitud', 60));

        // Otro error de la base sí se muestra
        $store->error = $this->trabado(1146);
        $this->expectException(QueryException::class);
        $limite->hit('carta-solicitud');
    }

    public function test_un_numero_gigante_no_se_avisa_como_falla_del_sistema(): void
    {
        $this->assertTrue(MonitorErrores::ignorar($this->trabado(1264)));
        $this->assertFalse(MonitorErrores::ignorar($this->trabado(1146)));
    }
}
