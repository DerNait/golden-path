<?php
namespace App\Enums;
enum RecommendationType: string
{
    case Calibrate = 'calibrate';
    case IncreaseWeight = 'increase_weight';
    case IncreaseRepetitions = 'increase_repetitions';
    case Maintain = 'maintain';
    case ReduceWeight = 'reduce_weight';
    case IncreaseRest = 'increase_rest';
    case PossibleDeload = 'possible_deload';
    case ManualReview = 'manual_review';
}
