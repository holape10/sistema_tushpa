<?php

namespace Tests\Feature;

use App\Support\Tenancy\Inventario;
use App\Support\Tenancy\Provisionador;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Crear empresas copiando una base plantilla (ya migrada) en vez de correr todas las migraciones desde cero */
class PlantillaEmpresasTest extends TestCase
{
    private const ORIGEN = 'bd_prueba_plantilla_origen';

    private const DESTINO = 'bd_prueba_plantilla_destino';

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        foreach ([self::ORIGEN, self::DESTINO] as $b) {
            DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$b}`");
        }
    }

    public function test_copia_la_estructura_los_indices_y_las_filas(): void
    {
        $c = DB::connection('central');
        $c->statement('CREATE DATABASE `'.self::ORIGEN.'`');
        $c->statement('CREATE DATABASE `'.self::DESTINO.'`');
        $c->statement('CREATE TABLE `'.self::ORIGEN.'`.modulos (mod_id INT AUTO_INCREMENT PRIMARY KEY, mod_url VARCHAR(80), UNIQUE KEY url_unica (mod_url))');
        $c->statement('CREATE TABLE `'.self::ORIGEN.'`.migrations (id INT AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255))');
        $c->table(self::ORIGEN.'.modulos')->insert([['mod_url' => '/inicio'], ['mod_url' => '/ventas']]);
        $c->table(self::ORIGEN.'.migrations')->insert(['migration' => '2026_01_01_crear']);

        $this->assertSame(2, Provisionador::copiarTablas(self::ORIGEN, self::DESTINO));

        $this->assertSame(['/inicio', '/ventas'], $c->table(self::DESTINO.'.modulos')->orderBy('mod_id')->pluck('mod_url')->all());
        $this->assertSame(1, $c->table(self::DESTINO.'.migrations')->count(), 'Las migraciones ya corridas van con la copia');
        $this->assertNotEmpty($c->select('SHOW INDEX FROM `'.self::DESTINO."`.modulos WHERE Key_name = 'url_unica'"), 'Los índices también');
        // La numeración sigue donde iba
        $c->table(self::DESTINO.'.modulos')->insert(['mod_url' => '/compras']);
        $this->assertSame(3, (int) $c->table(self::DESTINO.'.modulos')->max('mod_id'));
    }

    public function test_la_plantilla_nunca_sale_como_base_huerfana_para_borrar(): void
    {
        $this->assertContains(Provisionador::plantilla(), Inventario::protegidas());
    }

    public function test_no_acepta_nombres_de_base_raros(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Provisionador::copiarTablas('bd_x`; DROP DATABASE y; --', self::DESTINO);
    }
}
