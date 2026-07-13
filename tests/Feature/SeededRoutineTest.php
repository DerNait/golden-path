<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseAlternative;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\TrainingPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeededRoutineTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_plan_is_seeded_exactly_without_invented_weights(): void
    {
        $this->seed();
        $user=User::where('email','owner@example.com')->firstOrFail();
        $this->assertSame('body_recomposition',$user->profile->objective);
        $this->assertNull($user->profile->biological_sex);
        $this->assertNull($user->profile->initial_waist_cm);
        $routine=Routine::where('user_id',$user->id)->with('days.exercises')->firstOrFail();
        $this->assertCount(7,$routine->days);
        $this->assertCount(4,$routine->days->where('day_type','training'));
        $this->assertCount(3,$routine->days->where('day_type','rest'));
        $this->assertSame(27,$routine->days->sum(fn ($day)=>$day->exercises->count()));
        $this->assertSame(27,RoutineExercise::whereNull('target_weight')->count());
        $this->assertSame(Exercise::count(),Exercise::whereNull('youtube_url')->count());
        $this->assertGreaterThan(25,ExerciseAlternative::count());
        $expectedSets=[
            'Upper A'=>[3,3,2,2,2,2,2],
            'Lower A'=>[3,3,2,2,2,3],
            'Upper B'=>[3,3,2,2,2,2,2,2],
            'Lower B'=>[3,2,2,2,2,3],
        ];
        foreach($expectedSets as $dayName=>$sets) {
            $day=$routine->days->firstWhere('name',$dayName);
            $this->assertNotNull($day);
            $this->assertSame($sets,$day->exercises->pluck('target_sets')->map(fn ($value)=>(int)$value)->all());
            $this->assertTrue($day->exercises->every(fn ($item)=>$item->target_rir_min===1 && $item->target_rir_max===2));
        }
        $phase=TrainingPhase::where('user_id',$user->id)->where('status','active')->firstOrFail();
        $this->assertSame(48,$phase->planned_sessions);
        $this->assertSame(40,$phase->minimum_target_sessions);
    }

    public function test_seeders_can_run_twice_without_duplicate_domain_rows(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $user->update(['password' => 'changed-password']);
        $this->seed();

        $this->assertSame(1,User::count());
        $this->assertTrue(Hash::check('changed-password', $user->fresh()->password));
        $this->assertSame(7,\App\Models\RoutineDay::count());
        $this->assertSame(27,RoutineExercise::count());
        $this->assertSame(5,\App\Models\AvatarPhase::count());
        $this->assertSame(14,\App\Models\Achievement::count());
        $distinctPairs=ExerciseAlternative::query()
            ->select(['exercise_id','alternative_exercise_id'])
            ->distinct()
            ->get()
            ->count();
        $this->assertSame(ExerciseAlternative::count(),$distinctPairs);
    }
}
