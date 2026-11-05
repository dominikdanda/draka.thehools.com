<?php

namespace Tests\Feature;

use App\Actions\Captains\RecruitCaptainAction;
use App\Models\Campaign;
use App\Models\Captain;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaptainResourcesTest extends TestCase
{
    private const FIELDS = ['money', 'materials', 'chemicals', 'respect_capacity', 'respect_demand'];

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
        $this->travelTo(now()->startOfSecond());
    }

    private function migrate(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function resourceValues(string $html): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        $values = [];
        foreach ($xpath->query('//header//*[@data-captain-resource]') as $node) {
            $values[$node->getAttribute('data-captain-resource')] = trim($node->textContent);
        }

        return $values;
    }

    public function test_new_recruited_captain_has_zero_balances_and_respect(): void
    {
        $this->migrate();
        $player = Player::factory()->create();
        $player->resources->update(['money' => 999999]);
        $captain = app(RecruitCaptainAction::class)->execute($player, 'New Captain');
        foreach (self::FIELDS as $field) {
            $this->assertSame(0, $captain->$field);
            $this->assertSame(0, $captain->fresh()->$field);
        }
        $this->assertSame(999999, $player->resources->fresh()->money);
    }

    public function test_additive_migration_initializes_existing_captains_without_changing_history(): void
    {
        $newMigration = '2026_10_21_100000_add_resources_to_captains.php';
        $paths = array_values(array_map(fn ($path) => 'database/migrations/'.basename($path), array_filter(
            glob(database_path('migrations/*.php')),
            fn ($path) => basename($path) !== $newMigration,
        )));
        $this->artisan('migrate', ['--force' => true, '--path' => $paths])->assertExitCode(0);
        $player = Player::factory()->create();
        $ids = [];
        foreach ([null, now()->subDay()->toDateTimeString()] as $dismissedAt) {
            $ids[] = DB::table('captains')->insertGetId([
                'player_id' => $player->id, 'name' => 'Existing Captain', 'dismissed_at' => $dismissedAt,
                'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
            ]);
        }
        $before = DB::table('captains')->orderBy('id')->get();
        $this->migrate();
        foreach ($ids as $index => $id) {
            $captain = Captain::findOrFail($id);
            foreach (self::FIELDS as $field) {
                $this->assertSame(0, $captain->$field);
            }
            foreach ((array) $before[$index] as $field => $value) {
                $this->assertEquals($value, $captain->getRawOriginal($field));
            }
        }
        $this->assertDatabaseCount('captains', 2);
    }

    public function test_balances_are_independent_and_repeated_initialization_does_not_reset_them(): void
    {
        $this->migrate();
        $player = Player::factory()->create();
        $first = Captain::factory()->for($player)->create(['money' => 123, 'materials' => 45, 'chemicals' => 67, 'respect_capacity' => 10, 'respect_demand' => 3]);
        $second = Captain::factory()->for($player)->create();
        $before = $first->fresh()->getAttributes();
        $this->actingAs($player)->withSession(['current_captain_id' => $first->id]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->migrate();
            $this->get(route('start'))->assertOk();
            $this->get(route('captains.index'))->assertOk();
        }
        $this->assertSame($before, $first->fresh()->getAttributes());
        foreach (self::FIELDS as $field) {
            $this->assertSame(0, $second->fresh()->$field);
        }
        $this->assertDatabaseCount('captains', 2);
    }

    public function test_header_uses_selected_captain_on_dashboard_and_career(): void
    {
        $this->migrate();
        $player = Player::factory()->create();
        $player->resources->update(['money' => 999999]);
        $first = Captain::factory()->for($player)->create(['money' => 123, 'materials' => 45, 'chemicals' => 67, 'respect_capacity' => 10, 'respect_demand' => 3]);
        $second = Captain::factory()->for($player)->create(['money' => 456]);
        $foreign = Captain::factory()->create(['money' => 888888]);
        $this->actingAs($player)->withSession(['current_captain_id' => $first->id]);
        foreach (['start', 'career.index'] as $route) {
            $response = $this->get(route($route))->assertOk()->assertSee('Hajs')->assertSee('Materiały')->assertSee('Chemia')->assertSee('Respekt')
                ->assertDontSee('Gotówka')->assertDontSee('Zdrowie')->assertDontSee('999 999')->assertDontSee('888 888');
            $this->assertSame(['money' => '123', 'materials' => '45', 'chemicals' => '67', 'respect' => '3 / 10'], $this->resourceValues($response->getContent()));
        }
        $this->post(route('captains.select'), ['captain_id' => $second->id, 'return_to' => '/start'])->assertRedirect(route('start'));
        $this->assertSame('456', $this->resourceValues($this->get(route('start'))->getContent())['money']);
        $this->post(route('captains.select'), ['captain_id' => $foreign->id])->assertSessionHasErrors('captain_id');
        $this->assertSame($second->id, session('current_captain_id'));
    }

    public function test_career_keeps_global_energy_and_legacy_rewards_unchanged(): void
    {
        $this->migrate();
        $player = Player::factory()->withCaptain()->create();
        $captain = $player->captains()->sole();
        $campaign = Campaign::create(['slug' => 'classic', 'name' => 'Classic']);
        $chapter = $campaign->chapters()->create(['number' => 1, 'title' => 'First chapter']);
        $stage = $chapter->stages()->create([
            'number' => 1, 'global_number' => 1, 'title' => 'First stage', 'energy_cost' => 2,
            'money_reward_min' => 1, 'money_reward_max' => 2, 'experience_reward' => 1,
        ]);
        $this->actingAs($player)->get(route('career.chapters.show', [$campaign, $chapter]))->assertOk()->assertSee('Energia kariery: 40 / 40');
        $this->post(route('career.stages.complete', [$campaign, $chapter, $stage]))->assertSessionHasNoErrors();
        $this->assertSame(38, $player->resources->fresh()->energy);
        $this->assertGreaterThan(0, $player->resources->fresh()->money);
        $this->assertSame(1, $player->stats->fresh()->experience);
        foreach (self::FIELDS as $field) {
            $this->assertSame(0, $captain->fresh()->$field);
        }
    }
}
