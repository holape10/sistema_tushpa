<?php
// app/Models/Categoria.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $primaryKey = 'cat_id';
    public $timestamps = false;
    protected $guarded = [];
}