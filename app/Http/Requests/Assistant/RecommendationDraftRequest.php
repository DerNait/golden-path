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
                $split = $draft['suggested_rep_distribution'] ?? null;
                $total = $draft['suggested_total_repetitions'] ?? null;

                if ($split && $total !== null && array_sum($split) !== (int) $total) {
                    $validator->errors()->add(
                        "drafts.{$index}.suggested_rep_distribution",
                        'La distribucion por serie debe sumar el total de repeticiones sugerido.',
                    );
                }
            }
        });
    }
}
