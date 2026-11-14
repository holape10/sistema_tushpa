<?php

namespace Tests\Feature;

use App\Models\Central\Superadmin;
use App\Support\Servidor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Página «Servidor» del panel: memoria, disco, MySQL y el tamaño de la base de cada empresa (solo el dueño) */
class ServidorPanelTest extends TestCase
{
    private function conCentral(): void
    {
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
    }

    public function test_lee_la_memoria_disponible_del_vps(): void
    {
        $meminfo = "MemTotal:        8126464 kB\nMemFree:          512000 kB\nMemAvailable:    1468006 kB\nSwapTotal:       2097148 kB\nSwapFree:        1616124 kB\n";

        $m = Servidor::memoria($meminfo);

        $this->assertSame([8126464 * 1024, 1468006 * 1024, (8126464 - 1468006) * 1024, 2097148 * 1024, (2097148 - 1616124) * 1024],
            [$m['total'], $m['disponible'], $m['usada'], $m['swap_total'], $m['swap_usada']]);
        $this->assertNull(Servidor::memoria('sin datos'), 'Fuera de Linux no se inventa nada.');
        $this->assertSame(['512 B', '1.50 KB', '5.03 GB', '150 MB'], [Servidor::tamano(512), Servidor::tamano(1536), Servidor::tamano(5400000000), Servidor::tamano(157286400)]);
    }

    public function test_las_bases_van_de_la_mas_pesada_a_la_mas_liviana_sin_las_del_motor(): void
    {
        $this->conCentral();

        $bases = Servidor::bases();

        $this->assertNotEmpty($bases);
        $this->assertContains(DB::connection('central')->getDatabaseName(), $bases->pluck('base'));
        $this->assertSame($bases->pluck('total')->sortDesc()->values()->all(), $bases->pluck('total')->all());
        $this->assertEmpty(array_intersect(['mysql', 'sys', 'information_schema', 'performance_schema'], $bases->pluck('base')->all()));
        // Las tablas de una base del motor o con un nombre raro no se consultan
        $this->assertTrue(Servidor::tablasDe('mysql')->isEmpty());
        $this->assertTrue(Servidor::tablasDe("x' OR 1=1 --")->isEmpty());
        $this->assertNotEmpty(Servidor::tablasDe(DB::connection('central')->getDatabaseName()));
    }

    public function test_solo_el_dueno_ve_la_pagina(): void
    {
        $this->conCentral();
        DB::connection('central')->beginTransaction();
        try {
            $dueno = Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]);
            $vendedor = Superadmin::create(['nombre' => 'VENDEDOR', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => false]);
            $base = DB::connection('central')->getDatabaseName();
            URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);

            $this->actingAs($dueno, 'superadmin')->get(route('admin.servidor.index', ['base' => $base]))->assertOk()
                ->assertSee('Bases de datos')->assertSee($base)->assertSee('superadmins');
            $this->actingAs($vendedor, 'superadmin')->get(route('admin.servidor.index'))->assertForbidden();
        } finally {
            DB::connection('central')->rollBack();
        }
    }
}
