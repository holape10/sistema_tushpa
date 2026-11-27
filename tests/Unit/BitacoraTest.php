<?php

namespace Tests\Unit;

use App\Support\Bitacora;
use PHPUnit\Framework\TestCase;

/** Qué se considera un cambio para la bitácora */
class BitacoraTest extends TestCase
{
    public function test_los_numeros_y_los_vacios_iguales_no_son_cambio(): void
    {
        $this->assertSame([], Bitacora::diferencias(['precio' => '3.00', 'nota' => null, 'activo' => true], ['precio' => 3, 'nota' => '', 'activo' => 1]));
    }

    public function test_las_claves_se_anotan_sin_su_valor(): void
    {
        $cambios = Bitacora::diferencias(['password' => 'hash-viejo', 'claveSunat' => 'a', 'client_secret' => 'x'], ['password' => 'hash-nuevo', 'claveSunat' => 'b', 'client_secret' => 'y']);

        foreach (['password', 'claveSunat', 'client_secret'] as $campo) {
            $this->assertSame(['etiqueta' => $campo, 'antes' => null, 'despues' => '(cambió)'], $cambios[$campo]);
        }
    }

    public function test_las_listas_dicen_que_se_agrego_y_que_se_quito(): void
    {
        $cambios = Bitacora::diferencias(['modulos' => ['Ventas', 'Caja']], ['modulos' => ['Caja', 'Compras']], ['modulos' => 'Opciones del menú']);

        $this->assertSame(['Compras'], $cambios['modulos']['agrego']);
        $this->assertSame(['Ventas'], $cambios['modulos']['quito']);
        $this->assertSame([], Bitacora::diferencias(['modulos' => ['A', 'B']], ['modulos' => ['B', 'A']]), 'El orden no importa.');
    }

    public function test_con_etiquetas_solo_se_comparan_esos_campos_salvo_que_se_pidan_todos(): void
    {
        $antes = ['nombre' => 'A', 'FnuEmpresa' => 10, 'updated_at' => '2026-01-01'];
        $despues = ['nombre' => 'B', 'FnuEmpresa' => 5, 'updated_at' => '2026-02-02'];

        $this->assertSame(['nombre'], array_keys(Bitacora::diferencias($antes, $despues, ['nombre' => 'Nombre'])));
        $todos = Bitacora::diferencias($antes, $despues, ['nombre' => 'Nombre'], true);
        $this->assertSame(['nombre', 'FnuEmpresa'], array_keys($todos), 'updated_at nunca cuenta.');
        $this->assertSame('Nombre', $todos['nombre']['etiqueta']);
    }

    public function test_los_codigos_y_los_interruptores_se_leen_en_palabras(): void
    {
        $cambios = Bitacora::diferencias(['produccion' => '0', 'permite_descuentos' => 1, 'tip_igv_pred' => '10', 'FnuEmpresa' => 0],
            ['produccion' => '1', 'permite_descuentos' => 0, 'tip_igv_pred' => '20', 'FnuEmpresa' => 1],
            ['produccion' => 'Envío a SUNAT en producción', 'permite_descuentos' => 'Permite descuentos', 'tip_igv_pred' => 'IGV por defecto', 'FnuEmpresa' => 'Último N° de factura']);

        $this->assertSame('Envío a SUNAT en producción: Pruebas (BETA) → Producción (real) · Permite descuentos: Sí → No · IGV por defecto: Gravado → Exonerado'
            .' · Último N° de factura: 0 → 1', Bitacora::resumen($cambios, 4), 'Un correlativo sigue siendo un número');
        $this->assertSame('Producción (real)', Bitacora::mostrar('1', 'produccion'));
        $this->assertSame('1', Bitacora::mostrar('1'), 'Sin el campo, el valor sale tal cual');
    }

    public function test_el_resumen_se_lee_en_una_linea(): void
    {
        $cambios = Bitacora::diferencias(['propun' => 20, 'proest' => 'Activo', 'password' => 'x'], ['propun' => 22, 'proest' => '', 'password' => 'y'],
            ['propun' => 'Precio', 'proest' => 'Estado', 'password' => 'Contraseña']);

        $this->assertSame('Precio: 20 → 22 · Estado: Activo → (vacío) · Contraseña cambió', Bitacora::resumen($cambios));
    }
}
