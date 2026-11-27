<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Binary collation: "el"/"él" and "si"/"sí" are different forms, and lookups compare exact keys.
        // SQLite (tests) already compares binary by default and does not know MySQL collations.
        $binary = DB::getDriverName() === 'mysql' ? 'utf8mb4_bin' : null;

        Schema::create('words', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->string('key', 50)->collation($binary)->index();
            $table->string('form', 50)->collation($binary);
            $table->string('lemma', 50)->collation($binary);
            $table->string('pos', 20)->nullable();
            $table->string('source', 16)->index();

            $table->unique(['form', 'lemma']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
