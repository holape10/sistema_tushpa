<?php

namespace Tests\Feature;

use App\Models\Central\Superadmin;
use App\Support\Asistente\Asistente;
use App\Support\EdicionBd;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Explorador de bases del panel: modo edición con segunda clave (probar → ejecutar) y asistente IA que sugiere consultas */
class BaseDatosEdicionTest extends TestCase
{
    private const TABLA = 'prueba_edicion_bd';

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        if (! DB::connection('central')->getSchemaBuilder()->hasColumn('superadmins', 'clave_bd')) {
            $this->markTestSkipped('Falta la migración central del modo edición.');
        }
        $this->base = DB::connection('central')->getDatabaseName();
        DB::connection('central')->statement('DROP TABLE IF EXISTS '.self::TABLA);
        DB::connection('central')->statement('CREATE TABLE '.self::TABLA.' (id INT PRIMARY KEY, precio DECIMAL(10,2))');
        DB::connection('central')->table(self::TABLA)->insert([['id' => 1, 'precio' => 10], ['id' => 2, 'precio' => 20], ['id' => 3, 'precio' => 30]]);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->statement('DROP TABLE IF EXISTS '.self::TABLA);
        DB::connection('central')->table('panel_bd_cambios')->where('superadmin_id', 999999)->delete();
        parent::tearDown();
    }

    /** El dueño sin guardar en la base (el explorador corre en su propia conexión, fuera de la transacción de la prueba) */
    private function dueno(?string $claveBd = 'segunda-clave'): Superadmin
    {
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
        config(['database.default' => 'central']);

        return (new Superadmin)->forceFill(['id' => 999999, 'nombre' => 'DUEÑO', 'email' => 'dueno@prueba.test', 'activo' => true, 'es_dueno' => true,
            'password' => Hash::make('clave-panel'), 'clave_bd' => $claveBd ? Hash::make($claveBd) : null]);
    }

    private function precio(int $id): float
    {
        return (float) DB::connection('central')->table(self::TABLA)->where('id', $id)->value('precio');
    }

    public function test_solo_deja_ordenes_de_datos_precisas_en_la_base_elegida(): void
    {
        config(['database.default' => 'central']);
        $motivo = fn (string $sql) => Tenancy::en($this->base, fn () => EdicionBd::problema($sql));

        $this->assertNull($motivo('UPDATE '.self::TABLA.' SET precio = 5 WHERE id = 1'));
        $this->assertNull($motivo('UPDATE '.self::TABLA." SET precio = 5 WHERE id = 1 AND 'drop table' <> ''"));
        $this->assertStringContainsString('sin WHERE', $motivo('UPDATE '.self::TABLA.' SET precio = 5'));
        $this->assertStringContainsString('abarca todas', $motivo('DELETE FROM '.self::TABLA.' WHERE 1=1'));
        $this->assertStringContainsString('una orden a la vez', $motivo('DELETE FROM '.self::TABLA.' WHERE id = 1; DROP TABLE x'));
        $this->assertStringContainsString('solo se permite', $motivo('SELECT 1'));
        $this->assertStringContainsString('No se permite', $motivo('INSERT INTO '.self::TABLA.' SELECT SLEEP(10), 1'));
        $this->assertStringContainsString('internas', $motivo('DELETE FROM mysql.user WHERE user = "x"'));
        $otra = collect(DB::connection('central')->select('SHOW DATABASES'))->map(fn ($f) => array_values((array) $f)[0])->first(fn ($b) => $b !== $this->base && ! in_array($b, ['mysql', 'sys', 'information_schema', 'performance_schema'], true));
        if ($otra && $otra !== $this->base) {
            $this->assertStringContainsString('Solo se cambia la base elegida', $motivo("UPDATE {$otra}.productos SET precio_venta = 1 WHERE IdProducto = 1"));
        }
    }

    public function test_cambiar_datos_pide_la_segunda_clave_prueba_y_luego_ejecuta(): void
    {
        $this->actingAs($this->dueno(), 'superadmin');
        $sql = 'UPDATE '.self::TABLA.' SET precio = 99 WHERE id IN (1, 2)';

        // Sin abrir el modo edición no se prueba nada
        $this->post(route('admin.basedatos.probar'), ['base' => $this->base, 'sql_cambio' => $sql])->assertOk()->assertSee('El modo edición está cerrado');
        $this->post(route('admin.basedatos.desbloquear'), ['base' => $this->base, 'clave_bd' => 'clave-panel'])->assertSessionHasErrors('clave_bd');
        $this->post(route('admin.basedatos.desbloquear'), ['base' => $this->base, 'clave_bd' => 'segunda-clave'])
            ->assertRedirect(route('admin.basedatos.index', ['base' => $this->base]));

        // Probar no cambia nada y dice cuántas filas tocaría
        $prueba = $this->post(route('admin.basedatos.probar'), ['base' => $this->base, 'sql_cambio' => $sql])->assertOk()->assertSee('Ejecutar de verdad');
        $this->assertSame(2, $prueba->viewData('prueba')['filas']);
        $this->assertSame(10.0, $this->precio(1));

        // Solo se ejecuta exactamente lo que se probó
        $firma = $prueba->viewData('prueba')['firma'];
        $this->post(route('admin.basedatos.ejecutar'), ['base' => $this->base, 'sql_cambio' => 'UPDATE '.self::TABLA.' SET precio = 99 WHERE id > 0', 'firma' => $firma])
            ->assertOk()->assertSee('La orden cambió después de probarla');
        $this->assertSame(30.0, $this->precio(3));

        $this->post(route('admin.basedatos.ejecutar'), ['base' => $this->base, 'sql_cambio' => $sql, 'firma' => $firma])->assertOk()->assertSee('2 fila(s) cambiada(s)');
        $this->assertSame([99.0, 99.0, 30.0], [$this->precio(1), $this->precio(2), $this->precio(3)]);
        $this->assertDatabaseHas('panel_bd_cambios', ['superadmin_id' => 999999, 'base' => $this->base, 'filas' => 2, 'sql' => $sql], 'central');

        // Cerrado, ya no ejecuta aunque la firma sea buena
        $this->post(route('admin.basedatos.bloquear'), ['base' => $this->base])->assertRedirect();
        $this->post(route('admin.basedatos.ejecutar'), ['base' => $this->base, 'sql_cambio' => $sql, 'firma' => $firma])->assertOk()->assertSee('El modo edición se cerró');
    }

    public function test_el_modo_edicion_se_cierra_solo_a_los_minutos(): void
    {
        $this->actingAs($this->dueno(), 'superadmin');
        $this->post(route('admin.basedatos.desbloquear'), ['base' => $this->base, 'clave_bd' => 'segunda-clave']);
        $this->travel(EdicionBd::MINUTOS + 1)->minutes();

        $this->post(route('admin.basedatos.probar'), ['base' => $this->base, 'sql_cambio' => 'DELETE FROM '.self::TABLA.' WHERE id = 1'])->assertSee('El modo edición está cerrado');
    }

    public function test_la_segunda_clave_se_crea_con_la_clave_del_panel_y_debe_ser_distinta(): void
    {
        $this->actingAs($this->dueno(null), 'superadmin');
        $this->get(route('admin.basedatos.index', ['base' => $this->base]))->assertOk()->assertSee('Guardar segunda clave');

        $this->post(route('admin.basedatos.clave'), ['base' => $this->base, 'actual' => 'otra', 'nueva' => 'nueva-clave-1', 'nueva_confirmation' => 'nueva-clave-1'])
            ->assertSessionHasErrors('actual');
        $this->post(route('admin.basedatos.clave'), ['base' => $this->base, 'actual' => 'clave-panel', 'nueva' => 'clave-panel', 'nueva_confirmation' => 'clave-panel'])
            ->assertSessionHasErrors('nueva');
        $this->post(route('admin.basedatos.clave'), ['base' => $this->base, 'actual' => 'clave-panel', 'nueva' => 'nueva-clave-1', 'nueva_confirmation' => 'nueva-clave-1'])
            ->assertSessionHasNoErrors();
    }

    public function test_el_asistente_sugiere_la_consulta_para_copiar(): void
    {
        Asistente::$falso = fn () => (object) ['stopReason' => 'end_turn',
            'content' => [(object) ['type' => 'text', 'text' => "Primero revisa:\n```sql\nSELECT * FROM ".self::TABLA." WHERE id = 1\n```\nLuego:\n```sql\nUPDATE ".self::TABLA." SET precio = 5 WHERE id = 1\n```"]],
            'usage' => (object) ['inputTokens' => 100, 'outputTokens' => 50, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        $this->actingAs($this->dueno(), 'superadmin');

        $this->post(route('admin.basedatos.ia'), ['base' => $this->base, 'pregunta' => '¿cómo le pongo precio 5 al 1?'])->assertOk()
            ->assertSee('Usar en la consulta')->assertSee('Pasar al modo edición')->assertSee('Primero revisa:');
        $this->assertSame(10.0, $this->precio(1));
        DB::connection('central')->table('asistente_uso')->where('usuario', 'Base de datos: dueno@prueba.test')->delete();
    }
}
