<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Marca blanca de un revendedor: su dominio (wilcatsystems.pe), el prefijo de las bases de sus empresas (bd_ws_),
 * su logo y colores. Las empresas sin marca son de TUSHPA.
 */
class Marca extends Model
{
    protected $connection = 'central';

    protected $table = 'marcas';

    protected $guarded = [];

    protected $casts = ['activa' => 'boolean'];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'marca_id');
    }
}
