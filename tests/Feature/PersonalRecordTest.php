<?php

namespace Tests\Feature;

use App\Models\PersonalRecord;
use App\Models\RoutineDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_record_types_are_unique_and_recalculate_after_changes(): void
    {
        $this->seed();
        $user=User::where('email','owner@example.com')->firstOrFail();
        $this->actingAs($user);
        $day=RoutineDay::where('day_type','training')->firstOrFail();
        $session=$this->postJson('/api/workouts/start',['routine_day_id'=>$day->id])->assertCreated()->json('data');
        $exercise=$session['exercises'][0];

        $first=$this->postJson("/api/workout-exercises/{$exercise['id']}/sets",[
            'set_number'=>1,
            'set_type'=>'working',
            'weight'=>20,
            'weight_unit'=>$exercise['performed_exercise']['default_weight_unit'],
            'repetitions'=>10,
            'rir'=>2,
            'completed'=>true,
        ])->assertCreated()->json('set');

        $this->assertTrue($first['is_personal_record']);
        $this->assertSame(4,PersonalRecord::where('user_id',$user->id)->where('exercise_id',$exercise['performed_exercise']['id'])->count());
        $this->assertEqualsCanonicalizing([
            'heaviest_weight',
            'most_repetitions',
            'best_set_volume',
            'estimated_one_rep_max',
        ],PersonalRecord::where('user_id',$user->id)->pluck('record_type')->all());

        $second=$this->postJson("/api/workout-exercises/{$exercise['id']}/sets",[
            'set_number'=>2,
            'set_type'=>'working',
            'weight'=>25,
            'weight_unit'=>$exercise['performed_exercise']['default_weight_unit'],
            'repetitions'=>9,
            'rir'=>1,
            'completed'=>true,
        ])->assertCreated()->json('set');

        $this->assertSame(4,PersonalRecord::where('user_id',$user->id)->where('exercise_id',$exercise['performed_exercise']['id'])->count());
        $this->assertSame('25.00',PersonalRecord::where('record_type','heaviest_weight')->value('value'));
        $this->assertSame('225.00',PersonalRecord::where('record_type','best_set_volume')->value('value'));
        $this->assertSame('32.50',PersonalRecord::where('record_type','estimated_one_rep_max')->value('value'));
        $this->assertSame('10.00',PersonalRecord::where('record_type','most_repetitions')->value('value'));

        $this->deleteJson("/api/workout-sets/{$second['id']}")->assertNoContent();
        $this->assertSame(4,PersonalRecord::where('user_id',$user->id)->where('exercise_id',$exercise['performed_exercise']['id'])->count());
        $this->assertSame('20.00',PersonalRecord::where('record_type','heaviest_weight')->value('value'));
        $this->assertSame($first['id'],PersonalRecord::where('record_type','heaviest_weight')->value('workout_set_id'));

        $this->putJson("/api/workout-sets/{$first['id']}",[
            'set_number'=>1,
            'set_type'=>'working',
            'weight'=>21,
            'weight_unit'=>$exercise['performed_exercise']['default_weight_unit'],
            'repetitions'=>11,
            'rir'=>2,
            'completed'=>true,
        ])->assertOk();
        $this->assertSame(4,PersonalRecord::where('user_id',$user->id)->where('exercise_id',$exercise['performed_exercise']['id'])->count());
        $this->assertSame('231.00',PersonalRecord::where('record_type','best_set_volume')->value('value'));
    }
}
