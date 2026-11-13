<?php

namespace App\Jobs;

use App\Support\Antiguo\CargadorSql;
use App\Support\Procesos;

/**
 * Paso 1 del importador en segundo plano: cargar el respaldo subido (.sql, .zip o .sql.gz) en su base temporal. Uno
 * grande (100 MB o más) tarda minutos y la página se cortaba; así basta con subirlo y esperar en la pantalla de avance.
 */
class CargarRespaldoAntiguo extends ProcesoEmpresa
{
    public function __construct(string $base, int $procesoId, int $usuarioId, public string $archivo, public string $bd)
    {
        parent::__construct($base, $procesoId, $usuarioId);
    }

    protected function ejecutar(object $proceso): array
    {
        try {
            $res = CargadorSql::cargar($this->archivo, $this->bd);
        } finally {
            @unlink($this->archivo);
        }
        if (! in_array('productos', $res['tablas'], true)) {
            CargadorSql::admin()->statement("DROP DATABASE IF EXISTS `{$this->bd}`");
            throw new \RuntimeException('El archivo no trae la tabla productos del sistema antiguo.'.($res['errores'] ? ' Primer error: '.mb_substr($res['errores'][0], 0, 200) : ''));
        }
        $archivo = Procesos::carpeta($this->base).'/carga-'.$this->procesoId.'.json';
        file_put_contents($archivo, json_encode(['bd' => $this->bd, 'ejecutadas' => $res['ejecutadas'], 'tablas' => count($res['tablas']),
            'errores' => array_slice($res['errores'], 0, 50)], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

        return ['archivo' => $archivo, 'nombre' => 'carga.json', 'mensaje' => "Respaldo cargado: {$res['ejecutadas']} sentencias de ".count($res['tablas']).' tablas.'];
    }
}
