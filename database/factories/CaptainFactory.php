<?php

namespace Database\Factories;

use App\Models\Captain;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Captain>
 */
class CaptainFactory extends Factory
{
    protected $model = Captain::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'name' => fake()->name(),
            'dismissed_at' => null,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn () => ['dismissed_at' => now()]);
    }
}
