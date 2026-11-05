<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerResources;
use App\Services\Player\PlayerBootstrapService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class PlayerBootstrapTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['db']->purge('sqlite');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function player(): Player
    {
        // Bypass factory callbacks so initialization is exercised by the service.
        return Player::create(['lobby_account_id' => 123, 'nickname' => 'BootstrapTester']);
    }

    private function bootstrap(Player $player): Player
    {
        return app(PlayerBootstrapService::class)->ensurePlayerState($player);
    }

    public function test_first_initialization_uses_configured_defaults(): void
    {
        config([
            'thehools.formulas.player.starting_level' => 2,
            'thehools.formulas.player.starting_skill_points' => 7,
            'thehools.formulas.player.base_health' => 80,
            'thehools.formulas.player.base_energy' => 60,
            'thehools.formulas.player.starting_money' => 25,
        ]);
        $player = $this->bootstrap($this->player());

        $this->assertSame(2, $player->stats->level);
        $this->assertSame(0, $player->stats->experience);
        $this->assertSame(7, $player->stats->skill_points_unspent);
        $this->assertSame(80, $player->stats->health);
        $this->assertSame(60, $player->resources->energy);
        $this->assertSame(25, $player->resources->money);
        $this->assertNotNull($player->resources->regenerated_at);
        $this->assertDatabaseCount('player_stats', 1);
        $this->assertDatabaseCount('player_resources', 1);
    }

    public function test_repeated_initialization_with_cached_null_relations_is_idempotent(): void
    {
        $player = $this->player()->load(['stats', 'resources']);
        $this->assertNull($player->stats);
        $this->assertNull($player->resources);

        $first = $this->bootstrap($player);
        $second = $this->bootstrap($player);
        $third = $this->bootstrap($second);

        $this->assertSame($first->stats->getAttributes(), $third->stats->getAttributes());
        $this->assertSame($first->resources->getAttributes(), $third->resources->getAttributes());
        $this->assertDatabaseCount('player_stats', 1);
        $this->assertDatabaseCount('player_resources', 1);
    }

    public function test_existing_stats_are_preserved_and_used_for_missing_resources(): void
    {
        $player = $this->player();
        $stats = $player->stats()->create([
            'level' => 8, 'experience' => 321, 'energy_skill_points' => 3,
            'skill_points_unspent' => 12, 'health' => 42,
        ]);
        $before = $stats->refresh()->getAttributes();

        $result = $this->bootstrap($player->load(['stats', 'resources']));

        $this->assertSame($before, $result->stats->getAttributes());
        $this->assertSame($stats->maxEnergy(), $result->resources->energy);
        $this->assertDatabaseCount('player_stats', 1);
        $this->assertDatabaseCount('player_resources', 1);
    }

    public function test_existing_resources_are_preserved_when_stats_are_missing(): void
    {
        $player = $this->player();
        $resources = $player->resources()->create([
            'energy' => 9, 'money' => 9876, 'regenerated_at' => now()->subDay(),
        ]);
        $before = $resources->refresh()->getAttributes();

        $result = $this->bootstrap($player->load(['stats', 'resources']));

        $this->assertSame($before, $result->resources->getAttributes());
        $this->assertDatabaseCount('player_stats', 1);
        $this->assertDatabaseCount('player_resources', 1);
    }

    public function test_stale_loaded_relations_do_not_overwrite_current_database_state(): void
    {
        $player = $this->bootstrap($this->player());
        $player->stats()->update(['level' => 12, 'experience' => 456, 'health' => 30]);
        $player->resources()->update(['energy' => 0, 'money' => 9999, 'regenerated_at' => null]);
        $stats = $player->stats()->first()->getAttributes();
        $resources = $player->resources()->first()->getAttributes();

        $result = $this->bootstrap($player);

        $this->assertSame($stats, $result->stats->getAttributes());
        $this->assertSame($resources, $result->resources->getAttributes());
        $this->assertDatabaseCount('player_stats', 1);
        $this->assertDatabaseCount('player_resources', 1);
    }

    public function test_resource_creation_failure_rolls_back_stats_creation(): void
    {
        $player = $this->player();
        $event = 'eloquent.creating: '.PlayerResources::class;
        Event::listen($event, function () {
            throw new RuntimeException('Simulated resource creation failure');
        });

        try {
            $this->bootstrap($player);
            $this->fail('Expected resource creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated resource creation failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('player_stats', 0);
        $this->assertDatabaseCount('player_resources', 0);
        $this->bootstrap($player);
        $this->assertDatabaseCount('player_stats', 1);
        $this->assertDatabaseCount('player_resources', 1);
    }

    public function test_stats_unique_constraint_rejects_duplicate_current_state(): void
    {
        $player = $this->bootstrap($this->player());
        $this->expectException(QueryException::class);
        $player->stats()->create(['level' => 99]);
    }

    public function test_resources_unique_constraint_rejects_duplicate_current_state(): void
    {
        $player = $this->bootstrap($this->player());
        $this->expectException(QueryException::class);
        $player->resources()->create(['energy' => 99]);
    }

    public function test_migration_refuses_existing_duplicates_without_changing_data(): void
    {
        $migration = require database_path('migrations/2026_10_15_100000_add_unique_player_id_to_player_stats.php');
        $migration->down();
        $player = $this->player();
        $player->stats()->create(['level' => 8, 'experience' => 123]);
        $player->stats()->create(['level' => 2, 'experience' => 45]);
        $before = DB::table('player_stats')->orderBy('id')->get()->toArray();

        try {
            $migration->up();
            $this->fail('Expected duplicate detection to stop the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate player_stats.player_id', $exception->getMessage());
        }

        $this->assertEquals($before, DB::table('player_stats')->orderBy('id')->get()->toArray());
    }
}
