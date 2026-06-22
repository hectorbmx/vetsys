<?php

namespace App\Enums;

enum NotificationDeliveryChannel: string
{
    case TenantInApp = 'tenant_in_app';
    case CustomerInApp = 'customer_in_app';
    case Email = 'email';
    case Push = 'push';
}
