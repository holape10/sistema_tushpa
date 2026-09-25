<?php
// app/Models/Almacen.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'almacenes';
    protected $primaryKey = 'id_almacen';
    public $timestamps = false;
    protected $guarded = [];
}