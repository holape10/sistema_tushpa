<?php
// app/Models/ProductoPresentacion.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/** Otra forma de vender un producto: factor = unidades base que trae (SACO = 50 KG) */
class ProductoPresentacion extends Model
{
    protected $table = 'producto_presentacion';
    protected $primaryKey = 'id_presentacion';
    protected $guarded = [];
    protected $casts = ['factor' => 'float', 'precio' => 'float', 'estado' => 'boolean'];
}
