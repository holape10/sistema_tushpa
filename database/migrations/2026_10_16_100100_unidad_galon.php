<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Unidad SUNAT para combustibles (PV Grifo): GLL = galón (US)
return new class extends Migration {
    public function up(): void
    {
        DB::table('unidad_medida')->insertOrIgnore(['umecod' => 'GLL', 'umenom' => 'Galón', 'umecin' => 'GLL']);
    }

    public function down(): void
    {
        DB::table('unidad_medida')->where('umecod', 'GLL')->delete();
    }
};
