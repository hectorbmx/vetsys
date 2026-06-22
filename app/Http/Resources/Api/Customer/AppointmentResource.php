<?php

namespace App\Http\Resources\Api\Customer;

use App\Enums\AppointmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof AppointmentStatus ? $this->status : AppointmentStatus::from($this->status);
        $localStartsAt = $this->starts_at?->toImmutable()->setTimezone($this->timezone);
        $localEndsAt = $this->ends_at?->toImmutable()->setTimezone($this->timezone);

        return [
            'id' => $this->id,
            'status' => $status->value,
            'status_label' => $this->statusLabel($status),
            'animal' => [
                'id' => $this->animal_id,
                'name' => $this->animal_name_snapshot,
            ],
            'service' => [
                'id' => $this->catalog_item_id,
                'name' => $this->service_name_snapshot,
            ],
            'doctor' => [
                'id' => $this->doctor_user_id,
                'name' => $this->doctor_name_snapshot,
            ],
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'local_starts_at' => $localStartsAt?->toIso8601String(),
            'local_ends_at' => $localEndsAt?->toIso8601String(),
            'timezone' => $this->timezone,
            'duration_minutes' => $this->duration_minutes,
            'customer_reason' => $this->customer_reason,
            'rejection_reason' => $this->rejection_reason,
            'rejected_at' => $this->rejected_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'is_late_cancellation' => $this->is_late_cancellation,
            'cancellation_fee_status' => $this->cancellation_fee_status?->value,
            'cancellation_fee_amount' => $this->cancellation_fee_amount !== null
                ? (float) $this->cancellation_fee_amount
                : null,
            'requested_at' => $this->requested_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'no_show_at' => $this->no_show_at?->toISOString(),
            'pending_proposal' => new AppointmentProposalResource($this->whenLoaded('pendingProposal')),
            'proposals' => AppointmentProposalResource::collection($this->whenLoaded('proposals')),
            'events' => AppointmentEventResource::collection($this->whenLoaded('events')),
            'can_cancel' => in_array($status, [
                AppointmentStatus::PendingTenant,
                AppointmentStatus::PendingCustomer,
                AppointmentStatus::Confirmed,
            ], true) && $this->starts_at?->isFuture(),
            'can_respond_to_proposal' => $status === AppointmentStatus::PendingCustomer
                && $this->relationLoaded('pendingProposal')
                && $this->pendingProposal !== null,
        ];
    }

    private function statusLabel(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PendingTenant => 'Pendiente de confirmacion',
            AppointmentStatus::PendingCustomer => 'Esperando tu respuesta',
            AppointmentStatus::Confirmed => 'Confirmada',
            AppointmentStatus::Rejected => 'Rechazada',
            AppointmentStatus::Cancelled => 'Cancelada',
            AppointmentStatus::Completed => 'Completada',
            AppointmentStatus::NoShow => 'No asistio',
        };
    }
}
