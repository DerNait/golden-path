<?php

namespace Tests\Feature;

use App\Models\RoutineDay;
use App\Models\TrainingPhase;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\WorkoutSession;
use App\Models\XpEvent;
use App\Services\Gamification\GamificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationCriticalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private GamificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-07-15 12:00:00');
        $this->seed();
        $this->user=User::where('email','owner@example.com')->firstOrFail();
        $this->service=app(GamificationService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_weekly_streak_counts_unique_planned_days_and_stores_bounded_scores(): void
    {
        $days=RoutineDay::where('day_type','training')->orderBy('weekday')->get();
        $previousWeek=CarbonImmutable::now()->subWeek()->startOfWeek();

        for($index=0;$index<4;$index++) {
            $this->completedSession($days[0],$previousWeek->addHours($index));
        }

        $profile=$this->service->refresh($this->user);
        $this->assertSame(0,$profile->current_weekly_streak);
        $this->assertSame(0,$profile->perfect_weeks);

        foreach($days->slice(1) as $day) {
            $this->completedSession($day,$previousWeek->addDays($day->weekday-1)->addHours(10));
        }

        $profile=$this->service->refresh($this->user);
        $achievementXp=$this->service->evaluateAchievements($this->user);
        $this->assertSame(1,$profile->current_weekly_streak);
        $this->assertSame(1,$profile->perfect_weeks);
        $this->assertGreaterThan(0,$achievementXp);
        $this->assertGreaterThanOrEqual(0,(float)$profile->adherence_last_28_days);
        $this->assertLessThanOrEqual(100,(float)$profile->adherence_last_28_days);
        $this->assertGreaterThanOrEqual(0,$profile->energy);
        $this->assertLessThanOrEqual(100,$profile->energy);
        $this->assertGreaterThanOrEqual(0,$profile->combat_power);
        $this->assertLessThanOrEqual(10000,$profile->combat_power);
        $this->assertTrue($this->unlocked('first-perfect-week'));
    }

    public function test_maximum_phase_is_permanent_while_active_phase_can_drop(): void
    {
        $day=RoutineDay::where('day_type','training')->firstOrFail();
        for($index=0;$index<6;$index++) {
            $this->completedSession($day,CarbonImmutable::now()->subDays($index));
        }
        $this->user->gameProfile()->update(['total_xp'=>700]);

        $unlocked=$this->service->refresh($this->user);
        $this->assertSame(2,$unlocked->maximumPhase->position);

        $this->user->gameProfile()->update(['total_xp'=>0]);
        $this->user->workouts()->delete();
        $afterDrop=$this->service->refresh($this->user->fresh());

        $this->assertSame(2,$afterDrop->maximumPhase->position);
        $this->assertSame(1,$afterDrop->activePhase->position);
    }

    public function test_return_xp_and_achievements_are_idempotent(): void
    {
        $day=RoutineDay::where('day_type','training')->firstOrFail();
        $this->completedSession($day,CarbonImmutable::now()->subDays(15));
        $current=$this->completedSession($day,CarbonImmutable::now());

        $first=$this->service->rewardWorkout($current);
        $totalAfterFirst=(int)$this->user->gameProfile()->value('total_xp');
        $sessionXp=(int)$current->fresh()->xp_earned;
        $second=$this->service->rewardWorkout($current);

        $this->assertGreaterThanOrEqual(275,$first);
        $this->assertSame(0,$second);
        $this->assertSame($totalAfterFirst,(int)$this->user->gameProfile()->value('total_xp'));
        $this->assertSame($sessionXp,(int)$current->fresh()->xp_earned);
        $this->assertSame(1,XpEvent::where('event_type','return_after_break')->count());
        $this->assertTrue($this->unlocked('return-after-break'));
        $this->assertTrue($this->unlocked('first-workout'));
    }

    public function test_completing_phase_awards_xp_and_unlocks_achievement_once(): void
    {
        $this->actingAs($this->user);
        $phase=TrainingPhase::where('status','active')->firstOrFail();

        $first=$this->postJson("/api/training-phases/{$phase->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status','completed')
            ->json('xp_earned');
        $second=$this->postJson("/api/training-phases/{$phase->id}/complete")
            ->assertOk()
            ->json('xp_earned');

        $this->assertSame(1000,$first);
        $this->assertSame(0,$second);
        $this->assertSame(1,XpEvent::where('event_type','training_phase_completed')->count());
        $this->assertTrue($this->unlocked('first-phase'));
    }

    public function test_consistent_month_and_five_improvements_unlock_their_achievements(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 12:00:00');
        $days=RoutineDay::where('day_type','training')->orderBy('weekday')->get();
        foreach([6,13,20,27] as $mondayDay) {
            $week=CarbonImmutable::create(2026,7,$mondayDay)->startOfWeek();
            foreach($days as $day) {
                $this->completedSession($day,$week->addDays($day->weekday-1)->addHours(10));
            }
        }

        $planned=$days->flatMap->exercises->take(5);
        foreach($planned as $index=>$item) {
            foreach([8,9] as $exposureIndex=>$repetitions) {
                $session=$this->completedSession($item->routineDay,CarbonImmutable::create(2026,7,2+$index+$exposureIndex*20));
                $performed=$session->exercises()->create([
                    'planned_exercise_id'=>$item->exercise_id,
                    'performed_exercise_id'=>$item->exercise_id,
                    'routine_exercise_id'=>$item->id,
                    'position'=>1,
                    'planned_snapshot_json'=>$item->toArray(),
                ]);
                $performed->sets()->create([
                    'set_number'=>1,
                    'set_type'=>'working',
                    'weight'=>20,
                    'weight_unit'=>'kg',
                    'repetitions'=>$repetitions,
                    'rir'=>2,
                    'completed'=>true,
                    'completed_at'=>$session->finished_at,
                ]);
            }
        }

        $this->service->refresh($this->user);
        $this->service->evaluateAchievements($this->user);

        $this->assertTrue($this->unlocked('consistent-month'));
        $this->assertTrue($this->unlocked('five-improvements'));
        $this->assertTrue($this->unlocked('four-perfect-weeks'));
    }

    private function completedSession(RoutineDay $day,CarbonImmutable $startedAt): WorkoutSession
    {
        return $this->user->workouts()->create([
            'routine_id'=>$day->routine_id,
            'routine_day_id'=>$day->id,
            'training_phase_id'=>TrainingPhase::where('status','active')->value('id'),
            'name'=>$day->name,
            'started_at'=>$startedAt,
            'finished_at'=>$startedAt->addHour(),
            'status'=>'completed',
            'duration_seconds'=>3600,
            'routine_snapshot_json'=>[],
        ]);
    }

    private function unlocked(string $slug): bool
    {
        return UserAchievement::where('user_id',$this->user->id)
            ->whereHas('achievement',fn ($query)=>$query->where('slug',$slug))
            ->exists();
    }
}
