<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\PersonalRecord;
use App\Models\ProgressionRecommendation;
use App\Services\Gamification\GamificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class DashboardController extends Controller
{
    public function __construct(private readonly GamificationService $gamification) {}
    public function __invoke(Request $request): JsonResponse
    {
        $user=$request->user()->load(['profile','routines.days.exercises.exercise','trainingPhases'=>fn ($q)=>$q->where('status','active')]);
        $routine=$user->routines->firstWhere('is_active',true); $today=$routine?->days->firstWhere('weekday',now()->dayOfWeekIso);
        $weekStart=CarbonImmutable::now()->startOfWeek(); $weekly=$user->workouts()->where('status','completed')->whereBetween('started_at',[$weekStart,$weekStart->endOfWeek()])->count();
        $game=$this->gamification->refresh($user);
        $change=fn ($current,$initial)=>$current!==null&&$initial!==null ? round((float)$current-(float)$initial,2) : null;
        $bodyChanges=['weight_kg'=>$change($user->profile->current_body_weight_kg,$user->profile->initial_body_weight_kg),
            'waist_cm'=>$change($user->profile->current_waist_cm,$user->profile->initial_waist_cm)];

        return response()->json([
            'user'=>$user->only(['id','name','email']),'profile'=>$user->profile,'game'=>$game,'body_changes'=>$bodyChanges,
            'energy_explanation'=>"{$game->energy}/100: combina 70% de adherencia de los ultimos 14 dias y 30% de cercania al ultimo entrenamiento.",
            'today'=>$today,'weekly'=>['completed'=>$weekly,'goal'=>$user->profile->weekly_workout_goal],
            'sessions_this_month'=>$user->workouts()->whereIn('status',['completed','partial'])->whereBetween('started_at',[now()->startOfMonth(),now()->endOfMonth()])->count(),
            'active_phase'=>$user->trainingPhases->first(),'latest_measurement'=>$user->bodyMeasurements()->latest('recorded_on')->first(),
            'previous_measurement'=>$user->bodyMeasurements()->latest('recorded_on')->skip(1)->first(),
            'latest_records'=>PersonalRecord::where('user_id',$user->id)->with('exercise')->latest('achieved_at')->limit(5)->get(),
            'pending_recommendations'=>ProgressionRecommendation::where('user_id',$user->id)->where('status','pending')->with('exercise')->latest()->limit(5)->get(),
            'active_workout_id'=>$user->workouts()->where('status','in_progress')->value('id'),
        ]);
    }
}
