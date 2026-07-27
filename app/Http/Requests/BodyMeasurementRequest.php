<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class BodyMeasurementRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'recorded_on'=>['required','date','before_or_equal:today'],'body_weight_kg'=>['nullable','numeric','min:20','max:500'],
        'waist_cm'=>['nullable','numeric','min:30','max:300'],
        'body_fat_percentage'=>['nullable','numeric','min:0','max:70'],'fat_mass_kg'=>['nullable','numeric','min:0','max:200'],
        'lean_mass_kg'=>['nullable','numeric','min:0','max:200'],'skeletal_muscle_mass_kg'=>['nullable','numeric','min:0','max:200'],
        'total_body_water_kg'=>['nullable','numeric','min:0','max:200'],'visceral_fat_level'=>['nullable','integer','min:1','max:20'],
        'basal_metabolic_rate_kcal'=>['nullable','integer','min:0','max:5000'],'notes'=>['nullable','string','max:1000'],
    ]; }
    public function withValidator($validator): void { $validator->after(fn ($v) => ! $this->filled('body_weight_kg') && ! $this->filled('waist_cm') ? $v->errors()->add('measurement','Registra peso o cintura.') : null); }
}
