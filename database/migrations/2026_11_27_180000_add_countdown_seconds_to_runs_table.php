<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custom countdowns (1 to 60 minutes) need the real length per run, and a "Personalizado" value
 * the old MySQL enum cannot hold, so countdown_time becomes a plain string.
 */
return new class extends Migration
{
    private const FIXED = ['30 segundos' => 30, '1 minuto' => 60, '2 minutos' => 120, '3 minutos' => 180, '4 minutos' => 240, '5 minutos' => 300];

    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->string('countdown_time', 20)->nullable()->change();
            $table->unsignedSmallInteger('countdown_seconds')->nullable()->after('countdown_time');
        });

        foreach (self::FIXED as $label => $seconds) {
            DB::table('runs')->where('countdown_time', $label)->update(['countdown_seconds' => $seconds]);
        }
    }

    public function down(): void
    {
        // The old enum has no "Personalizado": custom runs lose their label (and their length with the column).
        DB::table('runs')->whereNotIn('countdown_time', array_keys(self::FIXED))->update(['countdown_time' => null]);

        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('countdown_seconds');
            $table->enum('countdown_time', array_keys(self::FIXED))->nullable()->change();
        });
    }
};
