<?php

namespace App\Enums;

enum AppointmentCancellationPolicy: string
{
    case NoPenalty = 'no_penalty';
    case LateFeeReview = 'late_fee_review';
}
