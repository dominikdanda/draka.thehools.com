<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStats extends Model
{
    protected $fillable = [
        'player_id',
        'level',
        'experience',
        'skill_points_unspent',
        'attack_skill_points',
        'defense_skill_points',
        'energy_skill_points',
        'health',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function attackPoints(): int
    {
        return $this->attack_skill_points * (int) config('thehools.formulas.player.attack_per_skill_point');
    }

    public function defensePoints(): int
    {
        return $this->defense_skill_points * (int) config('thehools.formulas.player.defense_per_skill_point');
    }

    public function maxEnergy(): int
    {
        $base = (int) config('thehools.formulas.player.base_energy');

        return $base + ($this->energy_skill_points * (int) config('thehools.formulas.player.energy_per_skill_point'));
    }

    public function maxHealth(): int
    {
        return (int) config('thehools.formulas.player.max_health');
    }
}
