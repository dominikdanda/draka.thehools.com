<?php

namespace App\Services\Game;

class ExperienceCalculator
{
    public function requiredForLevel(int $level): int
    {
        if ($level <= 0) {
            return 0;
        }

        $multiplier = (float) config('thehools.formulas.experience.multiplier', 1.0);

        return (int) floor($this->legacyS3Experience($level) * $multiplier);
    }

    public function totalRequiredForLevel(int $level): int
    {
        $total = 0;

        for ($current = 1; $current <= $level; $current++) {
            $total += $this->requiredForLevel($current);
        }

        return $total;
    }

    private function legacyS3Experience(int $level): int
    {
        $level++;

        return (int) (
            (5 / 3) * (
                pow($level, 3)
                - (6 * pow($level, 2))
                + (17 * $level - 12)
            )
        );
    }
}
