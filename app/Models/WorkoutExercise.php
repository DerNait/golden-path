<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WorkoutExercise extends Model
{
    protected $guarded = ['id','workout_session_id'];
    protected function casts(): array { return ['was_substituted'=>'boolean','planned_snapshot_json'=>'array','previous_performance_json'=>'array','recommendation_snapshot_json'=>'array','target_snapshot_json'=>'array']; }
    // Recent assistant recommendations for the exercise actually performed, newest
    // first. Exercises are user-scoped, so matching on exercise_id keeps it to this athlete.
    public function assistantRecommendations(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ProgressionRecommendation::class,'exercise_id','performed_exercise_id')->whereIn('status',['pending','accepted','modified'])->where('metadata_json->source','assistant')->where('created_at','>=',now()->subDays(14))->latest('id'); }
    // The one written for the set count this session plans: the same exercise at
    // three sets one day and two another keeps a recommendation per count.
    public function currentAssistantRecommendation(): ?ProgressionRecommendation { $sets=(int) data_get($this->planned_snapshot_json,'target_sets'); return $this->assistantRecommendations->first(fn ($r)=>! $r->target_sets || ! $sets || (int) $r->target_sets===$sets); }
    public function session(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WorkoutSession::class,'workout_session_id'); }
    public function plannedExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class,'planned_exercise_id'); }
    public function performedExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Exercise::class,'performed_exercise_id'); }
    public function routineExercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(RoutineExercise::class); }
    public function sets(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(WorkoutSet::class)->orderBy('set_number'); }
}
