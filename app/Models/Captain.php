<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Captain extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'name',
        'dismissed_at',
        'money',
        'materials',
        'chemicals',
        'respect_capacity',
        'respect_demand',
    ];

    protected $attributes = [
        'money' => 0,
        'materials' => 0,
        'chemicals' => 0,
        'respect_capacity' => 0,
        'respect_demand' => 0,
    ];

    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
            'money' => 'integer',
            'materials' => 'integer',
            'chemicals' => 'integer',
            'respect_capacity' => 'integer',
            'respect_demand' => 'integer',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }
}
