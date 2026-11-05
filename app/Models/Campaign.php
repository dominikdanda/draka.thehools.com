<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'artwork_path',
        'type',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
        'completion_rewards',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'completion_rewards' => 'array',
        ];
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(CareerChapter::class)->orderBy('number');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
