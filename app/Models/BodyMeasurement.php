<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BodyMeasurement extends Model
{
    protected $guarded = ['id','user_id'];
    protected function casts(): array { return ['recorded_on'=>'date','body_weight_kg'=>'decimal:2','waist_cm'=>'decimal:2']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
}
