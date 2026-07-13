<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class WorkoutFinishRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['session_difficulty'=>['nullable','integer','between:1,5'],'session_satisfaction'=>['nullable','integer','between:1,5'],'is_atypical'=>['sometimes','boolean'],'atypical_reason'=>['nullable','string','max:255'],'notes'=>['nullable','string','max:2000']]; }
}
