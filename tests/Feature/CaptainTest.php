<?php

namespace Tests\Feature;

use App\Models\Captain;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class CaptainTest extends TestCase
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

    public function test_player_can_own_multiple_captains(): void
    {
        $player = Player::factory()->create();
        $captains = Captain::factory()->count(3)->for($player)->create();

        $this->assertEquals($captains->modelKeys(), $player->captains()->orderBy('id')->get()->modelKeys());
        foreach ($captains as $captain) {
            $this->assertTrue($captain->player->is($player));
            $this->assertNull($captain->dismissed_at);
        }
    }

    public function test_captains_of_different_players_remain_independent(): void
    {
        $first = Captain::factory()->create(['name' => 'Same name']);
        $second = Captain::factory()->create(['name' => 'Same name']);
        $secondBefore = $second->getAttributes();

        $first->update(['name' => 'Changed name', 'dismissed_at' => now()]);

        $this->assertNotSame($first->player_id, $second->player_id);
        $this->assertSame([$first->id], $first->player->captains()->pluck('id')->all());
        $this->assertSame([$second->id], $second->player->captains()->active()->pluck('id')->all());
        $this->assertEquals($secondBefore, $second->refresh()->getAttributes());
    }

    public function test_dismissed_captains_are_retained_but_excluded_from_active_queries(): void
    {
        $player = Player::factory()->create();
        $active = Captain::factory()->for($player)->create();
        $dismissed = Captain::factory()->for($player)->dismissed()->create();

        $this->assertSame([$active->id], $player->captains()->active()->pluck('id')->all());
        $this->assertSame([$active->id], Captain::active()->pluck('id')->all());
        $this->assertSame(2, $player->captains()->count());
        $this->assertTrue(Captain::findOrFail($dismissed->id)->player->is($player));
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $dismissed->fresh()->dismissed_at);
        $this->assertDatabaseCount('captains', 2);
    }

    public function test_captain_requires_a_player(): void
    {
        $this->expectException(QueryException::class);
        Captain::create(['name' => 'No owner']);
    }

    public function test_captain_cannot_reference_a_nonexistent_player(): void
    {
        $this->expectException(QueryException::class);
        Captain::create(['player_id' => 999999, 'name' => 'Missing owner']);
    }

    public function test_captain_requires_a_name(): void
    {
        $player = Player::factory()->create();
        $this->expectException(QueryException::class);
        $player->captains()->create([]);
    }

    public function test_player_deletion_cannot_cascade_into_captain_history(): void
    {
        $captain = Captain::factory()->dismissed()->create();

        try {
            $captain->player->delete();
            $this->fail('Expected the foreign key to preserve Captain history.');
        } catch (QueryException) {
            $this->assertDatabaseHas('players', ['id' => $captain->player_id]);
            $this->assertDatabaseHas('captains', ['id' => $captain->id]);
        }
    }
}
