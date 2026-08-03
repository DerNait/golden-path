<?php

namespace App\Http\Requests\Assistant;

use App\Enums\RecommendationConfidence;
use App\Enums\RecommendationType;
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
            'drafts.*.recommendation_type' => ['required', Rule::enum(RecommendationType::class)],
            'drafts.*.confidence' => ['required', Rule::enum(RecommendationConfidence::class)],
            'drafts.*.reason' => ['required', 'string', 'max:2000'],
            'drafts.*.suggested_weight' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'drafts.*.suggested_total_repetitions' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'drafts.*.data_period' => ['nullable', 'string', 'max:100'],
            'drafts.*.provider' => ['required', 'string', 'max:100'],
            'drafts.*.model' => ['required', 'string', 'max:100'],
        ];
    }
}
