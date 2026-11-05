<?php

namespace App\Services\Career;

use App\Models\CareerChapter;
use App\Models\CareerStage;
use App\Models\Player;
use App\Models\PlayerChapterProgress;
use App\Models\PlayerStageProgress;

class ChapterMasteryService
{
    public function masteryThreshold(int $masteryLevel): int
    {
        $thresholds = config('thehools.formulas.career.chapter_mastery_thresholds', [100, 200, 300, 400, 500]);

        return $thresholds[$masteryLevel] ?? 600;
    }

    public function canAdvanceChapterMastery(Player $player, CareerChapter $chapter): bool
    {
        $progress = $this->chapterProgress($player, $chapter);
        $maxLevel = (int) config('thehools.formulas.career.chapter_mastery_levels', 5);

        if ($progress->mastery_level >= $maxLevel) {
            return false;
        }

        $threshold = $this->masteryThreshold($progress->mastery_level);

        foreach ($chapter->stages as $stage) {
            $stageProgress = $this->stageProgress($player, $stage);

            if ($stageProgress->mastery_progress < $threshold) {
                return false;
            }
        }

        return $chapter->stages->isNotEmpty();
    }

    public function advanceChapterMastery(Player $player, CareerChapter $chapter): ?PlayerChapterProgress
    {
        if (! $this->canAdvanceChapterMastery($player, $chapter)) {
            return null;
        }

        $progress = $this->chapterProgress($player, $chapter);
        $newLevel = $progress->mastery_level + 1;
        $progress->mastery_level = $newLevel;
        $progress->save();

        return $progress;
    }

    public function chapterProgress(Player $player, CareerChapter $chapter): PlayerChapterProgress
    {
        return PlayerChapterProgress::firstOrCreate(
            [
                'player_id' => $player->id,
                'chapter_id' => $chapter->id,
            ],
            ['mastery_level' => 0],
        );
    }

    public function stageProgress(Player $player, CareerStage $stage): PlayerStageProgress
    {
        return PlayerStageProgress::firstOrCreate(
            [
                'player_id' => $player->id,
                'stage_id' => $stage->id,
            ],
            ['mastery_progress' => 0, 'completions' => 0],
        );
    }

    public function masteryGain(int $currentProgress): float
    {
        if ($currentProgress < 100) {
            return 10;
        }

        if ($currentProgress < 200) {
            return 5;
        }

        if ($currentProgress < 300) {
            return 2;
        }

        if ($currentProgress < 400) {
            return 1;
        }

        return 0.5;
    }
}
