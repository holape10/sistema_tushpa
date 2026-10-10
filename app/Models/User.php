<?php

// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'IdUsuario';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    public function sucursal()
    {
        return $this->belongsTo(EmpresaNegocio::class, 'id_empresa_negocio', 'id_empresa_negocio');
    }

    public function roles()
    {
        return $this->belongsToMany(\stdClass::class, 'role_user', 'user_IdUsuario', 'role_id');
        // si luego quieres un Model Role, cámbialo por Role::class
    }

    public function modulos()
    {
        return $this->belongsToMany(Modulo::class, 'modulos_usuario', 'user_IdUsuario', 'mod_id');
    }

    public function rolPrincipal(): ?string
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('user_IdUsuario', $this->IdUsuario)
            ->value('roles.name');
    }

    // IDs de la tabla roles: 2 = admin, 4 = caja, 8 = mozo
    public function tieneRol(array $roleIds): bool
    {
        return (bool) array_intersect($this->rolesIds(), array_map('intval', $roleIds));
    }

    /** @var array<int, int>|null Roles leídos una sola vez por petición (el menú y los permisos los consultan varias veces) */
    private ?array $rolesCache = null;

    /** @return array<int, int> */
    private function rolesIds(): array
    {
        return $this->rolesCache ??= DB::table('role_user')->where('user_IdUsuario', $this->IdUsuario)
            ->pluck('role_id')->map(fn ($id) => (int) $id)->all();
    }

    public function esAdmin(): bool
    {
        return $this->tieneRol([2]);
    }

    /** ¿Tiene asignada esta opción del menú? (el administrador tiene todo) */
    public function tieneModulo(string $url): bool
    {
        return $this->esAdmin() || $this->modulos()->where('mod_url', $url)->exists();
    }

    public function esAdminOCaja(): bool
    {
        return $this->tieneRol([2, 4]);
    }

    public function esMozo(): bool
    {
        return $this->tieneRol([8]);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'emp_id', 'emp_id');
    }

    public function rutaInicio(): string
    {
        return match ($this->rolPrincipal()) {
            'mozo' => 'comandas.seleccion', // el mozo entra directo a las mesas
            'entrenador' => 'entrenador.index', // el entrenador entra a su panel
            'caja' => 'inicio', // accesos del menú del usuario
            default => 'inicio', // admin y cualquier otro caso
        };
    }
}
