<?php

namespace Tests\Feature;

use App\Models\BodyMeasurement;
use App\Models\Exercise;
use App\Models\ProgressionRecommendation;
use App\Models\RoutineDay;
use App\Models\TrainingPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_cannot_read_or_modify_foreign_resources(): void
    {
        $this->seed(); $owner=User::where('email','owner@example.com')->firstOrFail(); $other=User::factory()->create(); $this->actingAs($other);
        $exercise=Exercise::where('user_id',$owner->id)->firstOrFail();
        $this->getJson("/api/exercises/{$exercise->id}")->assertForbidden();

        $measurement=$owner->bodyMeasurements()->create(['recorded_on'=>today(),'body_weight_kg'=>59]);
        $this->deleteJson("/api/body-measurements/{$measurement->id}")->assertForbidden();
    }
    public function test_user_cannot_manage_foreign_workouts_routine_phases_or_recommendations(): void
    {
        $this->seed();
        $owner=User::where('email','owner@example.com')->firstOrFail();
        $other=User::factory()->create();
        $this->actingAs($other);

        $day=RoutineDay::whereHas('routine',fn ($query)=>$query->where('user_id',$owner->id))->where('day_type','training')->firstOrFail();
        $this->putJson("/api/routine-days/{$day->id}",[
            'name'=>$day->name,
            'weekday'=>$day->weekday,
            'day_type'=>$day->day_type,
            'estimated_minutes'=>$day->estimated_minutes,
            'notes'=>$day->notes,
        ])->assertForbidden();

        $phase=TrainingPhase::where('user_id',$owner->id)->firstOrFail();
        $this->postJson("/api/training-phases/{$phase->id}/complete")->assertForbidden();

        $planned=$day->exercises()->firstOrFail();
        $recommendation=ProgressionRecommendation::create([
            'user_id'=>$owner->id,
            'exercise_id'=>$planned->exercise_id,
            'routine_exercise_id'=>$planned->id,
            'recommendation_type'=>'maintain',
            'reason'=>'Propiedad ajena.',
            'confidence'=>'low',
            'status'=>'pending',
        ]);
        $this->postJson("/api/progression/recommendations/{$recommendation->id}/ignore")->assertForbidden();

        $session=$owner->workouts()->create([
            'routine_id'=>$day->routine_id,
            'routine_day_id'=>$day->id,
            'name'=>$day->name,
            'started_at'=>now(),
            'status'=>'in_progress',
            'routine_snapshot_json'=>[],
        ]);
        $this->getJson("/api/workouts/{$session->id}")->assertForbidden();
        $this->putJson("/api/workouts/{$session->id}",['notes'=>'Intento ajeno'])->assertForbidden();
        $this->postJson("/api/workouts/{$session->id}/cancel")->assertForbidden();
    }
}
