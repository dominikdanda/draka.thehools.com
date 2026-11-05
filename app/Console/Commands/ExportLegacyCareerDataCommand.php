<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportLegacyCareerDataCommand extends Command
{
    protected $signature = 'thehools:export-career-data {--output=database/data/classic_campaign.json}';

    protected $description = 'Export legacy s4 career content into JSON for seeding (read-only reference).';

    public function handle(): int
    {
        $legacyRoot = realpath(base_path('../draka.thehools.com/s4'));

        if ($legacyRoot === false) {
            $this->error('Legacy path not found: ../draka.thehools.com/s4');

            return self::FAILURE;
        }

        $langPath = $legacyRoot.'/lang/pl';
        $tablicePath = $legacyRoot.'/tablice.php';
        $optionsPath = $legacyRoot.'/options/Options.php';

        if (! file_exists($tablicePath)) {
            $this->error('Missing legacy tablice.php');

            return self::FAILURE;
        }

        foreach (['tekst.php', 'tekst_nowa_historia.php'] as $langFile) {
            if (file_exists($langPath.'/'.$langFile)) {
                require $langPath.'/'.$langFile;
            }
        }

        require $tablicePath;

        require_once $optionsPath;
        $options = new \Options();
        $chapterCount = 47;
        $items = [];

        foreach ($przedmioty as $index => $item) {
            if ($index === 0 || ($item[0] ?? '') === '') {
                continue;
            }

            $items[] = [
                'legacy_id' => $index,
                'name' => $item[0],
                'value' => (int) ($item[1] ?? 0),
            ];
        }

        $chapters = [];

        for ($chapterNumber = 1; $chapterNumber <= $chapterCount; $chapterNumber++) {
            $chapterData = $rozdzialy[$chapterNumber] ?? null;

            if ($chapterData === null) {
                continue;
            }

            $rewardRow = $options->rozdzialyNagrody[$chapterNumber] ?? [0, 0, 0, 0, 0, 0, 0];

            $chapters[] = [
                'number' => $chapterNumber,
                'title' => trim((string) ($chapterData[0] ?? '')),
                'introduction' => trim((string) ($chapterData[1] ?? '')),
                'featured_item_legacy_ids' => array_values(array_map('intval', $chapterData[5] ?? [])),
                'mastery_rewards' => [
                    'attack' => (int) ($rewardRow[1] ?? 0),
                    'defense' => (int) ($rewardRow[2] ?? 0),
                    'energy' => (int) ($rewardRow[3] ?? 0),
                    'skill_points' => (int) ($rewardRow[4] ?? 0),
                    'money' => (int) ($rewardRow[5] ?? 0),
                ],
                'stages' => $this->stagesForChapter($historia, $chapterNumber),
            ];
        }

        $payload = [
            'campaign' => [
                'slug' => 'classic',
                'name' => 'Classic Campaign',
                'description' => 'Main story campaign imported from legacy TheHools.',
                'type' => 'classic',
                'sort_order' => 1,
            ],
            'items' => $items,
            'chapters' => $chapters,
        ];

        $output = base_path($this->option('output'));
        File::ensureDirectoryExists(dirname($output));
        File::put($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Exported career data to '.$output);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<int, mixed>>  $historia
     * @return array<int, array<string, mixed>>
     */
    private function stagesForChapter(array $historia, int $chapterNumber): array
    {
        $stages = [];
        $start = ($chapterNumber * 10) - 9;
        $end = $chapterNumber * 10;

        for ($globalNumber = $start; $globalNumber <= $end; $globalNumber++) {
            $row = $historia[$globalNumber] ?? null;

            if ($row === null) {
                continue;
            }

            $maxMoney = (int) ($row[3] ?? 0);

            $stages[] = [
                'number' => $globalNumber - $start + 1,
                'global_number' => $globalNumber,
                'title' => trim((string) ($row[0] ?? '')),
                'subtitle' => trim((string) ($row[1] ?? '')),
                'energy_cost' => (int) ceil((float) ($row[2] ?? 0)),
                'money_reward_min' => (int) max(1, intdiv($maxMoney, 2)),
                'money_reward_max' => $maxMoney,
                'experience_reward' => (int) ($row[4] ?? 0),
                'reward_item_legacy_id' => (int) ($row[6] ?? 0) ?: null,
                'reward_item_legacy_id_2' => (int) ($row[7] ?? 0) ?: null,
                'required_item_legacy_id' => (int) ($row[8] ?? 0) ?: null,
                'required_item_legacy_id_2' => (int) ($row[9] ?? 0) ?: null,
            ];
        }

        return $stages;
    }
}
