<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class SubstitutionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['alternative_exercise_id'=>['required','integer','exists:exercises,id'],'reason'=>['required',Rule::in(['equipment_busy','equipment_unavailable','discomfort','personal_preference','other'])]]; }
}
