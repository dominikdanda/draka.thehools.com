<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LobbyLoginTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Keep all test schema changes isolated from configured databases.
        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', $connection);
        $app['config']->set('database.connections.lobby', $connection);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('thehools.world.server_id', 42);
        $app['db']->purge('sqlite');
        $app['db']->purge('lobby');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->travelTo(now()->startOfSecond());

        Schema::connection('lobby')->create('accounts_servers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('server_id');
            $table->string('uzyt_nazwa');
            $table->string('hash')->nullable();
            $table->string('client_details')->nullable();
            $table->timestamp('last_login');
        });
    }

    private function token(array $attributes = []): string
    {
        $token = str_repeat('a', 40);
        DB::connection('lobby')->table('accounts_servers')->insert(array_merge([
            'account_id' => 123,
            'server_id' => 42,
            'uzyt_nazwa' => 'TestPlayer',
            'hash' => $token,
            'client_details' => '127.0.0.1#PHPUnit',
            'last_login' => now()->subSeconds(19),
        ], $attributes));

        return $token;
    }

    private function assertRejected(string $token, int $status = 403): void
    {
        $this->get('/login/'.$token)->assertStatus($status);
        $this->assertGuest();
        $this->assertDatabaseCount('players', 0);
        $this->assertDatabaseCount('player_stats', 0);
        $this->assertDatabaseCount('player_resources', 0);
    }

    public function test_player_can_login_via_current_world_token(): void
    {
        $token = $this->token();
        $this->withSession(['probe' => 'preserved']);
        $sessionId = session()->getId();

        $this->get('/login/'.$token)->assertRedirect(route('captains.index'));

        $this->assertAuthenticated();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertDatabaseHas('players', ['lobby_account_id' => 123, 'nickname' => 'TestPlayer']);
        $this->assertDatabaseHas('player_stats', ['player_id' => auth()->id(), 'level' => 1]);
        $this->assertDatabaseCount('player_stats', 1);
        $this->assertDatabaseCount('player_resources', 1);
        $this->assertDatabaseHas('accounts_servers', ['hash' => null, 'client_details' => null], 'lobby');
    }

    public function test_token_for_another_world_is_rejected_without_consuming_it(): void
    {
        $token = $this->token(['server_id' => 43]);
        $this->assertRejected($token);
        $this->assertDatabaseHas('accounts_servers', [
            'hash' => $token,
            'client_details' => '127.0.0.1#PHPUnit',
        ], 'lobby');
    }

    public function test_token_at_expiration_boundary_is_rejected(): void
    {
        $token = $this->token(['last_login' => now()->subSeconds(20)]);
        $this->assertRejected($token);
        $this->assertDatabaseHas('accounts_servers', ['hash' => $token], 'lobby');
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->assertRejected($this->token(['last_login' => now()->subSeconds(21)]));
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->assertRejected(str_repeat('b', 40));
    }

    public function test_malformed_token_is_rejected_by_route(): void
    {
        $this->assertRejected('invalid', 404);
    }

    public function test_consumed_token_cannot_be_reused(): void
    {
        $token = $this->token();
        $this->get('/login/'.$token)->assertRedirect(route('captains.index'));
        auth()->logout();

        $this->get('/login/'.$token)->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('players', 1);
    }

    public function test_existing_player_with_captain_logs_in_to_dashboard(): void
    {
        $player = \App\Models\Player::factory()->withCaptain()->create(['lobby_account_id' => 123]);
        $captain = $player->captains()->sole();
        $token = $this->token();

        $this->withSession(['current_captain_id' => 999999])->get('/login/'.$token)
            ->assertRedirect(route('start'))->assertSessionHas('current_captain_id', $captain->id);
        $this->get(route('start'))->assertOk();
        $this->assertAuthenticatedAs($player);
    }

    public function test_missing_or_invalid_world_configuration_rejects_login(): void
    {
        $token = $this->token();
        foreach ([null, '', 0, -1, 'invalid'] as $serverId) {
            config(['thehools.world.server_id' => $serverId]);
            $this->assertRejected($token, 503);
        }
        $this->assertDatabaseHas('accounts_servers', ['hash' => $token], 'lobby');
    }
}
