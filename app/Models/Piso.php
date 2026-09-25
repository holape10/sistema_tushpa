<?php
// app/Models/Piso.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Piso extends Model
{
    protected $table = 'pisos';
    protected $primaryKey = 'pis_id';
    public $timestamps = false;
    protected $guarded = [];

    public function mesas() { return $this->hasMany(Mesa::class, 'pis_id', 'pis_id'); }
}