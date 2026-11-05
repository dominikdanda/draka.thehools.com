<?php

namespace App\Services\Player;

use App\Models\Player;
use App\Models\PlayerResources;
use App\Models\PlayerStats;
use Illuminate\Support\Carbon;

class PlayerBootstrapService
{
    public function ensurePlayerState(Player $player): Player
    {
        return $player->getConnection()->transaction(function () use ($player) {
            // Lock the parent even when neither one-to-one record exists yet.
            $player = $player->newQuery()->lockForUpdate()->findOrFail($player->getKey());

            $stats = $player->stats()->first() ?? $this->createStats($player);

            if (! $player->resources()->exists()) {
                $this->createResources($player, $stats);
            }

            return $player->load(['stats', 'resources']);
        }, 3);
    }

    private function createStats(Player $player): PlayerStats
    {
        return $player->stats()->create([
            'level' => (int) config('thehools.formulas.player.starting_level'),
            'experience' => 0,
            'skill_points_unspent' => (int) config('thehools.formulas.player.starting_skill_points'),
            'health' => (int) config('thehools.formulas.player.base_health'),
        ]);
    }

    private function createResources(Player $player, PlayerStats $stats): PlayerResources
    {
        return $player->resources()->create([
            'energy' => $stats->maxEnergy(),
            'money' => (int) config('thehools.formulas.player.starting_money'),
            'regenerated_at' => Carbon::now(),
        ]);
    }
}
