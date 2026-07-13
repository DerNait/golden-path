<?php

namespace App\Http\Resources;

use App\Models\RoutineDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class RoutineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $weekStart=CarbonImmutable::now()->startOfWeek();
        $weeklySessions=$request->user()->workouts()
            ->whereBetween('started_at',[$weekStart,$weekStart->endOfWeek()])
            ->orderBy('started_at')
            ->get()
            ->keyBy('routine_day_id');

        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'description'=>$this->description,
            'is_active'=>$this->is_active,
            'days'=>$this->days->map(fn (RoutineDay $day)=>[
                'id'=>$day->id,
                'name'=>$day->name,
                'weekday'=>$day->weekday,
                'day_type'=>$day->day_type,
                'position'=>$day->position,
                'estimated_minutes'=>$day->estimated_minutes,
                'notes'=>$day->notes,
                'week_status'=>$this->weekStatus($day,$weeklySessions,$weekStart),
                'exercises'=>$day->exercises->map(fn ($planned)=>array_merge($planned->toArray(),[
                    'target_weight_label'=>$planned->target_weight === null ? 'Por calibrar' : $planned->target_weight.' '.$planned->weight_unit,
                    'exercise'=>new ExerciseResource($planned->exercise),
                ])),
            ]),
        ];
    }

    private function weekStatus(RoutineDay $day,Collection $weeklySessions,CarbonImmutable $weekStart): string
    {
        if ($day->day_type==='rest') {
            return 'planned_rest';
        }

        $session=$weeklySessions->get($day->id);
        if ($session) {
            return $session->status;
        }

        return $weekStart->addDays($day->weekday-1)->isBefore(CarbonImmutable::today())
            ? 'pending'
            : 'scheduled';
    }
}
