<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One exercise inside a session. Shared by the session payload and the
 * substitution endpoint so the client always receives the same shape.
 */
class WorkoutExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'was_substituted' => $this->was_substituted,
            'substitution_reason' => $this->substitution_reason,
            'planned_exercise' => new ExerciseResource($this->plannedExercise),
            'performed_exercise' => new ExerciseResource($this->performedExercise),
            'planned' => $this->planned_snapshot_json,
            'previous_performance' => $this->previous_performance_json,
            'recommendation' => $this->recommendation_snapshot_json,
            'target' => $this->target_snapshot_json,
            'assistant_recommendation' => $this->relationLoaded('assistantRecommendation') ? $this->assistantRecommendation : null,
            'notes' => $this->notes,
            'sets' => $this->sets,
        ];
    }
}
