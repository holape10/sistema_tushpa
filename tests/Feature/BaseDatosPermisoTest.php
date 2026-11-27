<?php

namespace Tests\Feature;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Asistente\ConsultaSegura;
use App\Support\ExploradorBd;
use App\Support\Servidor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Permiso de Base de datos para alguien que no es el dueño (el experto de una marca): solo las bases de su marca */
class BaseDatosPermisoTest extends TestCase
{
    private const MARCA = 77;

    private string $central;

    private string $deLaMarca;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('central')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin la base central: '.$e->getMessage());
        }
        if (! DB::connection('central')->getSchemaBuilder()->hasColumn('superadmins', 'puede_base_datos')) {
            $this->markTestSkipped('Falta la migración central del permiso de Base de datos.');
        }
        $this->central = DB::connection('central')->getDatabaseName();
        $this->deLaMarca = (string) env('DB_PRUEBAS_DATABASE', 'bd_pruebas_tushpa');
        if (! Servidor::bases()->contains('base', $this->deLaMarca)) {
            $this->markTestSkipped('Falta la base de pruebas '.$this->deLaMarca);
        }
        DB::connection('central')->beginTransaction();
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
        // Una empresa de la marca (su base es la de pruebas)
        Cliente::create(['ruc' => '20777777771', 'base_datos' => $this->deLaMarca, 'razon_social' => 'EMPRESA DE LA MARCA', 'estado' => 'ACTIVO', 'marca_id' => self::MARCA]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }
        parent::tearDown();
    }

    /** Usuario del panel de TUSHPA ($marca = de la marca: ese entra por el panel de su marca) */
    private function usuario(bool $baseDatos, bool $dueno = false, bool $marca = false): Superadmin
    {
        return Superadmin::create(['nombre' => 'EXPERTO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true,
            'es_dueno' => $dueno, 'marca_id' => $marca ? self::MARCA : null, 'puede_base_datos' => $baseDatos, 'clave_bd' => Hash::make('segunda-clave')]);
    }

    public function test_el_experto_de_la_marca_solo_ve_las_bases_de_su_marca(): void
    {
        $experto = $this->usuario(true, marca: true);

        $this->assertSame([$this->deLaMarca], ExploradorBd::bases($experto)->pluck('base')->all());
        $this->assertNull(ExploradorBd::base($this->central, $experto), 'Nunca la base central');
        $this->assertSame($this->central, ExploradorBd::base($this->central, $this->usuario(false, true)), 'El dueño ve todas');
        $this->assertSame([], ExploradorBd::bases($this->usuario(false, marca: true))->all(), 'Sin el permiso, ninguna');
    }

    public function test_sin_marca_ve_solo_las_empresas_que_creo_y_nunca_la_central(): void
    {
        $experto = $this->usuario(true);
        Cliente::where('ruc', '20777777771')->update(['marca_id' => null, 'creado_por' => $experto->id]);

        $this->actingAs($experto, 'superadmin');
        $this->get(route('admin.basedatos.index'))->assertOk()->assertSee('EMPRESA DE LA MARCA');
        $this->post(route('admin.basedatos.consulta'), ['base' => $this->central, 'sql' => 'SELECT * FROM superadmins'])->assertNotFound();
        $this->post(route('admin.basedatos.desbloquear'), ['base' => $this->central, 'clave_bd' => 'segunda-clave']);
        $this->postJson(route('admin.basedatos.tabla'), ['base' => $this->central, 'tabla' => 'superadmins', 'borrar' => [['llave' => ['id' => 1]]]])->assertNotFound();
    }

    public function test_sin_el_permiso_no_entra(): void
    {
        $this->actingAs($this->usuario(false), 'superadmin');

        $this->get(route('admin.basedatos.index'))->assertForbidden();
    }

    public function test_una_consulta_no_puede_mirar_otra_base_ni_sin_punto(): void
    {
        config(['database.default' => 'central']);
        $motivo = fn (string $sql) => Tenancy::en($this->central, fn () => ConsultaSegura::problema($sql));

        $this->assertStringContainsString('Solo se consulta', $motivo('SHOW TABLES FROM '.$this->deLaMarca));
        $this->assertStringContainsString('Solo se consulta', $motivo('SHOW COLUMNS FROM productos IN `'.$this->deLaMarca.'`'));
        $this->assertStringContainsString('Solo se consulta', $motivo('SELECT * FROM '.$this->deLaMarca.'.productos'));
        $this->assertNull($motivo('SELECT nombre FROM superadmins WHERE nombre = \'from '.$this->deLaMarca.'\''), 'Dentro de un texto no cuenta');
    }

    public function test_solo_el_dueno_da_o_quita_el_permiso(): void
    {
        $experto = $this->usuario(false);
        $this->actingAs($this->usuario(false), 'superadmin');
        $this->post(route('admin.usuarios.base_datos', $experto->id))->assertForbidden();

        $this->actingAs($this->usuario(false, true), 'superadmin');
        $this->post(route('admin.usuarios.base_datos', $experto->id))->assertRedirect();
        $this->assertTrue((bool) $experto->fresh()->puede_base_datos);
    }
}
