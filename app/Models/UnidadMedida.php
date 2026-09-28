<?php
// app/Models/UnidadMedida.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidad_medida';
    protected $primaryKey = 'ume_id';
    public $timestamps = false;
    protected $guarded = [];
}