<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\XpEvent;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_xp_events_are_idempotent_and_maximum_phase_never_decreases(): void
    {
        $this->seed(); $user=User::where('email','owner@example.com')->firstOrFail(); $service=app(GamificationService::class);
        $this->assertSame(100,$service->award($user->id,'test','unique-event',100,'Test'));
        $this->assertSame(0,$service->award($user->id,'test','unique-event',100,'Test'));
        $this->assertSame(1,XpEvent::where('event_key','unique-event')->count());
        $this->assertSame(100,$user->gameProfile()->first()->total_xp);
        $before=$service->refresh($user)->maximum_avatar_phase_id;
        $service->refresh($user);
        $this->assertSame($before,$user->gameProfile()->first()->maximum_avatar_phase_id);
    }
}
