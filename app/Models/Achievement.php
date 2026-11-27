<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $description
 * @property string $icon
 * @property bool $hidden
 * @property int $sort
 */
class Achievement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'name', 'description', 'icon', 'hidden', 'sort'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['hidden' => 'boolean'];
    }

    /**
     * Usuarios que tienen el logro
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('run_id', 'unlocked_at')
            ->withTimestamps();
    }
}
