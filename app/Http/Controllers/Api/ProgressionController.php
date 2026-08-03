<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecommendationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendationActionRequest;
use App\Http\Resources\RecommendationResource;
use App\Models\ProgressionRecommendation;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Services\Progression\ExerciseTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgressionController extends Controller
{
    public function __construct(private readonly ExerciseTargetService $targets) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters=$request->validate(['status'=>['nullable',Rule::enum(RecommendationStatus::class)]]);
        $query=ProgressionRecommendation::where('user_id',$request->user()->id)->with('exercise')->latest();
        $query->when($filters['status']??null,fn ($query,$status)=>$query->where('status',$status));
        return RecommendationResource::collection($query->paginate(20));
    }

    public function accept(Request $request, ProgressionRecommendation $recommendation): JsonResponse
    {
        $this->authorize('update',$recommendation);
        $updated=DB::transaction(function () use ($recommendation): ProgressionRecommendation {
            $recommendation=$this->lockPending($recommendation);
            $this->applyTarget($recommendation,$recommendation->suggested_weight,$recommendation->suggested_total_repetitions,$recommendation->suggested_rep_distribution);
            $recommendation->update(['status'=>RecommendationStatus::Accepted->value,'accepted_at'=>now()]);
            return $recommendation->fresh('exercise');
        });
        return response()->json(['data'=>$updated]);
    }

    public function ignore(Request $request, ProgressionRecommendation $recommendation): JsonResponse
    {
        $this->authorize('update',$recommendation);
        $updated=DB::transaction(function () use ($recommendation): ProgressionRecommendation {
            $recommendation=$this->lockPending($recommendation);
            $recommendation->update(['status'=>RecommendationStatus::Ignored->value]);
            return $recommendation->fresh('exercise');
        });
        return response()->json(['data'=>$updated]);
    }

    public function modify(RecommendationActionRequest $request, ProgressionRecommendation $recommendation): JsonResponse
    {
        $this->authorize('update',$recommendation);
        $data=$request->validated();
        if (! array_filter($data,fn ($value)=>$value!==null)) {
            throw ValidationException::withMessages(['recommendation'=>'Indica un peso o una meta de repeticiones.']);
        }
        $updated=DB::transaction(function () use ($recommendation,$data): ProgressionRecommendation {
            $recommendation=$this->lockPending($recommendation);
            $this->applyTarget($recommendation,$data['suggested_weight']??null,$data['suggested_total_repetitions']??null,$data['suggested_rep_distribution']??null);
            $recommendation->update(array_merge($data,['status'=>RecommendationStatus::Modified->value,'accepted_at'=>now()]));
            return $recommendation->fresh('exercise');
        });
        return response()->json(['data'=>$updated]);
    }

    /**
     * Store the accepted target where it belongs. The routine slot is only
     * updated when the recommendation is about the exercise that slot plans;
     * for an alternative performed in that slot the target is kept per
     * (exercise, number of sets) so it never overwrites the planned exercise.
     */
    private function applyTarget(ProgressionRecommendation $recommendation, ?float $weight, ?int $totalReps, ?array $distribution = null): void
    {
        $slot=$recommendation->routineExercise;
        $plansThisExercise=$slot && (int) $slot->exercise_id===(int) $recommendation->exercise_id;
        $clamped=$totalReps!==null && $slot ? $this->clampTotalRepetitions($recommendation,$totalReps) : $totalReps;
        // A split that no longer adds up to the goal would mislead; drop it.
        if ($distribution && $clamped!==null && array_sum($distribution)!==$clamped) $distribution=null;

        if ($plansThisExercise) {
            $changes=[];
            if ($weight!==null) {
                $changes['target_weight']=$weight;
                $changes['progression_target_total_reps']=$slot->target_sets * $slot->minimum_reps;
            }
            if ($clamped!==null) $changes['progression_target_total_reps']=$clamped;
            if ($changes) $slot->update($changes);
        }

        $exercise=$recommendation->exercise;
        if (! $exercise || ($weight===null && $clamped===null && ! $distribution)) return;

        $sets=$this->contextSets($recommendation);
        if ($sets < 1) return;

        $this->targets->remember(User::findOrFail($recommendation->user_id),$exercise,$sets,$weight,$recommendation->weight_unit,$clamped,$distribution);
    }

    /**
     * How many sets this recommendation belongs to. An exercise trained only as
     * an alternative has no slot of its own, so it falls back to the slot it
     * was last performed in: the sets the routine asks for there, not however
     * many happened to be logged.
     */
    private function contextSets(ProgressionRecommendation $recommendation): int
    {
        if ($recommendation->routineExercise) return (int) $recommendation->routineExercise->target_sets;

        $own=RoutineExercise::where('exercise_id',$recommendation->exercise_id)
            ->whereHas('routineDay.routine',fn ($query)=>$query->where('user_id',$recommendation->user_id)->where('is_active',true))
            ->orderBy('routine_day_id')->orderBy('position')->value('target_sets');
        if ($own) return (int) $own;

        $lastPerformed=WorkoutExercise::where('performed_exercise_id',$recommendation->exercise_id)
            ->whereNotNull('routine_exercise_id')
            ->whereHas('session',fn ($query)=>$query->where('user_id',$recommendation->user_id))
            ->with('routineExercise:id,target_sets')->latest('id')->first();

        return (int) ($lastPerformed?->routineExercise?->target_sets ?? 0);
    }

    private function lockPending(ProgressionRecommendation $recommendation): ProgressionRecommendation
    {
        $locked=ProgressionRecommendation::whereKey($recommendation->id)->lockForUpdate()->firstOrFail();
        if ($locked->status!==RecommendationStatus::Pending->value) {
            throw ValidationException::withMessages(['recommendation'=>'La recomendacion ya fue procesada.']);
        }
        return $locked;
    }

    private function clampTotalRepetitions(ProgressionRecommendation $recommendation, int $total): int
    {
        $planned=$recommendation->routineExercise;
        $minimum=$planned->target_sets * $planned->minimum_reps;
        $maximum=$planned->target_sets * $planned->maximum_reps;

        return min($maximum,max($minimum,$total));
    }
}
