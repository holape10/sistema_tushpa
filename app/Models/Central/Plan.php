<?php
namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Plan de suscripción (base central): precio mensual y límites (null = sin límite) */
class Plan extends Model
{
    protected $connection = 'central';
    protected $table = 'planes';
    protected $guarded = [];
    protected $casts = ['precio' => 'float', 'destacado' => 'boolean', 'tienda_virtual' => 'boolean', 'activo' => 'boolean', 'max_usuarios' => 'integer'];

    /** @return string[] */
    public function listaCaracteristicas(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->caracteristicas))));
    }
}
