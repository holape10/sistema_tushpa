<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Plan de suscripción (base central): precio mensual y límites (null = sin límite) */
class Plan extends Model
{
    protected $connection = 'central';

    protected $table = 'planes';

    protected $guarded = [];

    /** Meses que se cobran en el plan anual cuando no se escribe su precio (12 - 2 meses gratis) */
    public const MESES_ANUAL = 10;

    protected $casts = ['precio' => 'float', 'precio_anual' => 'float', 'destacado' => 'boolean', 'tienda_virtual' => 'boolean', 'activo' => 'boolean', 'max_usuarios' => 'integer'];

    /** Lo que se paga al año: el precio anual escrito o 10 meses (2 gratis) */
    public function precioAnual(): float
    {
        return round($this->precio_anual ?: $this->precio * self::MESES_ANUAL, 2);
    }

    /** Cuánto se ahorra al pagar el año frente a 12 meses */
    public function ahorroAnual(): float
    {
        return max(0, round($this->precio * 12 - $this->precioAnual(), 2));
    }

    /** @return string[] */
    public function listaCaracteristicas(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->caracteristicas))));
    }
}
