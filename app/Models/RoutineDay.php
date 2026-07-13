<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RoutineDay extends Model
{
    protected $guarded = ['id','routine_id'];
    public function routine(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Routine::class); }
    public function exercises(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(RoutineExercise::class)->orderBy('position'); }
}
