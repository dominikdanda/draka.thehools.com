<?php

namespace App\Services\Career;

use App\Models\CareerChapter;
use App\Models\Player;
use App\Services\Player\SkillPointAllocationService;

class ChapterMasteryRewardService
{
    public function __construct(
        private readonly SkillPointAllocationService $skillPointAllocationService,
    ) {}

    /**
     * @return array<string, int>
     */
    public function apply(Player $player, CareerChapter $chapter, int $newMasteryLevel): array
    {
        $rewardLevel = (int) config('thehools.formulas.career.mastery_reward_at_level', 2);

        if ($newMasteryLevel !== $rewardLevel) {
            return [];
        }

        $rewards = $chapter->mastery_rewards ?? [];

        if ($rewards === []) {
            return [];
        }

        $player->load(['stats', 'resources']);
        $applied = [];

        if (! empty($rewards['money'])) {
            $player->resources->money += (int) $rewards['money'];
            $player->resources->save();
            $applied['money'] = (int) $rewards['money'];
        }

        if (! empty($rewards['attack'])) {
            $player->stats->attack_skill_points += (int) $rewards['attack'];
            $applied['attack'] = (int) $rewards['attack'];
        }

        if (! empty($rewards['defense'])) {
            $player->stats->defense_skill_points += (int) $rewards['defense'];
            $applied['defense'] = (int) $rewards['defense'];
        }

        if (! empty($rewards['energy'])) {
            $player->stats->energy_skill_points += (int) $rewards['energy'];
            $applied['energy'] = (int) $rewards['energy'];
        }

        if (! empty($rewards['skill_points'])) {
            $player->stats->skill_points_unspent += (int) $rewards['skill_points'];
            $applied['skill_points'] = (int) $rewards['skill_points'];
        }

        $player->stats->save();

        return $applied;
    }
}
