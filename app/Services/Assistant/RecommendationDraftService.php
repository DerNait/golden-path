<?php

namespace App\Services\Assistant;

use App\Enums\RecommendationStatus;
use App\Models\ProgressionRecommendation;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutExercise;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationDraftService
{
    /**
     * Persist assistant-authored recommendation drafts. Each draft supersedes
     * the current pending recommendation for its exercise and is stored as a
     * pending recommendation tagged with source=assistant, so the user reviews
     * and applies it through the existing accept/modify/ignore flow.
     *
     * @param  array<int,array<string,mixed>>  $drafts
     */
    public function create(User $user, array $drafts): Collection
    {
        return DB::transaction(function () use ($user, $drafts): Collection {
            return collect($drafts)->map(function (array $draft) use ($user): ProgressionRecommendation {
                $exerciseId = (int) $draft['exercise_id'];

                $lastPerformed = WorkoutExercise::where('performed_exercise_id', $exerciseId)
                    ->whereHas('session', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['completed', 'partial']))
                    ->latest('id')->first();
                $lastSessionId = $lastPerformed?->workout_session_id;

                // Exercises trained only as an alternative have no slot of their
                // own, so fall back to the slot they were last performed in.
                $routineExercise = RoutineExercise::where('exercise_id', $exerciseId)
                    ->whereHas('routineDay.routine', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
                    ->orderBy('routine_day_id')->orderBy('position')->first()
                    ?? $lastPerformed?->routineExercise;

                ProgressionRecommendation::where('user_id', $user->id)
                    ->where('exercise_id', $exerciseId)
                    ->where('status', RecommendationStatus::Pending->value)
                    ->update(['status' => RecommendationStatus::Superseded->value]);

                return ProgressionRecommendation::create([
                    'user_id' => $user->id,
                    'exercise_id' => $exerciseId,
                    'routine_exercise_id' => $routineExercise?->id,
                    'source_workout_session_id' => $lastSessionId,
                    'recommendation_type' => $draft['recommendation_type'],
                    'current_weight' => $routineExercise?->target_weight,
                    'suggested_weight' => $draft['suggested_weight'] ?? null,
                    'weight_unit' => $routineExercise?->weight_unit,
                    // A split with no explicit total still states the goal.
                    'suggested_total_repetitions' => $draft['suggested_total_repetitions']
                        ?? (isset($draft['suggested_rep_distribution']) ? array_sum($draft['suggested_rep_distribution']) : null),
                    'suggested_rep_distribution' => $draft['suggested_rep_distribution'] ?? null,
                    'reason' => $draft['reason'],
                    'confidence' => $draft['confidence'],
                    'status' => RecommendationStatus::Pending->value,
                    'metadata_json' => [
                        'source' => 'assistant',
                        'provider' => $draft['provider'],
                        'model' => $draft['model'],
                        'data_period' => $draft['data_period'] ?? null,
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);
            });
        });
    }
}
