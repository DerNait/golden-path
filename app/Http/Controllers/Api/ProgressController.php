<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\WorkoutExercise;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ProgressController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user=$request->user(); $phase=$user->trainingPhases()->where('status','active')->first();
        return response()->json(['phase'=>$phase,'completed_sessions'=>$user->workouts()->where('status','completed')->when($phase,fn ($q)=>$q->where('training_phase_id',$phase->id))->count(),'records'=>PersonalRecord::where('user_id',$user->id)->with('exercise')->latest('achieved_at')->get()]);
    }
    public function activity(Request $request): JsonResponse
    {
        $start=CarbonImmutable::now()->subDays(111)->startOfDay(); $sessions=$request->user()->workouts()->where('started_at','>=',$start)->with('exercises.sets')->get();
        $routine=$request->user()->routines()->where('is_active',true)->with('days')->first();
        $data=collect(range(0,111))->map(function (int $offset) use ($sessions,$routine,$start): array {
            $date=$start->addDays($offset); $session=$sessions->first(fn ($item)=>$item->started_at->isSameDay($date)); $day=$routine?->days->firstWhere('weekday',$date->dayOfWeekIso);
            $state=match (true) {
                $session?->status==='cancelled' => 'skipped',
                $session!==null => $session->status,
                $day?->day_type==='rest' => 'planned_rest',
                $day?->day_type==='training' && $date->isBefore(CarbonImmutable::today()) => 'skipped',
                default => 'none',
            }; $record=$session?->exercises->flatMap->sets->contains('is_personal_record',true)??false;
            return ['date'=>$date->format('Y-m-d'),'state'=>$state,'record'=>$record];
        }); return response()->json(['data'=>$data]);
    }
    public function body(Request $request): JsonResponse
    {
        $measurements=$request->user()->bodyMeasurements()->oldest('recorded_on')->get();
        $profile=$request->user()->profile;
        return response()->json(['data'=>$measurements,'summary'=>['initial_weight'=>$profile?->initial_body_weight_kg ?? $measurements->firstWhere('body_weight_kg','!=',null)?->body_weight_kg,'current_weight'=>$measurements->whereNotNull('body_weight_kg')->last()?->body_weight_kg ?? $profile?->current_body_weight_kg,'initial_waist'=>$profile?->initial_waist_cm ?? $measurements->firstWhere('waist_cm','!=',null)?->waist_cm,'current_waist'=>$measurements->whereNotNull('waist_cm')->last()?->waist_cm ?? $profile?->current_waist_cm]]);
    }
    public function exercises(Request $request): JsonResponse { return response()->json(['data'=>$request->user()->exercises()->where('is_active',true)->orderBy('name')->get(['id','name'])]); }
    public function exercise(Request $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('view',$exercise); $exposures=WorkoutExercise::where('performed_exercise_id',$exercise->id)->whereHas('session',fn ($q)=>$q->where('user_id',$request->user()->id)->whereIn('status',['completed','partial']))->with(['sets'=>fn ($q)=>$q->where('completed',true)->where('set_type','working'),'session'])->oldest('id')->get();
        $series=$exposures->map(function ($exposure): array {
            $sets=$exposure->sets; $best=$sets->sortByDesc(fn ($set)=>$set->volume)->first();
            return ['date'=>$exposure->session->started_at->format('Y-m-d'),'session_id'=>$exposure->workout_session_id,
                'max_weight'=>(float)$sets->max('weight'),'best_set'=>$best?['weight'=>(float)$best->weight,'repetitions'=>(int)$best->repetitions,'volume'=>(float)$best->volume]:null,
                'total_repetitions'=>(int)$sets->sum('repetitions'),'volume'=>(float)$sets->sum(fn ($set)=>$set->volume),
                'average_rir'=>$sets->whereNotNull('rir')->avg('rir'),'estimated_one_rep_max'=>(float)$sets->max('estimated_one_rep_max')];
        });
        return response()->json(['exercise'=>$exercise->load('muscleGroups'),'calibration'=>['exposures'=>$exposures->count(),'complete'=>$exposures->count()>=2],'series'=>$series,'records'=>PersonalRecord::where('user_id',$request->user()->id)->where('exercise_id',$exercise->id)->get(),'recommendation'=>\App\Models\ProgressionRecommendation::where('user_id',$request->user()->id)->where('exercise_id',$exercise->id)->latest()->first()]);
    }
}
