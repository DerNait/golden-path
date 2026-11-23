<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:4000'],
            // "change" is written automatically when the routine is edited.
            'kind' => ['sometimes', Rule::in(['note', 'summary'])],
            'for_date' => ['sometimes', 'date_format:Y-m-d'],
            'workout_session_id' => ['sometimes', 'nullable', 'integer', Rule::exists('workout_sessions', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
