<?php

namespace App\Http\Requests\Assistant;

use App\Enums\RecommendationConfidence;
use App\Enums\RecommendationType;
use App\Models\ExerciseAlternative;
use App\Models\RoutineExercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drafts' => ['required', 'array', 'min:1', 'max:50'],
            'drafts.*.exercise_id' => ['required', 'integer', Rule::exists('exercises', 'id')->where('user_id', $this->user()->id)],
            'drafts.*.target_sets' => ['required', 'integer', 'min:1', 'max:10'],
            'drafts.*.recommendation_type' => ['required', Rule::enum(RecommendationType::class)],
            'drafts.*.confidence' => ['required', Rule::enum(RecommendationConfidence::class)],
            'drafts.*.reason' => ['required', 'string', 'max:2000'],
            'drafts.*.suggested_weight' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'drafts.*.suggested_total_repetitions' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'drafts.*.suggested_rep_distribution' => ['nullable', 'array', 'max:10'],
            'drafts.*.suggested_rep_distribution.*' => ['integer', 'min:0', 'max:100'],
            'drafts.*.data_period' => ['nullable', 'string', 'max:100'],
            'drafts.*.provider' => ['required', 'string', 'max:100'],
            'drafts.*.model' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ((array) $this->input('drafts', []) as $index => $draft) {
                if (! is_array($draft)) continue;
                $split = $draft['suggested_rep_distribution'] ?? null;
                $total = $draft['suggested_total_repetitions'] ?? null;
                $sets = $draft['target_sets'] ?? null;

                if ($split && $total !== null && array_sum($split) !== (int) $total) {
                    $validator->errors()->add(
                        "drafts.{$index}.suggested_rep_distribution",
                        'La distribucion por serie debe sumar el total de repeticiones sugerido.',
                    );
                }

                if (is_array($split) && is_numeric($sets) && count($split) !== (int) $sets) {
                    $validator->errors()->add(
                        "drafts.{$index}.suggested_rep_distribution",
                        'La distribucion por serie debe tener un valor por cada serie de target_sets.',
                    );
                }

                // Goals are kept per (exercise, set count): a count the routine
                // never asks for would store a goal no session ever reads.
                if (is_numeric($sets) && isset($draft['exercise_id'])) {
                    $counts = $this->plannedSetCounts((int) $draft['exercise_id']);
                    if ($counts && ! in_array((int) $sets, $counts, true)) {
                        $validator->errors()->add(
                            "drafts.{$index}.target_sets",
                            'La rutina entrena este ejercicio con '.implode(' o ', $counts).' series; usa una de esas cantidades y publica una recomendacion por cada una.',
                        );
                    }
                }
            }
        });
    }

    /**
     * Set counts the active routine trains the exercise at: its own slots plus
     * the slots where it is allowed as an alternative. Empty for an exercise
     * outside the routine, which may then use any count.
     *
     * @return array<int,int>
     */
    private function plannedSetCounts(int $exerciseId): array
    {
        $plannedFor = ExerciseAlternative::where('alternative_exercise_id', $exerciseId)->pluck('exercise_id')->push($exerciseId);

        return RoutineExercise::whereIn('exercise_id', $plannedFor)
            ->whereHas('routineDay.routine', fn ($q) => $q->where('user_id', $this->user()->id)->where('is_active', true))
            ->distinct()->orderBy('target_sets')->pluck('target_sets')->map(fn ($sets) => (int) $sets)->all();
    }
}
