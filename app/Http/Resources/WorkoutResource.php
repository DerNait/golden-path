<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'scheduled_for'=>$this->scheduled_for,
            'started_at'=>$this->started_at,
            'finished_at'=>$this->finished_at,
            'duration_seconds'=>$this->duration_seconds,
            'status'=>$this->status,
            'sleep_hours'=>$this->sleep_hours,
            'energy_level'=>$this->energy_level,
            'motivation_level'=>$this->motivation_level,
            'discomfort_notes'=>$this->discomfort_notes,
            'session_difficulty'=>$this->session_difficulty,
            'session_satisfaction'=>$this->session_satisfaction,
            'is_atypical'=>$this->is_atypical,
            'atypical_reason'=>$this->atypical_reason,
            'notes'=>$this->notes,
            'total_volume'=>$this->total_volume,
            'working_sets_count'=>$this->working_sets_count,
            'xp_earned'=>$this->xp_earned,
            'power_earned'=>$this->power_earned,
            'routine_snapshot'=>$this->routine_snapshot_json,
            'exercises'=>$this->whenLoaded('exercises',fn ()=>$this->exercises->map(fn ($item)=>[
                'id'=>$item->id,
                'position'=>$item->position,
                'was_substituted'=>$item->was_substituted,
                'substitution_reason'=>$item->substitution_reason,
                'planned_exercise'=>new ExerciseResource($item->plannedExercise),
                'performed_exercise'=>new ExerciseResource($item->performedExercise),
                'planned'=>$item->planned_snapshot_json,
                'previous_performance'=>$item->previous_performance_json,
                'recommendation'=>$item->recommendation_snapshot_json,
                'notes'=>$item->notes,
                'sets'=>$item->sets,
            ])),
            'improvements'=>$this->whenLoaded('exercises',fn ()=>$this->improvements()),
            'weekly_progress'=>$this->when($this->relationLoaded('user') && $this->finished_at,fn ()=>$this->weeklyProgress()),
            'current_weekly_streak'=>$this->when(
                $this->relationLoaded('user') && $this->user->relationLoaded('gameProfile'),
                fn ()=>(int) ($this->user->gameProfile?->current_weekly_streak ?? 0),
            ),
        ];
    }

    private function improvements(): array
    {
        return $this->exercises->map(function ($exercise): ?array {
            $current=$exercise->sets->where('completed',true)->where('set_type','working');
            $previous=collect(data_get($exercise->previous_performance_json,'sets',[]))
                ->where('completed',true)->where('set_type','working');
            if ($current->isEmpty()) return null;

            $message=null;
            if ($current->contains('is_personal_record',true)) {
                $message='Nuevo record personal';
            } elseif ($previous->isNotEmpty()) {
                $repetitionGain=(int) $current->sum('repetitions')-(int) $previous->sum('repetitions');
                $currentRir=$current->whereNotNull('rir')->avg('rir');
                $previousRir=$previous->whereNotNull('rir')->avg('rir');
                if ($repetitionGain > 0) {
                    $message="+{$repetitionGain} repeticiones totales";
                } elseif ($repetitionGain === 0 && $currentRir !== null && $previousRir !== null && $currentRir > $previousRir) {
                    $message='Mismo rendimiento con '.number_format($currentRir-$previousRir,1).' RIR adicional';
                }
            }

            return $message ? ['exercise'=>$exercise->performedExercise->name,'message'=>$message] : null;
        })->filter()->values()->all();
    }

    private function weeklyProgress(): array
    {
        $start=CarbonImmutable::parse($this->started_at)->startOfWeek();
        $completed=$this->user->workouts()->where('status','completed')->whereNotNull('routine_day_id')
            ->whereBetween('started_at',[$start,$start->endOfWeek()])->distinct()->count('routine_day_id');

        return [
            'completed'=>$completed,
            'goal'=>(int) ($this->user->profile?->weekly_workout_goal ?? 4),
        ];
    }
}
