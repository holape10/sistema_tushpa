<?php

namespace Tests\Feature;

use App\Models\Central\Superadmin;
use App\Support\EdicionTabla;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Edición directa en la tabla del explorador: celdas, ➖ borrar y ➕ agregar, todo junto o nada, con control de choques */
class BaseDatosTablaTest extends TestCase
{
    private const TABLA = 'prueba_tabla_bd';

    private const SIN_LLAVE = 'prueba_tabla_bd_sin_llave';

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
        $central = DB::connection('central');
        $central->statement('DROP TABLE IF EXISTS '.self::TABLA);
        $central->statement('DROP TABLE IF EXISTS '.self::SIN_LLAVE);
        $central->statement('CREATE TABLE '.self::TABLA.' (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL, codigo VARCHAR(10) NULL,
            precio DECIMAL(10,2) NULL, clave_api VARCHAR(50) NULL, nota TEXT NULL, UNIQUE KEY codigo_unico (codigo))');
        $central->statement('CREATE TABLE '.self::SIN_LLAVE.' (nombre VARCHAR(50))');
        $central->table(self::TABLA)->insert([
            ['id' => 1, 'nombre' => 'ARROZ', 'codigo' => '007', 'precio' => 10, 'clave_api' => 'secreto', 'nota' => null],
            ['id' => 2, 'nombre' => 'AZUCAR', 'codigo' => '008', 'precio' => 20, 'clave_api' => null, 'nota' => str_repeat('x', 500)],
            ['id' => 3, 'nombre' => 'SAL', 'codigo' => '009', 'precio' => 30, 'clave_api' => null, 'nota' => null],
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('DROP TABLE IF EXISTS '.self::TABLA);
        DB::connection('central')->statement('DROP TABLE IF EXISTS '.self::SIN_LLAVE);
        DB::connection('central')->table('panel_bd_cambios')->where('superadmin_id', 999999)->delete();
        parent::tearDown();
    }

    /** El dueño con la segunda clave abierta (el explorador corre en su propia conexión, fuera de la transacción) */
    private function conClaveAbierta(): void
    {
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
        config(['database.default' => 'central']);
        $this->actingAs((new Superadmin)->forceFill(['id' => 999999, 'nombre' => 'DUEÑO', 'email' => 'dueno@prueba.test', 'activo' => true, 'es_dueno' => true,
            'password' => Hash::make('clave-panel'), 'clave_bd' => Hash::make('segunda-clave')]), 'superadmin');
        $this->post(route('admin.basedatos.desbloquear'), ['base' => $this->base, 'clave_bd' => 'segunda-clave'])->assertSessionHasNoErrors();
    }

    private function aplicar(array $cambios, string $tabla = self::TABLA): TestResponse
    {
        return $this->postJson(route('admin.basedatos.tabla'), ['base' => $this->base, 'tabla' => $tabla] + $cambios);
    }

    private function fila(int $id): ?object
    {
        return DB::connection('central')->table(self::TABLA)->where('id', $id)->first();
    }

    public function test_edita_borra_y_agrega_todo_junto_y_lo_registra_con_el_valor_de_antes(): void
    {
        $this->conClaveAbierta();

        $this->aplicar([
            'editar' => [['llave' => ['id' => 1], 'antes' => ['nombre' => 'ARROZ', 'precio' => '10.00'], 'valores' => ['nombre' => 'ARROZ EXTRA', 'precio' => '10']]],
            'borrar' => [['llave' => ['id' => 3]]],
            'agregar' => [['id' => '', 'nombre' => 'FIDEO', 'codigo' => '010', 'precio' => '5.5']],
        ])->assertOk()->assertJson(['ok' => true, 'editadas' => 1, 'borradas' => 1, 'agregadas' => 1]);

        $this->assertSame(['ARROZ EXTRA', '10.00'], [$this->fila(1)->nombre, $this->fila(1)->precio], 'El precio 10 es el mismo 10.00: no se toca');
        $this->assertNull($this->fila(3));
        $this->assertSame('FIDEO', DB::connection('central')->table(self::TABLA)->where('codigo', '010')->value('nombre'));
        $registro = DB::connection('central')->table('panel_bd_cambios')->where('superadmin_id', 999999)->value('sql');
        $this->assertStringContainsString('"antes": "ARROZ"', $registro);
        $this->assertStringContainsString('"nombre": "SAL"', $registro, 'La fila borrada queda completa en el registro');
        $this->assertStringNotContainsString('secreto', $registro);
    }

    public function test_si_un_cambio_falla_no_se_aplica_ninguno(): void
    {
        $this->conClaveAbierta();

        $this->aplicar([
            'editar' => [['llave' => ['id' => 1], 'antes' => ['nombre' => 'ARROZ'], 'valores' => ['nombre' => 'NO DEBE QUEDAR']]],
            'agregar' => [['nombre' => 'REPETIDO', 'codigo' => '009']],
        ])->assertStatus(422)->assertJsonFragment(['ok' => false])->assertJsonPath('mensaje', 'Ya existe otra fila con ese valor (la columna no acepta repetidos). No se cambió nada.');

        $this->assertSame('ARROZ', $this->fila(1)->nombre);
        $this->assertSame(3, DB::connection('central')->table(self::TABLA)->count());
    }

    public function test_no_pisa_un_dato_que_otro_cambio_mientras_lo_editabas(): void
    {
        $this->conClaveAbierta();
        DB::connection('central')->table(self::TABLA)->where('id', 1)->update(['precio' => 12]);   // otro lo cambió

        $this->aplicar(['editar' => [['llave' => ['id' => 1], 'antes' => ['precio' => '10.00'], 'valores' => ['precio' => '15']]]])
            ->assertStatus(422)->assertJsonFragment(['ok' => false]);

        $this->assertSame('12.00', $this->fila(1)->precio);
    }

    public function test_un_codigo_de_texto_no_se_confunde_con_un_numero(): void
    {
        $this->conClaveAbierta();

        $this->aplicar(['editar' => [['llave' => ['id' => 1], 'antes' => ['codigo' => '007'], 'valores' => ['codigo' => '7']]]])->assertOk()->assertJson(['editadas' => 1]);

        $this->assertSame('7', $this->fila(1)->codigo);
    }

    public function test_no_edita_claves_ni_la_llave_ni_textos_cortados_ni_tablas_sin_llave(): void
    {
        $this->conClaveAbierta();
        $editar = fn (array $valores, array $antes, int $id = 1) => $this->aplicar(['editar' => [['llave' => ['id' => $id], 'antes' => $antes, 'valores' => $valores]]]);

        $editar(['clave_api' => 'otra'], ['clave_api' => '•••• (oculto)'])->assertStatus(422)->assertJsonFragment(['ok' => false]);
        $editar(['id' => 50], ['id' => 1])->assertStatus(422);
        $editar(['nota' => 'corta'], ['nota' => str_repeat('x', 400).'…'], 2)->assertStatus(422)->assertJsonPath('mensaje', 'El texto de nota es muy largo para editarlo aquí: usa una orden SQL.');
        $this->aplicar(['borrar' => [['llave' => ['nombre' => 'X']]]], self::SIN_LLAVE)->assertStatus(422)->assertJsonFragment(['ok' => false]);
        $this->aplicar(['borrar' => [['llave' => ['id' => 99]]]])->assertStatus(422);

        $this->assertSame(['secreto', 1, 3], [$this->fila(1)->clave_api, (int) $this->fila(1)->id, DB::connection('central')->table(self::TABLA)->count()]);
    }

    public function test_tiene_tope_de_cambios_y_pide_la_segunda_clave(): void
    {
        $this->conClaveAbierta();
        $this->aplicar(['agregar' => array_fill(0, EdicionTabla::MAX_CAMBIOS + 1, ['nombre' => 'X'])])->assertStatus(422);
        $this->assertSame(3, DB::connection('central')->table(self::TABLA)->count());

        $this->post(route('admin.basedatos.bloquear'), ['base' => $this->base]);
        $this->aplicar(['borrar' => [['llave' => ['id' => 1]]]])->assertStatus(423);
        $this->assertNotNull($this->fila(1));
    }

    public function test_la_tabla_se_ve_editable_solo_con_la_segunda_clave_abierta(): void
    {
        $this->conClaveAbierta();
        $this->get(route('admin.basedatos.index', ['base' => $this->base, 'tabla' => self::TABLA]))->assertOk()->assertSee('Agregar fila')->assertDontSee('secreto');

        $this->post(route('admin.basedatos.bloquear'), ['base' => $this->base]);
        $this->get(route('admin.basedatos.index', ['base' => $this->base, 'tabla' => self::TABLA]))->assertOk()->assertDontSee('Agregar fila')
            ->assertSee('abre tu segunda clave');
    }
}
