<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CareerChapter;
use App\Models\Player;
use App\Services\Career\ChapterAccessService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CareerChapterDiscoveryTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['db']->purge('sqlite');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->travelTo(now()->startOfSecond());
    }

    private function campaign(int $count = 4): Campaign
    {
        $campaign = Campaign::create(['slug' => 'classic', 'name' => 'Classic Campaign']);
        $previous = null;
        for ($number = 1; $number <= $count; $number++) {
            $previous = $campaign->chapters()->create([
                'number' => $number, 'title' => 'ChapterTitle'.$number,
                'introduction' => 'SecretDescription'.$number,
                'required_chapter_id' => $previous?->id,
            ]);
        }

        return $campaign;
    }

    private function master(Player $player, CareerChapter $chapter): void
    {
        $player->chapterProgress()->create(['chapter_id' => $chapter->id, 'mastery_level' => 1]);
    }

    public function test_new_player_sees_first_chapter_and_one_locked_teaser(): void
    {
        $campaign = $this->campaign();
        $player = Player::factory()->withCaptain()->create();
        $chapters = $campaign->chapters;
        $response = $this->actingAs($player)->get(route('career.campaigns.show', $campaign));
        $response->assertOk()->assertSee('ChapterTitle1')->assertSee('ChapterTitle2')
            ->assertSee('Osiągnij co najmniej 1 poziom mistrzostwa')
            ->assertDontSee('ChapterTitle3')->assertDontSee('ChapterTitle4')
            ->assertDontSee('SecretDescription3')->assertDontSee('SecretDescription4');
        $response->assertSee(route('career.chapters.show', [$campaign, $chapters[0]]), false);
        $response->assertDontSee(route('career.chapters.show', [$campaign, $chapters[1]]), false);
        $response->assertDontSee(route('career.chapters.show', [$campaign, $chapters[2]]), false);
    }

    public function test_unlocking_second_chapter_reveals_third_as_teaser(): void
    {
        $campaign = $this->campaign();
        $player = Player::factory()->withCaptain()->create();
        $this->master($player, $campaign->chapters[0]);
        $response = $this->actingAs($player)->get(route('career.campaigns.show', $campaign));
        $response->assertOk()->assertSeeInOrder(['ChapterTitle1', 'ChapterTitle2', 'ChapterTitle3'])
            ->assertDontSee('ChapterTitle4')->assertSee('Mistrzostwo 1/5');
        $response->assertSee(route('career.chapters.show', [$campaign, $campaign->chapters[1]]), false);
        $response->assertDontSee(route('career.chapters.show', [$campaign, $campaign->chapters[2]]), false);
    }

    public function test_level_and_mastery_requirements_are_reported_only_when_unmet(): void
    {
        $campaign = $this->campaign();
        $player = Player::factory()->withCaptain()->create();
        $second = $campaign->chapters[1];
        $second->update(['required_player_level' => 5]);
        $url = route('career.campaigns.show', $campaign);
        $this->actingAs($player)->get($url)->assertSee('Wymagany poziom gracza: 5.')
            ->assertSee('Osiągnij co najmniej 1 poziom mistrzostwa');
        $this->master($player, $campaign->chapters[0]);
        $this->get($url)->assertSee('Wymagany poziom gracza: 5.')
            ->assertDontSee('Osiągnij co najmniej 1 poziom mistrzostwa')->assertDontSee('ChapterTitle3');
        $player->stats()->update(['level' => 5]);
        $this->get($url)->assertSee('ChapterTitle3')->assertDontSee('Wymagany poziom gracza: 5.');
    }

    public function test_locked_get_is_forbidden_and_post_has_no_gameplay_effects(): void
    {
        $campaign = $this->campaign();
        $player = Player::factory()->withCaptain()->create();
        $chapter = $campaign->chapters[1];
        $stage = $chapter->stages()->create([
            'number' => 1, 'global_number' => 1, 'title' => 'LockedStage',
            'energy_cost' => 1, 'money_reward_min' => 1, 'money_reward_max' => 2, 'experience_reward' => 1,
        ]);
        app(\App\Services\Player\PlayerRegenerationService::class)->apply($player);
        $stats = $player->stats->getAttributes();
        $resources = $player->resources->getAttributes();
        $this->actingAs($player)->get(route('career.chapters.show', [$campaign, $chapter]))
            ->assertForbidden()->assertDontSee('LockedStage');
        $this->post(route('career.stages.complete', [$campaign, $chapter, $stage]))
            ->assertSessionHasErrors('chapter');
        $this->assertSame($stats, $player->stats->fresh()->getAttributes());
        $this->assertSame($resources, $player->resources->fresh()->getAttributes());
        $this->assertDatabaseCount('player_stage_progress', 0);
        $this->assertDatabaseCount('player_chapter_progress', 0);
        $this->assertDatabaseCount('player_career_items', 0);
    }

    public function test_level_locked_first_chapter_is_also_protected(): void
    {
        $campaign = $this->campaign();
        $chapter = $campaign->chapters[0];
        $chapter->update(['required_player_level' => 5]);
        $this->actingAs(Player::factory()->withCaptain()->create())->get(route('career.chapters.show', [$campaign, $chapter]))
            ->assertForbidden();
        $this->get(route('career.campaigns.show', $campaign))->assertSee('ChapterTitle1')
            ->assertSee('Wymagany poziom gracza: 5.')->assertDontSee('ChapterTitle2');
    }

    public function test_fully_unlocked_campaign_shows_every_chapter_with_links(): void
    {
        $campaign = $this->campaign();
        $player = Player::factory()->withCaptain()->create();
        foreach ($campaign->chapters as $chapter) {
            $this->master($player, $chapter);
        }
        $response = $this->actingAs($player)->get(route('career.campaigns.show', $campaign));
        $response->assertOk()->assertDontSee('Rozdział zablokowany.');
        foreach ($campaign->chapters as $chapter) {
            $response->assertSee($chapter->title)
                ->assertSee(route('career.chapters.show', [$campaign, $chapter]), false);
        }
    }

    public function test_empty_campaign_has_an_empty_state(): void
    {
        $campaign = $this->campaign(0);
        $this->actingAs(Player::factory()->withCaptain()->create())->get(route('career.campaigns.show', $campaign))
            ->assertOk()->assertSee('Brak rozdziałów w tej kampanii.');
    }

    public function test_access_checks_load_progress_once_for_all_chapters(): void
    {
        $campaign = $this->campaign(47);
        $player = Player::factory()->withCaptain()->create();
        $chapters = $campaign->chapters;
        $service = app(ChapterAccessService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($chapters as $chapter) {
            $service->unmetRequirements($player, $chapter);
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $progressQueries = array_filter($queries, fn ($query) => str_contains($query['query'], 'player_chapter_progress'));
        $this->assertCount(1, $progressQueries);
    }
}
