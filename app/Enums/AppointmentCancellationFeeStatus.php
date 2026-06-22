<?php

namespace App\Enums;

enum AppointmentCancellationFeeStatus: string
{
    case NotApplicable = 'not_applicable';
    case PendingReview = 'pending_review';
    case Waived = 'waived';
    case Charged = 'charged';
}
