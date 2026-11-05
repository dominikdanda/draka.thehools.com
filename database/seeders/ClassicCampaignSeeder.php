<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CareerChapter;
use App\Models\CareerItem;
use App\Models\CareerStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ClassicCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/classic_campaign.json');

        if (! File::exists($path)) {
            $this->command?->warn('Missing '.$path.' — run php artisan thehools:export-career-data first.');

            return;
        }

        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $itemMap = [];

        foreach ($data['items'] as $item) {
            $model = CareerItem::updateOrCreate(
                ['legacy_id' => $item['legacy_id']],
                [
                    'name' => $item['name'],
                    'value' => $item['value'],
                ],
            );

            $itemMap[$item['legacy_id']] = $model->id;
        }

        $campaign = Campaign::updateOrCreate(
            ['slug' => $data['campaign']['slug']],
            [
                'name' => $data['campaign']['name'],
                'description' => $data['campaign']['description'] ?? null,
                'type' => $data['campaign']['type'] ?? 'classic',
                'is_active' => true,
                'sort_order' => $data['campaign']['sort_order'] ?? 0,
            ],
        );

        $previousChapterId = null;

        foreach ($data['chapters'] as $chapterData) {
            $chapter = CareerChapter::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'number' => $chapterData['number'],
                ],
                [
                    'title' => $chapterData['title'],
                    'introduction' => $chapterData['introduction'] ?? null,
                    'required_player_level' => 0,
                    'required_chapter_id' => $previousChapterId,
                    'featured_item_legacy_ids' => $chapterData['featured_item_legacy_ids'] ?? [],
                    'mastery_rewards' => $chapterData['mastery_rewards'] ?? [],
                ],
            );

            foreach ($chapterData['stages'] as $stageData) {
                CareerStage::updateOrCreate(
                    [
                        'chapter_id' => $chapter->id,
                        'number' => $stageData['number'],
                    ],
                    [
                        'global_number' => $stageData['global_number'],
                        'title' => $stageData['title'],
                        'subtitle' => $stageData['subtitle'] ?? null,
                        'energy_cost' => $stageData['energy_cost'],
                        'money_reward_min' => $stageData['money_reward_min'],
                        'money_reward_max' => $stageData['money_reward_max'],
                        'experience_reward' => $stageData['experience_reward'],
                        'reward_item_id' => $this->mapItem($itemMap, $stageData['reward_item_legacy_id'] ?? null),
                        'reward_item_id_2' => $this->mapItem($itemMap, $stageData['reward_item_legacy_id_2'] ?? null),
                        'required_item_id' => $this->mapItem($itemMap, $stageData['required_item_legacy_id'] ?? null),
                        'required_item_id_2' => $this->mapItem($itemMap, $stageData['required_item_legacy_id_2'] ?? null),
                    ],
                );
            }

            $previousChapterId = $chapter->id;
        }
    }

    /**
     * @param  array<int, int>  $itemMap
     */
    private function mapItem(array $itemMap, ?int $legacyId): ?int
    {
        if ($legacyId === null || $legacyId <= 0) {
            return null;
        }

        return $itemMap[$legacyId] ?? null;
    }
}
