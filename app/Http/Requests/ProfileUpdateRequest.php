<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'age'=>['nullable','integer','min:13','max:120'],'height_cm'=>['nullable','numeric','min:80','max:250'],
        'preferred_body_weight_unit'=>['required',Rule::in(['kg','lb'])],
        'objective'=>['required',Rule::in(['body_recomposition','muscle_gain','fat_loss','strength','general_fitness'])],
        'weekly_workout_goal'=>['required','integer','min:1','max:7'],'target_session_minutes'=>['required','integer','min:15','max:180'],
        'maximum_session_minutes'=>['required','integer','gte:target_session_minutes','max:240'],
        'protein_goal_min_grams'=>['nullable','integer','min:0','max:500'],'protein_goal_max_grams'=>['nullable','integer','gte:protein_goal_min_grams','max:500'],
        'sleep_goal_hours'=>['nullable','numeric','min:0','max:16'],'notes'=>['nullable','string','max:2000'],
    ]; }
}
