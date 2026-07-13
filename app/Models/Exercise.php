<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Exercise extends Model
{
    protected $guarded = ['id','user_id'];
    protected function casts(): array { return ['is_active'=>'boolean','default_increment'=>'decimal:2']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function muscleGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(MuscleGroup::class)->withPivot('is_primary'); }
    public function alternatives(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ExerciseAlternative::class)->orderBy('position'); }
    public function alternativeExercises(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(self::class,'exercise_alternatives','exercise_id','alternative_exercise_id')->withPivot(['id','position','notes'])->orderByPivot('position'); }
}
