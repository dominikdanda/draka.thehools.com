<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CareerChapter;
use App\Models\CareerStage;
use App\Models\Player;
use Database\Seeders\ClassicCampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClassicCampaignSeeder::class);
    }

    public function test_career_flow_lists_campaign_and_completes_first_stage(): void
    {
        $player = Player::factory()->withCaptain()->create();
        $campaign = Campaign::where('slug', 'classic')->firstOrFail();
        $chapter = CareerChapter::where('campaign_id', $campaign->id)->where('number', 1)->firstOrFail();
        $stage = CareerStage::where('chapter_id', $chapter->id)->where('number', 1)->firstOrFail();

        $this->actingAs($player)
            ->get(route('career.index'))
            ->assertOk()
            ->assertSee('Classic Campaign');

        $this->actingAs($player)
            ->post(route('career.stages.complete', [$campaign, $chapter, $stage]))
            ->assertRedirect(route('career.chapters.show', [$campaign, $chapter]));

        $player->refresh();
        $this->assertGreaterThan(0, $player->resources->money);
        $this->assertGreaterThan(0, $player->stats->experience);

        $this->actingAs($player)
            ->get(route('career.chapters.show', [$campaign, $chapter]))
            ->assertOk()
            ->assertSee('Etapy');
    }
}
