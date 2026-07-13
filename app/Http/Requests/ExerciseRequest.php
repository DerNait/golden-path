<?php

namespace App\Http\Requests;

use App\Support\YouTubeUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('youtube_url')) {
            return;
        }

        $canonical=YouTubeUrl::canonical($this->input('youtube_url'));
        if ($canonical) {
            $this->merge(['youtube_url'=>$canonical]);
        }
    }

    public function rules(): array
    {
        return [
            'name'=>['required','string','max:120'],
            'description'=>['nullable','string','max:2000'],
            'instructions'=>['nullable','string','max:5000'],
            'equipment'=>['nullable','string','max:100'],
            'youtube_url'=>[
                'nullable',
                'string',
                'max:2048',
                function (string $attribute,mixed $value,Closure $fail): void {
                    if (YouTubeUrl::videoId($value) === null) {
                        $fail('El video de referencia debe ser un enlace HTTPS valido de YouTube.');
                    }
                },
            ],
            'metric_type'=>['required',Rule::in(['weight_reps','reps_only','duration','weight_duration','bodyweight_reps','bodyweight_added_weight'])],
            'weight_mode'=>['required',Rule::in(['total','per_dumbbell','per_side','machine_stack','added_weight','bodyweight','not_applicable'])],
            'default_weight_unit'=>['nullable',Rule::in(['kg','lb'])],
            'default_increment'=>['nullable','numeric','min:0.01','max:100'],
            'is_active'=>['sometimes','boolean'],
            'muscle_group_ids'=>['sometimes','array'],
            'muscle_group_ids.*'=>['integer','exists:muscle_groups,id'],
        ];
    }
}
