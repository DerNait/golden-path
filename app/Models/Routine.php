<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Routine extends Model
{
    protected $guarded = ['id','user_id'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function days(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(RoutineDay::class)->orderBy('position'); }
    public function workouts(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(WorkoutSession::class); }
}
