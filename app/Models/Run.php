<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Run extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'score',
        'mode',
        'difficulty',
        'countdown_time',
        'words_correct',
        'words_error',
        'chars_skipped'
    ];

    /**
     * Conversión de los JSON a arrays
     * @var array
     */
    protected $casts = [
        'words_correct' => 'array',
        'words_error' => 'array',
        'chars_skipped' => 'array',
    ];

    /**
     * Obtiene el usuario que tiene la run
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Añade 1 punto a la puntuación
     * @return void
     */
    public function addPoints($points)
    {
        $this->score += $points;
        $this->save();
    }

    /**
     * Añade 1 punto a la puntuación
     * @return void
     */
    public function removePoints($points)
    {
        $this->score -= $points;
        $this->save();
    }

    /**
     * Añade una palabra correcta a la matriz JSON
     * @param string $word
     * @return void
     */
    public function addCorrectWord($word)
    {
        $wordsCorrect = $this->words_correct ?? [];
        $wordsCorrect[] = $word;
        
        $this->words_correct = $wordsCorrect;
        $this->save();
    }

    /**
     * Añade una palabra errónea a la matriz JSON
     * @param string $word
     * @return void
     */
    public function addErrorWord($word)
    {
        $wordsError = $this->words_error ?? [];
        $wordsError[] = $word;
        
        $this->words_error = $wordsError;
        $this->save();
    }

    /**
     * Añade las letras que se han saltado
     * @param array $chars
     * @return void
     */
    public function addCharsSkipped($chars)
    {
        $charsSkipped = $this->chars_skipped ?? [];
        $charsSkipped[] = $chars;
        
        $this->chars_skipped = $charsSkipped;
        $this->save();
    }
} 