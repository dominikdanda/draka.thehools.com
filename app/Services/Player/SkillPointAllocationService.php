<?php

namespace App\Services\Player;

use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Validation\ValidationException;

class SkillPointAllocationService
{
    public function allocate(Player $player, string $stat, int $points): PlayerStats
    {
        $stats = $player->stats;

        if ($stats === null) {
            throw ValidationException::withMessages([
                'skill' => 'Player stats are missing.',
            ]);
        }

        if ($points < 1) {
            throw ValidationException::withMessages([
                'points' => 'Allocate at least one point.',
            ]);
        }

        if ($points > $stats->skill_points_unspent) {
            throw ValidationException::withMessages([
                'points' => 'Not enough skill points.',
            ]);
        }

        match ($stat) {
            'attack' => $stats->attack_skill_points += $points,
            'defense' => $stats->defense_skill_points += $points,
            'energy' => $stats->energy_skill_points += $points,
            default => throw ValidationException::withMessages([
                'stat' => 'Invalid skill stat.',
            ]),
        };

        $stats->skill_points_unspent -= $points;
        $stats->save();

        $this->capEnergyToMax($player->fresh(['stats', 'resources']));

        return $stats->fresh();
    }

    private function capEnergyToMax(Player $player): void
    {
        $resources = $player->resources;
        $stats = $player->stats;

        if ($resources === null || $stats === null) {
            return;
        }

        $resources->energy = min($resources->energy, $stats->maxEnergy());
        $resources->save();
    }
}
