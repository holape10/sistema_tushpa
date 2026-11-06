<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\Impresion\ComprobantePdf;
use App\Support\Procesos;

/** ZIP con el PDF de cada comprobante (venta masiva) */
class ZipComprobantes extends ProcesoEmpresa
{
    /** @param  array<int, int>  $ids */
    public function __construct(string $base, int $procesoId, int $usuarioId, public array $ids)
    {
        parent::__construct($base, $procesoId, $usuarioId);
    }

    protected function ejecutar(object $proceso): array
    {
        $sucursal = (int) User::find($this->usuarioId)?->id_empresa_negocio;
        $ruta = Procesos::carpeta($this->base).DIRECTORY_SEPARATOR.'comprobantes-'.$this->procesoId.'.zip';
        $zip = new \ZipArchive;
        if ($zip->open($ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo preparar el ZIP.');
        }
        $n = 0;
        foreach ($this->ids as $id) {
            [$nombre, $contenido] = ComprobantePdf::generar((int) $id, $sucursal);
            $zip->addFromString($nombre, $contenido);
            $n++;
        }
        $zip->close();

        return ['archivo' => $ruta, 'nombre' => 'comprobantes_'.now()->format('Ymd_His').'.zip', 'mensaje' => "{$n} comprobante(s) en PDF."];
    }
}
