<?php

namespace App\Services\Game;

use App\Models\Player;
use App\Models\PlayerStats;

class LevelUpService
{
    public function __construct(
        private readonly ExperienceCalculator $experienceCalculator,
    ) {}

    public function applyExperience(Player $player, int $amount): void
    {
        $stats = $player->stats;

        if ($stats === null) {
            return;
        }

        $stats->experience += $amount;

        while ($this->shouldLevelUp($stats)) {
            $this->levelUp($stats);
        }

        $stats->save();
    }

    private function shouldLevelUp(PlayerStats $stats): bool
    {
        $required = $this->experienceCalculator->requiredForLevel($stats->level);

        return $required > 0 && $stats->experience >= $required;
    }

    private function levelUp(PlayerStats $stats): void
    {
        $required = $this->experienceCalculator->requiredForLevel($stats->level);
        $stats->experience -= $required;
        $stats->level++;
        $stats->skill_points_unspent += (int) config('thehools.formulas.player.skill_points_per_level');
    }
}
