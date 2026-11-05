<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Captain;
use App\Models\Player;
use Tests\TestCase;

class CaptainOnboardingTest extends TestCase
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

    public function test_new_player_is_redirected_without_a_redirect_loop(): void
    {
        $this->actingAs(Player::factory()->create())->get(route('start'))->assertRedirect(route('captains.index'));
        $this->get(route('captains.index'))->assertOk()->assertSee('Zrekrutuj kapitana');
        $this->assertDatabaseCount('captains', 0);
        $this->post(route('logout'))->assertRedirect(config('thehools.lobby_url'));
        $this->assertGuest();
    }

    public function test_career_get_and_post_are_blocked_before_regeneration_or_rewards(): void
    {
        $player = Player::factory()->create();
        $player->stats->update(['health' => 30]);
        $player->resources->update(['energy' => 10, 'regenerated_at' => now()->subHour()]);
        $stats = $player->stats->fresh()->getAttributes();
        $resources = $player->resources->fresh()->getAttributes();
        $campaign = Campaign::create(['slug' => 'classic', 'name' => 'Classic']);
        $chapter = $campaign->chapters()->create(['number' => 1, 'title' => 'First']);
        $stage = $chapter->stages()->create([
            'number' => 1, 'global_number' => 1, 'title' => 'Stage', 'energy_cost' => 1,
            'money_reward_min' => 1, 'money_reward_max' => 2, 'experience_reward' => 1,
        ]);
        $this->actingAs($player);
        foreach ([route('career.index'), route('career.campaigns.show', $campaign), route('career.chapters.show', [$campaign, $chapter])] as $url) {
            $this->get($url)->assertRedirect(route('captains.index'));
        }
        $this->post(route('career.stages.complete', [$campaign, $chapter, $stage]))->assertRedirect(route('captains.index'));
        $this->assertSame($stats, $player->stats->fresh()->getAttributes());
        $this->assertSame($resources, $player->resources->fresh()->getAttributes());
        $this->assertDatabaseCount('player_stage_progress', 0);
        $this->assertDatabaseCount('player_chapter_progress', 0);
        $this->assertDatabaseCount('player_career_items', 0);
    }

    public function test_recruitment_selects_captain_and_returns_to_dashboard(): void
    {
        $player = Player::factory()->create();
        $this->actingAs($player)->post(route('captains.store'), ['name' => 'Pierwszy'])
            ->assertRedirect(route('start'));
        $captain = $player->captains()->sole();
        $this->assertSame($captain->id, session('current_captain_id'));
        $this->get(route('start'))->assertOk()->assertSee('Kapitan został zrekrutowany i wybrany.');
        $this->get(route('career.index'))->assertOk();
    }

    public function test_existing_captain_is_selected_when_session_is_missing_or_invalid(): void
    {
        $player = Player::factory()->withCaptain()->create();
        $captain = $player->captains()->sole();
        $foreign = Captain::factory()->create();
        $dismissed = Captain::factory()->for($player)->dismissed()->create();
        foreach ([null, $foreign->id, $dismissed->id, 999999, ['invalid']] as $selection) {
            $this->actingAs($player)->withSession(['current_captain_id' => $selection])->get(route('career.index'))
                ->assertOk()->assertSessionHas('current_captain_id', $captain->id);
        }
        $this->assertDatabaseCount('captains', 3);
    }

    public function test_dismissed_captain_cannot_satisfy_onboarding(): void
    {
        $player = Player::factory()->create();
        $captain = Captain::factory()->for($player)->dismissed()->create();
        $before = $captain->fresh()->getAttributes();
        $this->actingAs($player)->withSession(['current_captain_id' => $captain->id])->get(route('start'))
            ->assertRedirect(route('captains.index'))->assertSessionMissing('current_captain_id');
        $this->get(route('captains.index'))->assertOk()->assertSee('Zrekrutuj kapitana');
        $this->assertSame($before, $captain->fresh()->getAttributes());
    }

    public function test_multiple_captains_require_explicit_selection_without_a_loop(): void
    {
        $player = Player::factory()->create();
        $captains = Captain::factory()->count(2)->for($player)->create();
        $this->actingAs($player)->get(route('career.index'))->assertRedirect(route('captains.index'));
        $this->get(route('captains.index'))->assertOk()->assertSee('Wybierz kapitana');
        $this->post(route('captains.select'), ['captain_id' => $captains[1]->id])->assertRedirect(route('captains.index'));
        $this->get(route('career.index'))->assertOk()->assertSessionHas('current_captain_id', $captains[1]->id);
    }
}
