<?php
// app/Models/Pedido.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';
    protected $primaryKey = 'ped_id';
    public $timestamps = false;
    protected $guarded = [];

    public function detalles() { return $this->hasMany(PedidoDetalle::class, 'ped_id', 'ped_id'); }
    public function mesa() { return $this->belongsTo(Mesa::class, 'mes_id', 'mes_id'); }
}