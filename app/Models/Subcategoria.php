<?php
// app/Models/Subcategoria.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Subcategoria extends Model
{
    protected $table = 'subcategorias';
    protected $primaryKey = 'subcat_id';
    public $timestamps = false;
    protected $guarded = [];
}