<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RoutineExercise extends Model
{
    protected $guarded = ['id','routine_day_id'];
    protected function casts(): array { return ['target_weight'=>'decimal:2','weight_increment'=>'decimal:2']; }
    public function routineDay(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(RoutineDay::class); }
    public function exercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class); }
}
