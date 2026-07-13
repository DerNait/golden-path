<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubstitutionRequest;
use App\Http\Requests\WorkoutFinishRequest;
use App\Http\Requests\WorkoutStartRequest;
use App\Http\Resources\WorkoutResource;
use App\Models\Exercise;
use App\Models\RoutineDay;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Services\Workouts\WorkoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class WorkoutController extends Controller
{
    public function __construct(private readonly WorkoutService $workouts) {}
    public function index(Request $request): JsonResponse
    {
        $filters=$request->validate([
            'from'=>['nullable','date'],
            'to'=>['nullable','date','after_or_equal:from'],
            'status'=>['nullable',Rule::in(['in_progress','completed','partial','cancelled'])],
            'name'=>['nullable','string','max:150'],
        ]);
        $query=$request->user()->workouts()->select([
            'id','name','scheduled_for','started_at','finished_at','duration_seconds','status',
            'total_volume','working_sets_count','xp_earned','power_earned',
        ])->withCount(['exercises as records_count'=>fn ($q)=>$q->whereHas('sets',fn ($s)=>$s->where('is_personal_record',true))])->latest('started_at');
        $query->when($filters['from']??null,fn ($q,$from)=>$q->whereDate('started_at','>=',$from))
            ->when($filters['to']??null,fn ($q,$to)=>$q->whereDate('started_at','<=',$to))
            ->when($filters['status']??null,fn ($q,$status)=>$q->where('status',$status))
            ->when($filters['name']??null,fn ($q,$name)=>$q->where('name','like','%'.$name.'%'));
        return response()->json($query->paginate(15));
    }
    public function start(WorkoutStartRequest $request): JsonResponse { $day=RoutineDay::with('routine')->findOrFail($request->validated('routine_day_id')); return response()->json(['data'=>new WorkoutResource($this->workouts->start($request->user(),$day,$request->safe()->except('routine_day_id')))],201); }
    public function current(Request $request): JsonResponse { $session=$request->user()->workouts()->where('status','in_progress')->with($this->workouts->relations())->first(); return response()->json(['data'=>$session ? new WorkoutResource($session) : null]); }
    public function show(Request $request, WorkoutSession $workoutSession): WorkoutResource { $this->authorize('view',$workoutSession); return new WorkoutResource($workoutSession->load($this->workouts->relations())); }
    public function update(Request $request, WorkoutSession $workoutSession): WorkoutResource { $this->authorize('update',$workoutSession); $workoutSession->update($request->validate(['sleep_hours'=>['nullable','numeric','between:0,24'],'energy_level'=>['nullable','integer','between:1,5'],'motivation_level'=>['nullable','integer','between:1,5'],'discomfort_notes'=>['nullable','string','max:1000'],'notes'=>['nullable','string','max:2000']])); return new WorkoutResource($workoutSession->load($this->workouts->relations())); }
    public function substitute(SubstitutionRequest $request, WorkoutExercise $workoutExercise): JsonResponse { $alternative=Exercise::where('user_id',$request->user()->id)->findOrFail($request->validated('alternative_exercise_id')); return response()->json(['data'=>$this->workouts->substitute($request->user(),$workoutExercise,$alternative,$request->validated('reason'))]); }
    public function finish(WorkoutFinishRequest $request, WorkoutSession $workoutSession): WorkoutResource { return new WorkoutResource($this->workouts->finish($request->user(),$workoutSession,'completed',$request->validated())); }
    public function partial(WorkoutFinishRequest $request, WorkoutSession $workoutSession): WorkoutResource { return new WorkoutResource($this->workouts->finish($request->user(),$workoutSession,'partial',$request->validated())); }
    public function cancel(Request $request, WorkoutSession $workoutSession): JsonResponse { $this->authorize('update',$workoutSession); if ($workoutSession->status!=='in_progress') return response()->json(['message'=>'La sesion ya no esta activa.'],422); $workoutSession->update(['status'=>'cancelled','finished_at'=>now(),'duration_seconds'=>(int) abs($workoutSession->started_at->diffInSeconds(now()))]); return response()->json(['message'=>'Entrenamiento cancelado.']); }
}
