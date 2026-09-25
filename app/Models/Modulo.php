<?php
// app/Models/Modulo.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';
    protected $primaryKey = 'mod_id';
    public $timestamps = false;
    protected $guarded = [];
}