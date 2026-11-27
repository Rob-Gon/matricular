<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The old difficulty mixed two independent settings: Facil = simple letters in any order,
 * Medio = all letters in any order, Dificil = all letters in plate order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->string('letter_set', 10)->default('simple')->after('mode');
            $table->boolean('ordered')->default(false)->after('letter_set');
        });

        DB::table('runs')->whereIn('difficulty', ['Medio', 'Dificil'])->update(['letter_set' => 'full']);
        DB::table('runs')->where('difficulty', 'Dificil')->update(['ordered' => true]);

        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->enum('difficulty', ['Facil', 'Medio', 'Dificil'])->default('Facil')->after('mode');
        });

        // Lossy: "simple letters in plate order" had no old level, so every ordered run becomes Dificil.
        DB::table('runs')->where('letter_set', 'full')->update(['difficulty' => 'Medio']);
        DB::table('runs')->where('ordered', true)->update(['difficulty' => 'Dificil']);

        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn(['letter_set', 'ordered']);
        });
    }
};
