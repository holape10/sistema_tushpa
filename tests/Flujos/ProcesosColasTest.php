<?php

namespace Tests\Flujos;

use App\Jobs\GenerarRespaldo;
use App\Jobs\ZipComprobantes;
use App\Support\Colas;
use App\Support\Procesos;
use App\Support\VentaDirecta;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/** Procesos en segundo plano: con trabajador van a la cola; sin trabajador se hacen en el momento, como siempre */
class ProcesosColasTest extends FlujoTestCase
{
    protected function tearDown(): void
    {
        Colas::$fingir = null;
        foreach (glob(Procesos::carpeta('bd_pruebas_tushpa').'/*') ?: [] as $archivo) {
            @unlink($archivo);
        }
        parent::tearDown();
    }

    /** Dos notas de venta para el ZIP */
    private function comprobantes(): array
    {
        $plato = $this->producto('JUANE', 15, 10);

        return [VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '13'), 'PV'),
            VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '13'), 'PV')];
    }

    public function test_sin_trabajador_el_zip_se_hace_en_el_momento_y_se_descarga(): void
    {
        Colas::$fingir = false;
        $ids = $this->comprobantes();

        $r = $this->get(route('ventas.masiva.zip', ['ids' => implode(',', $ids)]));

        $id = (int) DB::table('procesos')->max('id');
        $r->assertRedirect(route('procesos.show', $id));
        $p = DB::table('procesos')->where('id', $id)->first();
        $this->assertSame(['LISTO', '2 comprobante(s) en PDF.'], [$p->estado, $p->mensaje]);
        $this->get(route('procesos.show', $id))->assertOk()->assertSee('Descargar');
        $this->get(route('procesos.estado', $id))->assertJson(['estado' => 'LISTO', 'descargable' => true]);
        $this->get(route('procesos.descargar', $id))->assertOk()->assertDownload();
    }

    public function test_con_trabajador_va_a_la_cola_y_el_trabajador_lo_termina(): void
    {
        Colas::$fingir = true;
        Bus::fake();
        $ids = $this->comprobantes();

        $this->get(route('ventas.masiva.zip', ['ids' => implode(',', $ids)]))->assertRedirect();

        $id = (int) DB::table('procesos')->max('id');
        $this->assertSame('EN COLA', DB::table('procesos')->where('id', $id)->value('estado'), 'La página no esperó.');
        $tarea = null;
        Bus::assertDispatched(ZipComprobantes::class, function (ZipComprobantes $t) use (&$tarea, $id) {
            $tarea = $t;

            return $t->procesoId === $id && $t->base === 'bd_pruebas_tushpa' && $t->connection === 'database';
        });

        // Lo que hace el trabajador
        $tarea->handle();
        $this->assertSame('LISTO', DB::table('procesos')->where('id', $id)->value('estado'));
        $tarea->handle();
        $this->assertSame(1, DB::table('procesos')->where('estado', 'LISTO')->count(), 'Si llega dos veces, no se repite.');
    }

    public function test_la_copia_de_seguridad_sale_por_un_proceso(): void
    {
        Colas::$fingir = false;

        $this->post(route('respaldo.descargar'))->assertRedirect();

        $p = DB::table('procesos')->orderByDesc('id')->first();
        $this->assertSame('LISTO', $p->estado, (string) $p->mensaje);
        $this->assertStringEndsWith('.sql.gz', $p->nombre_archivo);
        $this->assertTrue(DB::table('bitacora')->where('accion', 'respaldo.descargar')->exists());
    }

    public function test_solo_quien_lo_pidio_o_el_administrador_ve_el_proceso(): void
    {
        Colas::$fingir = false;
        $this->get(route('ventas.masiva.zip', ['ids' => implode(',', $this->comprobantes())]));
        $id = (int) DB::table('procesos')->max('id');
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        $this->actingAs($cajero)->get(route('procesos.show', $id))->assertNotFound();
        $this->actingAs($cajero)->get(route('procesos.descargar', $id))->assertNotFound();
    }

    public function test_un_archivo_fuera_de_su_carpeta_no_se_descarga(): void
    {
        $id = DB::table('procesos')->insertGetId(['tipo' => 'x', 'descripcion' => 'x', 'estado' => 'LISTO', 'archivo' => base_path('.env'),
            'IdUsuario' => $this->usuario->IdUsuario, 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('procesos.descargar', $id))->assertNotFound();
    }

    public function test_si_el_trabajador_se_detuvo_la_tarea_en_espera_se_vuelve_a_pedir(): void
    {
        Colas::$fingir = false;
        $id = DB::table('procesos')->insertGetId(['tipo' => 'respaldo', 'descripcion' => 'Copia', 'estado' => 'EN COLA',
            'IdUsuario' => $this->usuario->IdUsuario, 'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)]);

        $this->get(route('procesos.estado', $id))->assertJson(['estado' => 'ERROR']);

        // Si el trabajador la toma después, ya no la hace
        (new GenerarRespaldo('bd_pruebas_tushpa', $id, (int) $this->usuario->IdUsuario))->handle();
        $this->assertSame('ERROR', DB::table('procesos')->where('id', $id)->value('estado'));
    }

    public function test_una_tarea_hecha_en_el_momento_no_cuenta_como_trabajador_vivo(): void
    {
        $archivo = Colas::archivoLatido();
        $antes = @filemtime($archivo);
        @unlink($archivo);
        try {
            Colas::$fingir = false;
            $this->get(route('ventas.masiva.zip', ['ids' => implode(',', $this->comprobantes())]));
            Colas::$fingir = null;

            $this->assertFalse(Colas::vivo(), 'Si contara, las siguientes tareas irían a una cola que nadie atiende.');
        } finally {
            $antes ? touch($archivo, $antes) : @unlink($archivo);
        }
    }

    public function test_el_latido_no_pausa_al_trabajador(): void
    {
        // Si alguien que escucha Looping devuelve false, el trabajador deja de tomar tareas
        $this->assertNotFalse(event(new Looping('database', 'default'), [], true));
    }

    public function test_el_latido_dice_si_el_trabajador_esta_vivo(): void
    {
        $archivo = Colas::archivoLatido();
        $antes = @filemtime($archivo);
        try {
            @unlink($archivo);
            $this->assertFalse(Colas::vivo(), 'Sin trabajador.');
            Colas::latido();
            $this->assertTrue(Colas::vivo());
            touch($archivo, time() - 120);
            $this->assertFalse(Colas::vivo(), 'Sin latido hace 2 minutos: se dio por detenido.');
        } finally {
            $antes ? touch($archivo, $antes) : @unlink($archivo);
        }
    }
}
