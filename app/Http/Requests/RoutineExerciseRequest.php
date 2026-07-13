<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RoutineExerciseRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'exercise_id'=>[$this->isMethod('post')?'required':'sometimes','integer','exists:exercises,id'],'priority'=>['required',Rule::in(['essential','recommended','optional'])],
        'target_sets'=>['required','integer','between:1,10'],'minimum_reps'=>['nullable','integer','min:0','max:1000'],'maximum_reps'=>['nullable','integer','gte:minimum_reps','max:1000'],
        'progression_target_reps'=>['nullable','integer','gte:minimum_reps','lte:maximum_reps'],'target_duration_seconds'=>['nullable','integer','min:1','max:7200'],
        'target_weight'=>['nullable','numeric','min:0','max:2000'],'weight_unit'=>['nullable',Rule::in(['kg','lb'])],
        'target_rir_min'=>['nullable','integer','between:0,5'],'target_rir_max'=>['nullable','integer','gte:target_rir_min','max:5'],
        'rest_seconds'=>['required','integer','between:0,900'],'weight_increment'=>['nullable','numeric','min:0.01','max:100'],
        'progression_type'=>['required',Rule::in(['double_progression','manual'])],'superset_group'=>['nullable','string','max:50'],'notes'=>['nullable','string','max:1000'],
    ]; }
}
