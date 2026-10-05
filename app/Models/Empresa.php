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
    protected $hidden = ['client_secret', 'sire_clave'];

    // Credenciales del API SIRE cifradas con APP_KEY (si cambias APP_KEY hay que volver a ingresarlas)
    protected $casts = ['client_secret' => 'encrypted', 'sire_clave' => 'encrypted'];

    public function sucursales()
    {
        return $this->hasMany(EmpresaNegocio::class, 'IdEmpresa', 'IdEmpresa');
    }
}