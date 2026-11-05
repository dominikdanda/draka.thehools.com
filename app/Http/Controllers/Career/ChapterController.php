<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CareerChapter;
use App\Services\Career\ChapterAccessService;
use App\Services\Career\ChapterMasteryService;
use App\Services\Career\StageAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChapterController extends Controller
{
    public function __construct(
        private readonly ChapterAccessService $chapterAccessService,
        private readonly ChapterMasteryService $chapterMasteryService,
        private readonly StageAccessService $stageAccessService,
    ) {}

    public function show(Campaign $campaign, CareerChapter $chapter): View
    {
        abort_unless($chapter->campaign_id === $campaign->id, 404);

        $player = Auth::user();
        $player->load(['stats', 'resources', 'stageProgress', 'careerItems.careerItem']);

        abort_unless($this->chapterAccessService->isChapterAccessible($player, $chapter), 403);

        $chapter->load(['stages.rewardItem', 'stages.requiredItem', 'stages.requiredItem2']);

        $chapterProgress = $this->chapterMasteryService->chapterProgress($player, $chapter);
        $accessible = $this->chapterAccessService->isChapterAccessible($player, $chapter);

        $stageProgressMap = $player->stageProgress->keyBy('stage_id');
        $stageRequirements = [];
        foreach ($chapter->stages as $stage) {
            $stage->setRelation('chapter', $chapter);
            $stageRequirements[$stage->id] = $this->stageAccessService->unmetRequirement($player, $stage);
        }

        return view('career.chapters.show', [
            'campaign' => $campaign,
            'chapter' => $chapter,
            'player' => $player,
            'chapterProgress' => $chapterProgress,
            'accessible' => $accessible,
            'stageProgressMap' => $stageProgressMap,
            'stageRequirements' => $stageRequirements,
        ]);
    }
}
