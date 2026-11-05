<?php

namespace App\Http\Controllers\Career;

use App\Actions\Career\CompleteStageAction;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CareerChapter;
use App\Models\CareerStage;
use Illuminate\Http\RedirectResponse;

class StageController extends Controller
{
    public function __construct(
        private readonly CompleteStageAction $completeStageAction,
    ) {}

    public function complete(Campaign $campaign, CareerChapter $chapter, CareerStage $stage): RedirectResponse
    {
        abort_unless($chapter->campaign_id === $campaign->id, 404);
        abort_unless($stage->chapter_id === $chapter->id, 404);

        $player = auth()->user();
        $result = $this->completeStageAction->execute($player, $stage);

        return redirect()
            ->route('career.chapters.show', [$campaign, $chapter])
            ->with('stage_result', $result);
    }
}
