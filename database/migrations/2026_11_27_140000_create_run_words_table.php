<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per attempt in a run: a word (valid or not) or a skipped plate.
 * Replaces the runs.words_correct / words_error / chars_skipped JSON arrays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8); // word | skip
            $table->char('plate', 3)->nullable(); // unknown for runs played before this table existed
            $table->string('input', 50)->nullable(); // as typed, accents included
            $table->string('key', 50)->nullable(); // normalized, to spot repeated words
            // Dictionary spelling of a valid word. Not a foreign key: dictionary:import rebuilds `words`.
            $table->string('form', 50)->nullable();
            $table->boolean('valid')->default(false);
            $table->string('reason', 20)->nullable(); // missing_letters | wrong_order | not_in_dictionary | repeated
            $table->smallInteger('points');
            $table->unsignedInteger('elapsed_ms')->nullable(); // since the plate was dealt
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['run_id', 'key']);
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->timestamp('plate_dealt_at', 3)->nullable()->after('plate');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('plate_dealt_at');
        });

        Schema::dropIfExists('run_words');
    }
};
