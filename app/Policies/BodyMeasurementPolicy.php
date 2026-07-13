<?php
namespace App\Policies;
use App\Models\BodyMeasurement;
use App\Models\User;
class BodyMeasurementPolicy { public function update(User $user, BodyMeasurement $model): bool { return $model->user_id === $user->id; } public function delete(User $user, BodyMeasurement $model): bool { return $this->update($user,$model); } }
