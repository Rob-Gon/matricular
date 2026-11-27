<?php

use App\Enums\CountdownModes;
use App\Enums\GameDifficulties;
use App\Enums\GameModes;
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
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->integer('score')->default(0);
            $table->enum('mode', [GameModes::CONTRARELOJ->value(), GameModes::INFINITO->value()])->default(GameModes::CONTRARELOJ->value());
            $table->enum('difficulty', [GameDifficulties::FACIL->value(), GameDifficulties::MEDIO->value(), GameDifficulties::DIFICIL->value()])->default(GameDifficulties::FACIL->value());
            $table->enum('countdown_time', [CountdownModes::TREINTA_SEGUNDOS->value(), CountdownModes::UN_MINUTO->value(), CountdownModes::DOS_MINUTOS->value(), CountdownModes::TRES_MINUTOS->value(), CountdownModes::CUATRO_MINUTOS->value(), CountdownModes::CINCO_MINUTOS->value()])->nullable();
            $table->json('words_correct')->nullable();
            $table->json('words_error')->nullable();
            $table->json('chars_skipped')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
