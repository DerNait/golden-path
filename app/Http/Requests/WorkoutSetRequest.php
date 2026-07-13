<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class WorkoutSetRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'set_number'=>['required','integer','min:1','max:100'],'set_type'=>['required',Rule::in(['warmup','working','backoff','drop'])],
        'weight'=>['nullable','numeric','min:0','max:2000'],'weight_unit'=>['nullable',Rule::in(['kg','lb'])],
        'repetitions'=>['nullable','integer','min:0','max:1000'],'duration_seconds'=>['nullable','integer','min:0','max:7200'],
        'rir'=>['nullable','integer','between:0,5'],'technique_rating'=>['nullable','integer','between:1,5'],
        'rest_seconds_actual'=>['nullable','integer','min:0','max:3600'],'completed'=>['sometimes','boolean'],'notes'=>['nullable','string','max:1000'],
    ]; }
}
