<?php
namespace App\Enums;
enum WorkoutStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Partial = 'partial';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
    case Rescheduled = 'rescheduled';
}
