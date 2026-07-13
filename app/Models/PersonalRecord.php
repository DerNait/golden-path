<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonalRecord extends Model
{
    protected $fillable = ['user_id','exercise_id','workout_set_id','record_type','value','weight_unit','achieved_at'];
    protected function casts(): array { return ['achieved_at'=>'datetime','value'=>'decimal:2']; }
    public function exercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function workoutSet(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WorkoutSet::class); }
}
