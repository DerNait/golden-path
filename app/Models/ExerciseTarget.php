<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseTarget extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['target_weight' => 'decimal:2'];
    }

    public function exercise(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
