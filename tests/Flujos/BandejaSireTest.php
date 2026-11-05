<?php

namespace Tests\Flujos;

use App\Models\User;
use App\Support\Estudio;
use App\Support\Sunat\Sire;
use Illuminate\Http\Client\Request as Peticion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Bandeja SIRE del estudio: las propuestas de todas sus empresas, sin cambiar la empresa en la que se trabaja */
class BandejaSireTest extends FlujoTestCase
{
    private const LISTA = '20553510661';     // SUNAT la entrega al toque

    private const LENTA = '20100070970';     // SUNAT la sigue preparando

    private const SIN_CLAVES = '20600695771';

    private string $estudio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->estudio = (string) $this->usuario->IdEmpresa;
        Estudio::activar($this->estudio);
        foreach ([self::LISTA => 'LLAMA PE SA', self::LENTA => 'SUPERMERCADOS PERUANOS SA', self::SIN_CLAVES => 'NUBEFACT SA'] as $ruc => $nombre) {
            $this->post(route('estudio.guardar'), ['ruc' => $ruc, 'razon_social' => $nombre])->assertSessionHasNoErrors();
        }
        foreach ([self::LISTA, self::LENTA] as $ruc) {
            $this->post(route('estudio.configurar', $ruc), ['client_id' => 'ID-'.$ruc, 'client_secret' => 'SECRETO', 'sire_usuario' => 'SIRE01', 'sire_clave' => 'CLAVE']);
        }
        $this->sunat();
    }

    /** SUNAT de mentira: el token dice de qué empresa es; la LENTA nunca termina */
    private function sunat(): void
    {
        $zip = tempnam(sys_get_temp_dir(), 'zip');
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::OVERWRITE);
        $z->addFromString('propuesta.txt', "RUC|Tipo CP/Doc.|Serie del CDP|Nro CP o Doc. Nro Inicial (Rango)|Total CP\n1|01|F001|1|10.00\n1|01|F001|2|20.00");
        $z->close();
        $contenido = file_get_contents($zip);
        Http::fake(function (Peticion $p) use ($contenido) {
            if (str_contains($p->url(), 'oauth2/token')) {
                return Http::response(['access_token' => 'tok-'.substr((string) $p['username'], 0, 11), 'expires_in' => 3600]);
            }
            $ruc = substr($p->header('Authorization')[0] ?? '', 11, 11);
            if (str_contains($p->url(), 'exporta')) {
                return Http::response(['numTicket' => 'T'.$ruc]);
            }
            if (str_contains($p->url(), 'consultaestadotickets')) {
                return Http::response(['registros' => [['numTicket' => 'T'.$ruc, 'codEstadoProceso' => $ruc === self::LENTA ? '02' : '06',
                    'desEstadoProceso' => $ruc === self::LENTA ? 'En proceso' : 'Terminado', 'perTributario' => '202605', 'codProceso' => '10',
                    'archivoReporte' => [['nomArchivoReporte' => 'p.zip', 'codTipoAchivoReporte' => '01']]]]]);
            }
            if (str_contains($p->url(), 'archivoreporte')) {
                return Http::response($contenido);
            }

            return Http::response([], 404);
        });
    }

    private function bandeja(string $ruc, string $libro, string $accion)
    {
        return $this->postJson(route("sire.bandeja.{$accion}", [$ruc, $libro, 'periodo' => '202605']))->assertOk();
    }

    public function test_trae_las_propuestas_de_cada_empresa_sin_cambiar_la_empresa_activa(): void
    {
        $this->get(route('sire.bandeja', ['periodo' => '202605']))->assertOk()
            ->assertSee('LLAMA PE SA')->assertSee('SUPERMERCADOS PERUANOS SA')->assertSee('NUBEFACT SA');

        // La que SUNAT entrega: se pide, se revisa y se descarga
        $this->bandeja(self::LISTA, 'compras', 'pedir')->assertJson(['estado' => 'ok', 'ticket' => 'T'.self::LISTA]);
        $this->bandeja(self::LISTA, 'compras', 'revisar')->assertJson(['estado' => 'listo', 'filas' => 2]);
        $lista = DB::table('sire_solicitudes')->where('IdEmpresa', self::LISTA)->where('libro', Sire::COMPRAS)->first();
        $this->assertNotNull($lista->archivo);
        Storage::assertExists($lista->archivo);

        // La lenta queda en proceso: la pantalla la deja y pasa a la siguiente
        $this->bandeja(self::LENTA, 'compras', 'pedir')->assertJson(['estado' => 'ok']);
        $this->bandeja(self::LENTA, 'compras', 'revisar')->assertJson(['estado' => 'proceso']);
        $this->assertNull(DB::table('sire_solicitudes')->where('IdEmpresa', self::LENTA)->value('archivo'));

        // Sin credenciales: avisa y no llama a SUNAT por ella
        $this->bandeja(self::SIN_CLAVES, 'ventas', 'pedir')->assertJson(['estado' => 'error'])->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'credenciales'));

        // Cada empresa con su propia credencial; el usuario sigue en su estudio
        Http::assertSent(fn (Peticion $p) => str_contains($p->url(), 'ID-'.self::LISTA.'/oauth2') && str_starts_with((string) $p['username'], self::LISTA));
        Http::assertNotSent(fn (Peticion $p) => str_contains($p->url(), self::SIN_CLAVES));
        $this->assertSame($this->estudio, (string) $this->usuario->fresh()->IdEmpresa);

        // La bandeja recuerda cómo quedó cada una al volver a entrar
        $this->get(route('sire.bandeja', ['periodo' => '202605']))->assertOk()->assertViewHas('filas', function (array $filas) {
            $estado = fn ($ruc) => collect($filas)->first(fn ($f) => $f['ruc'] === $ruc && $f['libro'] === 'compras')['estado'];

            return $estado(self::LISTA) === 'listo' && $estado(self::LENTA) === 'proceso' && $estado(self::SIN_CLAVES) === 'nada';
        });
    }

    public function test_un_pedido_viejo_que_nunca_termino_se_vuelve_a_pedir(): void
    {
        $this->bandeja(self::LENTA, 'ventas', 'pedir');
        DB::table('sire_solicitudes')->where('IdEmpresa', self::LENTA)->update(['created_at' => now()->subHours(3)]);
        $this->get(route('sire.bandeja', ['periodo' => '202605']))->assertOk()
            ->assertViewHas('filas', fn (array $filas) => collect($filas)->first(fn ($f) => $f['ruc'] === self::LENTA && $f['libro'] === 'ventas')['estado'] === 'nada');
    }

    public function test_solo_las_empresas_que_el_usuario_puede_ver(): void
    {
        $contador = $this->usuario->replicate();
        $contador->email = 'contador'.uniqid().'@prueba.test';
        $contador->save();
        DB::table('role_user')->insert(['role_id' => 15, 'user_IdUsuario' => $contador->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        DB::table('modulos_usuario')->insert(['user_IdUsuario' => $contador->IdUsuario, 'mod_id' => DB::table('modulos')->where('mod_url', '/sire/compras')->value('mod_id')]);
        Estudio::guardarAccesos(self::LISTA, [$contador->IdUsuario]);

        $this->actingAs($contador)->get(route('sire.bandeja'))->assertOk()->assertSee('LLAMA PE SA')->assertDontSee('SUPERMERCADOS PERUANOS SA');
        $this->actingAs($contador)->postJson(route('sire.bandeja.pedir', [self::LENTA, 'compras', 'periodo' => '202605']))->assertForbidden();
        $this->actingAs($contador)->postJson(route('sire.bandeja.pedir', [self::LISTA, 'compras', 'periodo' => '202605']))->assertOk();

        // Sin el SIRE en su menú no entra
        $cajero = $this->usuario->replicate();
        $cajero->email = 'cajero'.uniqid().'@prueba.test';
        $cajero->save();
        DB::table('role_user')->insert(['role_id' => 4, 'user_IdUsuario' => $cajero->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        $this->actingAs($cajero)->get(route('sire.bandeja'))->assertForbidden();
    }

    public function test_abrir_lleva_a_la_empresa_y_solo_a_pantallas_del_sistema(): void
    {
        $this->post(route('estudio.cambiar'), ['ruc' => self::LISTA, 'ir' => '/sire/dbf?periodo=202605'])->assertRedirect(url('/sire/dbf?periodo=202605'));
        $this->assertSame(self::LISTA, (string) $this->usuario->fresh()->IdEmpresa);
        foreach (['https://otra-pagina.com', '//otra-pagina.com', '/logout', '/sire/../usuarios'] as $ir) {
            $this->post(route('estudio.cambiar'), ['ruc' => self::LISTA, 'ir' => $ir])->assertSessionHasErrors('ir');
        }
    }

    public function test_la_pantalla_sire_de_una_empresa_sigue_igual(): void
    {
        $this->post(route('estudio.cambiar'), ['ruc' => self::LISTA]);
        $this->actingAs(User::find($this->usuario->IdUsuario));
        $this->postJson(route('sire.solicitar', 'ventas'), ['periodo' => '202605'])->assertOk()->assertJson(['estado' => 'ok']);
        $id = DB::table('sire_solicitudes')->where('IdEmpresa', self::LISTA)->value('id');
        $this->getJson(route('sire.estado', ['ventas', $id]))->assertOk()->assertJson(['estado' => 'listo']);
    }
}
