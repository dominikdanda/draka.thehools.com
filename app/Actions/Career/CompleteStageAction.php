<?php

namespace App\Actions\Career;

use App\Models\CareerStage;
use App\Models\Player;
use App\Services\Career\CareerItemInventoryService;
use App\Services\Career\ChapterAccessService;
use App\Services\Career\ChapterMasteryRewardService;
use App\Services\Career\ChapterMasteryService;
use App\Services\Career\StageAccessService;
use App\Services\Game\LevelUpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteStageAction
{
    public function __construct(
        private readonly ChapterAccessService $chapterAccessService,
        private readonly ChapterMasteryService $chapterMasteryService,
        private readonly CareerItemInventoryService $inventoryService,
        private readonly LevelUpService $levelUpService,
        private readonly ChapterMasteryRewardService $masteryRewardService,
        private readonly StageAccessService $stageAccessService,
    ) {}

    /**
     * @return array{
     *     money: int,
     *     experience: int,
     *     energy_spent: int,
     *     mastery_gain: float,
     *     mastery_progress: int,
     *     chapter_mastery_advanced: bool,
     *     mastery_rewards: array<string, int>
     * }
     */
    public function execute(Player $player, CareerStage $stage): array
    {
        $stage->loadMissing('chapter.stages');

        $chapter = $stage->chapter;
        $player->load(['stats', 'resources', 'chapterProgress', 'stageProgress']);

        if (! $this->chapterAccessService->isChapterAccessible($player, $chapter)) {
            throw ValidationException::withMessages([
                'chapter' => 'Chapter is locked.',
            ]);
        }

        $requirement = $this->stageAccessService->unmetRequirement($player, $stage);
        if ($requirement !== null) {
            throw ValidationException::withMessages(['stage' => $requirement]);
        }
        $this->assertEnergyAvailable($player, $stage);
        $this->assertRequiredItems($player, $stage);

        return DB::transaction(function () use ($player, $stage, $chapter) {
            $player->load(['stats', 'resources']);
            $progress = $this->chapterMasteryService->stageProgress($player, $stage);

            $energyCost = $stage->energy_cost;
            $moneyReward = random_int(
                (int) max(1, intdiv($stage->money_reward_max, 2)),
                (int) $stage->money_reward_max,
            );

            $this->inventoryService->consumeRequired($player, $stage);

            $player->resources->energy -= $energyCost;
            $player->resources->money += $moneyReward;
            $player->resources->save();

            $masteryGain = $this->chapterMasteryService->masteryGain($progress->mastery_progress);
            $progress->mastery_progress = min(
                (int) config('thehools.formulas.career.stage_mastery_cap', 500),
                (int) round($progress->mastery_progress + $masteryGain),
            );
            $progress->completions++;
            $progress->save();

            $this->inventoryService->grant($player, $stage->reward_item_id);
            $this->inventoryService->grant($player, $stage->reward_item_id_2);

            $this->levelUpService->applyExperience($player, $stage->experience_reward);

            $chapterAdvanced = false;
            $masteryRewards = [];

            if ($this->chapterMasteryService->canAdvanceChapterMastery($player, $chapter)) {
                $newProgress = $this->chapterMasteryService->advanceChapterMastery($player, $chapter);

                if ($newProgress !== null) {
                    $chapterAdvanced = true;
                    $masteryRewards = $this->masteryRewardService->apply($player, $chapter, $newProgress->mastery_level);
                }
            }

            return [
                'money' => $moneyReward,
                'experience' => $stage->experience_reward,
                'energy_spent' => $energyCost,
                'mastery_gain' => $masteryGain,
                'mastery_progress' => $progress->mastery_progress,
                'chapter_mastery_advanced' => $chapterAdvanced,
                'mastery_rewards' => $masteryRewards,
            ];
        });
    }

    private function assertEnergyAvailable(Player $player, CareerStage $stage): void
    {
        $energy = $player->resources?->energy ?? 0;

        if ($energy < $stage->energy_cost) {
            throw ValidationException::withMessages([
                'energy' => 'Not enough energy.',
            ]);
        }
    }

    private function assertRequiredItems(Player $player, CareerStage $stage): void
    {
        if (! $this->inventoryService->hasRequired($player, $stage)) {
            throw ValidationException::withMessages([
                'items' => 'Missing required career item.',
            ]);
        }
    }
}
