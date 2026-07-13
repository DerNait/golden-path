<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class BodyMeasurementRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'recorded_on'=>['required','date','before_or_equal:today'],'body_weight_kg'=>['nullable','numeric','min:20','max:500'],
        'waist_cm'=>['nullable','numeric','min:30','max:300'],'notes'=>['nullable','string','max:1000'],
    ]; }
    public function withValidator($validator): void { $validator->after(fn ($v) => ! $this->filled('body_weight_kg') && ! $this->filled('waist_cm') ? $v->errors()->add('measurement','Registra peso o cintura.') : null); }
}
