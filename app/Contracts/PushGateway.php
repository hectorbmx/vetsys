<?php

namespace App\Contracts;

interface PushGateway
{
    public function send(string $token, string $title, string $body, array $data): void;
}
