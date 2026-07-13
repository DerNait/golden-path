<?php
namespace App\Policies;
use App\Models\User;
use App\Models\WorkoutSession;
class WorkoutSessionPolicy { public function view(User $user, WorkoutSession $model): bool { return $model->user_id === $user->id; } public function update(User $user, WorkoutSession $model): bool { return $this->view($user,$model); } }
