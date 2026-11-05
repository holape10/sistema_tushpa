<?php

use App\Casts\Cifrado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad: la clave SOL (envío a SUNAT) y la contraseña del certificado digital se guardaban en texto plano y salían en
 * cualquier respaldo. Ahora van cifradas con la APP_KEY como las del SIRE (columna de texto: lo cifrado es más largo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $t) {
            $t->text('claveSunat')->nullable()->default(null)->change();
            $t->text('passcert')->nullable()->default(null)->change();
        });
        foreach (DB::table('empresa')->get(['IdEmpresa', 'claveSunat', 'passcert']) as $e) {
            $cambios = [];
            foreach (['claveSunat', 'passcert'] as $campo) {
                if ($e->{$campo} !== null && $e->{$campo} !== '' && ! Cifrado::estaCifrado($e->{$campo})) {
                    $cambios[$campo] = Crypt::encryptString($e->{$campo});
                }
            }
            if ($cambios) {
                DB::table('empresa')->where('IdEmpresa', $e->IdEmpresa)->update($cambios);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('empresa')->get(['IdEmpresa', 'claveSunat', 'passcert']) as $e) {
            $cambios = [];
            foreach (['claveSunat', 'passcert'] as $campo) {
                if (Cifrado::estaCifrado($e->{$campo})) {
                    $cambios[$campo] = Crypt::decryptString($e->{$campo});
                }
            }
            if ($cambios) {
                DB::table('empresa')->where('IdEmpresa', $e->IdEmpresa)->update($cambios);
            }
        }
    }
};
