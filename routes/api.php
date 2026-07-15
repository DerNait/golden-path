<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BodyMeasurementController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExerciseController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ProgressionController;
use App\Http\Controllers\Api\RoutineController;
use App\Http\Controllers\Api\TrainingPhaseController;
use App\Http\Controllers\Api\WorkoutController;
use App\Http\Controllers\Api\WorkoutSetController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'password']);

    Route::get('/push/config', [PushNotificationController::class, 'config']);
    Route::post('/push/subscriptions', [PushNotificationController::class, 'store']);
    Route::delete('/push/subscriptions', [PushNotificationController::class, 'destroy']);
    Route::post('/rest-timer/notifications', [PushNotificationController::class, 'scheduleRestTimer']);
    Route::delete('/rest-timer/notifications/current', [PushNotificationController::class, 'cancelRestTimer']);

    Route::get('/training-phases', [TrainingPhaseController::class, 'index']);
    Route::get('/training-phases/current', [TrainingPhaseController::class, 'current']);
    Route::post('/training-phases', [TrainingPhaseController::class, 'store']);
    Route::put('/training-phases/{trainingPhase}', [TrainingPhaseController::class, 'update']);
    Route::post('/training-phases/{trainingPhase}/activate', [TrainingPhaseController::class, 'activate']);
    Route::post('/training-phases/{trainingPhase}/complete', [TrainingPhaseController::class, 'complete']);

    Route::get('/body-measurements', [BodyMeasurementController::class, 'index']);
    Route::post('/body-measurements', [BodyMeasurementController::class, 'store']);
    Route::put('/body-measurements/{bodyMeasurement}', [BodyMeasurementController::class, 'update']);
    Route::delete('/body-measurements/{bodyMeasurement}', [BodyMeasurementController::class, 'destroy']);

    Route::get('/routine', [RoutineController::class, 'show']);
    Route::put('/routine', [RoutineController::class, 'update']);
    Route::post('/routine/days', [RoutineController::class, 'storeDay']);
    Route::put('/routine-days/{routineDay}', [RoutineController::class, 'updateDay']);
    Route::delete('/routine-days/{routineDay}', [RoutineController::class, 'destroyDay']);
    Route::post('/routine-days/reorder', [RoutineController::class, 'reorderDays']);
    Route::post('/routine-days/{routineDay}/exercises', [RoutineController::class, 'storeExercise']);
    Route::put('/routine-exercises/{routineExercise}', [RoutineController::class, 'updateExercise']);
    Route::delete('/routine-exercises/{routineExercise}', [RoutineController::class, 'destroyExercise']);
    Route::post('/routine-exercises/reorder', [RoutineController::class, 'reorderExercises']);

    Route::get('/exercises', [ExerciseController::class, 'index']);
    Route::post('/exercises', [ExerciseController::class, 'store']);
    Route::get('/exercises/{exercise}', [ExerciseController::class, 'show']);
    Route::put('/exercises/{exercise}', [ExerciseController::class, 'update']);
    Route::delete('/exercises/{exercise}', [ExerciseController::class, 'destroy']);
    Route::post('/exercises/{exercise}/image', [ExerciseController::class, 'uploadImage']);
    Route::delete('/exercises/{exercise}/image', [ExerciseController::class, 'deleteImage']);
    Route::post('/exercises/{exercise}/alternatives', [ExerciseController::class, 'addAlternative']);
    Route::delete('/exercise-alternatives/{exerciseAlternative}', [ExerciseController::class, 'deleteAlternative']);
    Route::post('/exercise-alternatives/reorder', [ExerciseController::class, 'reorderAlternatives']);

    Route::get('/workouts', [WorkoutController::class, 'index']);
    Route::post('/workouts/start', [WorkoutController::class, 'start'])->middleware('throttle:10,1');
    Route::get('/workouts/current', [WorkoutController::class, 'current']);
    Route::get('/workouts/{workoutSession}', [WorkoutController::class, 'show']);
    Route::put('/workouts/{workoutSession}', [WorkoutController::class, 'update']);
    Route::post('/workouts/{workoutSession}/finish', [WorkoutController::class, 'finish']);
    Route::post('/workouts/{workoutSession}/cancel', [WorkoutController::class, 'cancel']);
    Route::post('/workouts/{workoutSession}/mark-partial', [WorkoutController::class, 'partial']);
    Route::post('/workout-exercises/{workoutExercise}/substitute', [WorkoutController::class, 'substitute']);
    Route::post('/workout-exercises/{workoutExercise}/sets', [WorkoutSetController::class, 'store']);
    Route::put('/workout-sets/{workoutSet}', [WorkoutSetController::class, 'update']);
    Route::delete('/workout-sets/{workoutSet}', [WorkoutSetController::class, 'destroy']);

    Route::get('/progression/recommendations', [ProgressionController::class, 'index']);
    Route::post('/progression/recommendations/{recommendation}/accept', [ProgressionController::class, 'accept']);
    Route::post('/progression/recommendations/{recommendation}/ignore', [ProgressionController::class, 'ignore']);
    Route::post('/progression/recommendations/{recommendation}/modify', [ProgressionController::class, 'modify']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/progress/overview', [ProgressController::class, 'overview']);
    Route::get('/progress/activity-calendar', [ProgressController::class, 'activity']);
    Route::get('/progress/body', [ProgressController::class, 'body']);
    Route::get('/progress/exercises', [ProgressController::class, 'exercises']);
    Route::get('/progress/exercises/{exercise}', [ProgressController::class, 'exercise']);
    Route::get('/game/profile', [GameController::class, 'profile']);
    Route::get('/game/achievements', [GameController::class, 'achievements']);
    Route::get('/game/xp-events', [GameController::class, 'xpEvents']);
});
