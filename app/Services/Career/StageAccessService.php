<?php

namespace App\Services\Career;

use App\Models\CareerStage;
use App\Models\Player;

class StageAccessService
{
    public const REQUIRED_COMPLETIONS = 10;

    public function __construct(private readonly ChapterAccessService $chapterAccessService) {}

    public function unmetRequirement(Player $player, CareerStage $stage): ?string
    {
        $stage->loadMissing('chapter.stages');
        $player->loadMissing('stageProgress');

        $chapterRequirements = $this->chapterAccessService->unmetRequirements($player, $stage->chapter);
        if ($chapterRequirements !== []) {
            return implode(' ', $chapterRequirements);
        }
        if ($stage->number === 1) {
            return null;
        }

        $previous = $stage->chapter->stages->firstWhere('number', $stage->number - 1);
        if ($previous === null) {
            return 'Poprzedni etap jest niedostępny.';
        }
        $completions = (int) ($player->stageProgress->firstWhere('stage_id', $previous->id)?->completions ?? 0);

        if ($completions >= self::REQUIRED_COMPLETIONS) {
            return null;
        }

        return 'Ukończ poprzedni etap '.self::REQUIRED_COMPLETIONS.' razy. Etap '.$previous->number.': '.$completions.'/'.self::REQUIRED_COMPLETIONS.'.';
    }
}
