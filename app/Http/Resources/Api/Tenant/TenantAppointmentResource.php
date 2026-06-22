<?php

namespace App\Http\Resources\Api\Tenant;

use App\Enums\AppointmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAppointmentResource extends JsonResource
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
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer?->full_name,
                'email' => $this->customer?->email,
                'phone' => $this->customer?->phone,
            ],
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
            'buffer_minutes' => $this->buffer_minutes,
            'customer_reason' => $this->customer_reason,
            'internal_notes' => $this->internal_notes,
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'is_late_cancellation' => $this->is_late_cancellation,
            'cancellation_fee_status' => $this->cancellation_fee_status?->value,
            'cancellation_fee_amount' => $this->cancellation_fee_amount !== null
                ? (float) $this->cancellation_fee_amount
                : null,
            'requested_at' => $this->requested_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'no_show_at' => $this->no_show_at?->toISOString(),
            'pending_proposal' => new TenantAppointmentProposalResource($this->whenLoaded('pendingProposal')),
            'proposals' => TenantAppointmentProposalResource::collection($this->whenLoaded('proposals')),
            'events' => TenantAppointmentEventResource::collection($this->whenLoaded('events')),
            'actions' => [
                'confirm' => $status === AppointmentStatus::PendingTenant,
                'reject' => $status === AppointmentStatus::PendingTenant,
                'propose' => in_array($status, [
                    AppointmentStatus::PendingTenant,
                    AppointmentStatus::PendingCustomer,
                    AppointmentStatus::Confirmed,
                ], true),
                'cancel' => in_array($status, [
                    AppointmentStatus::PendingTenant,
                    AppointmentStatus::PendingCustomer,
                    AppointmentStatus::Confirmed,
                ], true),
                'complete' => $status === AppointmentStatus::Confirmed && $this->starts_at?->isPast(),
                'no_show' => $status === AppointmentStatus::Confirmed && $this->starts_at?->isPast(),
            ],
        ];
    }

    private function statusLabel(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PendingTenant => 'Pendiente de confirmacion',
            AppointmentStatus::PendingCustomer => 'Esperando respuesta del customer',
            AppointmentStatus::Confirmed => 'Confirmada',
            AppointmentStatus::Rejected => 'Rechazada',
            AppointmentStatus::Cancelled => 'Cancelada',
            AppointmentStatus::Completed => 'Completada',
            AppointmentStatus::NoShow => 'No asistio',
        };
    }
}
