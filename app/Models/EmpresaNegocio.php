<?php
// app/Models/EmpresaNegocio.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaNegocio extends Model
{
    protected $table = 'empresa_negocios';
    protected $primaryKey = 'id_empresa_negocio';
    protected $guarded = [];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'IdEmpresa', 'IdEmpresa');
    }
}