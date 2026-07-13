<?php

namespace Tests\Unit;

use App\Services\Gamification\GamificationService;
use Tests\TestCase;

class GamificationServiceTest extends TestCase
{
    public function test_level_formula_matches_documented_thresholds(): void
    {
        $service=app(GamificationService::class);
        $this->assertSame(1,$service->levelForXp(0));
        $this->assertSame(2,$service->levelForXp(100));
        $this->assertSame(3,$service->levelForXp(300));
        $this->assertSame(4,$service->levelForXp(600));
        $this->assertSame(5,$service->levelForXp(1000));
    }
}
