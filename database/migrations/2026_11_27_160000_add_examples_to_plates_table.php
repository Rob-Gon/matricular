<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shortest dictionary words that solve each plate, for "también valían" on the trip receipt.
 * Filled by `php artisan dictionary:plates`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plates', function (Blueprint $table) {
            $table->json('examples')->nullable();
            $table->json('examples_ordered')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plates', function (Blueprint $table) {
            $table->dropColumn(['examples', 'examples_ordered']);
        });
    }
};
