<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copies the JSON arrays of existing runs into run_words. Old runs never stored plates,
 * times or rejection reasons, so those stay null; accents are recovered from the dictionary.
 * Points are literal (+10 / -5) so later changes to the scoring constants do not alter history.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('runs')->orderBy('id')->chunkById(500, function ($runs): void {
            $rows = [];
            $decoded = [];

            foreach ($runs as $run) {
                $decoded[$run->id] = [
                    'correct' => json_decode($run->words_correct ?? '[]', true) ?: [],
                    'error' => json_decode($run->words_error ?? '[]', true) ?: [],
                    'skipped' => json_decode($run->chars_skipped ?? '[]', true) ?: [],
                ];
            }

            $forms = DB::table('words')
                ->whereIn('key', array_merge([], ...array_column($decoded, 'correct')))
                ->pluck('form', 'key');

            foreach ($runs as $run) {
                $base = ['run_id' => $run->id, 'plate' => null, 'elapsed_ms' => null, 'created_at' => $run->created_at];

                foreach ($decoded[$run->id]['correct'] as $key) {
                    $rows[] = $base + ['kind' => 'word', 'input' => mb_strtolower($key), 'key' => $key, 'form' => $forms[$key] ?? null, 'valid' => true, 'reason' => null, 'points' => 10];
                }
                foreach ($decoded[$run->id]['error'] as $key) {
                    $rows[] = $base + ['kind' => 'word', 'input' => mb_strtolower($key), 'key' => $key, 'form' => null, 'valid' => false, 'reason' => null, 'points' => -5];
                }
                foreach ($decoded[$run->id]['skipped'] as $letters) {
                    $rows[] = ['kind' => 'skip', 'input' => null, 'key' => null, 'form' => null, 'valid' => false, 'reason' => null, 'points' => -5]
                        + ['plate' => implode('', $letters)] + $base;
                }
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('run_words')->insert($chunk);
            }
        });
    }

    public function down(): void
    {
        DB::table('run_words')->delete();
    }
};
