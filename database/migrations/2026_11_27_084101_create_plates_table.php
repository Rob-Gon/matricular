<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Every possible plate (20³) with how many dictionary words solve it, so the generator
        // can avoid impossible plates. Rebuilt by `php artisan dictionary:plates`.
        Schema::create('plates', function (Blueprint $table) {
            $table->char('plate', 3)->primary();
            $table->boolean('simple')->index();
            $table->unsignedInteger('solutions')->index();
            $table->unsignedInteger('solutions_ordered')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plates');
    }
};
