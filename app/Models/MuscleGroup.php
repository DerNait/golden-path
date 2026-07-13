<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MuscleGroup extends Model
{
    protected $fillable = ['name','slug'];
    public function exercises(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(Exercise::class)->withPivot('is_primary'); }
}
