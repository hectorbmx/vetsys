<?php

namespace App\Http\Resources\Api\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAppointmentEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->event_type?->value,
            'previous_status' => $this->previous_status?->value,
            'new_status' => $this->new_status?->value,
            'metadata' => $this->metadata,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
