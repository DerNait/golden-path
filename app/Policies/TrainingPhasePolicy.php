<?php
namespace App\Policies;
use App\Models\TrainingPhase;
use App\Models\User;
class TrainingPhasePolicy { public function view(User $user, TrainingPhase $model): bool { return $model->user_id === $user->id; } public function update(User $user, TrainingPhase $model): bool { return $this->view($user,$model); } }
