<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Services\Progression\ProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $exercise;
    private RoutineExercise $planned;
    private ProgressionService $service;

    protected function setUp(): void
    {
        parent::setUp(); $this->seed();
        $this->user=User::where('email','owner@example.com')->firstOrFail();
        $this->planned=RoutineExercise::with('exercise')->firstOrFail();
        $this->exercise=$this->planned->exercise;
        $this->service=app(ProgressionService::class);
    }

    public function test_first_two_exposures_are_calibration(): void
    {
        $this->assertSame('calibrate',$this->service->recommend($this->user,$this->exercise,$this->planned)['recommendation_type']);
        $this->exposure([8,8,8]);
        $this->assertSame('calibrate',$this->service->recommend($this->user,$this->exercise,$this->planned)['recommendation_type']);
    }

    public function test_mastering_range_with_rir_recommends_weight_increase(): void
    {
        $this->exposure([8,9,9]); $this->exposure([10,10,10],[2,1,1]);
        $result=$this->service->recommend($this->user,$this->exercise,$this->planned);
        $this->assertSame('increase_weight',$result['recommendation_type']);
        $this->assertSame('high',$result['confidence']);
        $this->assertGreaterThan($result['current_weight'],$result['suggested_weight']);
    }

    public function test_in_range_performance_targets_more_repetitions(): void
    {
        $this->exposure([8,8,8]); $this->exposure([10,9,8],[2,1,1]);
        $result=$this->service->recommend($this->user,$this->exercise,$this->planned);
        $this->assertSame('increase_repetitions',$result['recommendation_type']);
        $this->assertContains($result['suggested_total_repetitions'],[28,29]);
    }

    public function test_atypical_session_never_triggers_weight_increase(): void
    {
        $this->exposure([9,9,9]); $this->exposure([10,10,10],[2,2,2],true);
        $result=$this->service->recommend($this->user,$this->exercise,$this->planned);
        $this->assertSame('maintain',$result['recommendation_type']);
        $this->assertSame('low',$result['confidence']);
    }

    public function test_two_bad_exposures_reduce_weight_or_prioritize_short_rest(): void
    {
        $this->exposure([5,5,5],[1,1,1],false,[150,150,150]); $this->exposure([5,5,5],[1,1,1],false,[150,150,150]);
        $this->assertSame('reduce_weight',$this->service->recommend($this->user,$this->exercise,$this->planned)['recommendation_type']);

        WorkoutSession::query()->delete();
        $this->exposure([5,5,5],[1,1,1],false,[60,60,60]); $this->exposure([9,6,4],[1,1,1],false,[60,60,60]);
        $this->assertSame('increase_rest',$this->service->recommend($this->user,$this->exercise,$this->planned)['recommendation_type']);
    }

    public function test_four_unchanged_exposures_flag_stagnation(): void
    {
        for($i=0;$i<4;$i++) $this->exposure([8,8,8],[1,1,1]);
        $this->assertSame('possible_deload',$this->service->recommend($this->user,$this->exercise,$this->planned)['recommendation_type']);
    }

    public function test_same_repetitions_with_better_rir_counts_as_progress(): void
    {
        $this->exposure([8,8,8],[1,1,1]);
        $this->exposure([8,8,8],[2,2,2]);
        $result=$this->service->recommend($this->user,$this->exercise,$this->planned);
        $this->assertSame('maintain',$result['recommendation_type']);
        $this->assertStringContainsString('menor esfuerzo',$result['reason']);
    }

    public function test_mastered_repetitions_do_not_raise_weight_with_zero_rir(): void
    {
        $this->exposure([8,8,8],[1,1,1]);
        $this->exposure([10,10,10],[0,0,0]);
        $result=$this->service->recommend($this->user,$this->exercise,$this->planned);
        $this->assertNotSame('increase_weight',$result['recommendation_type']);
        $this->assertNull($result['suggested_weight']);
    }

    public function test_one_bad_exposure_maintains_weight_before_reducing(): void
    {
        $this->exposure([8,8,8],[2,2,2]);
        $this->exposure([5,5,5],[1,1,1],false,[150,150,150]);
        $result=$this->service->recommend($this->user,$this->exercise,$this->planned);
        $this->assertSame('maintain',$result['recommendation_type']);
        $this->assertSame(20.0,$result['current_weight']);
        $this->assertNull($result['suggested_weight']);
    }

    public function test_alternative_history_is_never_mixed(): void
    {
        $this->exposure([10,10,10]); $this->exposure([10,10,10]);
        $alternative=$this->exercise->alternativeExercises()->firstOrFail();
        $this->assertSame('calibrate',$this->service->recommend($this->user,$alternative,$this->planned)['recommendation_type']);
    }

    private function exposure(array $repetitions, array $rirs=[2,2,2], bool $atypical=false, array $rests=[150,150,150]): WorkoutExercise
    {
        $session=$this->user->workouts()->create(['routine_id'=>$this->planned->routineDay->routine_id,'routine_day_id'=>$this->planned->routine_day_id,'name'=>'Test','started_at'=>now(),'finished_at'=>now(),'status'=>'completed','is_atypical'=>$atypical]);
        $performed=$session->exercises()->create(['planned_exercise_id'=>$this->exercise->id,'performed_exercise_id'=>$this->exercise->id,'routine_exercise_id'=>$this->planned->id,'position'=>1,'planned_snapshot_json'=>$this->planned->toArray()]);
        foreach($repetitions as $index=>$reps) $performed->sets()->create(['set_number'=>$index+1,'set_type'=>'working','weight'=>20,'weight_unit'=>'kg','repetitions'=>$reps,'rir'=>$rirs[$index]??1,'rest_seconds_actual'=>$rests[$index]??150,'completed'=>true,'completed_at'=>now()]);
        return $performed;
    }
}
