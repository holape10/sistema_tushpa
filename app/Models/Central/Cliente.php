<?php
namespace App\Models\Central;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Model;

/** Empresa cliente del multi-empresa (base central): su RUC es el subdominio y su base es bd_{RUC} */
class Cliente extends Model
{
    protected $connection = 'central';
    protected $table = 'clientes';
    protected $guarded = [];
    protected $casts = ['vence_el' => 'date'];

    public function activo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function url(): string
    {
        return Tenancy::urlCliente($this->ruc);
    }
}
