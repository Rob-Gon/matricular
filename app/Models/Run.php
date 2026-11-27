<?php

namespace App\Models;

use App\Enums\LetterSets;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property Carbon|null $plate_dealt_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $ended_at
 * @property list<string>|null $words_correct
 * @property list<string>|null $words_error
 * @property list<list<string>>|null $chars_skipped
 * @property-read Collection<int, RunWord> $runWords
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
        'plate_dealt_at',
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
     * Every attempt of the run, in order: words (valid or not) and skipped plates.
     *
     * @return HasMany<RunWord, $this>
     */
    public function runWords(): HasMany
    {
        return $this->hasMany(RunWord::class);
    }

    /**
     * Stored with milliseconds (the model's other dates are whole seconds) to time each answer.
     *
     * @return Attribute<Carbon|null, Carbon|null>
     */
    protected function plateDealtAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Carbon::parse($value),
            set: fn (?Carbon $value) => $value?->format('Y-m-d H:i:s.v'),
        );
    }

    /**
     * Runs where at least one word was tried; empty runs are ignored in rankings.
     *
     * @param  Builder<Run>  $query
     */
    public function scopeAttempted(Builder $query): void
    {
        $query->whereHas('runWords', fn (Builder $query) => $query->where('kind', RunWord::KIND_WORD));
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

    /**
     * The run's settings as short Spanish labels for the game and summary screens.
     *
     * @return list<string>
     */
    public function settingsLabels(): array
    {
        return [
            $this->countdown_time ? "Contrarreloj, {$this->countdown_time}" : 'Sin límite',
            $this->letter_set === LetterSets::SIMPLE ? 'Letras simples' : 'Todas las letras',
            $this->ordered ? 'En orden' : 'Cualquier orden',
        ];
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

    public function dealPlate(string $plate): void
    {
        $this->plate = $plate;
        $this->plate_dealt_at = now();
    }

    public function hasCorrectWord(string $key): bool
    {
        return $this->runWords()->where('valid', true)->where('key', $key)->exists();
    }

    // shortcut: the JSON arrays are still written during the move to run_words; drop them once
    // nothing reads them (F4, next PR).

    public function addCorrectWord(string $key, string $input, ?string $form): void
    {
        $this->words_correct = [...($this->words_correct ?? []), $key];
        $this->score += self::POINTS_CORRECT;
        $this->record(['kind' => RunWord::KIND_WORD, 'input' => $input, 'key' => $key, 'form' => $form, 'valid' => true, 'points' => self::POINTS_CORRECT]);
    }

    public function addErrorWord(string $key, string $input, string $reason): void
    {
        $this->words_error = [...($this->words_error ?? []), $key];
        $this->score -= self::POINTS_PENALTY;
        $this->record(['kind' => RunWord::KIND_WORD, 'input' => $input, 'key' => $key, 'reason' => $reason, 'points' => -self::POINTS_PENALTY]);
    }

    /** A word already accepted in this run: kept for the stats, scores nothing. */
    public function addRepeatedWord(string $key, string $input): void
    {
        $this->record(['kind' => RunWord::KIND_WORD, 'input' => $input, 'key' => $key, 'reason' => RunWord::REASON_REPEATED, 'points' => 0]);
    }

    public function skipPlate(string $newPlate): void
    {
        $this->chars_skipped = [...($this->chars_skipped ?? []), $this->plateLetters()];
        $this->score -= self::POINTS_PENALTY;
        $this->record(['kind' => RunWord::KIND_SKIP, 'points' => -self::POINTS_PENALTY]);
        $this->dealPlate($newPlate);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): void
    {
        $this->runWords()->create([
            'plate' => $this->plate,
            'elapsed_ms' => $this->plate_dealt_at === null ? null : (int) abs(now()->diffInMilliseconds($this->plate_dealt_at)),
            ...$attributes,
        ]);
    }
}
