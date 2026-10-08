<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Toda guía impresa lleva QR: el de SUNAT cuando ya fue aceptada y, mientras tanto, uno que abre
 * {dominio}/guia/v/{token} (página pública con los datos de la guía y su estado en SUNAT, para quien la revise en carretera).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gre_cabecera', 'token')) {
            Schema::table('gre_cabecera', fn (Blueprint $t) => $t->string('token', 32)->nullable()->unique());
        }
        DB::table('gre_cabecera')->whereNull('token')->pluck('gre_id')
            ->each(fn ($id) => DB::table('gre_cabecera')->where('gre_id', $id)->update(['token' => Str::random(32)]));
    }

    public function down(): void
    {
        Schema::table('gre_cabecera', fn (Blueprint $t) => $t->dropColumn('token'));
    }
};
