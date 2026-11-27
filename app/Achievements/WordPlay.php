<?php

namespace App\Achievements;

use App\Models\Run;
use App\Models\RunWord;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a word achievement sees after an attempt: the run, the attempt just recorded (valid or not)
 * and, on demand, the run's attempts so far. The history is loaded once and shared by every check.
 */
final class WordPlay
{
    /** @var Collection<int, RunWord>|null */
    private ?Collection $history = null;

    public function __construct(public readonly Run $run, public readonly RunWord $attempt) {}

    public function accepted(): bool
    {
        return $this->attempt->valid;
    }

    public function key(): string
    {
        return (string) $this->attempt->key;
    }

    /**
     * The run's attempts so far (this one included), oldest first.
     *
     * @return Collection<int, RunWord>
     */
    public function history(): Collection
    {
        return $this->history ??= $this->run->runWords()->orderBy('id')->get();
    }
}
