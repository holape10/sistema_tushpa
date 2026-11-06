<?php

namespace App\Jobs;

use App\Support\Bitacora;
use App\Support\Procesos;
use App\Support\Tenancy\Respaldos;
use Illuminate\Support\Facades\DB;

/** Copia de seguridad completa de la empresa (.sql.gz) para descargar */
class GenerarRespaldo extends ProcesoEmpresa
{
    protected function ejecutar(object $proceso): array
    {
        $ruta = Respaldos::crear($this->base, 'descarga', Procesos::carpeta($this->base));
        $ruc = (string) DB::table('empresa')->value('IdEmpresa');
        Bitacora::registrar('respaldo.descargar', 'Generó la copia de seguridad completa de la empresa', null,
            ['alerta' => true, 'datos' => ['tamano_mb' => round(filesize($ruta) / 1048576, 2)]]);

        return ['archivo' => $ruta, 'nombre' => 'copia-tushpa-'.$ruc.'-'.now()->format('Y-m-d-Hi').'.sql.gz',
            'mensaje' => 'Copia lista ('.number_format(filesize($ruta) / 1048576, 1).' MB).'];
    }
}
