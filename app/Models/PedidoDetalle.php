<?php
// app/Models/PedidoDetalle.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PedidoDetalle extends Model
{
    protected $table = 'pedidos_detalle';
    protected $primaryKey = 'ped_det_id';
    public $timestamps = false;
    protected $guarded = [];

    public function producto() { return $this->belongsTo(Producto::class, 'IdProducto', 'IdProducto'); }
}