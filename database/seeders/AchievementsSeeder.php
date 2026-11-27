<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementsSeeder extends Seeder
{
    /**
     * Syncs the achievements table with config/achievements.php. Safe to run on every deploy:
     * existing rows are updated by key, so players keep what they unlocked.
     */
    public function run(): void
    {
        $rows = collect(config('achievements'))->values()->map(fn (array $achievement, int $index) => [
            'key' => $achievement['key'],
            'name' => $achievement['name'],
            'description' => $achievement['description'],
            'icon' => $achievement['icon'],
            'hidden' => $achievement['hidden'],
            'sort' => $index,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        Achievement::upsert($rows, ['key'], ['name', 'description', 'icon', 'hidden', 'sort', 'updated_at']);
    }
}
