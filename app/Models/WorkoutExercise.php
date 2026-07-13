<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkoutExercise extends Model
{
    protected $guarded = ['id','workout_session_id'];
    protected function casts(): array { return ['was_substituted'=>'boolean','planned_snapshot_json'=>'array','previous_performance_json'=>'array','recommendation_snapshot_json'=>'array']; }
    public function session(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WorkoutSession::class,'workout_session_id'); }
    public function plannedExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class,'planned_exercise_id'); }
    public function performedExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class,'performed_exercise_id'); }
    public function routineExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(RoutineExercise::class); }
    public function sets(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(WorkoutSet::class)->orderBy('set_number'); }
}
