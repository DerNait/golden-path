<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class RecommendationActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['suggested_weight'=>['nullable','numeric','min:0','max:2000'],'suggested_total_repetitions'=>['nullable','integer','min:0','max:5000']]; }
}
