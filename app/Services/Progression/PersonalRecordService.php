<?php

namespace App\Services\Progression;

use App\Models\PersonalRecord;
use App\Models\WorkoutSet;
use Illuminate\Support\Collection;

class PersonalRecordService
{
    public function evaluate(WorkoutSet $set): Collection
    {
        $set->loadMissing('workoutExercise.session');
        $workoutExercise = $set->workoutExercise;
        $userId = $workoutExercise->session->user_id;
        $exerciseId = $workoutExercise->performed_exercise_id;

        $sets = WorkoutSet::query()
            ->where('completed', true)
            ->where('set_type', '!=', 'warmup')
            ->whereHas('workoutExercise', function ($query) use ($exerciseId, $userId): void {
                $query->where('performed_exercise_id', $exerciseId)
                    ->whereHas('session', fn ($session) => $session->where('user_id', $userId));
            })
            ->get();

        $metrics = collect([
            'heaviest_weight' => $sets->max(fn (WorkoutSet $item) => (float) ($item->weight ?? 0)),
            'most_repetitions' => $sets->max(fn (WorkoutSet $item) => (int) ($item->repetitions ?? 0)),
            'best_set_volume' => $sets->max(fn (WorkoutSet $item) => $item->volume),
            'estimated_one_rep_max' => $sets->max(fn (WorkoutSet $item) => (float) ($item->estimated_one_rep_max ?? 0)),
        ])->filter(fn ($value) => (float) ($value ?? 0) > 0);

        $records = $metrics->map(function (float|int $value, string $type) use ($sets, $userId, $exerciseId): PersonalRecord {
            $recordSet = $sets->first(fn (WorkoutSet $item) => match ($type) {
                'heaviest_weight' => (float) ($item->weight ?? 0) === (float) $value,
                'most_repetitions' => (int) ($item->repetitions ?? 0) === (int) $value,
                'best_set_volume' => $item->volume === (float) $value,
                'estimated_one_rep_max' => (float) ($item->estimated_one_rep_max ?? 0) === (float) $value,
            });
            return PersonalRecord::updateOrCreate(
                ['user_id'=>$userId,'exercise_id'=>$exerciseId,'record_type'=>$type],
                ['workout_set_id'=>$recordSet?->id,'value'=>$value,'weight_unit'=>$recordSet?->weight_unit,'achieved_at'=>$recordSet?->completed_at ?? now()],
            );
        })->values();

        $set->forceFill(['is_personal_record' => $records->contains(fn (PersonalRecord $record) => (int) $record->workout_set_id === (int) $set->id)])->save();
        return $records->where('workout_set_id', $set->id)->values();
    }
}
