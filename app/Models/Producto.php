<?php
// app/Models/Producto.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'IdProducto';
    protected $guarded = [];

    public function categoria() { return $this->belongsTo(Categoria::class, 'cat_id', 'cat_id'); }
    public function subcategoria() { return $this->belongsTo(Subcategoria::class, 'subcat_id', 'subcat_id'); }

    public function itemsCombo()
    {
        return $this->hasMany(Combo::class, 'IdProducto_rel', 'IdProducto');
    }

    public function getTipoNombreAttribute(): string
    {
        return match ((int) $this->promocion) {
            4 => 'Insumo',
            2 => 'Preparado',
            6 => 'Combo',
            default => 'Producto',
        };
    }
}