<?php
namespace App\Policies;
use App\Models\ProgressionRecommendation;
use App\Models\User;
class ProgressionRecommendationPolicy { public function update(User $user, ProgressionRecommendation $model): bool { return $model->user_id === $user->id; } }
