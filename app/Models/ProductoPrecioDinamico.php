<?php
// app/Models/ProductoPrecioDinamico.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/** Precio especial por día (0 = todos) y rango de horas; si hora_fin <= hora_inicio cruza la medianoche */
class ProductoPrecioDinamico extends Model
{
    protected $table = 'producto_precio_dinamico';
    protected $primaryKey = 'id_precio_dinamico';
    protected $guarded = [];
    protected $casts = ['precio' => 'float', 'activo' => 'boolean'];
}
