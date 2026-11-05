<?php

namespace App\Models\Central;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Empresa cliente del multi-empresa (base central). Entra por su RUC ({RUC}.dominio) o, si tiene,
 * por su subdominio propio (demo.dominio). Su base es bd_{RUC}. Si es de una marca (revendedor con dominio propio),
 * el dominio y el prefijo de la base son los de su marca: 2060….wilcatsystems.pe y bd_ws_{RUC}.
 */
class Cliente extends Model
{
    protected $connection = 'central';

    protected $table = 'clientes';

    protected $guarded = [];

    /** sol_clave: clave SOL principal, guardada cifrada (con ella se traen los XML de las compras por json.pe) */
    protected $casts = ['vence_el' => 'date', 'prueba_hasta' => 'date', 'suspendido_el' => 'date', 'cert_vence' => 'date', 'sol_clave' => 'encrypted'];

    protected $hidden = ['sol_clave'];

    /** (la columna `plan` guarda el nombre como texto; la relación tiene otro nombre para no chocar) */
    public function planContratado()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** La marca del revendedor (null = TUSHPA) */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    /** Dominio por el que entra: el de su marca o el de TUSHPA */
    public function dominio(): string
    {
        return $this->marca_id ? (string) $this->marca?->dominio : (string) config('tenancy.dominio');
    }

    /** Prefijo de su base de datos: el de su marca (bd_ws_) o el de TUSHPA (bd_) */
    public function prefijoBd(): string
    {
        return $this->marca_id ? (string) $this->marca?->prefijo_bd : (string) config('tenancy.prefijo_bd');
    }

    public function activo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    /** Se registró en la web y todavía no paga (prueba gratis, vigente o vencida) */
    public function enPrueba(): bool
    {
        return $this->prueba_hasta !== null;
    }

    /** Días que le quedan de prueba (0 = hoy es el último día; negativo = ya venció) */
    public function diasPrueba(): ?int
    {
        return $this->prueba_hasta ? (int) now()->startOfDay()->diffInDays($this->prueba_hasta, false) : null;
    }

    /** Subdominio con el que entra: el propio (demo) o el RUC */
    public function subdominioAcceso(): string
    {
        return $this->subdominio ?: $this->ruc;
    }

    public function host(): string
    {
        return $this->subdominioAcceso().'.'.$this->dominio();
    }

    public function url(): string
    {
        return Tenancy::urlCliente($this->subdominioAcceso(), $this->dominio());
    }

    /** ¿Ya tiene certificado https? (lo crea deploy/ssl-clientes.sh en el servidor) */
    public function tieneHttps(): bool
    {
        return is_file('/etc/httpd/conf.d/tushpa-ssl-'.$this->host().'.conf');
    }
}
