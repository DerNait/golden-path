<?php

namespace App\Services\Assistant;

use App\Enums\RecommendationStatus;
use App\Models\Exercise;
use App\Models\ProgressionRecommendation;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Services\Progression\ExerciseTargetService;
use App\Services\Progression\RecommendationApplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationDraftService
{
    public function __construct(
        private readonly ExerciseTargetService $targets,
        private readonly RecommendationApplier $applier,
    ) {}

    /**
     * Publish assistant-authored recommendations. The assistant is the only
     * source of recommendations, so each one applies as soon as it is
     * published: its load and repetition goal become the target for the set
     * count it was written for. Anything still pending for the exercise is
     * superseded.
     *
     * @param  array<int,array<string,mixed>>  $drafts
     */
    public function create(User $user, array $drafts): Collection
    {
        return DB::transaction(function () use ($user, $drafts): Collection {
            return collect($drafts)->map(function (array $draft) use ($user): ProgressionRecommendation {
                $exercise = Exercise::findOrFail((int) $draft['exercise_id']);
                $sets = isset($draft['target_sets']) ? (int) $draft['target_sets'] : null;

                $lastPerformed = $this->lastPerformed($user, $exercise, $sets);
                $routineExercise = $this->slot($user, $exercise, $sets) ?? $lastPerformed?->routineExercise;
                $current = $this->targets->resolve($user, $exercise, $routineExercise);

                ProgressionRecommendation::where('user_id', $user->id)
                    ->where('exercise_id', $exercise->id)
                    ->where('status', RecommendationStatus::Pending->value)
                    ->update(['status' => RecommendationStatus::Superseded->value]);

                $total = $draft['suggested_total_repetitions']
                    // A split with no explicit total still states the goal.
                    ?? (isset($draft['suggested_rep_distribution']) ? array_sum($draft['suggested_rep_distribution']) : null);

                $recommendation = ProgressionRecommendation::create([
                    'user_id' => $user->id,
                    'exercise_id' => $exercise->id,
                    'routine_exercise_id' => $routineExercise?->id,
                    'target_sets' => $sets ?? $routineExercise?->target_sets,
                    'source_workout_session_id' => $lastPerformed?->workout_session_id,
                    'recommendation_type' => $draft['recommendation_type'],
                    'current_weight' => $current['weight'],
                    'suggested_weight' => $draft['suggested_weight'] ?? null,
                    'weight_unit' => $current['weight_unit'],
                    'suggested_total_repetitions' => $total,
                    'suggested_rep_distribution' => $draft['suggested_rep_distribution'] ?? null,
                    'reason' => $draft['reason'],
                    'confidence' => $draft['confidence'],
                    'status' => RecommendationStatus::Accepted->value,
                    'accepted_at' => now(),
                    'metadata_json' => [
                        'source' => 'assistant',
                        'provider' => $draft['provider'],
                        'model' => $draft['model'],
                        'data_period' => $draft['data_period'] ?? null,
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);

                $this->applier->apply(
                    $recommendation,
                    isset($draft['suggested_weight']) ? (float) $draft['suggested_weight'] : null,
                    $total !== null ? (int) $total : null,
                    $draft['suggested_rep_distribution'] ?? null,
                );

                return $recommendation;
            });
        });
    }

    /**
     * The exercise's own slot in the active routine, at the requested set
     * count when one is given.
     */
    private function slot(User $user, Exercise $exercise, ?int $sets): ?RoutineExercise
    {
        return RoutineExercise::where('exercise_id', $exercise->id)
            ->whereHas('routineDay.routine', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->when($sets, fn ($q) => $q->where('target_sets', $sets))
            ->orderBy('routine_day_id')->orderBy('position')->first();
    }

    /**
     * Latest session where the exercise was performed. Exercises trained only
     * as an alternative have no slot of their own, so the slot they were
     * performed in stands in; with a set count, prefer a slot asking for it.
     */
    private function lastPerformed(User $user, Exercise $exercise, ?int $sets): ?WorkoutExercise
    {
        $query = fn () => WorkoutExercise::where('performed_exercise_id', $exercise->id)
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['completed', 'partial']))
            ->with('routineExercise')->latest('id');

        return ($sets ? $query()->whereHas('routineExercise', fn ($q) => $q->where('target_sets', $sets))->first() : null)
            ?? $query()->first();
    }
}
