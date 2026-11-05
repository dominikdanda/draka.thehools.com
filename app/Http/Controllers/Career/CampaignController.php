<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Career\ChapterAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = Campaign::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->withCount('chapters')
            ->get();

        return view('career.campaigns.index', [
            'campaigns' => $campaigns,
            'player' => Auth::user(),
        ]);
    }

    public function show(Campaign $campaign, ChapterAccessService $chapterAccessService): View
    {
        $player = Auth::user();
        $player->load(['stats', 'chapterProgress']);

        $lockedChapterShown = false;
        $requirements = [];
        $chapters = $campaign->chapters()->get()->filter(function ($chapter) use ($player, $chapterAccessService, &$lockedChapterShown, &$requirements) {
            $unmet = $chapterAccessService->unmetRequirements($player, $chapter);
            if ($unmet === []) {
                $requirements[$chapter->id] = [];

                return true;
            }
            if ($lockedChapterShown) {
                return false;
            }
            $lockedChapterShown = true;
            $requirements[$chapter->id] = $unmet;

            return true;
        });

        return view('career.campaigns.show', [
            'campaign' => $campaign,
            'chapters' => $chapters,
            'player' => $player,
            'requirements' => $requirements,
        ]);
    }
}
