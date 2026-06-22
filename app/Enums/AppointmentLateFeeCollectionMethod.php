<?php

namespace App\Enums;

enum AppointmentLateFeeCollectionMethod: string
{
    case Account = 'account';
    case NextVisit = 'next_visit';
}
