<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiplayer lobbies: a code-protected room where up to 16 players gather on the starting grid.
 * A player can only be in one lobby at a time (unique user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lobbies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 7)->unique(); // shaped like a plate: four digits and three letters
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('password')->nullable(); // hashed
            $table->string('format', 10); // race | rounds | first
            $table->json('settings');
            $table->string('status', 10)->default('waiting')->index(); // waiting | playing | finished
            $table->timestamp('last_activity_at')->index();
            $table->timestamps();
        });

        Schema::create('lobby_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lobby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('car_color', 7);
            $table->boolean('ready')->default(false);
            $table->timestamp('joined_at', 3); // grid order and host succession

            $table->index(['lobby_id', 'joined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lobby_players');
        Schema::dropIfExists('lobbies');
    }
};
