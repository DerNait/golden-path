<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AvatarPhase extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['visual_config_json'=>'array','requires_completed_phase'=>'boolean']; }
    public function maximumProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(GameProfile::class,'maximum_avatar_phase_id'); }
    public function activeProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(GameProfile::class,'active_avatar_phase_id'); }
}
