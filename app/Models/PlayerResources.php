<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerResources extends Model
{
    protected $fillable = [
        'player_id',
        'energy',
        'money',
        'regenerated_at',
        'health_regenerated_at',
    ];

    protected function casts(): array
    {
        return [
            'regenerated_at' => 'datetime',
            'health_regenerated_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
