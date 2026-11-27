<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lobby_matches', function (Blueprint $table) {
            $table->id();
            // Kept when the lobby closes, for the players' multiplayer stats.
            $table->foreignId('lobby_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 10);
            $table->json('settings');
            // The seeded plate sequence: every player gets the same plates in the same order.
            $table->json('plates');
            $table->dateTime('starts_at', 3);
            $table->dateTime('ends_at', 3);
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->index(['lobby_id', 'id']);
            $table->index(['finished_at', 'ends_at']);
        });

        Schema::table('runs', function (Blueprint $table) {
            // Set on multiplayer runs only; solo queries exclude them (Run's "solo" global scope).
            $table->foreignId('lobby_match_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            // How many plates the run has dealt; a match run takes plate N of the match's sequence.
            $table->unsignedInteger('plate_number')->default(0)->after('plate');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lobby_match_id');
            $table->dropColumn('plate_number');
        });

        Schema::dropIfExists('lobby_matches');
    }
};
