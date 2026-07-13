<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkoutSet extends Model
{
    protected $guarded = ['id','workout_exercise_id','is_personal_record'];
    protected $appends = ['volume','estimated_one_rep_max'];
    protected function casts(): array { return ['completed'=>'boolean','is_personal_record'=>'boolean','completed_at'=>'datetime','weight'=>'decimal:2']; }
    public function workoutExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WorkoutExercise::class); }
    public function getVolumeAttribute(): float { return $this->completed && $this->set_type !== 'warmup' ? (float)($this->weight ?? 0) * (int)($this->repetitions ?? 0) : 0; }
    public function getEstimatedOneRepMaxAttribute(): ?float { return $this->volume > 0 && $this->repetitions <= 15 ? round((float)$this->weight * (1 + $this->repetitions / 30), 2) : null; }
}
