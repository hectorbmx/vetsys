<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case PendingTenant = 'pending_tenant';
    case PendingCustomer = 'pending_customer';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';
}
