<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * run_words now holds every attempt; the JSON arrays it replaced go away.
 * Rolling back rebuilds them from run_words.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn(['words_correct', 'words_error', 'chars_skipped']);
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->json('words_correct')->nullable();
            $table->json('words_error')->nullable();
            $table->json('chars_skipped')->nullable();
        });

        DB::table('runs')->orderBy('id')->chunkById(500, function ($runs): void {
            $attempts = DB::table('run_words')->whereIn('run_id', $runs->pluck('id'))->orderBy('id')->get()->groupBy('run_id');

            foreach ($runs as $run) {
                $rows = $attempts->get($run->id, collect());
                $words = $rows->where('kind', 'word')->where('reason', '!==', 'repeated');

                DB::table('runs')->where('id', $run->id)->update([
                    'words_correct' => json_encode($words->filter(fn ($row) => (bool) $row->valid)->pluck('key')->values()),
                    'words_error' => json_encode($words->reject(fn ($row) => (bool) $row->valid)->pluck('key')->values()),
                    'chars_skipped' => json_encode($rows->where('kind', 'skip')->map(fn ($row) => str_split((string) $row->plate))->values()),
                ]);
            }
        });
    }
};
