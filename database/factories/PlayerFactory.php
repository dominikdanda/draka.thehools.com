<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerResources;
use App\Models\PlayerStats;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'lobby_account_id' => fake()->unique()->numberBetween(1000, 999999),
            'nickname' => fake()->userName(),
            'avatar' => '🥷',
            'city' => fake()->city(),
            'description' => fake()->optional()->sentence(),
            'is_premium' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Player $player) {
            $stats = PlayerStats::create([
                'player_id' => $player->id,
                'level' => 1,
                'experience' => 0,
                'skill_points_unspent' => 4,
                'health' => 100,
            ]);

            PlayerResources::create([
                'player_id' => $player->id,
                'energy' => $stats->maxEnergy(),
                'money' => 0,
                'regenerated_at' => Carbon::now(),
            ]);
        });
    }

    public function premium(): static
    {
        return $this->state(fn () => ['is_premium' => true]);
    }

    public function withCaptain(): static
    {
        return $this->afterCreating(function (Player $player) {
            \App\Models\Captain::factory()->for($player)->create();
        });
    }
}
