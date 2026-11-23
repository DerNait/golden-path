<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edits to one routine slot. On update every field is optional; a short
 * reason is always required because it is shown to the athlete.
 */
class RoutineSlotRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $exercise = Rule::exists('exercises', 'id')->where('user_id', $this->user()->id)->where('is_active', true);

        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'exercise_id' => [$required, 'integer', $exercise],
            'priority' => ['sometimes', Rule::in(['essential', 'recommended', 'optional'])],
            'target_sets' => [$required, 'integer', 'between:1,10'],
            'minimum_reps' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'maximum_reps' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'target_weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:2000'],
            'weight_unit' => ['sometimes', 'nullable', Rule::in(['kg', 'lb'])],
            'target_rir_min' => ['sometimes', 'nullable', 'integer', 'between:0,5'],
            'target_rir_max' => ['sometimes', 'nullable', 'integer', 'between:0,5'],
            'rest_seconds' => ['sometimes', 'integer', 'between:0,900'],
            'weight_increment' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
