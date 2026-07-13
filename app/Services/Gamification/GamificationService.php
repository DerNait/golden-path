<?php

namespace App\Services\Gamification;

use App\Models\Achievement;
use App\Models\AvatarPhase;
use App\Models\GameProfile;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\TrainingPhase;
use App\Models\WorkoutSession;
use App\Models\WorkoutExercise;
use App\Models\XpEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    private array $progressSnapshots=[];
    public function rewardWorkout(WorkoutSession $session): int
    {
        return DB::transaction(function () use ($session): int {
            $session->loadMissing('exercises.sets');
            $beforePower = (int) GameProfile::where('user_id', $session->user_id)->value('combat_power');
            $planned = $session->exercises->sum(fn ($exercise) => (int) data_get($exercise->planned_snapshot_json, 'target_sets', 0));
            $completed = $session->exercises->sum(fn ($exercise) => $exercise->sets->where('completed', true)->where('set_type', 'working')->count());
            $earned = 0;

            if ($session->status === 'completed') {
                $earned += $this->award($session->user_id, 'workout_completed', "workout:{$session->id}:completed", 100, 'Entrenamiento completado', $session->id);
            } elseif ($session->status === 'partial' && $planned > 0 && $completed / $planned >= .5) {
                $earned += $this->award($session->user_id, 'workout_partial', "workout:{$session->id}:partial", 50, 'Entrenamiento parcial con al menos 50% de series', $session->id);
            }

            if ($planned > 0 && $completed >= $planned) {
                $earned += $this->award($session->user_id, 'all_sets', "workout:{$session->id}:all-sets", 25, 'Todas las series planificadas completadas', $session->id);
            }

            $recordSets = $session->exercises->flatMap->sets->where('is_personal_record', true)->take(3);
            foreach ($recordSets as $recordSet) {
                $earned += $this->award($session->user_id, 'personal_record', "workout:{$session->id}:record:{$recordSet->id}", 25, 'Nuevo record personal', $session->id);
            }

            $previousFinishedAt = WorkoutSession::where('user_id',$session->user_id)->whereKeyNot($session->id)
                ->whereIn('status',['completed','partial'])->whereNotNull('finished_at')->latest('finished_at')->value('finished_at');
            if ($previousFinishedAt && CarbonImmutable::parse($previousFinishedAt)->diffInDays($session->started_at) >= 14) {
                $earned += $this->award($session->user_id,'return_after_break',"workout:{$session->id}:return",75,'Regreso despues de 14 dias sin entrenar',$session->id);
            }

            $weekStart = CarbonImmutable::parse($session->started_at)->startOfWeek();
            $weeklyCompleted = $this->completedPlannedSessions($session->user,$weekStart,$weekStart->endOfWeek());
            $goal = (int) $session->user->profile->weekly_workout_goal;
            if ($weeklyCompleted >= $goal) {
                $earned += $this->award($session->user_id, 'weekly_goal', "week:{$session->user_id}:{$weekStart->format('Y-m-d')}:goal", 200, 'Meta semanal completada', $session->id);
            }

            $profile = $this->refresh($session->user);
            $achievementXp = $this->evaluateAchievements($session->user);
            if ($achievementXp > 0) {
                $earned += $achievementXp;
                $profile = $this->refresh($session->user);
            }
            $session->update([
                'xp_earned'=>(int) $session->xp_earned+$earned,
                'power_earned'=>(int) $session->power_earned+max(0,$profile->combat_power-$beforePower),
            ]);
            return $earned;
        });
    }

    public function rewardMeasurement(User $user, string $weekKey): int
    {
        $amount = $this->award($user->id, 'body_measurement', "measurement:{$user->id}:{$weekKey}", 10, 'Primera medicion corporal de la semana');
        $this->refresh($user);
        return $amount;
    }

    public function rewardPhaseCompletion(User $user, TrainingPhase $phase): int
    {
        $earned=$this->award($user->id,'training_phase_completed',"phase:{$phase->id}:completed",500,'Fase de entrenamiento completada');
        $earned += $this->evaluateAchievements($user);
        $this->refresh($user);

        return $earned;
    }

    public function award(int $userId, string $type, string $key, int $amount, string $description, ?int $workoutId = null, ?int $achievementId = null): int
    {
        $event = XpEvent::firstOrCreate(['event_key'=>$key], [
            'user_id'=>$userId,'workout_session_id'=>$workoutId,'achievement_id'=>$achievementId,
            'event_type'=>$type,'amount'=>$amount,'description'=>$description,
        ]);
        if (! $event->wasRecentlyCreated) {
            return 0;
        }
        GameProfile::where('user_id', $userId)->increment('total_xp', $amount);
        return $amount;
    }

    public function refresh(User $user): GameProfile
    {
        $user->loadMissing(['profile','gameProfile']);
        $profile = $user->gameProfile ?: GameProfile::create(['user_id'=>$user->id])->refresh();
        $energy = $this->energy($user);
        $streak = $this->weeklyStreak($user);
        $perfectWeeks = max($profile->perfect_weeks, $this->perfectWeeks($user));
        $adherence = $this->adherenceLast28Days($user);
        $power = $this->combatPower($user, $streak, $adherence);
        $completedWorkouts = WorkoutSession::where('user_id',$user->id)->where('status','completed')->count();
        $completedPhase = $user->trainingPhases()->where('status','completed')->get()->contains(function (TrainingPhase $phase) use ($user): bool {
            $completed=$user->workouts()->where('training_phase_id',$phase->id)->where('status','completed')->count();
            return $completed >= (int) ceil($phase->planned_sessions * .80);
        });
        $eligibleMaximum = AvatarPhase::query()->orderByDesc('position')->get()->first(function (AvatarPhase $phase) use ($profile, $completedWorkouts, $completedPhase, $perfectWeeks): bool {
            return $profile->total_xp >= $phase->minimum_xp
                && $completedWorkouts >= $phase->minimum_completed_workouts
                && $perfectWeeks >= $phase->minimum_perfect_weeks
                && (! $phase->requires_completed_phase || $completedPhase);
        }) ?? AvatarPhase::orderBy('position')->first();
        $profile->loadMissing('maximumPhase');
        $maximum = $profile->maximumPhase && $profile->maximumPhase->position > $eligibleMaximum->position ? $profile->maximumPhase : $eligibleMaximum;
        $activePosition = match (true) {
            $energy >= 70 => $maximum->position,
            $energy >= 45 => max(1, $maximum->position - 1),
            $energy >= 25 => max(1, $maximum->position - 2),
            default => 1,
        };
        $active = AvatarPhase::where('position', $activePosition)->first();
        $profile->update([
            'level'=>$this->levelForXp($profile->total_xp),'energy'=>$energy,'combat_power'=>$power,
            'current_weekly_streak'=>$streak,'best_weekly_streak'=>max($profile->best_weekly_streak,$streak),'perfect_weeks'=>$perfectWeeks,'adherence_last_28_days'=>$adherence,
            'maximum_avatar_phase_id'=>$maximum?->id,'active_avatar_phase_id'=>$active?->id,
            'last_activity_at'=>$user->workouts()->whereIn('status',['completed','partial'])->max('finished_at'),
        ]);
        return $profile->fresh(['maximumPhase','activePhase']);
    }

    public function levelForXp(int $xp): int
    {
        $level = 1;
        while (100 * ($level + 1) * $level / 2 <= $xp) $level++;
        return $level;
    }

    private function energy(User $user): int
    {
        $start = CarbonImmutable::now()->subDays(13)->startOfDay();
        $trainingWeekdays = $user->routines()->where('is_active',true)->with('days')->first()?->days->where('day_type','training')->pluck('weekday') ?? collect();
        $planned = collect(range(0,13))->filter(fn (int $offset) => $trainingWeekdays->contains(CarbonImmutable::now()->subDays($offset)->dayOfWeekIso))->count();
        $completed = $this->completedPlannedSessions($user,$start,CarbonImmutable::now()->endOfDay());
        $adherence = $planned > 0 ? min(1, $completed / $planned) : 1;
        $last = $user->workouts()->whereIn('status',['completed','partial'])->max('finished_at');
        $days = $last ? CarbonImmutable::parse($last)->diffInDays(CarbonImmutable::now()) : 14;
        $recency = max(0, 1 - $days / 14);
        return (int) round(min(100, max(0, $adherence * 70 + $recency * 30)));
    }

    private function weeklyStreak(User $user): int
    {
        $goal = (int) ($user->profile?->weekly_workout_goal ?? 4);
        $streak = 0;
        $currentCount = $this->completedPlannedSessions($user,CarbonImmutable::now()->startOfWeek(),CarbonImmutable::now()->endOfWeek());
        $offset = $currentCount >= $goal ? 0 : 1;
        for ($week = $offset; $week < 52 + $offset; $week++) {
            $start = CarbonImmutable::now()->startOfWeek()->subWeeks($week);
            $count = $this->completedPlannedSessions($user,$start,$start->endOfWeek());
            if ($count < $goal) break;
            $streak++;
        }
        return $streak;
    }

    private function perfectWeeks(User $user): int
    {
        $goal=(int)($user->profile?->weekly_workout_goal??4);
        return $user->workouts()->where('status','completed')->whereNotNull('routine_day_id')->get(['started_at','routine_day_id'])
            ->groupBy(fn ($session)=>$session->started_at->format('o-W'))
            ->filter(fn ($sessions)=>$sessions->unique('routine_day_id')->count()>=$goal)->count();
    }

    private function adherenceLast28Days(User $user): float
    {
        $trainingWeekdays=$user->routines()->where('is_active',true)->with('days')->first()?->days->where('day_type','training')->pluck('weekday') ?? collect();
        $planned=collect(range(0,27))->filter(fn (int $offset)=>$trainingWeekdays->contains(CarbonImmutable::now()->subDays($offset)->dayOfWeekIso))->count();
        $completed=$this->completedPlannedSessions($user,CarbonImmutable::now()->subDays(27)->startOfDay(),CarbonImmutable::now()->endOfDay());
        return round(min(100,$completed/max(1,$planned)*100),2);
    }

    private function combatPower(User $user, int $streak, float $consistency): int
    {
        $snapshot=$this->exerciseProgress($user);
        $progress=$snapshot['eligible']>0 ? count($snapshot['improved_ids'])/$snapshot['eligible']*100 : 0;
        $streakScore = min(100, $streak / 8 * 100);
        return (int) round(($consistency * .60 + $progress * .30 + $streakScore * .10) * 100);
    }

    public function evaluateAchievements(User $user): int
    {
        $counts = [
            'workouts_completed'=>$user->workouts()->where('status','completed')->count(),
            'records'=>\App\Models\PersonalRecord::where('user_id',$user->id)->count(),
            'working_sets'=>\App\Models\WorkoutSet::where('set_type','working')->where('completed',true)->whereHas('workoutExercise.session',fn ($q)=>$q->where('user_id',$user->id))->count(),
            'repetitions'=>(int) \App\Models\WorkoutSet::where('set_type','working')->where('completed',true)->whereHas('workoutExercise.session',fn ($q)=>$q->where('user_id',$user->id))->sum('repetitions'),
            'perfect_weeks'=>(int) GameProfile::where('user_id',$user->id)->value('perfect_weeks'),
            'consistent_months'=>$this->consistentMonths($user),
            'returns'=>XpEvent::where('user_id',$user->id)->where('event_type','return_after_break')->count(),
            'phases_completed'=>$user->trainingPhases()->where('status','completed')->count(),
            'improved_exercises'=>count($this->exerciseProgress($user)['improved_ids']),
        ];
        $earned=0;
        Achievement::all()->each(function (Achievement $achievement) use ($user, $counts, &$earned): void {
            if (($counts[$achievement->criteria_type] ?? 0) < ($achievement->criteria_value ?? 1)) return;
            $unlocked = UserAchievement::firstOrCreate(['user_id'=>$user->id,'achievement_id'=>$achievement->id], ['unlocked_at'=>now()]);
            if ($unlocked->wasRecentlyCreated) {
                $earned += $this->award($user->id,'achievement',"achievement:{$user->id}:{$achievement->id}",$achievement->xp_reward,"Logro desbloqueado: {$achievement->name}",null,$achievement->id);
            }
        });

        return $earned;
    }
    private function exerciseProgress(User $user): array
    {
        if (isset($this->progressSnapshots[$user->id])) return $this->progressSnapshots[$user->id];

        $groups=WorkoutExercise::query()
            ->whereHas('session',fn ($query)=>$query->where('user_id',$user->id)->whereIn('status',['completed','partial']))
            ->whereHas('sets',fn ($query)=>$query->where('completed',true)->where('set_type','working'))
            ->with(['sets'=>fn ($query)=>$query->where('completed',true)->where('set_type','working')])
            ->latest('id')->get()->groupBy('performed_exercise_id');

        $eligible=0;
        $improved=[];
        foreach($groups as $exerciseId=>$exposures) {
            $recent=$exposures->take(4);
            if($recent->count()<2) continue;
            $eligible++;
            $metrics=$recent->map(function (WorkoutExercise $exposure): array {
                $sets=$exposure->sets;
                return [
                    'weight'=>(float)$sets->max('weight'),
                    'repetitions'=>(int)$sets->sum('repetitions'),
                    'volume'=>(float)$sets->sum(fn ($set)=>$set->volume),
                    'estimated_one_rep_max'=>(float)$sets->max('estimated_one_rep_max'),
                    'rir'=>$sets->whereNotNull('rir')->avg('rir'),
                ];
            });
            $latest=$metrics->first();
            $older=$metrics->slice(1);
            $hasImproved=collect(['weight','repetitions','volume','estimated_one_rep_max'])
                ->contains(fn (string $metric)=>$latest[$metric]>$older->max($metric));
            if(!$hasImproved && $latest['rir']!==null && $latest['repetitions']>=$older->max('repetitions')) {
                $olderRir=$older->whereNotNull('rir')->max('rir');
                $hasImproved=$olderRir!==null && $latest['rir']>$olderRir;
            }
            if($hasImproved) $improved[]=(int)$exerciseId;
        }

        return $this->progressSnapshots[$user->id]=['eligible'=>$eligible,'improved_ids'=>$improved];
    }


    private function completedPlannedSessions(User $user, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $user->workouts()->where('status','completed')->whereNotNull('routine_day_id')->whereBetween('started_at',[$start,$end])
            ->get(['started_at','routine_day_id'])
            ->unique(fn (WorkoutSession $session)=>$session->started_at->format('o-W').'-'.$session->routine_day_id)
            ->count();
    }

    private function consistentMonths(User $user): int
    {
        $goal=max(1,(int) ($user->profile?->weekly_workout_goal ?? 4) * 4);
        return $user->workouts()->where('status','completed')->whereNotNull('routine_day_id')->get(['started_at','routine_day_id'])
            ->groupBy(fn (WorkoutSession $session)=>$session->started_at->format('Y-m'))
            ->filter(fn ($sessions)=>$sessions->unique(fn (WorkoutSession $session)=>$session->started_at->format('o-W').'-'.$session->routine_day_id)->count()>=$goal)
            ->count();
    }
}
