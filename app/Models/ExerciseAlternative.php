<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExerciseAlternative extends Model
{
    protected $fillable = ['exercise_id','alternative_exercise_id','position','notes'];
    public function exercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class); }
    public function alternativeExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class,'alternative_exercise_id'); }
}
