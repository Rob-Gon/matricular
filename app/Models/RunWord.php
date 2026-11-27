<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt in a run: a word (valid or not) or a skipped plate.
 *
 * @property int $id
 * @property int $run_id
 * @property string $kind
 * @property string|null $plate
 * @property string|null $input
 * @property string|null $key
 * @property string|null $form
 * @property string|null $categories
 * @property bool $valid
 * @property string|null $reason
 * @property int $points
 * @property int|null $elapsed_ms
 * @property Carbon $created_at
 */
class RunWord extends Model
{
    public const KIND_WORD = 'word';

    public const KIND_SKIP = 'skip';

    public const REASON_MISSING_LETTERS = 'missing_letters';

    public const REASON_WRONG_ORDER = 'wrong_order';

    public const REASON_NOT_IN_DICTIONARY = 'not_in_dictionary';

    public const REASON_REPEATED = 'repeated';

    public const REASON_TRAP = 'trap';

    /** "Rounds": a valid word another player already scored on this round's plate. Scores 0. */
    public const REASON_TAKEN = 'taken';

    /** Reasons that neither score nor count against accuracy. */
    public const NEUTRAL_REASONS = [self::REASON_REPEATED, self::REASON_TAKEN];

    public const UPDATED_AT = null;

    // Milliseconds matter: answer times now, "first wins" ordering in multiplayer later.
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'plate', 'input', 'key', 'form', 'categories', 'valid', 'reason', 'points', 'elapsed_ms'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid' => 'boolean',
            'points' => 'integer',
            'elapsed_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * The word as it should be shown: dictionary spelling when known, else what the player typed.
     */
    public function display(): string
    {
        return $this->form ?? mb_strtolower((string) $this->input);
    }

    /**
     * Parts of speech of an accepted word ("noun", "adj", "verb"...).
     *
     * @return list<string>
     */
    public function categoryList(): array
    {
        return $this->categories === null || $this->categories === '' ? [] : explode(',', $this->categories);
    }

    public function hasCategory(string $category): bool
    {
        return in_array($category, $this->categoryList(), true);
    }

    public function isRepeated(): bool
    {
        return $this->reason === self::REASON_REPEATED;
    }

    /**
     * Why the word was rejected, in the player's words.
     */
    public function reasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_MISSING_LETTERS => 'Le faltan letras de la matrícula',
            self::REASON_WRONG_ORDER => 'Las letras no van en el orden de la matrícula',
            self::REASON_NOT_IN_DICTIONARY => 'No está en el diccionario',
            self::REASON_REPEATED => 'Repetida: no suma ni resta',
            self::REASON_TRAP => 'Buen intento: esa no cuela',
            self::REASON_TAKEN => 'Ya la dijo otro jugador en la ronda: no suma',
            default => 'No valió',
        };
    }

    /**
     * Words the player tried, valid or not (skips excluded).
     *
     * @param  Builder<RunWord>  $query
     */
    public function scopeWords(Builder $query): void
    {
        $query->where('kind', self::KIND_WORD);
    }
}
