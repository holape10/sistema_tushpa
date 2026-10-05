<?php
namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Usuario del panel del dueño del sistema (no es usuario de ninguna empresa) */
class Superadmin extends Authenticatable
{
    protected $connection = 'central';
    protected $table = 'superadmins';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['ultimo_acceso' => 'datetime', 'password' => 'hashed'];
}
