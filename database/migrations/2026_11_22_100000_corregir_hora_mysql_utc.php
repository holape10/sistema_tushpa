<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige la hora de las ventas y resúmenes que tomó el reloj de MySQL (UTC en el servidor: 5 horas adelantada).
 * Solo actúa si MySQL y PHP (America/Lima) tienen horas distintas; en una PC con la hora de Perú no cambia nada.
 *  - cpe_cabecera: facturas, boletas y notas de venta del sistema (las notas de crédito/débito y lo importado del
 *    sistema antiguo ya tenían la hora de Perú).
 *  - resumenes: todos.
 * Desde ahora la hora la pone PHP (App\Support\HoraLocal\ConsultaConHora).
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = strtotime((string) DB::selectOne('SELECT NOW() AS n')->n);
        $php = strtotime(now()->format('Y-m-d H:i:s'));
        // Diferencia en horas (redondeada a media hora); menos de 30 minutos = mismo reloj
        $horas = round(($mysql - $php) / 1800) / 2;
        if (abs($horas) < 0.5) {
            return;
        }
        $minutos = (int) round($horas * 60);

        if (Schema::hasTable('cpe_cabecera')) {
            DB::update("UPDATE cpe_cabecera SET fecha_hora = fecha_hora - INTERVAL {$minutos} MINUTE
                WHERE fecha_hora IS NOT NULL AND tdocod NOT IN ('07', '08') AND (ped_tip IS NULL OR ped_tip <> 'ANTIGUO')");
        }
        if (Schema::hasTable('resumenes')) {
            DB::update("UPDATE resumenes SET fecha_hora = fecha_hora - INTERVAL {$minutos} MINUTE WHERE fecha_hora IS NOT NULL");
        }
    }

    public function down(): void
    {
        // No se revierte: la hora corregida es la real
    }
};
