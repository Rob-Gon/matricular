<?php

namespace App\Models;

use App\Enums\LetterSets;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property int $score
 * @property string $mode
 * @property LetterSets $letter_set
 * @property bool $ordered
 * @property string|null $countdown_time
 * @property string|null $plate
 * @property Carbon|null $ends_at
 * @property Carbon|null $ended_at
 * @property list<string>|null $words_correct
 * @property list<string>|null $words_error
 * @property list<list<string>>|null $chars_skipped
 */
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory, SoftDeletes;

    public const POINTS_CORRECT = 10;

    public const POINTS_PENALTY = 5;

    /** Extra seconds after ends_at during which a word is still accepted, to absorb network latency. */
    public const LATENCY_GRACE_SECONDS = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'score',
        'mode',
        'letter_set',
        'ordered',
        'countdown_time',
        'plate',
        'ends_at',
        'ended_at',
        'words_correct',
        'words_error',
        'chars_skipped',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'letter_set' => LetterSets::class,
            'ordered' => 'boolean',
            'words_correct' => 'array',
            'words_error' => 'array',
            'chars_skipped' => 'array',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Obtiene el usuario que tiene la run
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Runs where at least one word was tried; empty runs are ignored in rankings.
     *
     * @param  Builder<Run>  $query
     */
    public function scopeAttempted(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereJsonLength('words_correct', '>', 0)
            ->orWhereJsonLength('words_error', '>', 0));
    }

    /**
     * Runs the callback in a transaction with this run's row locked and reloaded, so concurrent
     * requests on the same run (word, plate change, end) cannot overwrite each other's score.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLock(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            static::whereKey($this->getKey())->lockForUpdate()->first();
            $this->refresh();

            return $callback();
        });
    }

    public function isOver(): bool
    {
        if ($this->ended_at !== null) {
            return true;
        }

        return $this->ends_at !== null
            && now()->greaterThan($this->ends_at->copy()->addSeconds(self::LATENCY_GRACE_SECONDS));
    }

    public function remainingSeconds(): ?int
    {
        if ($this->ends_at === null) {
            return null;
        }

        return (int) max(0, ceil(now()->diffInSeconds($this->ends_at, false)));
    }

    /**
     * @return list<string>
     */
    public function plateLetters(): array
    {
        return str_split((string) $this->plate);
    }

    public function hasCorrectWord(string $word): bool
    {
        return in_array($word, $this->words_correct ?? [], true);
    }

    public function addCorrectWord(string $word): void
    {
        $this->words_correct = [...($this->words_correct ?? []), $word];
        $this->score += self::POINTS_CORRECT;
    }

    public function addErrorWord(string $word): void
    {
        $this->words_error = [...($this->words_error ?? []), $word];
        $this->score -= self::POINTS_PENALTY;
    }

    public function skipPlate(string $newPlate): void
    {
        $this->chars_skipped = [...($this->chars_skipped ?? []), $this->plateLetters()];
        $this->score -= self::POINTS_PENALTY;
        $this->plate = $newPlate;
    }
}
