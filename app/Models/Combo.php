<?php
// app/Models/Combo.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    protected $table = 'combos';
    protected $primaryKey = 'comb_id';
    public $timestamps = false;
    protected $guarded = [];

    public function itemProducto() { return $this->belongsTo(Producto::class, 'IdProducto_comb', 'IdProducto'); }
}