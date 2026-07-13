<?php
namespace App\Policies;
use App\Models\Exercise;
use App\Models\User;
class ExercisePolicy { public function before(User $user, string $ability): ?bool { return null; } public function view(User $user, Exercise $model): bool { return $model->user_id === $user->id; } public function update(User $user, Exercise $model): bool { return $this->view($user,$model); } public function delete(User $user, Exercise $model): bool { return $this->view($user,$model); } }
