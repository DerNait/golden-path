<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecommendationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendationActionRequest;
use App\Http\Resources\RecommendationResource;
use App\Models\ProgressionRecommendation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgressionController extends Controller
{
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
            if ($recommendation->routineExercise) {
                $changes=[];
                if ($recommendation->suggested_weight!==null) $changes['target_weight']=$recommendation->suggested_weight;
                if ($recommendation->suggested_total_repetitions!==null) {
                    $changes['progression_target_reps']=min($recommendation->routineExercise->maximum_reps,$recommendation->suggested_total_repetitions);
                }
                if ($changes) $recommendation->routineExercise->update($changes);
            }
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
            if ($recommendation->routineExercise) {
                $changes=[];
                if (($data['suggested_weight']??null)!==null) $changes['target_weight']=$data['suggested_weight'];
                if (($data['suggested_total_repetitions']??null)!==null) {
                    $changes['progression_target_reps']=min($recommendation->routineExercise->maximum_reps,$data['suggested_total_repetitions']);
                }
                if ($changes) $recommendation->routineExercise->update($changes);
            }
            $recommendation->update(array_merge($data,['status'=>RecommendationStatus::Modified->value,'accepted_at'=>now()]));
            return $recommendation->fresh('exercise');
        });
        return response()->json(['data'=>$updated]);
    }

    private function lockPending(ProgressionRecommendation $recommendation): ProgressionRecommendation
    {
        $locked=ProgressionRecommendation::whereKey($recommendation->id)->lockForUpdate()->firstOrFail();
        if ($locked->status!==RecommendationStatus::Pending->value) {
            throw ValidationException::withMessages(['recommendation'=>'La recomendacion ya fue procesada.']);
        }
        return $locked;
    }
}
