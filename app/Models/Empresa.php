<?php
// app/Models/Empresa.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresa';
    protected $primaryKey = 'IdEmpresa';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function sucursales()
    {
        return $this->hasMany(EmpresaNegocio::class, 'IdEmpresa', 'IdEmpresa');
    }
}