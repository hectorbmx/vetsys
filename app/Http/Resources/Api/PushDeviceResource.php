<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PushDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform?->value,
            'device_uuid' => $this->device_uuid,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
        ];
    }
}
