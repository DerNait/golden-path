<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProgressionRecommendation extends Model
{
    protected $fillable = ['user_id','exercise_id','routine_exercise_id','source_workout_session_id','recommendation_type','current_weight','suggested_weight','weight_unit','suggested_total_repetitions','reason','confidence','status','metadata_json','accepted_at'];
    protected function casts(): array { return ['metadata_json'=>'array','accepted_at'=>'datetime']; }
    public function exercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class); }
    public function routineExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(RoutineExercise::class); }
}
