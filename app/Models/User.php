<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the runs for the user.
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class, 'user_id', 'id');
    }

    public function achievements()
    {
        return $this->hasManyThrough(Achievement::class, UserAchievement::class);
    }

    // Verificar si un usuario tiene un logro específico
    public function hasAchievement($achievementId)
    {
        return $this->userAchievements()->where('achievement_id', $achievementId)->exists();
    }

    // Desbloquear un logro
    public function unlockAchievement($achievementId)
    {
        if (! $this->hasAchievement($achievementId)) {
            UserAchievement::create([
                'user_id' => $this->id,
                'achievement_id' => $achievementId,
                'unlocked_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    // Obtener logros desbloqueados
    public function unlockedAchievements()
    {
        return $this->achievements()->whereHas('userAchievements', function ($query) {
            $query->where('user_id', $this->id)->whereNotNull('unlocked_at');
        });
    }
}
