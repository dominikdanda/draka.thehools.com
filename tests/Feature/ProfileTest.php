<?php

namespace Tests\Feature;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_update_profile_and_allocate_skill_points(): void
    {
        $player = Player::factory()->withCaptain()->create();

        $this->actingAs($player)
            ->put(route('profile.update'), [
                'avatar' => '🔥',
                'city' => 'Warszawa',
                'description' => 'Test profile',
            ])
            ->assertRedirect(route('profile.index'));

        $player->refresh();
        $this->assertSame('Warszawa', $player->city);

        $this->actingAs($player)
            ->post(route('profile.skills'), [
                'stat' => 'attack',
                'points' => 2,
            ])
            ->assertRedirect(route('profile.index'));

        $player->stats->refresh();
        $this->assertSame(2, $player->stats->skill_points_unspent);
        $this->assertSame(2, $player->stats->attack_skill_points);
        $this->assertSame(20, $player->stats->attackPoints());
    }

    public function test_logout_redirects_to_lobby(): void
    {
        $player = Player::factory()->withCaptain()->create();

        $this->actingAs($player)
            ->post(route('logout'))
            ->assertRedirect(config('thehools.lobby_url'));

        $this->assertGuest();
    }
}
