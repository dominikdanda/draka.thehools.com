<?php

namespace App\Services\Career;

use App\Models\CareerStage;
use App\Models\Player;
use App\Models\PlayerCareerItem;
use Illuminate\Validation\ValidationException;

class CareerItemInventoryService
{
    public function quantity(Player $player, ?int $careerItemId): int
    {
        if ($careerItemId === null) {
            return 0;
        }

        return (int) PlayerCareerItem::query()
            ->where('player_id', $player->id)
            ->where('career_item_id', $careerItemId)
            ->value('quantity');
    }

    public function hasRequired(Player $player, CareerStage $stage): bool
    {
        if ($stage->required_item_id !== null && $this->quantity($player, $stage->required_item_id) < 1) {
            return false;
        }

        if ($stage->required_item_id_2 !== null && $this->quantity($player, $stage->required_item_id_2) < 1) {
            return false;
        }

        return true;
    }

    public function consumeRequired(Player $player, CareerStage $stage): void
    {
        if ($stage->required_item_id !== null) {
            $this->remove($player, $stage->required_item_id, 1);
        }

        if ($stage->required_item_id_2 !== null) {
            $this->remove($player, $stage->required_item_id_2, 1);
        }
    }

    public function grant(Player $player, ?int $careerItemId, int $quantity = 1): void
    {
        if ($careerItemId === null || $quantity <= 0) {
            return;
        }

        $holding = PlayerCareerItem::firstOrCreate(
            [
                'player_id' => $player->id,
                'career_item_id' => $careerItemId,
            ],
            ['quantity' => 0],
        );

        $holding->quantity += $quantity;
        $holding->save();
    }

    public function remove(Player $player, int $careerItemId, int $quantity): void
    {
        $holding = PlayerCareerItem::query()
            ->where('player_id', $player->id)
            ->where('career_item_id', $careerItemId)
            ->first();

        if ($holding === null || $holding->quantity < $quantity) {
            throw ValidationException::withMessages([
                'items' => 'Missing required career item.',
            ]);
        }

        $holding->quantity -= $quantity;

        if ($holding->quantity === 0) {
            $holding->delete();
        } else {
            $holding->save();
        }
    }
}
