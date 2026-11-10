<?php

// app/Models/EmpresaNegocio.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaNegocio extends Model
{
    protected $table = 'empresa_negocios';

    protected $primaryKey = 'id_empresa_negocio';

    protected $guarded = [];

    /**
     * Cómo se nombra la sucursal al elegirla (arriba y en el login): su nombre y, si la tiene, su descripción
     * ("RUIZ SOUZA · SERIE 1"). La descripción que el sistema pone sola ("Sucursal - RUC") no se muestra.
     */
    public static function etiqueta(object $sucursal): string
    {
        $nombre = trim((string) ($sucursal->nombre_comercial ?? '')) ?: 'SUCURSAL '.$sucursal->id_empresa_negocio;
        $descripcion = trim((string) ($sucursal->tipo_negocio ?? ''));
        $automatica = (bool) preg_match('/^(oficina principal|principal|sucursal)\s*-\s*\d{11}$/i', $descripcion);

        return mb_strtoupper($descripcion === '' || $automatica || mb_strtoupper($descripcion) === mb_strtoupper($nombre) ? $nombre : $nombre.' · '.$descripcion);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'IdEmpresa', 'IdEmpresa');
    }
}
