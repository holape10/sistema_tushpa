<?php

namespace Tests\Unit;

use App\Support\Antiguo\CargadorSql;
use PHPUnit\Framework\TestCase;

/** El respaldo del sistema antiguo comprimido en .zip (lo que se puede hacer en Windows): se carga el .sql de adentro */
class CargadorSqlZipTest extends TestCase
{
    private function zip(array $archivos): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zt').'.zip';
        $z = new \ZipArchive;
        $z->open($ruta, \ZipArchive::CREATE);
        foreach ($archivos as $nombre => $contenido) {
            $z->addFromString($nombre, $contenido);
        }
        $z->close();

        return $ruta;
    }

    public function test_saca_el_sql_mas_grande_del_zip(): void
    {
        $sql = "CREATE TABLE productos (IdProducto INT);\nINSERT INTO productos VALUES (1),(2);\n";
        $zip = $this->zip(['leeme.txt' => 'hola', 'respaldo/WILCAT.sql' => $sql, 'viejo.sql' => 'SELECT 1;']);

        $sacado = CargadorSql::sacarDelZip($zip);

        $this->assertStringEndsWith('.sql', $sacado);
        $this->assertSame($sql, file_get_contents($sacado));
        @unlink($sacado);
        @unlink($zip);
    }

    public function test_un_zip_sin_sql_avisa(): void
    {
        $zip = $this->zip(['fotos/a.jpg' => 'x']);

        $this->expectExceptionMessage('no trae ningún archivo .sql');
        try {
            CargadorSql::sacarDelZip($zip);
        } finally {
            @unlink($zip);
        }
    }
}
