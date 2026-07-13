<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GameProfile extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['last_activity_at'=>'datetime','adherence_last_28_days'=>'decimal:2']; }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function maximumPhase(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(AvatarPhase::class,'maximum_avatar_phase_id'); }
    public function activePhase(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(AvatarPhase::class,'active_avatar_phase_id'); }
}
