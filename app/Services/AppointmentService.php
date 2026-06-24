<?php

namespace App\Services;

use App\Enums\AppointmentCancellationFeeStatus;
use App\Enums\AppointmentCancellationPolicy;
use App\Enums\AppointmentEventType;
use App\Enums\AppointmentLateFeeType;
use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentDomainException;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentProposal;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerUserLink;
use App\Models\FinalUserPatientAssignment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private AppointmentAvailabilityService $availability,
        private AppointmentScheduleLockService $scheduleLocks,
        private AppointmentIdempotencyService $idempotency,
        private TenantAppointmentAccessService $staffAccess,
    ) {}

    public function request(
        Tenant $tenant,
        User $actor,
        Customer $customer,
        Animal $animal,
        CatalogItem $service,
        DateTimeInterface $startsAtUtc,
        ?string $customerReason = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        $startsAtUtc = CarbonImmutable::instance($startsAtUtc)->utc();
        $nowUtc = $this->now($now);

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'request',
            $idempotencyKey,
            [
                'customer_id' => $customer->id,
                'animal_id' => $animal->id,
                'catalog_item_id' => $service->id,
                'starts_at' => $startsAtUtc,
                'customer_reason' => $customerReason,
            ],
            function () use ($tenant, $actor, $customer, $animal, $service, $startsAtUtc, $customerReason, $nowUtc) {
                $this->authorizeCustomer($tenant, $actor, $customer, $animal);
                $this->ensureServiceAvailable($tenant, $service);
                $setting = $tenant->appointmentSetting()->first();
                $timezone = $setting?->timezone ?: 'UTC';
                $date = $startsAtUtc->setTimezone($timezone)->format('Y-m-d');
                $slot = $this->availability
                    ->slotsForDate($tenant, $service, $date, $nowUtc)
                    ->first(fn ($slot) => $slot->startsAtUtc->equalTo($startsAtUtc));

                if (! $slot) {
                    throw $this->conflict(
                        'APPOINTMENT_SLOT_UNAVAILABLE',
                        'El horario seleccionado ya no esta disponible.',
                    );
                }

                $doctor = $setting->doctor()->firstOrFail();
                $appointment = Appointment::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'animal_id' => $animal->id,
                    'doctor_user_id' => $doctor->id,
                    'catalog_item_id' => $service->id,
                    'service_name_snapshot' => $service->name,
                    'animal_name_snapshot' => $animal->name,
                    'doctor_name_snapshot' => $doctor->veterinarianProfile?->professional_name ?: $doctor->name,
                    'starts_at' => $slot->startsAtUtc,
                    'ends_at' => $slot->endsAtUtc,
                    'timezone' => $slot->timezone,
                    'duration_minutes' => $slot->durationMinutes,
                    'buffer_minutes' => $slot->bufferMinutes,
                    'status' => AppointmentStatus::PendingTenant,
                    'customer_reason' => $customerReason,
                    'requested_at' => $nowUtc,
                    'created_by_user_id' => $actor->id,
                ]);

                $this->recordEvent(
                    $appointment,
                    $actor,
                    AppointmentEventType::Requested,
                    null,
                    AppointmentStatus::PendingTenant,
                );

                return $appointment;
            },
        );

        return $result;
    }

    public function createManual(
        Tenant $tenant,
        User $actor,
        Customer $customer,
        Animal $animal,
        CatalogItem $service,
        DateTimeInterface $startsAtUtc,
        ?int $durationMinutes = null,
        ?string $customerReason = null,
        ?string $internalNotes = null,
        ?string $idempotencyKey = null,
        bool $requiresCustomerConfirmation = false,
        ?DateTimeInterface $now = null,
    ): Appointment {
        $startsAtUtc = CarbonImmutable::instance($startsAtUtc)->utc();
        $nowUtc = $this->now($now);

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'create-manual',
            $idempotencyKey,
            [
                'customer_id' => $customer->id,
                'animal_id' => $animal->id,
                'catalog_item_id' => $service->id,
                'starts_at' => $startsAtUtc,
                'duration_minutes' => $durationMinutes,
                'customer_reason' => $customerReason,
                'internal_notes' => $internalNotes,
                'requires_customer_confirmation' => $requiresCustomerConfirmation,
            ],
            function () use (
                $tenant,
                $actor,
                $customer,
                $animal,
                $service,
                $startsAtUtc,
                $durationMinutes,
                $customerReason,
                $internalNotes,
                $requiresCustomerConfirmation,
                $nowUtc,
            ) {
                $this->staffAccess->authorize($tenant, $actor);
                $this->ensureManualParticipants($tenant, $customer, $animal);
                $this->ensureServiceAvailable($tenant, $service);
                $setting = $tenant->appointmentSetting()->with('doctor.veterinarianProfile')->first();
                $doctor = $setting?->doctor;

                if (! $setting || ! $doctor || ! $doctor->is_active || ! $doctor->veterinarianProfile?->is_active) {
                    throw new AppointmentDomainException(
                        'APPOINTMENT_CONFIGURATION_INCOMPLETE',
                        'La agenda no tiene un veterinario activo configurado.',
                        422,
                    );
                }

                if ($startsAtUtc->lessThanOrEqualTo($nowUtc)) {
                    throw new AppointmentDomainException(
                        'APPOINTMENT_START_IN_PAST',
                        'La cita manual debe iniciar en el futuro.',
                        422,
                    );
                }

                $duration = $durationMinutes
                    ?? $service->appointment_duration_minutes
                    ?? $setting->default_duration_minutes;
                $buffer = (int) ($service->appointment_buffer_minutes ?: 0);
                $this->ensureDuration((int) $duration);
                $endsAtUtc = $startsAtUtc->addMinutes((int) $duration);
                $this->scheduleLocks->lock($tenant, $doctor->id, $startsAtUtc, $setting->timezone);

                if (! $this->availability->intervalIsAvailable(
                    $tenant,
                    $doctor->id,
                    $startsAtUtc,
                    $endsAtUtc,
                    $buffer,
                    now: $nowUtc,
                )) {
                    throw $this->conflict(
                        'APPOINTMENT_SLOT_UNAVAILABLE',
                        'El horario seleccionado ya no esta disponible.',
                    );
                }

                $status = $requiresCustomerConfirmation
                    ? AppointmentStatus::PendingCustomer
                    : AppointmentStatus::Confirmed;

                $appointment = Appointment::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'animal_id' => $animal->id,
                    'doctor_user_id' => $doctor->id,
                    'catalog_item_id' => $service->id,
                    'service_name_snapshot' => $service->name,
                    'animal_name_snapshot' => $animal->name,
                    'doctor_name_snapshot' => $doctor->veterinarianProfile->professional_name ?: $doctor->name,
                    'starts_at' => $startsAtUtc,
                    'ends_at' => $endsAtUtc,
                    'timezone' => $setting->timezone,
                    'duration_minutes' => $duration,
                    'buffer_minutes' => $buffer,
                    'status' => $status,
                    'customer_reason' => $customerReason,
                    'internal_notes' => $internalNotes,
                    'requested_at' => $nowUtc,
                    'confirmed_at' => $requiresCustomerConfirmation ? null : $nowUtc,
                    'created_by_user_id' => $actor->id,
                ]);

                if ($requiresCustomerConfirmation) {
                    $expiresAt = $nowUtc->addHours($setting->proposal_hold_hours)
                        ->min($startsAtUtc->subMinutes($setting->minimum_notice_minutes));

                    if ($expiresAt->lessThanOrEqualTo($nowUtc)) {
                        throw new AppointmentDomainException(
                            'APPOINTMENT_PROPOSAL_EXPIRY_INVALID',
                            'La fecha propuesta no deja tiempo suficiente para responder.',
                            422,
                        );
                    }

                    $proposal = AppointmentProposal::create([
                        'tenant_id' => $tenant->id,
                        'appointment_id' => $appointment->id,
                        'proposed_by_user_id' => $actor->id,
                        'starts_at' => $startsAtUtc,
                        'ends_at' => $endsAtUtc,
                        'duration_minutes' => $duration,
                        'previous_appointment_status' => AppointmentStatus::PendingTenant,
                        'message' => $customerReason,
                        'status' => AppointmentProposalStatus::Pending,
                        'expires_at' => $expiresAt,
                    ]);

                    $this->recordEvent(
                        $appointment,
                        $actor,
                        AppointmentEventType::Proposed,
                        null,
                        AppointmentStatus::PendingCustomer,
                        [
                            'proposal_id' => $proposal->id,
                            'source' => 'tenant_manual',
                            'starts_at' => $startsAtUtc->toIso8601String(),
                            'ends_at' => $endsAtUtc->toIso8601String(),
                            'expires_at' => $expiresAt->toIso8601String(),
                        ],
                    );

                    return $appointment;
                }

                $this->recordEvent(
                    $appointment,
                    $actor,
                    AppointmentEventType::CreatedManually,
                    null,
                    AppointmentStatus::Confirmed,
                    ['source' => 'tenant'],
                );

                return $appointment;
            },
        );

        return $result;
    }

    public function confirm(
        Appointment $appointment,
        User $actor,
        ?int $durationMinutes = null,
        ?string $internalNotes = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        $tenant = $appointment->tenant()->firstOrFail();
        $nowUtc = $this->now($now);

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'confirm',
            $idempotencyKey,
            [
                'appointment_id' => $appointment->id,
                'duration_minutes' => $durationMinutes,
                'internal_notes' => $internalNotes,
            ],
            function () use ($tenant, $appointment, $actor, $durationMinutes, $internalNotes, $nowUtc) {
                $this->staffAccess->authorize($tenant, $actor);
                $locked = $this->lockAppointment($tenant, $appointment->id);
                $this->ensureStatus($locked, [AppointmentStatus::PendingTenant]);
                $service = $locked->catalogItem;
                $this->ensureServiceAvailable($tenant, $service);
                $duration = $durationMinutes ?? $locked->duration_minutes;
                $this->ensureDuration($duration);
                $endsAt = $locked->starts_at->toImmutable()->addMinutes($duration);

                $this->lockAndEnsureAvailable(
                    $tenant,
                    $locked,
                    $locked->starts_at,
                    $endsAt,
                    $locked->buffer_minutes,
                    $nowUtc,
                );

                $previous = $locked->status;
                $locked->update([
                    'status' => AppointmentStatus::Confirmed,
                    'ends_at' => $endsAt,
                    'duration_minutes' => $duration,
                    'internal_notes' => $internalNotes ?? $locked->internal_notes,
                    'confirmed_at' => $nowUtc,
                ]);
                $this->recordEvent(
                    $locked,
                    $actor,
                    AppointmentEventType::Confirmed,
                    $previous,
                    AppointmentStatus::Confirmed,
                );

                return $locked->fresh();
            },
        );

        return $result;
    }

    public function reject(
        Appointment $appointment,
        User $actor,
        string $reason,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        $tenant = $appointment->tenant()->firstOrFail();
        $nowUtc = $this->now($now);

        if (blank($reason)) {
            throw new AppointmentDomainException(
                'APPOINTMENT_REJECTION_REASON_REQUIRED',
                'Indica el motivo visible del rechazo.',
                422,
            );
        }

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'reject',
            $idempotencyKey,
            ['appointment_id' => $appointment->id, 'reason' => trim($reason)],
            function () use ($tenant, $appointment, $actor, $reason, $nowUtc) {
                $this->staffAccess->authorize($tenant, $actor);
                $locked = $this->lockAppointment($tenant, $appointment->id);
                $this->ensureStatus($locked, [AppointmentStatus::PendingTenant]);
                $previous = $locked->status;
                $locked->update([
                    'status' => AppointmentStatus::Rejected,
                    'rejected_at' => $nowUtc,
                    'rejection_reason' => trim($reason),
                ]);
                $this->recordEvent(
                    $locked,
                    $actor,
                    AppointmentEventType::Rejected,
                    $previous,
                    AppointmentStatus::Rejected,
                    ['reason' => trim($reason)],
                );

                return $locked->fresh();
            },
        );

        return $result;
    }

    public function propose(
        Appointment $appointment,
        User $actor,
        DateTimeInterface $startsAtUtc,
        ?int $durationMinutes = null,
        ?string $message = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): AppointmentProposal {
        $tenant = $appointment->tenant()->firstOrFail();
        $startsAtUtc = CarbonImmutable::instance($startsAtUtc)->utc();
        $nowUtc = $this->now($now);

        /** @var AppointmentProposal $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'propose',
            $idempotencyKey,
            [
                'appointment_id' => $appointment->id,
                'starts_at' => $startsAtUtc,
                'duration_minutes' => $durationMinutes,
                'message' => $message,
            ],
            function () use ($tenant, $appointment, $actor, $startsAtUtc, $durationMinutes, $message, $nowUtc) {
                $this->staffAccess->authorize($tenant, $actor);
                $locked = $this->lockAppointment($tenant, $appointment->id);
                $this->ensureStatus($locked, [
                    AppointmentStatus::PendingTenant,
                    AppointmentStatus::PendingCustomer,
                    AppointmentStatus::Confirmed,
                ]);
                $service = $locked->catalogItem;
                $this->ensureServiceAvailable($tenant, $service);
                $setting = $tenant->appointmentSetting()->firstOrFail();
                $duration = $durationMinutes ?? $locked->duration_minutes;
                $this->ensureDuration($duration);
                $endsAt = $startsAtUtc->addMinutes($duration);
                $latestPending = AppointmentProposal::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('appointment_id', $locked->id)
                    ->where('status', AppointmentProposalStatus::Pending->value)
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();
                $previousStatus = $locked->status === AppointmentStatus::PendingCustomer
                    ? ($latestPending?->previous_appointment_status ?? AppointmentStatus::PendingTenant)
                    : $locked->status;
                $expiresAt = $nowUtc->addHours($setting->proposal_hold_hours)
                    ->min($startsAtUtc->subMinutes($setting->minimum_notice_minutes));

                if ($expiresAt->lessThanOrEqualTo($nowUtc)) {
                    throw new AppointmentDomainException(
                        'APPOINTMENT_PROPOSAL_EXPIRY_INVALID',
                        'La fecha propuesta no deja tiempo suficiente para responder.',
                        422,
                    );
                }

                $lastBookableDay = $nowUtc->setTimezone($setting->timezone)
                    ->startOfDay()
                    ->addDays($setting->booking_window_days);
                if ($startsAtUtc->setTimezone($setting->timezone)->startOfDay()->greaterThan($lastBookableDay)) {
                    throw new AppointmentDomainException(
                        'APPOINTMENT_OUTSIDE_BOOKING_WINDOW',
                        'La propuesta esta fuera de la ventana maxima de agenda.',
                        422,
                    );
                }

                $this->lockAndEnsureAvailable(
                    $tenant,
                    $locked,
                    $startsAtUtc,
                    $endsAt,
                    $locked->buffer_minutes,
                    $nowUtc,
                    $latestPending?->id,
                );

                if ($latestPending) {
                    $latestPending->update([
                        'status' => AppointmentProposalStatus::Superseded,
                        'responded_at' => $nowUtc,
                    ]);
                }

                $proposal = AppointmentProposal::create([
                    'tenant_id' => $tenant->id,
                    'appointment_id' => $locked->id,
                    'proposed_by_user_id' => $actor->id,
                    'starts_at' => $startsAtUtc,
                    'ends_at' => $endsAt,
                    'duration_minutes' => $duration,
                    'previous_appointment_status' => $previousStatus,
                    'message' => $message,
                    'status' => AppointmentProposalStatus::Pending,
                    'expires_at' => $expiresAt,
                ]);
                $appointmentPrevious = $locked->status;
                $locked->update(['status' => AppointmentStatus::PendingCustomer]);
                $this->recordEvent(
                    $locked,
                    $actor,
                    AppointmentEventType::Proposed,
                    $appointmentPrevious,
                    AppointmentStatus::PendingCustomer,
                    [
                        'proposal_id' => $proposal->id,
                        'starts_at' => $startsAtUtc->toIso8601String(),
                        'ends_at' => $endsAt->toIso8601String(),
                        'expires_at' => $expiresAt->toIso8601String(),
                    ],
                );

                return $proposal;
            },
        );

        return $result;
    }

    public function acceptProposal(
        Appointment $appointment,
        AppointmentProposal $proposal,
        User $actor,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        $tenant = $appointment->tenant()->firstOrFail();
        $nowUtc = $this->now($now);

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'accept-proposal',
            $idempotencyKey,
            ['appointment_id' => $appointment->id, 'proposal_id' => $proposal->id],
            function () use ($tenant, $appointment, $proposal, $actor, $nowUtc) {
                $locked = $this->lockAppointment($tenant, $appointment->id);
                $this->authorizeAppointmentCustomer($tenant, $actor, $locked);
                $lockedProposal = $this->lockProposal($tenant, $locked, $proposal->id);
                $this->ensurePendingProposal($lockedProposal, $nowUtc);
                $this->ensureStatus($locked, [AppointmentStatus::PendingCustomer]);

                $this->lockAndEnsureAvailable(
                    $tenant,
                    $locked,
                    $lockedProposal->starts_at,
                    $lockedProposal->ends_at,
                    $locked->buffer_minutes,
                    $nowUtc,
                    $lockedProposal->id,
                );

                $previous = $locked->status;
                $lockedProposal->update([
                    'status' => AppointmentProposalStatus::Accepted,
                    'responded_at' => $nowUtc,
                    'responded_by_user_id' => $actor->id,
                ]);
                $locked->update([
                    'starts_at' => $lockedProposal->starts_at,
                    'ends_at' => $lockedProposal->ends_at,
                    'duration_minutes' => $lockedProposal->duration_minutes,
                    'status' => AppointmentStatus::Confirmed,
                    'confirmed_at' => $nowUtc,
                ]);
                $this->recordEvent(
                    $locked,
                    $actor,
                    AppointmentEventType::ProposalAccepted,
                    $previous,
                    AppointmentStatus::Confirmed,
                    ['proposal_id' => $lockedProposal->id],
                );

                return $locked->fresh();
            },
        );

        return $result;
    }

    public function rejectProposal(
        Appointment $appointment,
        AppointmentProposal $proposal,
        User $actor,
        ?string $responseMessage = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        $tenant = $appointment->tenant()->firstOrFail();
        $nowUtc = $this->now($now);

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'reject-proposal',
            $idempotencyKey,
            [
                'appointment_id' => $appointment->id,
                'proposal_id' => $proposal->id,
                'response_message' => $responseMessage,
            ],
            function () use ($tenant, $appointment, $proposal, $actor, $responseMessage, $nowUtc) {
                $locked = $this->lockAppointment($tenant, $appointment->id);
                $this->authorizeAppointmentCustomer($tenant, $actor, $locked);
                $lockedProposal = $this->lockProposal($tenant, $locked, $proposal->id);
                $this->ensurePendingProposal($lockedProposal, $nowUtc);
                $this->ensureStatus($locked, [AppointmentStatus::PendingCustomer]);
                $restoredStatus = $lockedProposal->previous_appointment_status;
                $lockedProposal->update([
                    'status' => AppointmentProposalStatus::Rejected,
                    'responded_at' => $nowUtc,
                    'responded_by_user_id' => $actor->id,
                    'response_message' => $responseMessage,
                ]);
                $locked->update(['status' => $restoredStatus]);
                $this->recordEvent(
                    $locked,
                    $actor,
                    AppointmentEventType::ProposalRejected,
                    AppointmentStatus::PendingCustomer,
                    $restoredStatus,
                    ['proposal_id' => $lockedProposal->id, 'message' => $responseMessage],
                );

                return $locked->fresh();
            },
        );

        return $result;
    }

    public function cancel(
        Appointment $appointment,
        User $actor,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        $tenant = $appointment->tenant()->firstOrFail();
        $nowUtc = $this->now($now);

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            'cancel',
            $idempotencyKey,
            ['appointment_id' => $appointment->id, 'reason' => $reason],
            function () use ($tenant, $appointment, $actor, $reason, $nowUtc) {
                $locked = $this->lockAppointment($tenant, $appointment->id);
                $isCustomer = $actor->hasRole('customer');

                if ($isCustomer) {
                    $this->authorizeAppointmentCustomer($tenant, $actor, $locked);
                    if ($nowUtc->greaterThanOrEqualTo($locked->starts_at)) {
                        throw $this->conflict(
                            'APPOINTMENT_CANCELLATION_CLOSED',
                            'La cita ya no puede cancelarse desde la app.',
                        );
                    }
                } else {
                    $this->staffAccess->authorize($tenant, $actor);
                    if (blank($reason)) {
                        throw new AppointmentDomainException(
                            'APPOINTMENT_CANCELLATION_REASON_REQUIRED',
                            'Indica el motivo visible de la cancelacion.',
                            422,
                        );
                    }
                }

                $this->ensureStatus($locked, [
                    AppointmentStatus::PendingTenant,
                    AppointmentStatus::PendingCustomer,
                    AppointmentStatus::Confirmed,
                ]);
                $setting = $tenant->appointmentSetting()->first();
                $isLate = $isCustomer
                    && $locked->status === AppointmentStatus::Confirmed
                    && $setting
                    && $nowUtc->greaterThan(
                        $locked->starts_at->toImmutable()->subMinutes($setting->customer_cancellation_notice_minutes)
                    );
                $feeStatus = $isLate && $setting?->cancellation_policy === AppointmentCancellationPolicy::LateFeeReview
                    ? AppointmentCancellationFeeStatus::PendingReview
                    : AppointmentCancellationFeeStatus::NotApplicable;
                $feeAmount = $feeStatus === AppointmentCancellationFeeStatus::PendingReview
                    ? $this->suggestedCancellationFee($locked, $setting)
                    : null;
                $previous = $locked->status;

                AppointmentProposal::query()
                    ->where('appointment_id', $locked->id)
                    ->where('status', AppointmentProposalStatus::Pending->value)
                    ->update([
                        'status' => AppointmentProposalStatus::Superseded->value,
                        'responded_at' => $nowUtc,
                        'updated_at' => $nowUtc,
                    ]);
                $locked->update([
                    'status' => AppointmentStatus::Cancelled,
                    'cancelled_at' => $nowUtc,
                    'cancelled_by_user_id' => $actor->id,
                    'cancellation_reason' => $reason,
                    'is_late_cancellation' => $isLate,
                    'cancellation_fee_status' => $feeStatus,
                    'cancellation_fee_amount' => $feeAmount,
                ]);
                $this->recordEvent(
                    $locked,
                    $actor,
                    AppointmentEventType::Cancelled,
                    $previous,
                    AppointmentStatus::Cancelled,
                    [
                        'reason' => $reason,
                        'actor_type' => $isCustomer ? 'customer' : 'tenant',
                        'is_late_cancellation' => $isLate,
                        'cancellation_fee_status' => $feeStatus->value,
                        'cancellation_fee_amount' => $feeAmount,
                    ],
                );

                return $locked->fresh();
            },
        );

        return $result;
    }

    public function complete(
        Appointment $appointment,
        User $actor,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        return $this->finish(
            $appointment,
            $actor,
            AppointmentStatus::Completed,
            AppointmentEventType::Completed,
            $idempotencyKey,
            $now,
        );
    }

    public function markNoShow(
        Appointment $appointment,
        User $actor,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $now = null,
    ): Appointment {
        return $this->finish(
            $appointment,
            $actor,
            AppointmentStatus::NoShow,
            AppointmentEventType::NoShow,
            $idempotencyKey,
            $now,
        );
    }

    public function expireProposal(
        AppointmentProposal $proposal,
        ?DateTimeInterface $now = null,
    ): bool {
        $nowUtc = $this->now($now);

        return (bool) DB::transaction(function () use ($proposal, $nowUtc) {
            $lockedProposal = AppointmentProposal::query()->whereKey($proposal->id)->lockForUpdate()->first();

            if (
                ! $lockedProposal
                || $lockedProposal->status !== AppointmentProposalStatus::Pending
                || $lockedProposal->expires_at->greaterThan($nowUtc)
            ) {
                return false;
            }

            $appointment = Appointment::query()
                ->whereKey($lockedProposal->appointment_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedProposal->update([
                'status' => AppointmentProposalStatus::Expired,
                'responded_at' => $nowUtc,
            ]);
            $otherPendingExists = AppointmentProposal::query()
                ->where('appointment_id', $appointment->id)
                ->whereKeyNot($lockedProposal->id)
                ->where('status', AppointmentProposalStatus::Pending->value)
                ->exists();

            if (! $otherPendingExists && $appointment->status === AppointmentStatus::PendingCustomer) {
                $appointment->update(['status' => $lockedProposal->previous_appointment_status]);
            }

            $this->recordEvent(
                $appointment,
                null,
                AppointmentEventType::ProposalExpired,
                AppointmentStatus::PendingCustomer,
                $appointment->status,
                ['proposal_id' => $lockedProposal->id],
            );

            return true;
        }, 3);
    }

    public function expireDueProposals(?DateTimeInterface $now = null, int $limit = 500): int
    {
        $nowUtc = $this->now($now);
        $expired = 0;

        AppointmentProposal::query()
            ->where('status', AppointmentProposalStatus::Pending->value)
            ->where('expires_at', '<=', $nowUtc)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $proposalId) use ($nowUtc, &$expired) {
                $proposal = AppointmentProposal::find($proposalId);
                if ($proposal && $this->expireProposal($proposal, $nowUtc)) {
                    $expired++;
                }
            });

        return $expired;
    }

    private function finish(
        Appointment $appointment,
        User $actor,
        AppointmentStatus $targetStatus,
        AppointmentEventType $eventType,
        ?string $idempotencyKey,
        ?DateTimeInterface $now,
    ): Appointment {
        $tenant = $appointment->tenant()->firstOrFail();
        $nowUtc = $this->now($now);

        /** @var Appointment $result */
        $result = $this->idempotency->execute(
            $tenant,
            $actor,
            $targetStatus->value,
            $idempotencyKey,
            ['appointment_id' => $appointment->id],
            function () use ($tenant, $appointment, $actor, $targetStatus, $eventType, $nowUtc) {
                $this->staffAccess->authorize($tenant, $actor);
                $locked = $this->lockAppointment($tenant, $appointment->id);
                $this->ensureStatus($locked, [AppointmentStatus::Confirmed]);

                if ($nowUtc->lessThan($locked->starts_at)) {
                    throw $this->conflict(
                        'APPOINTMENT_NOT_STARTED',
                        'La cita aun no ha iniciado.',
                    );
                }

                $previous = $locked->status;
                $updates = ['status' => $targetStatus];
                $updates[$targetStatus === AppointmentStatus::Completed ? 'completed_at' : 'no_show_at'] = $nowUtc;
                $locked->update($updates);
                $this->recordEvent($locked, $actor, $eventType, $previous, $targetStatus);

                return $locked->fresh();
            },
        );

        return $result;
    }

    private function authorizeCustomer(Tenant $tenant, User $actor, Customer $customer, Animal $animal): void
    {
        if (
            (int) $actor->tenant_id !== (int) $tenant->id
            || ! $actor->is_active
            || ! $actor->hasRole('customer')
            || (int) $customer->tenant_id !== (int) $tenant->id
            || $customer->status !== 'active'
            || (int) $animal->tenant_id !== (int) $tenant->id
            || (int) $animal->customer_id !== (int) $customer->id
            || $animal->status !== 'active'
        ) {
            throw new AppointmentDomainException(
                'APPOINTMENT_FORBIDDEN',
                'No tienes acceso para solicitar esta cita.',
                403,
            );
        }

        $activeLink = CustomerUserLink::query()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('user_id', $actor->id)
            ->whereNull('revoked_at')
            ->exists();
        $activeAccess = CustomerPortalAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('access_starts_at')->orWhere('access_starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('access_ends_at')->orWhere('access_ends_at', '>=', now());
            })
            ->exists();
        $activeAssignment = FinalUserPatientAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('user_id', $actor->id)
            ->where('animal_id', $animal->id)
            ->whereNull('revoked_at')
            ->exists();

        if (! $activeLink || ! $activeAccess || ! $activeAssignment) {
            throw new AppointmentDomainException(
                'CUSTOMER_PORTAL_ACCESS_INACTIVE',
                'El acceso del customer o de la mascota no esta activo.',
                403,
            );
        }
    }

    private function authorizeAppointmentCustomer(Tenant $tenant, User $actor, Appointment $appointment): void
    {
        $customer = $appointment->customer;
        $animal = $appointment->animal;

        if (! $customer || ! $animal) {
            throw new AppointmentDomainException(
                'APPOINTMENT_FORBIDDEN',
                'La cita ya no tiene participantes activos.',
                403,
            );
        }

        $this->authorizeCustomer($tenant, $actor, $customer, $animal);
    }

    private function ensureManualParticipants(Tenant $tenant, Customer $customer, Animal $animal): void
    {
        if (
            $customer->trashed()
            || (int) $customer->tenant_id !== (int) $tenant->id
            || $customer->status !== 'active'
            || $animal->trashed()
            || (int) $animal->tenant_id !== (int) $tenant->id
            || (int) $animal->customer_id !== (int) $customer->id
            || $animal->status !== 'active'
        ) {
            throw new AppointmentDomainException(
                'APPOINTMENT_PARTICIPANTS_INVALID',
                'El customer o la mascota no estan disponibles para agenda.',
                422,
            );
        }
    }

    private function ensureServiceAvailable(Tenant $tenant, ?CatalogItem $service): void
    {
        if (
            ! $service
            || $service->trashed()
            || (int) $service->tenant_id !== (int) $tenant->id
            || $service->type !== 'service'
            || ! $service->is_active
            || ! $service->is_bookable
        ) {
            throw new AppointmentDomainException(
                'APPOINTMENT_SERVICE_UNAVAILABLE',
                'El servicio ya no esta disponible para agenda.',
                422,
            );
        }
    }

    private function lockAndEnsureAvailable(
        Tenant $tenant,
        Appointment $appointment,
        DateTimeInterface $startsAtUtc,
        DateTimeInterface $endsAtUtc,
        int $bufferMinutes,
        DateTimeInterface $now,
        ?int $excludeProposalId = null,
    ): void {
        $setting = $tenant->appointmentSetting()->firstOrFail();
        $doctorUserId = (int) $appointment->doctor_user_id;
        $this->scheduleLocks->lock($tenant, $doctorUserId, $startsAtUtc, $setting->timezone);

        if (! $this->availability->intervalIsAvailable(
            $tenant,
            $doctorUserId,
            $startsAtUtc,
            $endsAtUtc,
            $bufferMinutes,
            $appointment->id,
            $excludeProposalId,
            $now,
        )) {
            throw $this->conflict(
                'APPOINTMENT_SLOT_UNAVAILABLE',
                'El horario ya no esta disponible.',
            );
        }
    }

    private function lockAppointment(Tenant $tenant, int $appointmentId): Appointment
    {
        return Appointment::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($appointmentId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockProposal(Tenant $tenant, Appointment $appointment, int $proposalId): AppointmentProposal
    {
        return AppointmentProposal::query()
            ->where('tenant_id', $tenant->id)
            ->where('appointment_id', $appointment->id)
            ->whereKey($proposalId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureStatus(Appointment $appointment, array $allowed): void
    {
        if (! in_array($appointment->status, $allowed, true)) {
            throw $this->conflict(
                'APPOINTMENT_INVALID_TRANSITION',
                'El estado actual no permite esta operacion.',
            );
        }
    }

    private function ensurePendingProposal(AppointmentProposal $proposal, CarbonImmutable $nowUtc): void
    {
        if ($proposal->status !== AppointmentProposalStatus::Pending) {
            throw $this->conflict(
                'APPOINTMENT_PROPOSAL_SUPERSEDED',
                'La propuesta ya fue procesada o reemplazada.',
            );
        }

        if ($proposal->expires_at->lessThanOrEqualTo($nowUtc)) {
            throw $this->conflict(
                'APPOINTMENT_PROPOSAL_EXPIRED',
                'La propuesta de horario ya vencio.',
            );
        }
    }

    private function ensureDuration(int $duration): void
    {
        if ($duration < 5 || $duration > 480) {
            throw new AppointmentDomainException(
                'APPOINTMENT_DURATION_INVALID',
                'La duracion debe estar entre 5 y 480 minutos.',
                422,
            );
        }
    }

    private function suggestedCancellationFee(Appointment $appointment, $setting): ?float
    {
        if (! $setting?->late_fee_type || $setting->late_fee_value === null) {
            return null;
        }

        $value = (float) $setting->late_fee_value;

        if ($setting->late_fee_type === AppointmentLateFeeType::Fixed) {
            return round($value, 2);
        }

        $price = (float) ($appointment->catalogItem?->current_price ?? 0);

        return round($price * min($value, 100) / 100, 2);
    }

    private function recordEvent(
        Appointment $appointment,
        ?User $actor,
        AppointmentEventType $eventType,
        ?AppointmentStatus $previousStatus,
        ?AppointmentStatus $newStatus,
        array $metadata = [],
    ): AppointmentEvent {
        return AppointmentEvent::create([
            'tenant_id' => $appointment->tenant_id,
            'appointment_id' => $appointment->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function now(?DateTimeInterface $now): CarbonImmutable
    {
        return $now ? CarbonImmutable::instance($now)->utc() : CarbonImmutable::now('UTC');
    }

    private function conflict(string $code, string $message): AppointmentDomainException
    {
        return new AppointmentDomainException($code, $message);
    }
}
