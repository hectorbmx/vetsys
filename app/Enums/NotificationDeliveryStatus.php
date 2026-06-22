<?php

namespace App\Enums;

enum NotificationDeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
