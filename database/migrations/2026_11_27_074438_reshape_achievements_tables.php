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
        Schema::table('achievements', function (Blueprint $table) {
            $table->renameColumn('achievement', 'name');
        });

        Schema::table('achievements', function (Blueprint $table) {
            // Stable identifier used by the code (config/achievements.php); names can be reworded freely.
            $table->string('key')->unique()->after('id');
            $table->string('icon')->default('trophy')->after('description');
            $table->boolean('hidden')->default(false)->after('icon');
            $table->unsignedSmallInteger('sort')->default(0)->after('hidden');
        });

        Schema::table('user_achievements', function (Blueprint $table) {
            $table->foreignId('run_id')->nullable()->after('achievement_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_achievements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('run_id');
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'icon', 'hidden', 'sort']);
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->renameColumn('name', 'achievement');
        });
    }
};
