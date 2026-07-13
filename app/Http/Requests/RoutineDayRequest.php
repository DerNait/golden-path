<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoutineDayRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $day=$this->route('routineDay');
        $weekday=['required','integer','between:1,7'];
        if ($day) {
            $weekday[]=Rule::unique('routine_days','weekday')
                ->where(fn ($query)=>$query->where('routine_id',$day->routine_id))
                ->ignore($day->id);
        }

        return [
            'name'=>['required','string','max:100'],
            'weekday'=>$weekday,
            'day_type'=>['required',Rule::in(['training','rest'])],
            'estimated_minutes'=>['nullable','integer','min:15','max:240'],
            'notes'=>['nullable','string','max:1000'],
        ];
    }
}
