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
        Schema::table('runs', function (Blueprint $table) {
            // The server owns the current plate and the clock, so clients cannot forge either.
            $table->char('plate', 3)->nullable()->after('countdown_time');
            $table->timestamp('ends_at')->nullable()->after('plate');
            $table->timestamp('ended_at')->nullable()->after('ends_at');

            // Deleting an account must also delete its runs instead of failing on the foreign key.
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users');

            $table->dropColumn(['plate', 'ends_at', 'ended_at']);
        });
    }
};
