<?php

namespace Tests\Unit;

use App\Services\Game\ExperienceCalculator;
use Tests\TestCase;

class ExperienceCalculatorTest extends TestCase
{
    public function test_required_experience_uses_s3_curve(): void
    {
        $calculator = new ExperienceCalculator();

        $this->assertSame(0, $calculator->requiredForLevel(0));
        $this->assertGreaterThan(0, $calculator->requiredForLevel(1));
        $this->assertGreaterThan(
            $calculator->requiredForLevel(1),
            $calculator->requiredForLevel(5),
        );
    }
}
