<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An extra plate for players tied on a podium position ("tie_resolution = tiebreak").
        Schema::create('match_tiebreaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lobby_match_id')->constrained()->cascadeOnDelete();
            // The position the tied players share before the tiebreak.
            $table->unsignedTinyInteger('position');
            $table->json('player_ids');
            $table->string('plate', 3);
            $table->dateTime('starts_at', 3);
            $table->dateTime('ends_at', 3);
            // Null winner once resolved: nobody solved it in time and the players keep sharing the position.
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('winning_word', 50)->nullable();
            $table->dateTime('resolved_at', 3)->nullable();
            $table->timestamps();

            $table->index(['lobby_match_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_tiebreaks');
    }
};
