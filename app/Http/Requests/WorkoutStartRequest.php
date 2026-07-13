<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class WorkoutStartRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['routine_day_id'=>['required','integer','exists:routine_days,id'],'sleep_hours'=>['nullable','numeric','between:0,24'],'energy_level'=>['nullable','integer','between:1,5'],'motivation_level'=>['nullable','integer','between:1,5'],'discomfort_notes'=>['nullable','string','max:1000']]; }
}
