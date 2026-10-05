<?php
// app/Models/Turno.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turnos';
    protected $primaryKey = 'id_turno';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['apertura' => 'datetime', 'cierre' => 'datetime'];

    // Denominaciones del arqueo: campo => valor en soles
    public const DENOMINACIONES = [
        'cant_m_10_centimos' => 0.10, 'cant_m_20_centimos' => 0.20, 'cant_m_50_centimos' => 0.50,
        'cant_m_1_sol' => 1, 'cant_m_2_soles' => 2, 'cant_m_5_soles' => 5,
        'cant_c_10_soles' => 10, 'cant_c_20_soles' => 20, 'cant_c_50_soles' => 50,
        'cant_c_100_soles' => 100, 'cant_c_200_soles' => 200,
    ];

    public function usuario() { return $this->belongsTo(User::class, 'IdUsuario', 'IdUsuario'); }

    // Turno abierto del usuario en su sucursal (null si no tiene)
    public static function abiertoDe(User $user): ?self
    {
        return static::where('IdUsuario', $user->IdUsuario)
            ->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->where('estado', 'ABIERTO')
            ->latest('id_turno')
            ->first();
    }
}
