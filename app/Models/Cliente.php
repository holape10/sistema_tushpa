<?php
// app/Models/Cliente.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'cliente';
    protected $primaryKey = 'clicod';
    public $timestamps = false;
    protected $guarded = [];
}