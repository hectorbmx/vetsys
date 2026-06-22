<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->event_type?->value,
            'previous_status' => $this->previous_status?->value,
            'new_status' => $this->new_status?->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
