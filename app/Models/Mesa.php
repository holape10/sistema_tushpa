<?php
// app/Models/Mesa.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    protected $table = 'mesas';
    protected $primaryKey = 'mes_id';
    public $timestamps = false;
    protected $guarded = [];

    public function piso() { return $this->belongsTo(Piso::class, 'pis_id', 'pis_id'); }
}