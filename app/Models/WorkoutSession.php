<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkoutSession extends Model
{
    protected $guarded = ['id','user_id'];
    protected function casts(): array { return ['scheduled_for'=>'date','started_at'=>'datetime','finished_at'=>'datetime','is_atypical'=>'boolean','routine_snapshot_json'=>'array']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function routineDay(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(RoutineDay::class); }
    public function exercises(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(WorkoutExercise::class)->orderBy('position'); }
    public function routine(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Routine::class); }
    public function trainingPhase(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(TrainingPhase::class); }
}
