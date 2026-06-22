<?php

namespace App\Services;

use App\Contracts\PushGateway;

class DisabledPushGateway implements PushGateway
{
    public function send(string $token, string $title, string $body, array $data): void
    {
        // Disabled environments skip the delivery before reaching the gateway.
    }
}
