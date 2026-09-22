<?php

namespace App\Services\Workouts;

use App\Models\Exercise;
use App\Models\RoutineDay;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Services\Gamification\GamificationService;
use App\Services\Progression\ExerciseTargetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Session lifecycle. Progression advice comes only from the AI assistant,
 * whose published recommendations are already the targets resolved here.
 */
class WorkoutService
{
    public function __construct(
        private readonly GamificationService $gamification,
        private readonly ExerciseTargetService $targets,
    ) {}

    public function start(User $user, RoutineDay $day, array $checkIn): WorkoutSession
    {
        return DB::transaction(function () use ($user, $day, $checkIn): WorkoutSession {
            if ($day->routine->user_id !== $user->id || $day->day_type !== 'training') abort(403);
            if ($user->workouts()->where('status','in_progress')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['session'=>'Ya existe un entrenamiento activo.']);
            }
            $day->load(['routine','exercises.exercise.muscleGroups']);
            $snapshot = ['routine'=>['id'=>$day->routine->id,'name'=>$day->routine->name],'day'=>$day->toArray(),'captured_at'=>now()->toIso8601String()];
            $session = $user->workouts()->create(array_merge($checkIn, [
                'routine_id'=>$day->routine_id,'routine_day_id'=>$day->id,
                'training_phase_id'=>$user->trainingPhases()->where('status','active')->value('id'),
                'name'=>$day->name,'scheduled_for'=>today(),'started_at'=>now(),'status'=>'in_progress','routine_snapshot_json'=>$snapshot,
            ]));
            foreach ($day->exercises as $planned) {
                $previous = $this->previousPerformance($user, $planned->exercise);
                $session->exercises()->create([
                    'planned_exercise_id'=>$planned->exercise_id,'performed_exercise_id'=>$planned->exercise_id,
                    'routine_exercise_id'=>$planned->id,'position'=>$planned->position,
                    'planned_snapshot_json'=>$planned->load('exercise.muscleGroups')->toArray(),
                    'previous_performance_json'=>$previous,
                    'target_snapshot_json'=>$this->targets->resolve($user,$planned->exercise,$planned),
                ]);
            }
            return $session->load($this->relations());
        });
    }

    public function substitute(User $user, WorkoutExercise $workoutExercise, Exercise $alternative, ?string $reason): WorkoutExercise
    {
        $workoutExercise->loadMissing('session','plannedExercise.alternatives');
        if ($workoutExercise->session->user_id !== $user->id || $workoutExercise->session->status !== 'in_progress') abort(403);
        // Going back to the planned exercise is always allowed: the equipment
        // that was busy may free up before the first set is logged.
        $isRevert = (int) $alternative->id === (int) $workoutExercise->planned_exercise_id;
        if (! $isRevert && ! $workoutExercise->plannedExercise->alternatives->contains('alternative_exercise_id',$alternative->id)) {
            throw ValidationException::withMessages(['alternative_exercise_id'=>'El ejercicio no esta configurado como alternativa.']);
        }
        if ($workoutExercise->sets()->exists()) throw ValidationException::withMessages(['exercise'=>'No se puede sustituir despues de registrar series.']);
        $routineExercise = $workoutExercise->routineExercise;
        $workoutExercise->update([
            'performed_exercise_id'=>$alternative->id,'was_substituted'=>! $isRevert,'substitution_reason'=>$isRevert ? null : $reason,
            'previous_performance_json'=>$this->previousPerformance($user,$alternative),'recommendation_snapshot_json'=>null,
            'target_snapshot_json'=>$this->targets->resolve($user,$alternative,$routineExercise),
        ]);
        return $workoutExercise->fresh($this->relationsForExercise());
    }

    public function finish(User $user, WorkoutSession $session, string $status, array $feedback): WorkoutSession
    {
        return DB::transaction(function () use ($user, $session, $status, $feedback): WorkoutSession {
            if ($session->user_id !== $user->id || $session->status !== 'in_progress') abort(403);
            $session->load('exercises.sets');
            $sets = $session->exercises->flatMap->sets->where('completed',true)->where('set_type','!=','warmup');
            if ($status === 'completed' && $sets->isEmpty()) {
                throw ValidationException::withMessages(['session'=>'Registra al menos una serie efectiva antes de completar el entrenamiento.']);
            }
            $session->update(array_merge($feedback, [
                'status'=>$status,'finished_at'=>now(),'duration_seconds'=>(int) abs($session->started_at->diffInSeconds(now())),
                'total_volume'=>$sets->sum(fn ($set)=>$set->volume),'working_sets_count'=>$sets->count(),
            ]));
            if (in_array($status,['completed','partial'],true)) {
                $this->gamification->rewardWorkout($session->fresh(['exercises.sets','user.profile']));
            }
            return $session->fresh($this->relations());
        });
    }

    public function relations(): array
    {
        return ['user.profile','user.gameProfile','routineDay','exercises.plannedExercise.muscleGroups','exercises.plannedExercise.alternativeExercises','exercises.performedExercise.muscleGroups','exercises.sets','exercises.assistantRecommendations'];
    }

    private function relationsForExercise(): array
    {
        return ['plannedExercise.muscleGroups','plannedExercise.alternativeExercises','performedExercise.muscleGroups','sets','assistantRecommendations'];
    }

    private function previousPerformance(User $user, Exercise $exercise): ?array
    {
        $previous = WorkoutExercise::where('performed_exercise_id',$exercise->id)
            ->whereHas('session',fn ($query)=>$query->where('user_id',$user->id)->whereIn('status',['completed','partial']))
            ->with(['sets'=>fn ($query)=>$query->where('completed',true),'session'])->latest('id')->first();
        if (! $previous) return null;
        return ['session_id'=>$previous->workout_session_id,'date'=>$previous->session->started_at,'sets'=>$previous->sets->toArray()];
    }
}
