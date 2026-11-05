<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CareerChapter;
use App\Models\Player;
use App\Services\Player\PlayerRegenerationService;
use Tests\TestCase;

class CareerStageUnlockingTest extends TestCase
{
    private Player $player;

    private Campaign $campaign;

    private CareerChapter $chapter;

    private array $stages;

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
        $this->player = Player::factory()->withCaptain()->create();
        app(PlayerRegenerationService::class)->apply($this->player);
        $this->actingAs($this->player);
        $this->campaign = Campaign::create(['slug' => 'classic', 'name' => 'Classic Campaign']);
        $this->chapter = $this->campaign->chapters()->create(['number' => 1, 'title' => 'First chapter']);
        for ($number = 1; $number <= 3; $number++) {
            $this->stages[$number] = $this->chapter->stages()->create([
                'number' => $number, 'global_number' => $number, 'title' => 'Stage '.$number,
                'energy_cost' => 1, 'money_reward_min' => 1, 'money_reward_max' => 2, 'experience_reward' => 1,
            ]);
        }
    }

    private function complete(int $number)
    {
        return $this->from($this->chapterUrl())->post(route('career.stages.complete', [
            $this->campaign, $this->chapter, $this->stages[$number],
        ]));
    }

    private function chapterUrl(): string
    {
        return route('career.chapters.show', [$this->campaign, $this->chapter]);
    }

    private function progress(int $number, int $completions, int $mastery = 0): void
    {
        $this->player->stageProgress()->updateOrCreate(['stage_id' => $this->stages[$number]->id], [
            'completions' => $completions, 'mastery_progress' => $mastery,
        ]);
    }

    public function test_first_stage_is_available_and_second_has_no_completion_form(): void
    {
        $this->get($this->chapterUrl())->assertOk()->assertSee('Etap 1: 0/10.')
            ->assertSee(route('career.stages.complete', [$this->campaign, $this->chapter, $this->stages[1]]), false)
            ->assertDontSee(route('career.stages.complete', [$this->campaign, $this->chapter, $this->stages[2]]), false);
        $this->complete(1)->assertSessionHasNoErrors()->assertRedirect($this->chapterUrl());
        $this->assertDatabaseHas('player_stage_progress', ['stage_id' => $this->stages[1]->id, 'completions' => 1]);
    }

    public function test_locked_posts_at_zero_and_nine_completions_have_no_effects(): void
    {
        foreach ([0, 9] as $count) {
            // High mastery must not replace the required completion count.
            $this->progress(1, $count, 500);
            $stats = $this->player->stats()->first()->getAttributes();
            $resources = $this->player->resources()->first()->getAttributes();
            $progress = $this->player->stageProgress()->get()->toArray();
            $this->complete(2)->assertSessionHasErrors('stage');
            $this->assertSame($stats, $this->player->stats()->first()->getAttributes());
            $this->assertSame($resources, $this->player->resources()->first()->getAttributes());
            $this->assertSame($progress, $this->player->stageProgress()->get()->toArray());
            $this->assertDatabaseCount('player_career_items', 0);
            $this->assertDatabaseCount('player_chapter_progress', 0);
        }
    }

    public function test_ten_successes_unlock_only_the_next_stage_and_keep_it_repeatable(): void
    {
        for ($count = 0; $count < 10; $count++) {
            $this->complete(1)->assertSessionHasNoErrors();
        }
        $this->complete(2)->assertSessionHasNoErrors();
        $this->complete(3)->assertSessionHasErrors('stage');
        for ($count = 1; $count < 10; $count++) {
            $this->complete(2)->assertSessionHasNoErrors();
        }
        $this->complete(3)->assertSessionHasNoErrors();
        $this->complete(2)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('player_stage_progress', ['stage_id' => $this->stages[1]->id, 'completions' => 10]);
        $this->assertDatabaseHas('player_stage_progress', ['stage_id' => $this->stages[2]->id, 'completions' => 11]);
        $this->assertDatabaseHas('player_stage_progress', ['stage_id' => $this->stages[3]->id, 'completions' => 1]);
    }

    public function test_ui_shows_real_completion_progress_and_unlocks_independently_of_mastery(): void
    {
        $this->progress(1, 4);
        $this->get($this->chapterUrl())->assertSee('Ukończ poprzedni etap 10 razy. Etap 1: 4/10.');
        $this->progress(1, 10, 0);
        $this->get($this->chapterUrl())
            ->assertSee(route('career.stages.complete', [$this->campaign, $this->chapter, $this->stages[2]]), false)
            ->assertDontSee(route('career.stages.complete', [$this->campaign, $this->chapter, $this->stages[3]]), false);
        $this->complete(2)->assertSessionHasNoErrors();
    }

    public function test_chapter_level_and_previous_chapter_locks_still_apply(): void
    {
        $previous = $this->campaign->chapters()->create(['number' => 2, 'title' => 'Prerequisite']);
        $this->chapter->update(['required_player_level' => 5, 'required_chapter_id' => $previous->id]);
        $this->progress(1, 10, 100);
        $this->get($this->chapterUrl())->assertForbidden();
        $this->complete(2)->assertSessionHasErrors('chapter');
        $this->player->stats()->update(['level' => 5]);
        $this->complete(2)->assertSessionHasErrors('chapter');
        $this->player->chapterProgress()->create(['chapter_id' => $previous->id, 'mastery_level' => 1]);
        $this->complete(2)->assertSessionHasNoErrors();
    }

    public function test_missing_previous_stage_is_locked_without_creating_progress(): void
    {
        $stage = $this->chapter->stages()->create([
            'number' => 5, 'global_number' => 5, 'title' => 'Missing predecessor',
            'energy_cost' => 1, 'money_reward_min' => 1, 'money_reward_max' => 2, 'experience_reward' => 1,
        ]);
        $this->post(route('career.stages.complete', [$this->campaign, $this->chapter, $stage]))->assertSessionHasErrors('stage');
        $this->assertDatabaseCount('player_stage_progress', 0);
    }
}
