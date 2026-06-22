<?php

namespace App\Enums;

enum AppointmentProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';
}
