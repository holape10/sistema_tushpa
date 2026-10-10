<?php

// app/Models/Mesa.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $primaryKey = 'mes_id';

    public $timestamps = false;

    protected $guarded = [];

    public function piso()
    {
        return $this->belongsTo(Piso::class, 'pis_id', 'pis_id');
    }

    /**
     * Nombre con el piso delante ("PISO 02 - MESA 03"), como sale en la comanda: hay locales que
     * empiezan la numeración de mesas desde 1 en cada piso.
     */
    public static function etiqueta(?string $piso, ?string $mesa): string
    {
        return trim(trim((string) $piso).' - '.trim((string) $mesa), ' -');
    }
}
