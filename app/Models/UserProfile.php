<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserProfile extends Model
{
    protected $guarded = ['id','user_id'];
    protected function casts(): array { return ['height_cm'=>'decimal:2','initial_body_weight_kg'=>'decimal:2','current_body_weight_kg'=>'decimal:2','initial_waist_cm'=>'decimal:2','current_waist_cm'=>'decimal:2','sleep_goal_hours'=>'decimal:1']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
}
