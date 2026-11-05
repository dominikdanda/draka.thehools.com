<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Player\PlayerRegenerationService;
use Tests\TestCase;

class PlayerRegenerationTest extends TestCase
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

    private function player(array $attributes = []): Player
    {
        $player = Player::factory()->create($attributes);
        $player->stats->update(['health' => 50]);
        $player->resources->update(['energy' => 10, 'regenerated_at' => now()]);

        return $player->fresh(['stats', 'resources']);
    }

    private function apply(Player $player): void
    {
        app(PlayerRegenerationService::class)->apply($player);
    }

    public function test_first_full_interval_and_same_timestamp_are_counted_once(): void
    {
        $player = $this->player();
        $this->travel(60)->seconds();
        $this->apply($player);
        $this->assertSame(11, $player->resources->energy);
        $this->apply($player);
        $this->assertSame(11, $player->resources->energy);
        $this->assertSame(50, $player->stats->health);
    }

    public function test_requests_at_30_60_90_and_120_seconds_preserve_both_clocks(): void
    {
        $player = $this->player();
        foreach ([[10, 50], [11, 50], [11, 50], [12, 51]] as [$energy, $health]) {
            $this->travel(30)->seconds();
            $this->apply($player);
            $this->assertSame($energy, $player->resources->energy);
            $this->assertSame($health, $player->stats->health);
        }
    }

    public function test_requests_at_90_and_120_seconds_preserve_leftover_time(): void
    {
        $player = $this->player();
        $start = now()->copy();
        $this->travel(90)->seconds();
        $this->apply($player);
        $this->assertSame(11, $player->resources->energy);
        $this->assertTrue($player->resources->regenerated_at->equalTo($start->copy()->addSeconds(60)));
        $this->assertTrue($player->resources->health_regenerated_at->equalTo($start));
        $this->travel(30)->seconds();
        $this->apply($player);
        $this->assertSame(12, $player->resources->energy);
        $this->assertSame(51, $player->stats->health);
    }

    public function test_non_multiple_intervals_and_health_amount_are_independent(): void
    {
        config(['thehools.formulas.health.regen_seconds' => 100, 'thehools.formulas.health.regen_amount' => 3]);
        $player = $this->player();
        foreach ([[60, 11, 50], [40, 11, 53], [20, 12, 53], [80, 13, 56]] as [$seconds, $energy, $health]) {
            $this->travel($seconds)->seconds();
            $this->apply($player);
            $this->assertSame($energy, $player->resources->energy);
            $this->assertSame($health, $player->stats->health);
        }
    }

    public function test_caps_do_not_bank_elapsed_time_or_reduce_values(): void
    {
        $player = $this->player();
        $player->stats()->update(['health' => 100]);
        $player->resources()->update(['energy' => 40]);
        $this->travel(3600)->seconds();
        $this->apply($player);
        $this->assertSame(40, $player->resources->energy);
        $this->assertSame(100, $player->stats->health);
        $player->resources()->update(['energy' => 39]);
        $player->stats()->update(['health' => 99]);
        $this->apply($player);
        $this->assertSame(39, $player->resources->energy);
        $this->assertSame(99, $player->stats->health);
        $this->travel(120)->seconds();
        $this->apply($player);
        $this->assertSame(40, $player->resources->energy);
        $this->assertSame(100, $player->stats->health);
        $player->resources()->update(['energy' => 45]);
        $this->apply($player);
        $this->assertSame(45, $player->resources->energy);
    }

    public function test_premium_rates(): void
    {
        $player = $this->player(['is_premium' => true]);
        $this->travel(45)->seconds();
        $this->apply($player);
        $this->assertSame(11, $player->resources->energy);
        $this->assertSame(50, $player->stats->health);
        $this->travel(45)->seconds();
        $this->apply($player);
        $this->assertSame(12, $player->resources->energy);
        $this->assertSame(51, $player->stats->health);
    }

    public function test_premium_until_is_read_from_database_instead_of_cached_player(): void
    {
        $player = $this->player();
        $player->newQuery()->whereKey($player->id)->update(['premium_until' => now()->addDay()]);
        $this->travel(90)->seconds();
        $this->apply($player);
        $this->assertSame(12, $player->resources->energy);
        $this->assertSame(51, $player->stats->health);
    }

    public function test_missing_future_and_invalid_timestamps_restart_without_rewards(): void
    {
        $player = $this->player();
        foreach ([null, now()->addHour()->toDateTimeString(), 'invalid', '0000-00-00 00:00:00'] as $timestamp) {
            // Raw update allows testing corrupt legacy timestamps without Eloquent casts.
            $player->getConnection()->table('player_resources')->where('player_id', $player->id)->update([
                'regenerated_at' => $timestamp, 'health_regenerated_at' => $timestamp,
            ]);
            $this->apply($player);
            $this->assertSame(10, $player->resources->energy);
            $this->assertSame(50, $player->stats->health);
            $this->assertTrue($player->resources->regenerated_at->equalTo(now()));
            $this->assertTrue($player->resources->health_regenerated_at->equalTo(now()));
        }
        $this->travel(120)->seconds();
        $this->apply($player);
        $this->assertSame(12, $player->resources->energy);
        $this->assertSame(51, $player->stats->health);
    }

    public function test_stale_loaded_relations_do_not_overwrite_other_fields(): void
    {
        $player = $this->player();
        $player->stats()->update(['experience' => 777, 'level' => 8, 'skill_points_unspent' => 13, 'health' => 20]);
        $player->resources()->update(['money' => 999, 'energy' => 2]);
        $beforeStats = $player->stats()->first()->getAttributes();
        $beforeResources = $player->resources()->first()->getAttributes();
        $this->travel(120)->seconds();
        $this->apply($player);
        $this->assertSame(4, $player->resources->energy);
        $this->assertSame(21, $player->stats->health);
        foreach (['experience', 'level', 'skill_points_unspent', 'attack_skill_points', 'defense_skill_points', 'energy_skill_points'] as $field) {
            $this->assertSame($beforeStats[$field], $player->stats->$field);
        }
        $this->assertSame($beforeResources['money'], $player->resources->money);
        $this->assertSame(4, $player->resources()->first()->energy);
    }

    public function test_missing_player_state_is_not_created_by_regeneration(): void
    {
        $player = Player::create(['lobby_account_id' => 456, 'nickname' => 'NoState']);
        $this->apply($player);
        $this->assertDatabaseCount('player_stats', 0);
        $this->assertDatabaseCount('player_resources', 0);
    }

    public function test_capped_energy_does_not_discard_health_progress(): void
    {
        $player = $this->player();
        $player->resources()->update(['energy' => 40]);
        $this->travel(60)->seconds();
        $this->apply($player);
        $this->travel(60)->seconds();
        $this->apply($player);
        $this->assertSame(40, $player->resources->energy);
        $this->assertSame(51, $player->stats->health);
    }
}
