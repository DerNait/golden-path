<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\TrainingPhase;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class TrainingPhaseController extends Controller
{
    public function __construct(private readonly GamificationService $gamification) {}
    public function index(Request $request): JsonResponse { return response()->json(['data'=>$request->user()->trainingPhases()->latest('starts_on')->get()]); }
    public function current(Request $request): JsonResponse { return response()->json(['data'=>$request->user()->trainingPhases()->where('status','active')->first()]); }
    public function store(Request $request): JsonResponse { $data=$this->validateData($request); $this->ensureRoutineOwnership($request,$data); return response()->json(['data'=>$request->user()->trainingPhases()->create($data)],201); }
    public function update(Request $request, TrainingPhase $trainingPhase): JsonResponse { $this->authorize('update',$trainingPhase); $data=$this->validateData($request); $this->ensureRoutineOwnership($request,$data); $trainingPhase->update($data); return response()->json(['data'=>$trainingPhase]); }
    public function activate(Request $request, TrainingPhase $trainingPhase): JsonResponse { $this->authorize('update',$trainingPhase); DB::transaction(function () use ($request,$trainingPhase): void { $request->user()->trainingPhases()->where('status','active')->whereKeyNot($trainingPhase->id)->update(['status'=>'upcoming']); $trainingPhase->update(['status'=>'active']); }); return response()->json(['data'=>$trainingPhase]); }
    public function complete(Request $request, TrainingPhase $trainingPhase): JsonResponse { $this->authorize('update',$trainingPhase); $earned=DB::transaction(function () use ($request,$trainingPhase): int { $trainingPhase->update(['status'=>'completed']); return $this->gamification->rewardPhaseCompletion($request->user(),$trainingPhase); }); return response()->json(['data'=>$trainingPhase,'xp_earned'=>$earned]); }
    private function validateData(Request $request): array { return $request->validate(['routine_id'=>['nullable','integer','exists:routines,id'],'name'=>['required','string','max:150'],'description'=>['nullable','string','max:2000'],'starts_on'=>['required','date'],'ends_on'=>['required','date','after:starts_on'],'status'=>['required',Rule::in(['upcoming','active','completed','cancelled'])],'planned_sessions'=>['required','integer','min:1'],'minimum_target_sessions'=>['required','integer','min:1','lte:planned_sessions'],'target_weight_min_kg'=>['nullable','numeric','min:20'],'target_weight_max_kg'=>['nullable','numeric','gte:target_weight_min_kg'],'target_waist_reduction_min_cm'=>['nullable','numeric','min:0'],'target_waist_reduction_max_cm'=>['nullable','numeric','gte:target_waist_reduction_min_cm'],'protein_goal_min_grams'=>['nullable','integer','min:0'],'protein_goal_max_grams'=>['nullable','integer','gte:protein_goal_min_grams'],'sleep_goal_hours'=>['nullable','numeric','between:0,16']]); }
    private function ensureRoutineOwnership(Request $request,array $data): void { if (($data['routine_id']??null) && ! $request->user()->routines()->whereKey($data['routine_id'])->exists()) abort(403); }
}
