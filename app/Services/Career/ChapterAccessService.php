<?php

namespace App\Services\Career;

use App\Models\CareerChapter;
use App\Models\Player;

class ChapterAccessService
{
    public function isChapterAccessible(Player $player, CareerChapter $chapter): bool
    {
        return $this->unmetRequirements($player, $chapter) === [];
    }

    /** @return list<string> */
    public function unmetRequirements(Player $player, CareerChapter $chapter): array
    {
        $player->loadMissing(['stats', 'chapterProgress']);
        $requirements = [];

        if ($chapter->required_player_level > 0) {
            $level = $player->stats?->level ?? 1;

            if ($level < $chapter->required_player_level) {
                $requirements[] = 'Wymagany poziom gracza: '.$chapter->required_player_level.'.';
            }
        }

        if ($chapter->required_chapter_id === null) {
            return $requirements;
        }

        $previousProgress = $player->chapterProgress->firstWhere('chapter_id', $chapter->required_chapter_id);

        if ($previousProgress === null || $previousProgress->mastery_level <= 0) {
            $requirements[] = 'Osiągnij co najmniej 1 poziom mistrzostwa wymaganego poprzedniego rozdziału.';
        }

        return $requirements;
    }

    public function highestUnlockedChapterNumber(Player $player, int $campaignId): int
    {
        $chapters = CareerChapter::query()
            ->where('campaign_id', $campaignId)
            ->orderBy('number')
            ->get();

        $highest = 1;

        foreach ($chapters as $chapter) {
            if ($this->isChapterAccessible($player, $chapter)) {
                $highest = max($highest, $chapter->number);
            }
        }

        return $highest;
    }
}
