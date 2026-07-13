<?php
namespace App\Enums;
enum RecommendationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Ignored = 'ignored';
    case Modified = 'modified';
    case Superseded = 'superseded';
}
