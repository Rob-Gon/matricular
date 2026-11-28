<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lobby_matches', function (Blueprint $table) {
            // "First wins": the plate everyone is on, when it was dealt and when it closed (won or
            // failed by everyone). The clock alone cannot tell, unlike rounds.
            $table->unsignedSmallInteger('plate_number')->default(0)->after('plates');
            $table->dateTime('plate_started_at', 3)->nullable()->after('plate_number');
            $table->dateTime('plate_closes_at', 3)->nullable()->after('plate_started_at');
        });

        Schema::table('runs', function (Blueprint $table) {
            // Arrival time of the word being judged right now, so the plate waits for it.
            $table->dateTime('answering_since', 3)->nullable()->after('plate_dealt_at');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('answering_since');
        });

        Schema::table('lobby_matches', function (Blueprint $table) {
            $table->dropColumn(['plate_number', 'plate_started_at', 'plate_closes_at']);
        });
    }
};
