<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareerChapter extends Model
{
    protected $fillable = [
        'campaign_id',
        'number',
        'title',
        'introduction',
        'artwork_path',
        'required_player_level',
        'required_chapter_id',
        'featured_item_legacy_ids',
        'mastery_rewards',
    ];

    protected function casts(): array
    {
        return [
            'featured_item_legacy_ids' => 'array',
            'mastery_rewards' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function requiredChapter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'required_chapter_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CareerStage::class, 'chapter_id')->orderBy('number');
    }
}
