<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parts of speech of each accepted word ("noun,verb"), stored when the word is accepted so
 * category achievements and stats read them from the run instead of joining the dictionary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('run_words', function (Blueprint $table) {
            $table->string('categories', 120)->nullable()->after('form');
        });
    }

    public function down(): void
    {
        Schema::table('run_words', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }
};
