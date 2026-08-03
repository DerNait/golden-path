<?php

namespace App\Services\Progression;

use App\Models\Exercise;
use App\Models\ExerciseTarget;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutSet;

/**
 * Resolves the target an exercise deserves in the context it is being trained.
 *
 * A routine slot stores the target of the exercise it plans, so performing an
 * alternative there (for example the Upper B barbell press as an Upper A
 * alternative) must not inherit the planned exercise's load. Targets are kept
 * per (exercise, number of sets) because the same movement at three sets is a
 * different demand than at two.
 */
class ExerciseTargetService
{
    /** @return array{sets:int,min_reps:?int,max_reps:?int,weight:?float,weight_unit:?string,total_reps:?int,source:string} */
    public function resolve(User $user, Exercise $exercise, ?RoutineExercise $slot): array
    {
        $sets = max(1, (int) ($slot->target_sets ?? 1));
        $context = [
            'sets' => $sets,
            'min_reps' => $slot?->minimum_reps !== null ? (int) $slot->minimum_reps : null,
            'max_reps' => $slot?->maximum_reps !== null ? (int) $slot->maximum_reps : null,
        ];

        $stored = ExerciseTarget::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)->where('target_sets', $sets)->first();

        if ($stored && ($stored->target_weight !== null || $stored->target_total_reps !== null)) {
            return $context + [
                'weight' => $stored->target_weight !== null ? (float) $stored->target_weight : null,
                'weight_unit' => $stored->weight_unit ?? $exercise->default_weight_unit,
                'total_reps' => $stored->target_total_reps !== null ? (int) $stored->target_total_reps : null,
                'source' => 'exercise_target',
            ];
        }

        // The slot plans this very exercise: its stored target already applies.
        if ($slot && (int) $slot->exercise_id === (int) $exercise->id) {
            return $context + [
                'weight' => $slot->target_weight !== null ? (float) $slot->target_weight : null,
                'weight_unit' => $slot->weight_unit ?? $exercise->default_weight_unit,
                'total_reps' => $slot->progression_target_total_reps !== null ? (int) $slot->progression_target_total_reps : null,
                'source' => 'routine_slot',
            ];
        }

        // Performed as an alternative: borrow the exercise's own slot elsewhere
        // in the routine and scale its rep goal to today's number of sets.
        $home = RoutineExercise::where('exercise_id', $exercise->id)
            ->whereHas('routineDay.routine', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->orderBy('routine_day_id')->orderBy('position')->first();

        if ($home) {
            $homeSets = max(1, (int) $home->target_sets);
            $total = $home->progression_target_total_reps !== null
                ? (int) round(((int) $home->progression_target_total_reps / $homeSets) * $sets)
                : null;

            return $context + [
                'weight' => $home->target_weight !== null ? (float) $home->target_weight : null,
                'weight_unit' => $home->weight_unit ?? $exercise->default_weight_unit,
                'total_reps' => $total,
                'source' => 'home_slot',
            ];
        }

        // Nothing planned anywhere: fall back to the last load actually used.
        $lastSet = WorkoutSet::query()
            ->where('completed', true)->where('set_type', 'working')->whereNotNull('weight')
            ->whereHas('workoutExercise', fn ($q) => $q->where('performed_exercise_id', $exercise->id)
                ->whereHas('session', fn ($s) => $s->where('user_id', $user->id)->whereIn('status', ['completed', 'partial'])))
            ->latest('id')->first();

        return $context + [
            'weight' => $lastSet?->weight !== null ? (float) $lastSet->weight : null,
            'weight_unit' => $lastSet?->weight_unit ?? $exercise->default_weight_unit,
            'total_reps' => null,
            'source' => $lastSet ? 'last_performance' : 'none',
        ];
    }

    public function remember(User $user, Exercise $exercise, int $sets, ?float $weight, ?string $unit, ?int $totalReps): ExerciseTarget
    {
        $target = ExerciseTarget::firstOrNew([
            'user_id' => $user->id, 'exercise_id' => $exercise->id, 'target_sets' => max(1, $sets),
        ]);

        if ($weight !== null) {
            $target->target_weight = $weight;
            $target->weight_unit = $unit ?? $exercise->default_weight_unit;
        }

        if ($totalReps !== null) {
            $target->target_total_reps = $totalReps;
        }

        $target->save();

        return $target;
    }
}
