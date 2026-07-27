<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutSetRequest;
use App\Models\PersonalRecord;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\Progression\PersonalRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutSetController extends Controller
{
    public function __construct(private readonly PersonalRecordService $records) {}

    public function store(WorkoutSetRequest $request, WorkoutExercise $workoutExercise): JsonResponse
    {
        $this->ownExercise($request,$workoutExercise);
        $this->validateWeightUnit($workoutExercise,$request->validated());
        $result=DB::transaction(function () use ($request,$workoutExercise): array {
            if ($workoutExercise->sets()->where('set_number',$request->validated('set_number'))->where('set_type',$request->validated('set_type'))->exists()) {
                throw ValidationException::withMessages(['set_number'=>'Esta serie ya fue registrada.']);
            }
            $set=$workoutExercise->sets()->create(array_merge($request->validated(),[
                'completed_at'=>$request->boolean('completed',true)?now():null,
                'body_weight_kg'=>$this->bodyWeightSnapshot($workoutExercise),
            ]));
            $newRecords=$set->completed && $set->set_type!=='warmup' ? $this->records->evaluate($set) : collect();
            return ['set'=>$set->fresh(),'new_records'=>$newRecords];
        });
        return response()->json($result,201);
    }

    public function update(WorkoutSetRequest $request, WorkoutSet $workoutSet): JsonResponse
    {
        $workoutSet->load('workoutExercise.session');
        $this->ownExercise($request,$workoutSet->workoutExercise);
        $this->validateWeightUnit($workoutSet->workoutExercise,$request->validated());
        $result=DB::transaction(function () use ($request,$workoutSet): array {
            $workoutSet=WorkoutSet::whereKey($workoutSet->id)->lockForUpdate()->firstOrFail();
            $duplicate=$workoutSet->workoutExercise->sets()->where('set_number',$request->validated('set_number'))
                ->where('set_type',$request->validated('set_type'))->whereKeyNot($workoutSet->id)->exists();
            if ($duplicate) throw ValidationException::withMessages(['set_number'=>'Esta serie ya fue registrada.']);
            $workoutSet->update(array_merge($request->validated(),[
                'completed_at'=>$request->boolean('completed',true)?($workoutSet->completed_at??now()):null,
                'body_weight_kg'=>$this->bodyWeightSnapshot($workoutSet->workoutExercise),
            ]));
            $newRecords=$workoutSet->completed && $workoutSet->set_type!=='warmup' ? $this->records->evaluate($workoutSet) : collect();
            return ['set'=>$workoutSet->fresh(),'new_records'=>$newRecords];
        });
        return response()->json($result);
    }

    public function destroy(Request $request, WorkoutSet $workoutSet): JsonResponse
    {
        $workoutSet->load('workoutExercise.session');
        $this->ownExercise($request,$workoutSet->workoutExercise);
        DB::transaction(function () use ($request,$workoutSet): void {
            $workoutSet=WorkoutSet::whereKey($workoutSet->id)->lockForUpdate()->firstOrFail();
            $exerciseId=$workoutSet->workoutExercise->performed_exercise_id;
            $workoutSet->delete();
            $remaining=WorkoutSet::query()->where('completed',true)->where('set_type','!=','warmup')
                ->whereHas('workoutExercise',fn ($query)=>$query->where('performed_exercise_id',$exerciseId)
                    ->whereHas('session',fn ($session)=>$session->where('user_id',$request->user()->id)))
                ->first();
            if ($remaining) {
                $this->records->evaluate($remaining);
            } else {
                PersonalRecord::where('user_id',$request->user()->id)->where('exercise_id',$exerciseId)->delete();
            }
        });
        return response()->json([],204);
    }

    private function ownExercise(Request $request, WorkoutExercise $exercise): void
    {
        if ($exercise->session->user_id!==$request->user()->id || $exercise->session->status!=='in_progress') abort(403);
    }

    private function validateWeightUnit(WorkoutExercise $exercise,array $data): void
    {
        $unit=$exercise->performedExercise->default_weight_unit;
        if (($data['weight']??null)!==null && $unit && ($data['weight_unit']??null)!==$unit) {
            throw ValidationException::withMessages(['weight_unit'=>"La unidad debe ser {$unit}."]);
        }
    }

    // Snapshot the athlete's body weight (kg) so bodyweight movements count toward volume. Null for loaded lifts, keeping their volume unchanged.
    private function bodyWeightSnapshot(WorkoutExercise $exercise): ?float
    {
        if (! in_array($exercise->performedExercise->metric_type, ['bodyweight_reps','bodyweight_added_weight'], true)) {
            return null;
        }
        $user=$exercise->session->user;
        $weight=$user->profile?->current_body_weight_kg
            ?? $user->bodyMeasurements()->whereNotNull('body_weight_kg')->latest('recorded_on')->latest('id')->value('body_weight_kg');
        return $weight!==null ? (float)$weight : null;
    }
}
