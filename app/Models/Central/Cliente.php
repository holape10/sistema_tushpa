<?php
namespace App\Models\Central;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Model;

/**
 * Empresa cliente del multi-empresa (base central). Entra por su RUC ({RUC}.dominio) o, si tiene,
 * por su subdominio propio (demo.dominio). Su base es bd_{RUC}.
 */
class Cliente extends Model
{
    protected $connection = 'central';
    protected $table = 'clientes';
    protected $guarded = [];
    protected $casts = ['vence_el' => 'date'];

    /** (la columna `plan` guarda el nombre como texto; la relación tiene otro nombre para no chocar) */
    public function planContratado()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function activo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    /** Subdominio con el que entra: el propio (demo) o el RUC */
    public function subdominioAcceso(): string
    {
        return $this->subdominio ?: $this->ruc;
    }

    public function host(): string
    {
        return $this->subdominioAcceso() . '.' . config('tenancy.dominio');
    }

    public function url(): string
    {
        return Tenancy::urlCliente($this->subdominioAcceso());
    }

    /** ¿Ya tiene certificado https? (lo crea deploy/ssl-clientes.sh en el servidor) */
    public function tieneHttps(): bool
    {
        return is_file('/etc/httpd/conf.d/tushpa-ssl-' . $this->host() . '.conf');
    }
}
