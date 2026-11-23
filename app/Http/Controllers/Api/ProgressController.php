<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\WorkoutExercise;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $firstOf=fn (string $field)=>$measurements->firstWhere($field,'!=',null)?->{$field};
        $lastOf=fn (string $field)=>$measurements->whereNotNull($field)->last()?->{$field};
        return response()->json(['data'=>$measurements,'summary'=>[
            'initial_weight'=>$profile?->initial_body_weight_kg ?? $firstOf('body_weight_kg'),
            'current_weight'=>$lastOf('body_weight_kg') ?? $profile?->current_body_weight_kg,
            'initial_waist'=>$profile?->initial_waist_cm ?? $firstOf('waist_cm'),
            'current_waist'=>$lastOf('waist_cm') ?? $profile?->current_waist_cm,
            'initial_body_fat'=>$firstOf('body_fat_percentage'),'current_body_fat'=>$lastOf('body_fat_percentage'),
            'initial_muscle'=>$firstOf('skeletal_muscle_mass_kg'),'current_muscle'=>$lastOf('skeletal_muscle_mass_kg'),
            'current_lean_mass'=>$lastOf('lean_mass_kg'),'current_body_water'=>$lastOf('total_body_water_kg'),
            'current_visceral_fat'=>$lastOf('visceral_fat_level'),'current_bmr'=>$lastOf('basal_metabolic_rate_kcal'),
        ]]);
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

    /**
     * Direct working sets per muscle group per week (Monday to Sunday), against
     * what the active routine plans for a full cycle. Warm-ups do not count.
     */
    public function weeklyVolume(Request $request): JsonResponse
    {
        $weeks = (int) ($request->validate(['weeks' => ['nullable', 'integer', 'between:1,12']])['weeks'] ?? 4);
        $user = $request->user();
        $thisWeek = CarbonImmutable::now()->startOfWeek();
        $starts = collect(range($weeks - 1, 0))->map(fn (int $back) => $thisWeek->subWeeks($back));

        $rows = DB::table('workout_sets as ws')
            ->join('workout_exercises as we', 'we.id', '=', 'ws.workout_exercise_id')
            ->join('workout_sessions as s', 's.id', '=', 'we.workout_session_id')
            ->join('exercise_muscle_group as emg', function ($join): void {
                $join->on('emg.exercise_id', '=', 'we.performed_exercise_id')->where('emg.is_primary', '=', 1);
            })
            ->join('muscle_groups as mg', 'mg.id', '=', 'emg.muscle_group_id')
            ->where('s.user_id', $user->id)->whereIn('s.status', ['completed', 'partial'])
            ->where('ws.completed', true)->where('ws.set_type', '!=', 'warmup')
            ->where('s.started_at', '>=', $starts->first()->startOfDay())
            ->get(['mg.id as muscle_id', 'mg.name as muscle', 's.started_at']);

        $planned = [];
        $routine = $user->routines()->where('is_active', true)->with(['days' => fn ($q) => $q->where('day_type', 'training'), 'days.exercises.exercise.muscleGroups'])->first();
        foreach ($routine?->days ?? [] as $day) {
            foreach ($day->exercises as $slot) {
                $group = $slot->exercise->muscleGroups->firstWhere('pivot.is_primary', true);
                if ($group) $planned[$group->id] = ($planned[$group->id] ?? 0) + (int) $slot->target_sets;
            }
        }

        $muscles = [];
        foreach ($rows as $row) {
            $key = CarbonImmutable::parse($row->started_at)->startOfWeek()->toDateString();
            $muscles[$row->muscle_id]['name'] = $row->muscle;
            $muscles[$row->muscle_id]['weeks'][$key] = ($muscles[$row->muscle_id]['weeks'][$key] ?? 0) + 1;
        }
        foreach ($routine?->days ?? [] as $day) {
            foreach ($day->exercises as $slot) {
                $group = $slot->exercise->muscleGroups->firstWhere('pivot.is_primary', true);
                if ($group && ! isset($muscles[$group->id])) $muscles[$group->id] = ['name' => $group->name, 'weeks' => []];
            }
        }

        $data = collect($muscles)->map(fn (array $item, int $id) => [
            'id' => $id, 'name' => $item['name'], 'planned' => $planned[$id] ?? 0,
            'weeks' => $starts->map(fn ($start) => $item['weeks'][$start->toDateString()] ?? 0)->values(),
        ])->sortByDesc('planned')->values();

        return response()->json([
            'weeks' => $starts->map->toDateString()->values(),
            'muscles' => $data,
            'target' => ['min' => 10, 'max' => 20],
        ]);
    }
}
