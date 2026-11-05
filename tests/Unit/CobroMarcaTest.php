<?php

namespace Tests\Unit;

use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Models\Central\Superadmin;
use App\Support\Tenancy\DatosCobro;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Una empresa de una marca le paga a su revendedor: sus cuentas o las de otro usuario de su marca; si aún no puso
 * ninguna, solo su contacto. Nunca las cuentas ni el pago en línea del dueño del sistema.
 */
class CobroMarcaTest extends TestCase
{
    private Marca $wilcat;

    private Superadmin $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('central');
        $central = Schema::connection('central');
        $central->create('clientes', function (Blueprint $t) {
            $t->id();
            $t->string('ruc');
            $t->unsignedBigInteger('creado_por')->nullable();
            $t->timestamps();
        });
        $central->create('superadmins', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->boolean('activo')->default(true);
            $t->boolean('es_dueno')->default(false);
            $t->string('cobro_titular')->nullable();
            $t->string('cobro_whatsapp')->nullable();
            $t->string('cobro_telefono')->nullable();
            $t->timestamps();
        });
        $central->create('superadmin_cobros', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('superadmin_id');
            $t->string('tipo', 10);
            $t->string('banco')->nullable();
            $t->string('titular')->nullable();
            $t->string('cuenta')->nullable();
            $t->string('cci')->nullable();
            $t->string('qr')->nullable();
            $t->unsignedInteger('orden')->default(0);
            $t->timestamps();
        });
        (include database_path('migrations/central/2027_01_24_000001_marcas.php'))->up();

        $this->wilcat = Marca::create(['nombre' => 'WILCAT SYSTEMS', 'razon_social' => 'WILCAT SYSTEMS E.I.R.L.', 'dominio' => 'wilcatsystems.pe',
            'prefijo_bd' => 'bd_ws_', 'telefono' => '965 123 456']);
        $this->dueno = Superadmin::create(['nombre' => 'DUEÑO', 'es_dueno' => true]);
        $this->dueno->cobros()->create(['tipo' => 'CUENTA', 'banco' => 'BCP', 'titular' => 'HOLAPE', 'cuenta' => '111-DEL-DUENO']);
    }

    public function test_sin_cuentas_de_su_marca_solo_sale_el_contacto_del_revendedor(): void
    {
        $vendedor = Superadmin::create(['nombre' => 'WILCAT', 'marca_id' => $this->wilcat->id]);
        $cliente = Cliente::create(['ruc' => '20222222222', 'creado_por' => $vendedor->id, 'marca_id' => $this->wilcat->id]);

        $pago = DatosCobro::para($cliente);
        $this->assertSame([], $pago['cuentas'], 'Nunca las cuentas del dueño.');
        $this->assertSame('WILCAT SYSTEMS E.I.R.L.', $pago['titular']);
        $this->assertSame('51965123456', $pago['whatsapp']);
        $this->assertFalse(DatosCobro::cobraElDueno($cliente), 'Tampoco el pago en línea del dueño.');
    }

    public function test_con_cuentas_de_su_marca_paga_al_revendedor(): void
    {
        $vendedor = Superadmin::create(['nombre' => 'WILCAT VENTAS', 'marca_id' => $this->wilcat->id]);
        $otro = Superadmin::create(['nombre' => 'WILCAT CAJA', 'marca_id' => $this->wilcat->id]);
        $otro->cobros()->create(['tipo' => 'CUENTA', 'banco' => 'BBVA', 'titular' => 'WILCAT', 'cuenta' => '222-DE-WILCAT']);
        $cliente = Cliente::create(['ruc' => '20222222222', 'creado_por' => $vendedor->id, 'marca_id' => $this->wilcat->id]);

        $this->assertSame(['222-DE-WILCAT'], array_column(DatosCobro::para($cliente)['cuentas'], 'cuenta'));
    }

    public function test_las_empresas_de_tushpa_siguen_pagando_al_dueno(): void
    {
        $cliente = Cliente::create(['ruc' => '20111111111', 'creado_por' => $this->dueno->id]);

        $this->assertSame(['111-DEL-DUENO'], array_column(DatosCobro::para($cliente)['cuentas'], 'cuenta'));
        $this->assertTrue(DatosCobro::cobraElDueno($cliente));
    }
}
