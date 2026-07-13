<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrainingPhase extends Model
{
    protected $guarded = ['id','user_id'];
    protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function routine(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Routine::class); }
    public function workouts(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(WorkoutSession::class); }
}
