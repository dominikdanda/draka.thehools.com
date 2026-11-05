<?php

namespace Tests\Feature;

use App\Models\Player;
use Tests\TestCase;

class DashboardTest extends TestCase
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
    }

    public function test_start_page_shows_live_player_data(): void
    {
        $player = Player::factory()->withCaptain()->create([
            'nickname' => 'AlphaTester',
            'avatar' => '😎',
        ]);

        $player->stats->update(['level' => 3, 'experience' => 10]);
        $player->resources->update(['money' => 999999, 'energy' => 25]);
        $player->captains()->sole()->update(['money' => 500]);

        $response = $this->actingAs($player)->get(route('start'));

        $response->assertOk();
        $response->assertSee('AlphaTester');
        $response->assertSee('500');
        $response->assertDontSee('999 999');
        $response->assertSee('Poziom 3');
    }
}
