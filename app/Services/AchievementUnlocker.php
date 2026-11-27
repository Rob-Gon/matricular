<?php

namespace App\Services;

use App\Achievements\Contracts\RunAchievement;
use App\Achievements\Contracts\WordAchievement;
use App\Achievements\WordPlay;
use App\Models\Achievement;
use App\Models\Run;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates the achievement catalogue (config/achievements.php) after game events.
 * One query loads what the player already owns; only the remaining achievements of the
 * matching kind are checked, so the cost per word is a few in-memory comparisons.
 */
class AchievementUnlocker
{
    private const PLATINUM = 'platinum';

    /** @var list<array{key: string, name: string, description: string, icon: string}> */
    private array $unlocked = [];

    public function afterWord(WordPlay $play): void
    {
        $this->evaluate($play->run, WordAchievement::class, fn (WordAchievement $achievement) => $achievement->isUnlockedByWord($play));
    }

    public function afterRun(Run $run): void
    {
        $this->evaluate($run, RunAchievement::class, fn (RunAchievement $achievement) => $achievement->isUnlockedByRun($run));
    }

    /**
     * Achievements unlocked by this instance, to show as notifications.
     *
     * @return list<array{key: string, name: string, description: string, icon: string}>
     */
    public function unlocked(): array
    {
        return $this->unlocked;
    }

    /**
     * @param  class-string  $contract
     */
    private function evaluate(Run $run, string $contract, Closure $passes): void
    {
        /** @var Collection<int, array{key: string, name: string, description: string, icon: string, hidden: bool, class: class-string|null}> $catalogue */
        $catalogue = collect(config('achievements'));
        $owned = Achievement::whereHas('users', fn ($query) => $query->whereKey($run->user_id))->pluck('key')->all();
        $before = count($this->unlocked);

        foreach ($catalogue as $definition) {
            if ($definition['class'] === null
                || in_array($definition['key'], $owned, true)
                || ! is_a($definition['class'], $contract, true)
                || ! $passes(app($definition['class']))) {
                continue;
            }

            if ($this->unlock($run, $definition)) {
                $owned[] = $definition['key'];
            }
        }

        if (count($this->unlocked) > $before && ! in_array(self::PLATINUM, $owned, true)) {
            $this->unlockPlatinumIfComplete($run, $catalogue, $owned);
        }
    }

    /**
     * @param  Collection<int, array{key: string, name: string, description: string, icon: string, hidden: bool, class: class-string|null}>  $catalogue
     * @param  list<string>  $owned
     */
    private function unlockPlatinumIfComplete(Run $run, Collection $catalogue, array $owned): void
    {
        $platinum = $catalogue->firstWhere('key', self::PLATINUM);
        $remaining = $catalogue->where('key', '!==', self::PLATINUM)->pluck('key')->diff($owned);

        if ($platinum !== null && $remaining->isEmpty()) {
            $this->unlock($run, $platinum);
        }
    }

    /**
     * @param  array{key: string, name: string, description: string, icon: string}  $definition
     */
    private function unlock(Run $run, array $definition): bool
    {
        $achievementId = Achievement::where('key', $definition['key'])->value('id');

        // Catalogue not seeded yet: nothing to unlock.
        if ($achievementId === null) {
            return false;
        }

        // The unique (user_id, achievement_id) index makes concurrent unlocks safe.
        $inserted = DB::table('user_achievements')->insertOrIgnore([
            'user_id' => $run->user_id,
            'achievement_id' => $achievementId,
            'run_id' => $run->id,
            'unlocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return false;
        }

        $this->unlocked[] = Arr::only($definition, ['key', 'name', 'description', 'icon']);

        return true;
    }
}
