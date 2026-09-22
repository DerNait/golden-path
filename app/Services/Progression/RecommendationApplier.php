<?php

namespace App\Services\Progression;

use App\Models\ProgressionRecommendation;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutExercise;

/**
 * Writes a recommendation's load and repetition goal where the next session
 * will read them.
 *
 * Targets are kept per (exercise, number of sets): the same movement planned
 * at three sets on one day and two on another, or trained as an alternative,
 * keeps a separate goal for each. The routine slot is only updated when it
 * plans this very exercise at that set count, so an alternative never
 * overwrites the planned exercise.
 */
class RecommendationApplier
{
    public function __construct(private readonly ExerciseTargetService $targets) {}

    public function apply(ProgressionRecommendation $recommendation, ?float $weight, ?int $totalReps, ?array $distribution = null): void
    {
        $sets = $this->contextSets($recommendation);
        $slot = $recommendation->routineExercise;
        $plansThisExercise = $slot
            && (int) $slot->exercise_id === (int) $recommendation->exercise_id
            && (int) $slot->target_sets === $sets;
        $clamped = $totalReps !== null && $slot ? $this->clampTotalRepetitions($slot, $sets, $totalReps) : $totalReps;
        // A split that no longer adds up to the goal would mislead; drop it.
        if ($distribution && $clamped !== null && array_sum($distribution) !== $clamped) $distribution = null;

        if ($plansThisExercise) {
            $changes = [];
            if ($weight !== null) {
                $changes['target_weight'] = $weight;
                $changes['progression_target_total_reps'] = $slot->target_sets * $slot->minimum_reps;
            }
            if ($clamped !== null) $changes['progression_target_total_reps'] = $clamped;
            if ($changes) $slot->update($changes);
        }

        $exercise = $recommendation->exercise;
        if (! $exercise || ($weight === null && $clamped === null && ! $distribution)) return;
        if ($sets < 1) return;

        $this->targets->remember(User::findOrFail($recommendation->user_id), $exercise, $sets, $weight, $recommendation->weight_unit, $clamped, $distribution);
    }

    /**
     * How many sets this recommendation belongs to: the count it was written
     * for, else the slot it points at. An exercise trained only as an
     * alternative has no slot of its own, so it falls back to the slot it was
     * last performed in: the sets the routine asks for there, not however many
     * happened to be logged.
     */
    public function contextSets(ProgressionRecommendation $recommendation): int
    {
        if ($recommendation->target_sets) return (int) $recommendation->target_sets;
        if ($recommendation->routineExercise) return (int) $recommendation->routineExercise->target_sets;

        $own = RoutineExercise::where('exercise_id', $recommendation->exercise_id)
            ->whereHas('routineDay.routine', fn ($query) => $query->where('user_id', $recommendation->user_id)->where('is_active', true))
            ->orderBy('routine_day_id')->orderBy('position')->value('target_sets');
        if ($own) return (int) $own;

        $lastPerformed = WorkoutExercise::where('performed_exercise_id', $recommendation->exercise_id)
            ->whereNotNull('routine_exercise_id')
            ->whereHas('session', fn ($query) => $query->where('user_id', $recommendation->user_id))
            ->with('routineExercise:id,target_sets')->latest('id')->first();

        return (int) ($lastPerformed?->routineExercise?->target_sets ?? 0);
    }

    private function clampTotalRepetitions(RoutineExercise $slot, int $sets, int $total): int
    {
        $minimum = $sets * (int) $slot->minimum_reps;
        $maximum = $sets * (int) $slot->maximum_reps;
        if ($maximum < 1) return $total;

        return min($maximum, max($minimum, $total));
    }
}
