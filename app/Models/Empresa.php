<?php

// app/Models/Empresa.php

namespace App\Models;

use App\Casts\Cifrado;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresa';

    protected $primaryKey = 'IdEmpresa';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['client_secret', 'sire_clave', 'sol_clave', 'claveSunat', 'passcert'];

    // Credenciales del API SIRE, la Clave SOL principal (estudio contable), la clave SOL de envío y la del certificado,
    // cifradas con APP_KEY (si cambias APP_KEY hay que volver a ingresarlas)
    protected $casts = ['client_secret' => 'encrypted', 'sire_clave' => 'encrypted', 'sol_clave' => 'encrypted',
        'claveSunat' => Cifrado::class, 'passcert' => Cifrado::class];

    public function sucursales()
    {
        return $this->hasMany(EmpresaNegocio::class, 'IdEmpresa', 'IdEmpresa');
    }
}
