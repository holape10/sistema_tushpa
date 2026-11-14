<?php

namespace Tests\Feature;

use App\Models\Central\Superadmin;
use App\Support\ProcesosLinux;
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

    /** Un /proc de Linux de mentira: [pid, nombre, cmdline, MB de RAM, hilos, uid, ticks de CPU] */
    private function procFalso(array $procesos): string
    {
        $dir = sys_get_temp_dir().'/proc_'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/stat", "cpu  1000 0 500 8500 0 0 0 0 0 0\ncpu0 250 0 125 2125 0 0 0 0 0 0\n");
        foreach ($procesos as [$pid, $nombre, $cmdline, $mb, $hilos, $uid, $ticks]) {
            mkdir("{$dir}/{$pid}");
            file_put_contents("{$dir}/{$pid}/stat", "{$pid} ({$nombre}) S 1 1 1 0 -1 4194560 100 0 0 0 {$ticks} 0 0 0 20 0 {$hilos} 0");
            file_put_contents("{$dir}/{$pid}/status", "Name:\t{$nombre}\nUid:\t{$uid}\t{$uid}\t{$uid}\t{$uid}\nVmRSS:\t".($mb * 1024)." kB\nThreads:\t{$hilos}\n");
            file_put_contents("{$dir}/{$pid}/cmdline", str_replace(' ', "\0", $cmdline));
        }

        return $dir;
    }

    public function test_los_procesos_se_agrupan_y_se_explican(): void
    {
        $proc = $this->procFalso([
            [1, 'systemd', '/usr/lib/systemd/systemd', 16, 1, 0, 10],
            [200, 'mysqld', '/usr/sbin/mysqld', 5300, 40, 27, 50],
            [300, 'php-fpm', 'php-fpm: pool www', 70, 1, 48, 5],
            [301, 'php-fpm', 'php-fpm: pool www', 72, 1, 48, 5],
            [400, 'php', 'php artisan queue:work --sleep=3', 90, 1, 48, 0],
            [500, 'minero-raro', '/tmp/minero-raro', 900, 8, 48, 0],
        ]);

        $lista = ProcesosLinux::agrupados($proc, 0)->keyBy('clave');

        $this->assertSame('mysqld', $lista->keys()->first(), 'Primero el que más memoria usa.');
        $this->assertSame(['MySQL (base de datos)', 'necesario', 40, 5300 * 1048576], [$lista['mysqld']->nombre, $lista['mysqld']->nivel, $lista['mysqld']->hilos, $lista['mysqld']->memoria]);
        $this->assertSame([2, 142 * 1048576, [300, 301]], [$lista['php-fpm']->procesos, $lista['php-fpm']->memoria, $lista['php-fpm']->pids], 'Los PHP de las páginas van juntos.');
        $this->assertSame('Trabajador de colas (TUSHPA)', $lista['php:colas']->nombre);
        $this->assertSame('revisar', $lista['minero-raro']->nivel, 'Algo desconocido que gasta mucho se marca para revisar.');
        $this->assertNull(ProcesosLinux::agrupados(sys_get_temp_dir().'/no_existe_'.uniqid()), 'Sin /proc (Windows) no se inventa nada.');
    }

    public function test_consultas_en_curso_y_trabajos_en_segundo_plano(): void
    {
        $this->conCentral();

        $consultas = Servidor::consultasActivas();
        $trabajos = Servidor::trabajos();

        $this->assertIsInt($consultas['dormidas']);
        $this->assertTrue($consultas['activas']->every(fn ($p) => $p->comando !== 'Sleep'), 'Solo las que están trabajando.');
        $this->assertSame(['vivo', 'latido', 'pendientes', 'fallidas', 'total_pendientes', 'total_fallidas'], array_keys($trabajos));
    }

    /** El dueño sin guardar en la base (el explorador corre en su propia conexión, fuera de la transacción de la prueba) */
    private function dueno(bool $esDueno = true): Superadmin
    {
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
        config(['database.default' => 'central']);

        return (new Superadmin)->forceFill(['id' => 999999, 'nombre' => 'DUEÑO', 'email' => 'dueno@prueba.test', 'activo' => true, 'es_dueno' => $esDueno]);
    }

    public function test_el_explorador_solo_lee(): void
    {
        $this->conCentral();
        $base = DB::connection('central')->getDatabaseName();
        $dueno = $this->dueno();

        $this->actingAs($dueno, 'superadmin')->get(route('admin.basedatos.index', ['base' => $base, 'tabla' => 'migrations']))->assertOk()
            ->assertSee('solo lectura')->assertSee('migrations')->assertSee('Columnas');
        $this->post(route('admin.basedatos.consulta'), ['base' => $base, 'sql' => 'SELECT 41 + 1 AS respuesta'])->assertOk()->assertSee('respuesta')->assertSee('42');
        // Nada que escriba, ni otra base, ni las del motor
        $this->post(route('admin.basedatos.consulta'), ['base' => $base, 'sql' => 'DELETE FROM migrations'])->assertOk()->assertSee('Solo se permiten consultas de lectura');
        $this->post(route('admin.basedatos.consulta'), ['base' => $base, 'sql' => 'SELECT 1; DROP TABLE migrations'])->assertOk()->assertSee('Solo una sentencia');
        $this->post(route('admin.basedatos.consulta'), ['base' => $base, 'sql' => 'SELECT * FROM mysql.user'])->assertOk()->assertDontSee('authentication_string');
        $this->post(route('admin.basedatos.consulta'), ['base' => 'mysql', 'sql' => 'SELECT 1'])->assertNotFound();
        $this->get(route('admin.basedatos.index', ['base' => $base, 'tabla' => 'no_existe']))->assertOk()->assertSee('No existe la tabla');
        $this->assertTrue(DB::connection('central')->getSchemaBuilder()->hasTable('migrations'));
        // El Excel de la consulta
        $this->post(route('admin.basedatos.consulta'), ['base' => $base, 'sql' => 'SELECT migration FROM migrations', 'excel' => 1])->assertOk()->assertDownload();

        $this->actingAs($this->dueno(false), 'superadmin')->get(route('admin.basedatos.index'))->assertForbidden();
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
