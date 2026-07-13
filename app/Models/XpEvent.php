<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class XpEvent extends Model
{
    protected $guarded = ['id'];
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function workoutSession(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WorkoutSession::class); }
    public function achievement(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Achievement::class); }
}
