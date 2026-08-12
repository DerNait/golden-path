<?php
namespace App\Http\Requests;
use App\Models\WorkoutExercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class SubstitutionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'alternative_exercise_id'=>['required','integer','exists:exercises,id'],
        // Going back to the planned exercise undoes the substitution, so there
        // is nothing to justify; every other swap still needs its reason.
        'reason'=>[Rule::requiredIf(fn ()=>! $this->returnsToPlanned()),'nullable',Rule::in(['equipment_busy','equipment_unavailable','discomfort','personal_preference','other'])],
    ]; }

    private function returnsToPlanned(): bool
    {
        $exercise=$this->route('workoutExercise');

        return $exercise instanceof WorkoutExercise
            && (int) $this->input('alternative_exercise_id')===(int) $exercise->planned_exercise_id;
    }
}
