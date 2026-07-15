<?php

namespace Tests\Feature;

use App\Models\ProgressionRecommendation;
use App\Models\RoutineDay;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp(); $this->seed(); $this->user=User::where('email','owner@example.com')->firstOrFail(); $this->actingAs($this->user);
    }

    public function test_session_snapshot_sets_volume_and_completion_are_persisted(): void
    {
        $day=RoutineDay::where('day_type','training')->firstOrFail();
        $started=$this->postJson('/api/workouts/start',['routine_day_id'=>$day->id,'sleep_hours'=>7.5,'energy_level'=>4])->assertCreated()->json('data');
        $this->assertNotEmpty($started['routine_snapshot']);
        $this->assertCount($day->exercises()->count(),$started['exercises']);
        $this->postJson('/api/workouts/start',['routine_day_id'=>$day->id])->assertUnprocessable()->assertJsonValidationErrors('session');
        $exercise=$started['exercises'][0];
        $set=$this->postJson("/api/workout-exercises/{$exercise['id']}/sets",[
            'set_number'=>1,'set_type'=>'working','weight'=>20,'weight_unit'=>'lb','repetitions'=>10,'rir'=>2,'completed'=>true,
        ])->assertCreated()->json('set');
        $this->assertSame(200.0,(float)$set['volume']);
        $this->assertTrue($set['is_personal_record']);
        $finished=$this->postJson("/api/workouts/{$started['id']}/finish",['session_difficulty'=>3,'session_satisfaction'=>4])->assertOk()->json('data');
        $this->assertSame('completed',$finished['status']);
        $this->assertSame(200.0,(float)$finished['total_volume']);
        $this->assertGreaterThanOrEqual(125,$finished['xp_earned']);
    }

    public function test_substitution_keeps_planned_exercise_and_uses_independent_history(): void
    {
        $day=RoutineDay::where('name','Upper A')->firstOrFail();
        $session=$this->postJson('/api/workouts/start',['routine_day_id'=>$day->id])->assertCreated()->json('data');
        $item=$session['exercises'][0]; $alternative=$item['planned_exercise']['alternatives'][0];
        $updated=$this->postJson("/api/workout-exercises/{$item['id']}/substitute",['alternative_exercise_id'=>$alternative['id'],'reason'=>'equipment_busy'])->assertOk()->json('data');
        $this->assertTrue($updated['was_substituted']);
        $this->assertSame($item['planned_exercise']['id'],$updated['planned_exercise']['id']);
        $this->assertSame($alternative['id'],$updated['performed_exercise']['id']);
        $this->assertNull($updated['previous_performance_json']);
    }

    public function test_warmups_do_not_count_for_volume_or_records(): void
    {
        $day=RoutineDay::where('day_type','training')->firstOrFail(); $session=$this->postJson('/api/workouts/start',['routine_day_id'=>$day->id])->json('data'); $exercise=$session['exercises'][0];
        $set=$this->postJson("/api/workout-exercises/{$exercise['id']}/sets",['set_number'=>1,'set_type'=>'warmup','weight'=>10,'weight_unit'=>'lb','repetitions'=>10,'rir'=>4,'completed'=>true])->assertCreated()->json('set');
        $this->assertSame(0.0,(float)$set['volume']); $this->assertFalse($set['is_personal_record']);
        $this->postJson("/api/workouts/{$session['id']}/finish",[])->assertUnprocessable()->assertJsonValidationErrors('session');
        $finished=$this->postJson("/api/workouts/{$session['id']}/mark-partial",[])->assertOk()->json('data');
        $this->assertSame(0.0,(float)$finished['total_volume']);
    }

    public function test_duration_summary_and_historical_snapshot_remain_stable(): void
    {
        $day=RoutineDay::where('day_type','training')->firstOrFail();
        $started=$this->postJson('/api/workouts/start',['routine_day_id'=>$day->id])->assertCreated()->json('data');
        $session=WorkoutSession::findOrFail($started['id']);
        $session->update(['started_at'=>now()->subMinutes(10)]);
        $exercise=$started['exercises'][0];
        $originalSets=$exercise['planned']['target_sets'];

        $this->postJson("/api/workout-exercises/{$exercise['id']}/sets",[
            'set_number'=>1,
            'set_type'=>'working',
            'weight'=>20,
            'weight_unit'=>$exercise['performed_exercise']['default_weight_unit'],
            'repetitions'=>10,
            'rir'=>2,
            'completed'=>true,
        ])->assertCreated();

        $finished=$this->postJson("/api/workouts/{$started['id']}/finish",[])->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(599,$finished['duration_seconds']);
        $this->assertLessThanOrEqual(601,$finished['duration_seconds']);
        $this->assertSame(1,$finished['weekly_progress']['completed']);
        $this->assertSame(4,$finished['weekly_progress']['goal']);
        $this->assertNotEmpty($finished['improvements']);

        $day->exercises()->whereKey($exercise['planned']['id'])->update(['target_sets'=>9]);
        $historical=$this->getJson("/api/workouts/{$started['id']}")->assertOk()->json('data');
        $this->assertSame($originalSets,$historical['exercises'][0]['planned']['target_sets']);
        $this->assertSame($finished['routine_snapshot'],$historical['routine_snapshot']);
    }

    public function test_new_recommendation_supersedes_pending_recommendations_for_the_same_exercise(): void
    {
        $day=RoutineDay::where('day_type','training')->firstOrFail();
        $started=$this->postJson('/api/workouts/start',['routine_day_id'=>$day->id])->assertCreated()->json('data');
        $exercise=$started['exercises'][0];
        $old=ProgressionRecommendation::create([
            'user_id'=>$this->user->id,
            'exercise_id'=>$exercise['performed_exercise']['id'],
            'routine_exercise_id'=>$exercise['planned']['id'],
            'recommendation_type'=>'maintain',
            'current_weight'=>20,
            'weight_unit'=>'lb',
            'reason'=>'Recomendacion anterior.',
            'confidence'=>'medium',
            'status'=>'pending',
        ]);

        $this->postJson("/api/workout-exercises/{$exercise['id']}/sets",[
            'set_number'=>1,'set_type'=>'working','weight'=>20,'weight_unit'=>'lb','repetitions'=>8,'rir'=>2,'completed'=>true,
        ])->assertCreated();
        $this->postJson("/api/workouts/{$started['id']}/finish",[])->assertOk();

        $this->assertSame('superseded',$old->fresh()->status);
        $this->assertDatabaseHas('progression_recommendations',[
            'user_id'=>$this->user->id,
            'exercise_id'=>$exercise['performed_exercise']['id'],
            'source_workout_session_id'=>$started['id'],
            'status'=>'pending',
        ]);
    }
}
