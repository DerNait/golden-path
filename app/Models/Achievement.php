<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Achievement extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['unlock_config_json'=>'array']; }
    public function userAchievements(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(UserAchievement::class); }
}
