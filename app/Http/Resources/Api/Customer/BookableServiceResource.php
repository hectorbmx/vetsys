<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookableServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->booking_description,
            'duration_minutes' => (int) ($this->appointment_duration_minutes
                ?: $request->attributes->get('appointment_default_duration', 30)),
            'buffer_minutes' => (int) ($this->appointment_buffer_minutes ?: 0),
        ];
    }
}
