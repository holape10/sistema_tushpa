<?php
// app/Models/TipoProducto.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TipoProducto extends Model
{
    protected $table = 'tipo_producto';
    protected $primaryKey = 'tip_pro_id';
    public $timestamps = false;
    protected $guarded = [];
}