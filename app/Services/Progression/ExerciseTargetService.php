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
    /**
     * Ideal repetitions per set for a total: the remainder is front-loaded, so
     * 29 reps in 3 sets reads 10/10/9 -- the first sets are the fresh ones.
     *
     * @return array<int,int>
     */
    public static function distribute(int $total, int $sets): array
    {
        if ($total < 1 || $sets < 1) return [];

        $base = intdiv($total, $sets);
        $remainder = $total % $sets;

        return array_map(fn (int $index): int => $base + ($index < $remainder ? 1 : 0), range(0, $sets - 1));
    }

    /** @return array{sets:int,min_reps:?int,max_reps:?int,weight:?float,weight_unit:?string,total_reps:?int,rep_distribution:array<int,int>,source:string} */
    public function resolve(User $user, Exercise $exercise, ?RoutineExercise $slot): array
    {
        $sets = max(1, (int) ($slot->target_sets ?? 1));
        $context = [
            'sets' => $sets,
            'min_reps' => $slot?->minimum_reps !== null ? (int) $slot->minimum_reps : null,
            'max_reps' => $slot?->maximum_reps !== null ? (int) $slot->maximum_reps : null,
        ];

        $weight = null;
        $unit = null;
        $total = null;
        // Where the weight came from; the rep goal may come from another step.
        $source = 'none';

        $stored = ExerciseTarget::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)->where('target_sets', $sets)->first();

        $distribution = [];

        if ($stored) {
            $weight = $stored->target_weight !== null ? (float) $stored->target_weight : null;
            $unit = $weight !== null ? $stored->weight_unit : null;
            $total = $stored->target_total_reps !== null ? (int) $stored->target_total_reps : null;
            $distribution = array_map('intval', (array) ($stored->rep_distribution ?? []));
            $source = $weight !== null ? 'exercise_target' : $source;
        }

        // The slot plans this very exercise: its stored target already applies.
        // Otherwise borrow the exercise's own slot elsewhere in the routine and
        // scale its rep goal to today's number of sets.
        $plansThisExercise = $slot && (int) $slot->exercise_id === (int) $exercise->id;
        $reference = $plansThisExercise ? $slot : RoutineExercise::where('exercise_id', $exercise->id)
            ->whereHas('routineDay.routine', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->orderBy('routine_day_id')->orderBy('position')->first();

        if ($reference) {
            $referenceSets = max(1, (int) $reference->target_sets);
            $referenceTotal = $reference->progression_target_total_reps !== null
                ? (int) round(((int) $reference->progression_target_total_reps / $referenceSets) * $sets)
                : null;

            if ($weight === null && $reference->target_weight !== null) {
                $weight = (float) $reference->target_weight;
                $unit = $reference->weight_unit;
                $source = $plansThisExercise ? 'routine_slot' : 'home_slot';
            }
            if ($total === null) {
                $total = $referenceTotal;
            }
        }

        // Never planned a load: keep training with the one last used.
        if ($weight === null) {
            $lastSet = WorkoutSet::query()
                ->where('completed', true)->where('set_type', 'working')->whereNotNull('weight')
                ->whereHas('workoutExercise', fn ($q) => $q->where('performed_exercise_id', $exercise->id)
                    ->whereHas('session', fn ($s) => $s->where('user_id', $user->id)->whereIn('status', ['completed', 'partial'])))
                ->latest('id')->first();

            if ($lastSet) {
                $weight = (float) $lastSet->weight;
                $unit = $lastSet->weight_unit;
                $source = 'last_performance';
            }
        }

        // A stored split only applies to the sets it was written for.
        if (count($distribution) !== $sets) {
            $distribution = self::distribute((int) $total, $sets);
        }

        return $context + [
            'weight' => $weight,
            'weight_unit' => $unit ?? $exercise->default_weight_unit,
            'total_reps' => $total,
            'rep_distribution' => $distribution,
            'source' => $source,
        ];
    }

    public function remember(User $user, Exercise $exercise, int $sets, ?float $weight, ?string $unit, ?int $totalReps, ?array $distribution = null): ExerciseTarget
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

        if ($distribution) {
            $target->rep_distribution = array_values(array_map('intval', $distribution));
        }

        $target->save();

        return $target;
    }
}
