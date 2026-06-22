<?php

namespace App\Http\Resources\Api\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAppointmentProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'message' => $this->message,
            'status' => $this->status?->value,
            'expires_at' => $this->expires_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
            'response_message' => $this->response_message,
            'proposed_by' => $this->whenLoaded('proposer', fn () => $this->proposer ? [
                'id' => $this->proposer->id,
                'name' => $this->proposer->name,
            ] : null),
        ];
    }
}
