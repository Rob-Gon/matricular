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
        // Same reasoning as the words table: exact, accent-sensitive matching on MySQL.
        $binary = DB::getDriverName() === 'mysql' ? 'utf8mb4_bin' : null;

        // A word can have several categories ("japonés": adjective and noun), so they get their own
        // table keyed by the written word, which may be a form ("camiones") or a lemma ("camión").
        Schema::create('word_categories', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->string('word', 50)->collation($binary);
            $table->string('pos', 12);
            $table->string('source', 16)->index();

            $table->unique(['word', 'pos']);
        });

        Schema::table('words', function (Blueprint $table) {
            $table->dropColumn('pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('words', function (Blueprint $table) {
            $table->string('pos', 20)->nullable()->after('lemma');
        });

        Schema::dropIfExists('word_categories');
    }
};
