<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User;

class Player extends User
{
    use HasFactory;

    protected $fillable = [
        'lobby_account_id',
        'nickname',
        'avatar',
        'city',
        'description',
        'is_premium',
        'premium_until',
    ];

    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
            'premium_until' => 'datetime',
        ];
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PlayerStats::class);
    }

    public function resources(): HasOne
    {
        return $this->hasOne(PlayerResources::class);
    }

    public function captains(): HasMany
    {
        return $this->hasMany(Captain::class);
    }

    public function careerItems(): HasMany
    {
        return $this->hasMany(PlayerCareerItem::class);
    }

    public function chapterProgress(): HasMany
    {
        return $this->hasMany(PlayerChapterProgress::class);
    }

    public function stageProgress(): HasMany
    {
        return $this->hasMany(PlayerStageProgress::class);
    }

    public function hasActivePremium(): bool
    {
        if ($this->is_premium) {
            return true;
        }

        return $this->premium_until !== null && $this->premium_until->isFuture();
    }
}
