<?php

namespace Tests\Unit;

use App\Http\Middleware\IdentificarEmpresa;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El dominio principal (tushpa.app) solo abre las páginas de su lista; lo demás lleva a "Ingresar". Cada ruta nueva de
 * routes/sitio.php tiene que estar en esa lista (si no, la página "no abre" y manda a Ingresar).
 */
class PaginasDelSitioTest extends TestCase
{
    public function test_todas_las_paginas_del_sitio_abren_en_el_dominio_principal(): void
    {
        config(['tenancy.dominio' => 'tushpa.app']);
        Route::domain('tushpa.app')->name('sitio.')->group(base_path('routes/sitio.php'));
        Route::getRoutes()->refreshNameLookups();

        $permitida = (new \ReflectionMethod(IdentificarEmpresa::class, 'esPaginaDelSitio'))->getClosure(app(IdentificarEmpresa::class));
        $ejemplos = ['cliente' => '7', 'pago' => '3', 'id' => '5', 'slug' => 'mi-negocio', 'ruc' => '20123456789', 'sub' => 'demo', 'ciudad' => 'iquitos', 'token' => str_repeat('a', 40)];
        $revisadas = 0;
        foreach (Route::getRoutes() as $ruta) {
            if (! str_starts_with((string) $ruta->getName(), 'sitio.') || str_contains($ruta->uri(), 'sistema-de-facturacion-electronica-')) {
                continue;
            }
            $camino = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $ejemplos[$m[1]] ?? 'x', $ruta->uri());
            $this->assertTrue($permitida($camino === '/' ? '/' : ltrim($camino, '/')), "La página /{$camino} ({$ruta->getName()}) no está en la lista de IdentificarEmpresa.");
            $revisadas++;
        }
        $this->assertGreaterThan(10, $revisadas);
    }
}
