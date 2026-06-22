<?php

namespace App\Enums;

enum AppointmentLateFeeType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
