<?php
// app/Models/MedioPago.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MedioPago extends Model
{
    protected $table = 'medios_pagos';
    protected $primaryKey = 'id_med_pag';
    public $timestamps = false;
    protected $guarded = [];
}