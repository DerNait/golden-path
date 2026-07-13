<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserAchievement extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['unlocked_at'=>'datetime']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function achievement(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Achievement::class); }
}
