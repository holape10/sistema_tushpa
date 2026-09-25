<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        return \Illuminate\Support\Facades\DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('user_IdUsuario', $this->IdUsuario)
            ->value('roles.name');
    }

    public function rutaInicio(): string
    {
        return match ($this->rolPrincipal()) {
            'mozo' => 'dashboard', // cámbialo a 'mesas.index' cuando exista ese módulo
            'caja' => 'dashboard', // cámbialo a 'pos.index' cuando exista
            default => 'dashboard', // admin y cualquier otro caso
        };
    }
}