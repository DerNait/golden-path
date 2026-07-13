<?php
namespace App\Models;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = ['name','email','password'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed']; }
    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(UserProfile::class); }
    public function routines(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Routine::class); }
    public function trainingPhases(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(TrainingPhase::class); }
    public function bodyMeasurements(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(BodyMeasurement::class); }
    public function exercises(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Exercise::class); }
    public function workouts(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(WorkoutSession::class); }
    public function gameProfile(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(GameProfile::class); }
}
